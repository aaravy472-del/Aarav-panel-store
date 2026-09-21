<?php
// ============================================================
// API Router — all REST endpoints for the Hasi Panel Store
// ============================================================

require_once __DIR__ . '/../includes/helpers.php';
corsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Strip base path if API is at /api/api.php or similar
$path = preg_replace('#.*?/api\.php#', '', $path) ?: '';
$path = trim($path, '/');
$segments = $path ? explode('/', $path) : [];
$route = $segments[0] ?? '';
$sub = $segments[1] ?? '';
$body = getRequestBody();

// ── AUTH ──────────────────────────────────────────────────
if ($route === 'auth') {
  if ($sub === 'signup' && $method === 'POST') {
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $whatsapp = trim($body['whatsapp_number'] ?? '');
    $refCode = strtoupper(trim($body['referral_code'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Invalid email'], 400);
    if (strlen($password) < 6) jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    $existing = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) jsonResponse(['error' => 'Email already registered'], 400);
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $code = genReferralCode();
    $controls = getAppControls();
    $signupBonus = (float)($controls['signup_bonus_amount'] ?? 0);
    $bonusCurrency = $controls['signup_bonus_currency'] ?? 'PKR';
    $rates = getExchangeRates();
    $bonusUSDT = $signupBonus > 0 ? convertToUSDT($signupBonus, $bonusCurrency, $rates) : 0;
    $referredBy = null;
    if ($refCode) {
      $referrer = Database::fetch("SELECT referral_code FROM users WHERE referral_code = ?", [$refCode]);
      if ($referrer) $referredBy = $refCode;
    }
    $userId = Database::insert('users', [
      'email' => $email, 'password_hash' => $hash, 'whatsapp_number' => $whatsapp ?: null,
      'balance' => $bonusUSDT, 'referral_code' => $code, 'referred_by' => $referredBy,
    ]);
    $token = createToken($userId);
    jsonResponse(['token' => $token, 'user' => ['id' => $userId, 'email' => $email]]);
  }

  if ($sub === 'signin' && $method === 'POST') {
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $user = Database::fetch("SELECT * FROM users WHERE email = ?", [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
      jsonResponse(['error' => 'Invalid credentials'], 401);
    }
    if ($user['status'] === 'banned') jsonResponse(['error' => 'Account banned'], 403);
    $token = createToken($user['id'], (bool)$user['is_admin']);
    unset($user['password_hash']);
    jsonResponse(['token' => $token, 'user' => $user]);
  }

  if ($sub === 'me' && $method === 'GET') {
    $user = requireAuth();
    unset($user['password_hash']);
    $secAdmin = null;
    if ($user['is_secondary_admin'] && !$user['is_admin']) {
      $secAdmin = Database::fetch("SELECT * FROM secondary_admins WHERE user_id = ?", [$user['id']]);
    }
    jsonResponse(['user' => $user, 'secAdmin' => $secAdmin]);
  }

  if ($sub === 'update-password' && $method === 'POST') {
    $user = requireAuth();
    $newPassword = $body['password'] ?? '';
    if (strlen($newPassword) < 6) jsonResponse(['error' => 'Password too short'], 400);
    Database::update('users', ['password_hash' => password_hash($newPassword, PASSWORD_BCRYPT)], 'id = ?', [$user['id']]);
    jsonResponse(['status' => 'ok']);
  }
}

// ── PANELS (store) ────────────────────────────────────────
if ($route === 'panels') {
  if ($method === 'GET' && $sub === '') {
    $panels = Database::fetchAll("SELECT * FROM panels WHERE status = 'active' ORDER BY sort_order ASC, created_at DESC");
    foreach ($panels as &$p) {
      $p['plans'] = json_decode($p['plans'] ?? '[]', true);
      $p['tags'] = json_decode($p['tags'] ?? '[]', true);
    }
    jsonResponse(['panels' => $panels]);
  }
  if ($method === 'GET' && $sub === 'all' && hasPermission('panels')) {
    $panels = Database::fetchAll("SELECT * FROM panels ORDER BY sort_order ASC, created_at DESC");
    foreach ($panels as &$p) {
      $p['plans'] = json_decode($p['plans'] ?? '[]', true);
      $p['tags'] = json_decode($p['tags'] ?? '[]', true);
    }
    jsonResponse(['panels' => $panels]);
  }
  if ($method === 'POST' && $sub === 'create' && hasPermission('panels')) {
    $plans = $body['plans'] ?? [];
    $tags = $body['tags'] ?? [];
    $id = Database::insert('panels', [
      'name' => $body['name'], 'description' => $body['description'] ?? null,
      'video' => $body['video'] ?? null, 'demo_video' => $body['demo_video'] ?? null,
      'setup_video' => $body['setup_video'] ?? null, 'thumbnail_url' => $body['thumbnail_url'] ?? null,
      'link' => $body['link'] ?? null, 'update_file_2' => $body['update_file_2'] ?? null,
      'demo_button_text' => $body['demo_button_text'] ?? 'Demo Video',
      'setup_button_text' => $body['setup_button_text'] ?? 'Setup Video',
      'update_file_1_text' => $body['update_file_1_text'] ?? 'Update File 1',
      'update_file_2_text' => $body['update_file_2_text'] ?? 'Update File 2',
      'status' => $body['status'] ?? 'active', 'plans' => json_encode($plans),
      'tags' => json_encode($tags), 'price_currency' => $body['price_currency'] ?? 'PKR',
      'profit_percent' => $body['profit_percent'] ?? 0,
      'badge_text' => $body['badge_text'] ?? 'Premium Quality',
      'badge_enabled' => $body['badge_enabled'] ?? 1,
      'sort_order' => $body['sort_order'] ?? 0,
    ]);
    jsonResponse(['id' => $id]);
  }
  if ($method === 'POST' && is_numeric($sub) && hasPermission('panels')) {
    $data = [];
    foreach (['name','description','video','demo_video','setup_video','thumbnail_url','link','update_file_2',
      'demo_button_text','setup_button_text','update_file_1_text','update_file_2_text','status','price_currency',
      'profit_percent','badge_text','badge_enabled','sort_order'] as $f) {
      if (array_key_exists($f, $body)) $data[$f] = $body[$f];
    }
    if (isset($body['plans'])) $data['plans'] = json_encode($body['plans']);
    if (isset($body['tags'])) $data['tags'] = json_encode($body['tags']);
    if ($data) Database::update('panels', $data, 'id = ?', [(int)$sub]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'delete' && hasPermission('panels')) {
    Database::delete('panels', 'id = ?', [(int)$body['id']]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'reorder' && hasPermission('panels')) {
    $orders = $body['orders'] ?? [];
    foreach ($orders as $id => $order) {
      Database::update('panels', ['sort_order' => (int)$order], 'id = ?', [(int)$id]);
    }
    jsonResponse(['status' => 'ok']);
  }
}

// ── PURCHASE ──────────────────────────────────────────────
if ($route === 'purchase' && $method === 'POST') {
  $user = requireAuth();
  $panelId = (int)($body['panel_id'] ?? 0);
  $planKey = $body['plan_key'] ?? '';
  $quantity = max(1, min(10, (int)($body['quantity'] ?? 1)));
  $panel = Database::fetch("SELECT * FROM panels WHERE id = ? AND status = 'active'", [$panelId]);
  if (!$panel) jsonResponse(['error' => 'Panel not found'], 404);
  $plans = json_decode($panel['plans'] ?? '[]', true);
  $plan = null;
  foreach ($plans as $p) { if ($p['key'] === $planKey) { $plan = $p; break; } }
  if (!$plan) jsonResponse(['error' => 'Plan not found'], 404);
  $rates = getExchangeRates();
  $baseUSDT = round(convertToUSDT((float)$plan['price'], $panel['price_currency'], $rates), 2);
  $addl = (float)($plan['additional_amount'] ?? 0);
  if ($addl > 0) $baseUSDT = round($baseUSDT + convertToUSDT($addl, 'PKR', $rates), 2);
  $controls = getAppControls();
  $userDiscount = (float)($user['discount_percent'] ?? 0);
  $globalDiscount = ($controls['global_discount_enabled'] ?? 0) ? (float)$controls['global_discount_percent'] : 0;
  $discount = max($userDiscount, $globalDiscount);
  if ($discount > 0) $baseUSDT = round($baseUSDT * (1 - $discount / 100), 2);
  $totalPrice = round($baseUSDT * $quantity, 2);
  if ((float)$user['balance'] < $totalPrice) jsonResponse(['error' => 'Insufficient balance', 'needed' => $totalPrice, 'balance' => (float)$user['balance']], 402);
  $isApiLinked = !empty($panel['api_provider_id']) && (!empty($panel['api_service_id']) || !empty($plan['api_service_id']));
  $durations = Database::fetchAll("SELECT * FROM plan_durations ORDER BY sort_order ASC");
  $targetPool = poolFromLabel($plan['label'] ?? '', $plan['key']);
  $deliveredKeys = [];
  $purchaseIds = [];
  $ranOut = false;
  for ($i = 0; $i < $quantity; $i++) {
    $keyValue = 'PENDING_API_DELIVERY';
    if (!$isApiLinked) {
      $keyRow = Database::fetch("SELECT * FROM keys_pool WHERE panel_id = ? AND pool_type = ? AND is_used = 0 ORDER BY created_at ASC LIMIT 1", [$panelId, $targetPool]);
      if (!$keyRow) { $ranOut = true; break; }
      Database::update('keys_pool', ['is_used' => 1], 'id = ?', [$keyRow['id']]);
      $keyValue = $keyRow['key_value'];
    }
    $dDays = durationDays($targetPool, $plan['label'] ?? '', $durations, $plan);
    $dHours = durationHours($targetPool, $plan['label'] ?? '', $durations, $plan);
    $expiry = null;
    if ($dHours && $dHours > 0) $expiry = date('Y-m-d H:i:s', time() + $dHours * 3600);
    elseif ($dDays > 0) $expiry = date('Y-m-d H:i:s', time() + $dDays * 86400);
    $pid = Database::insert('purchases', [
      'user_id' => $user['id'], 'panel_id' => $panelId, 'panel_name' => $panel['name'],
      'plan' => $plan['key'], 'label' => $plan['label'] ?? '', 'price' => $baseUSDT,
      'key_value' => $keyValue, 'link' => $panel['link'], 'duration_days' => $dDays,
      'duration_hours' => $dHours, 'expiry_date' => $expiry,
    ]);
    $deliveredKeys[] = $keyValue;
    $purchaseIds[] = $pid;
  }
  if (count($deliveredKeys) === 0) {
    jsonResponse(['error' => $ranOut ? 'Out of stock' : 'Purchase failed'], 400);
  }
  // Deduct balance
  Database::update('users', ['balance' => (float)$user['balance'] - $totalPrice], 'id = ?', [$user['id']]);
  // Forward to API if needed
  if ($isApiLinked) {
    $serviceApiId = $plan['api_service_id'] ?? $panel['api_service_id'];
    $provider = Database::fetch("SELECT * FROM api_providers WHERE id = ?", [$panel['api_provider_id']]);
    if ($provider) {
      $unitCost = (float)($plan['api_price'] ?? $baseUSDT);
      $apiResult = callExternalApi($provider, '/generate_key.php', 'POST', ['variant_id' => $serviceApiId, 'quantity' => $quantity]);
      $profit = round(($baseUSDT - $unitCost) * $quantity, 2);
      if ($apiResult['success']) {
        $apiKey = extractKeyValue($apiResult['data']);
        $apiOrderId = extractOrderId($apiResult['data']);
        if ($apiKey) {
          foreach ($purchaseIds as $pid) {
            Database::update('purchases', ['key_value' => $apiKey], 'id = ?', [$pid]);
          }
          $deliveredKeys = array_fill(0, count($deliveredKeys), $apiKey);
        }
        Database::insert('api_orders', [
          'provider_id' => $provider['id'], 'purchase_id' => $purchaseIds[0] ?? null,
          'user_id' => $user['id'], 'api_order_id' => $apiOrderId, 'api_key_value' => $apiKey,
          'quantity' => $quantity, 'unit_cost' => $unitCost, 'unit_sell' => $baseUSDT,
          'profit' => $profit, 'status' => 'success',
        ]);
      } else {
        Database::insert('api_orders', [
          'provider_id' => $provider['id'], 'purchase_id' => $purchaseIds[0] ?? null,
          'user_id' => $user['id'], 'quantity' => $quantity, 'unit_cost' => $unitCost,
          'unit_sell' => $baseUSDT, 'profit' => $profit, 'status' => 'failed',
          'error_message' => $apiResult['error'] ?? 'API call failed',
        ]);
      }
    }
  }
  // Return updated balance
  $updatedUser = Database::fetch("SELECT balance FROM users WHERE id = ?", [$user['id']]);
  jsonResponse([
    'status' => 'ok', 'keys' => $deliveredKeys, 'purchase_ids' => $purchaseIds,
    'charged' => $totalPrice, 'balance' => (float)$updatedUser['balance'],
    'panel_name' => $panel['name'], 'plan_label' => $plan['label'] ?? '',
    'link' => $panel['link'], 'partial' => $ranOut,
    'delivered_count' => count($deliveredKeys),
  ]);
}

// ── MY KEYS ───────────────────────────────────────────────
if ($route === 'my-keys' && $method === 'GET') {
  $user = requireAuth();
  $rows = Database::fetchAll("SELECT * FROM purchases WHERE user_id = ? AND hidden_from_user = 0 ORDER BY created_at DESC", [$user['id']]);
  jsonResponse(['purchases' => $rows]);
}

if ($route === 'my-keys' && $method === 'POST' && $sub === 'hide') {
  $user = requireAuth();
  Database::update('purchases', ['hidden_from_user' => 1], 'id = ? AND user_id = ?', [(int)$body['id'], $user['id']]);
  jsonResponse(['status' => 'ok']);
}

// ── KEYS POOL (admin) ─────────────────────────────────────
if ($route === 'keys-pool') {
  requirePermission('manage-keys');
  if ($method === 'GET') {
    $pool = $body['pool_type'] ?? getQueryParam('pool_type', '');
    $rows = Database::fetchAll("SELECT * FROM keys_pool WHERE pool_type = ? AND is_used = 0 ORDER BY created_at DESC", [$pool]);
    jsonResponse(['keys' => $rows]);
  }
  if ($method === 'POST' && $sub === 'add') {
    $panelId = (int)$body['panel_id'];
    $poolType = $body['pool_type'];
    $keys = explode("\n", $body['keys_text'] ?? '');
    $count = 0;
    foreach ($keys as $k) {
      $k = trim($k);
      if (!$k) continue;
      Database::insert('keys_pool', ['panel_id' => $panelId, 'pool_type' => $poolType, 'key_value' => $k]);
      $count++;
    }
    jsonResponse(['status' => 'ok', 'count' => $count]);
  }
  if ($method === 'POST' && $sub === 'delete') {
    Database::delete('keys_pool', 'id = ?', [(int)$body['id']]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'update') {
    Database::update('keys_pool', ['key_value' => $body['key_value']], 'id = ?', [(int)$body['id']]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'GET' && $sub === 'stock') {
    $rows = Database::fetchAll("SELECT pool_type, COUNT(*) as cnt FROM keys_pool WHERE is_used = 0 GROUP BY pool_type");
    $stock = [];
    foreach ($rows as $r) $stock[$r['pool_type']] = (int)$r['cnt'];
    jsonResponse(['stock' => $stock]);
  }
  if ($method === 'GET' && $sub === 'download') {
    $rows = Database::fetchAll("SELECT k.*, p.name as panel_name FROM keys_pool k LEFT JOIN panels p ON k.panel_id = p.id ORDER BY k.created_at DESC");
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="stored-keys.txt"');
    foreach ($rows as $r) {
      echo $r['key_value'] . "  |  " . ($GLOBALS['POOL_LABELS'][$r['pool_type']] ?? $r['pool_type']) . "  |  " . ($r['panel_name'] ?? 'Unknown') . ($r['is_used'] ? ' [USED]' : '') . "\n";
    }
    exit;
  }
}

// ── PAYMENTS ──────────────────────────────────────────────
if ($route === 'payments') {
  if ($method === 'POST' && $sub === 'submit') {
    $user = requireAuth();
    $amt = (float)($body['amount'] ?? 0);
    if ($amt <= 0) jsonResponse(['error' => 'Invalid amount'], 400);
    Database::insert('payments', [
      'user_id' => $user['id'], 'email' => $user['email'], 'amount' => $amt,
      'currency' => $body['currency'] ?? 'PKR', 'utr' => $body['utr'] ?? null,
      'account_holder' => $body['account_holder'] ?? null,
      'payment_method' => $body['payment_method'] ?? null,
    ]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'GET' && hasPermission('payments')) {
    $rows = Database::fetchAll("SELECT p.*, u.email AS user_email FROM payments p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
    jsonResponse(['payments' => $rows]);
  }
  if ($method === 'POST' && $sub === 'approve' && hasPermission('payments')) {
    $payment = Database::fetch("SELECT * FROM payments WHERE id = ?", [(int)$body['id']]);
    if (!$payment) jsonResponse(['error' => 'Not found'], 404);
    $rates = getExchangeRates();
    $usdt = convertToUSDT((float)$payment['amount'], $payment['currency'] ?? 'USDT', $rates);
    $profile = Database::fetch("SELECT balance FROM users WHERE id = ?", [$payment['user_id']]);
    Database::update('users', ['balance' => (float)$profile['balance'] + $usdt], 'id = ?', [$payment['user_id']]);
    Database::update('payments', ['status' => 'approved'], 'id = ?', [$payment['id']]);
    // Referral bonus
    $controls = getAppControls();
    $refPercent = (float)($controls['referral_bonus_percent'] ?? 0);
    if ($refPercent > 0) {
      $user = Database::fetch("SELECT referred_by FROM users WHERE id = ?", [$payment['user_id']]);
      if ($user['referred_by']) {
        $referrer = Database::fetch("SELECT id, balance FROM users WHERE referral_code = ?", [$user['referred_by']]);
        if ($referrer) {
          $bonus = round($usdt * $refPercent / 100, 2);
          Database::update('users', ['balance' => (float)$referrer['balance'] + $bonus], 'id = ?', [$referrer['id']]);
        }
      }
    }
    jsonResponse(['status' => 'ok', 'credited' => $usdt]);
  }
  if ($method === 'POST' && $sub === 'reject' && hasPermission('payments')) {
    Database::update('payments', ['status' => 'rejected'], 'id = ?', [(int)$body['id']]);
    jsonResponse(['status' => 'ok']);
  }
}

// ── PAYMENT METHODS ───────────────────────────────────────
if ($route === 'payment-methods') {
  if ($method === 'GET') {
    $activeOnly = getQueryParam('active', '0') === '1';
    $sql = "SELECT * FROM payment_methods";
    if ($activeOnly) $sql .= " WHERE status = 'active'";
    $sql .= " ORDER BY sort_order ASC";
    jsonResponse(['methods' => Database::fetchAll($sql)]);
  }
  if ($method === 'POST' && $sub === 'create' && hasPermission('payment-methods')) {
    $id = Database::insert('payment_methods', [
      'name' => $body['name'], 'logo' => $body['logo'] ?? null,
      'account_number' => $body['account_number'] ?? null,
      'account_holder' => $body['account_holder'] ?? null,
      'description' => $body['description'] ?? null, 'qr_image' => $body['qr_image'] ?? null,
      'sort_order' => $body['sort_order'] ?? 0, 'status' => $body['status'] ?? 'active',
      'currency' => $body['currency'] ?? 'USDT',
    ]);
    jsonResponse(['id' => $id]);
  }
  if ($method === 'POST' && is_numeric($sub) && hasPermission('payment-methods')) {
    $data = [];
    foreach (['name','logo','account_number','account_holder','description','qr_image','sort_order','status','currency'] as $f) {
      if (array_key_exists($f, $body)) $data[$f] = $body[$f];
    }
    if ($data) Database::update('payment_methods', $data, 'id = ?', [(int)$sub]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'delete' && hasPermission('payment-methods')) {
    Database::delete('payment_methods', 'id = ?', [(int)$body['id']]);
    jsonResponse(['status' => 'ok']);
  }
}

// ── PAYMENT SETTINGS ──────────────────────────────────────
if ($route === 'payment-settings') {
  if ($method === 'GET') {
    jsonResponse(['settings' => Database::fetch("SELECT * FROM payment_settings WHERE id = 1")]);
  }
  if ($method === 'POST' && hasPermission('settings')) {
    $data = [];
    foreach (['upi_id','qr_image','gateway_api_key','gateway_visible','binance_pay_name',
      'binance_btn1_label','binance_btn1_amount','binance_btn1_link',
      'binance_btn2_label','binance_btn2_amount','binance_btn2_link',
      'binance_btn3_label','binance_btn3_amount','binance_btn3_link','binance_pay_visible'] as $f) {
      if (array_key_exists($f, $body)) $data[$f] = $body[$f];
    }
    if ($data) Database::update('payment_settings', $data, 'id = 1');
    jsonResponse(['status' => 'ok']);
  }
}

// ── PROMOTIONS ────────────────────────────────────────────
if ($route === 'promotions') {
  if ($method === 'GET') {
    $now = date('Y-m-d H:i:s');
    $rows = Database::fetchAll("SELECT * FROM promotions WHERE status = 1 AND (start_date IS NULL OR start_date <= ?) AND (end_date IS NULL OR end_date >= ?) ORDER BY created_at DESC", [$now, $now]);
    jsonResponse(['promotions' => $rows]);
  }
  if ($method === 'GET' && $sub === 'all' && hasPermission('promotions')) {
    jsonResponse(['promotions' => Database::fetchAll("SELECT * FROM promotions ORDER BY created_at DESC")]);
  }
  if ($method === 'POST' && $sub === 'create' && hasPermission('promotions')) {
    $id = Database::insert('promotions', [
      'title' => $body['title'] ?? null, 'description' => $body['description'] ?? null,
      'image' => $body['image'] ?? null, 'link' => $body['link'] ?? null,
      'button_text' => $body['button_text'] ?? null, 'button_link' => $body['button_link'] ?? null,
      'start_date' => $body['start_date'] ?? null, 'end_date' => $body['end_date'] ?? null,
      'status' => $body['status'] ?? 1,
    ]);
    jsonResponse(['id' => $id]);
  }
  if ($method === 'POST' && is_numeric($sub) && hasPermission('promotions')) {
    $data = [];
    foreach (['title','description','image','link','button_text','button_link','start_date','end_date','status'] as $f) {
      if (array_key_exists($f, $body)) $data[$f] = $body[$f];
    }
    if ($data) Database::update('promotions', $data, 'id = ?', [(int)$sub]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'delete' && hasPermission('promotions')) {
    Database::delete('promotions', 'id = ?', [(int)$body['id']]);
    jsonResponse(['status' => 'ok']);
  }
}

// ── CATEGORIES ────────────────────────────────────────────
if ($route === 'categories') {
  if ($method === 'GET') {
    jsonResponse(['categories' => Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC")]);
  }
  if ($method === 'GET' && $sub === 'all' && hasPermission('panels')) {
    jsonResponse(['categories' => Database::fetchAll("SELECT * FROM categories ORDER BY sort_order ASC")]);
  }
  if ($method === 'POST' && $sub === 'create' && hasPermission('panels')) {
    $id = Database::insert('categories', ['name' => $body['name'], 'sort_order' => $body['sort_order'] ?? 0, 'status' => $body['status'] ?? 'active']);
    jsonResponse(['id' => $id]);
  }
  if ($method === 'POST' && is_numeric($sub) && hasPermission('panels')) {
    $data = [];
    foreach (['name','sort_order','status'] as $f) { if (array_key_exists($f, $body)) $data[$f] = $body[$f]; }
    if ($data) Database::update('categories', $data, 'id = ?', [(int)$sub]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'delete' && hasPermission('panels')) {
    Database::delete('categories', 'id = ?', [(int)$body['id']]);
    jsonResponse(['status' => 'ok']);
  }
}

if ($route === 'panel-categories') {
  if ($method === 'GET') {
    jsonResponse(['mappings' => Database::fetchAll("SELECT * FROM panel_categories")]);
  }
  if ($method === 'POST' && $sub === 'assign' && hasPermission('panels')) {
    $panelId = (int)$body['panel_id'];
    $catIds = $body['category_ids'] ?? [];
    Database::delete('panel_categories', 'panel_id = ?', [$panelId]);
    foreach ($catIds as $cid) {
      Database::query("INSERT IGNORE INTO panel_categories (panel_id, category_id) VALUES (?, ?)", [$panelId, (int)$cid]);
    }
    jsonResponse(['status' => 'ok']);
  }
}

// ── PLAN DURATIONS ────────────────────────────────────────
if ($route === 'plan-durations') {
  if ($method === 'GET') {
    jsonResponse(['durations' => Database::fetchAll("SELECT * FROM plan_durations ORDER BY sort_order ASC")]);
  }
  if ($method === 'POST' && $sub === 'create' && hasPermission('panels')) {
    $key = strtolower(preg_replace('/[^a-z0-9]+/', '_', $body['label'] ?? '')) . '_' . time();
    $id = Database::insert('plan_durations', [
      'key' => $key, 'label' => $body['label'], 'value_hours' => (int)$body['value_hours'],
      'default_price' => (float)($body['default_price'] ?? 0), 'is_default' => 0,
      'sort_order' => $body['sort_order'] ?? 99,
    ]);
    jsonResponse(['id' => $id]);
  }
  if ($method === 'POST' && is_numeric($sub) && hasPermission('panels')) {
    $data = [];
    foreach (['label','value_hours','default_price','sort_order'] as $f) { if (array_key_exists($f, $body)) $data[$f] = $body[$f]; }
    if ($data) Database::update('plan_durations', $data, 'id = ?', [(int)$sub]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'delete' && hasPermission('panels')) {
    Database::delete('plan_durations', 'id = ? AND is_default = 0', [(int)$body['id']]);
    jsonResponse(['status' => 'ok']);
  }
}

// ── USERS (admin) ─────────────────────────────────────────
if ($route === 'users') {
  if ($method === 'GET' && hasPermission('users')) {
    $rows = Database::fetchAll("SELECT id, email, whatsapp_number, balance, is_admin, is_hidden, discount_percent, referred_by, referral_code, avatar_url, status, created_at FROM users WHERE is_hidden = 0 ORDER BY created_at DESC");
    jsonResponse(['users' => $rows]);
  }
  if ($method === 'POST' && $sub === 'create' && hasPermission('users')) {
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Invalid email'], 400);
    if (strlen($password) < 6) jsonResponse(['error' => 'Password too short'], 400);
    $existing = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) jsonResponse(['error' => 'Email exists'], 400);
    $id = Database::insert('users', [
      'email' => $email, 'password_hash' => password_hash($password, PASSWORD_BCRYPT),
      'whatsapp_number' => $body['whatsapp'] ?? null, 'referral_code' => genReferralCode(),
    ]);
    jsonResponse(['id' => $id]);
  }
  if ($method === 'POST' && $sub === 'update' && hasPermission('users')) {
    $uid = (int)$body['id'];
    $data = [];
    foreach (['email','whatsapp_number','balance','discount_percent','status'] as $f) { if (array_key_exists($f, $body)) $data[$f] = $body[$f]; }
    if (!empty($body['password'])) $data['password_hash'] = password_hash($body['password'], PASSWORD_BCRYPT);
    if ($data) Database::update('users', $data, 'id = ?', [$uid]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'delete' && hasPermission('users')) {
    $uid = (int)$body['id'];
    $u = Database::fetch("SELECT is_admin FROM users WHERE id = ?", [$uid]);
    if ($u && $u['is_admin']) jsonResponse(['error' => 'Cannot delete admin'], 400);
    Database::delete('users', 'id = ?', [$uid]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'GET' && is_numeric($sub) && hasPermission('users')) {
    $purchases = Database::fetchAll("SELECT * FROM purchases WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [(int)$sub]);
    jsonResponse(['purchases' => $purchases]);
  }
}

// ── DELIVERY HISTORY ──────────────────────────────────────
if ($route === 'delivery-history' && $method === 'GET' && hasPermission('key-delivery')) {
  $rows = Database::fetchAll("SELECT p.*, u.email, u.referral_code FROM purchases p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
  jsonResponse(['purchases' => $rows]);
}

// ── DASHBOARD STATS ───────────────────────────────────────
if ($route === 'dashboard' && $method === 'GET' && hasPermission('dashboard')) {
  $stats = [
    'users' => (int)Database::scalar("SELECT COUNT(*) FROM users WHERE is_hidden = 0"),
    'activeUsers' => (int)Database::scalar("SELECT COUNT(*) FROM users WHERE balance > 0 AND is_hidden = 0"),
    'panels' => (int)Database::scalar("SELECT COUNT(*) FROM panels WHERE status = 'active'"),
    'poolKeys' => (int)Database::scalar("SELECT COUNT(*) FROM keys_pool WHERE is_used = 0"),
    'paymentMethods' => (int)Database::scalar("SELECT COUNT(*) FROM payment_methods"),
    'pendingPayments' => (int)Database::scalar("SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
    'lifetimeSold' => (int)Database::scalar("SELECT COUNT(*) FROM purchases"),
    'lifetimeRevenue' => (float)Database::scalar("SELECT COALESCE(SUM(price),0) FROM purchases"),
    'todaySold' => (int)Database::scalar("SELECT COUNT(*) FROM purchases WHERE DATE(created_at) = CURDATE()"),
    'todayRevenue' => (float)Database::scalar("SELECT COALESCE(SUM(price),0) FROM purchases WHERE DATE(created_at) = CURDATE()"),
    'categories' => (int)Database::scalar("SELECT COUNT(*) FROM categories WHERE status = 'active'"),
    'apiProviders' => (int)Database::scalar("SELECT COUNT(*) FROM api_providers WHERE status = 'active'"),
    'apiOrders' => (int)Database::scalar("SELECT COUNT(*) FROM api_orders"),
  ];
  jsonResponse(['stats' => $stats]);
}

// ── APP CONTROLS ──────────────────────────────────────────
if ($route === 'controls') {
  if ($method === 'GET') {
    jsonResponse(['controls' => getAppControls()]);
  }
  if ($method === 'POST' && hasPermission('settings')) {
    $data = [];
    foreach (['support_button_enabled','footer_icons_enabled','theme_switcher_enabled',
      'developer_credit_enabled','developer_credit_text','developer_credit_url',
      'floating_channel_enabled','floating_whatsapp_link','floating_whatsapp_number','floating_whatsapp_message',
      'default_theme','user_profile_enabled','user_balance_enabled','referral_bonus_percent',
      'signup_bonus_amount','signup_bonus_currency','welcome_message_enabled','welcome_message_line1',
      'welcome_message_line2','welcome_message_duration','global_discount_percent','global_discount_enabled',
      'maintenance_enabled','maintenance_ends_at','currency_switcher_enabled'] as $f) {
      if (array_key_exists($f, $body)) $data[$f] = $body[$f];
    }
    if ($data) Database::update('app_controls', $data, 'id = 1');
    jsonResponse(['status' => 'ok']);
  }
}

// ── SITE BRANDING ─────────────────────────────────────────
if ($route === 'branding') {
  if ($method === 'GET') {
    jsonResponse(['branding' => getSiteBranding()]);
  }
  if ($method === 'POST' && hasPermission('settings')) {
    $data = [];
    foreach (['site_name','site_title','site_description','favicon_url','logo_url','og_image_url','site_url'] as $f) {
      if (array_key_exists($f, $body)) $data[$f] = $body[$f];
    }
    if ($data) Database::update('site_branding', $data, 'id = 1');
    jsonResponse(['status' => 'ok']);
  }
}

// ── SUPPORT LINKS ─────────────────────────────────────────
if ($route === 'support-links') {
  if ($method === 'GET') {
    jsonResponse(['links' => getSupportLinks()]);
  }
  if ($method === 'POST' && hasPermission('tg-config')) {
    $data = [];
    foreach (['telegram_login','telegram_dashboard','telegram_channel_floating','whatsapp','instagram',
      'facebook','youtube','wa_support_enabled','wa_support_number','wa_support_icon','wa_support_message'] as $f) {
      if (array_key_exists($f, $body)) $data[$f] = $body[$f];
    }
    if ($data) Database::update('support_links', $data, 'id = 1');
    jsonResponse(['status' => 'ok']);
  }
}

// ── EXCHANGE RATES ────────────────────────────────────────
if ($route === 'exchange-rates') {
  if ($method === 'GET') {
    jsonResponse(['rates' => getExchangeRates()]);
  }
  if ($method === 'POST' && hasPermission('settings')) {
    $data = [];
    foreach (['usdt_to_pkr','usdt_to_inr','usdt_to_bdt','usdt_to_lkr','usdt_to_npr','usdt_to_brl','usdt_to_usdt'] as $f) {
      if (array_key_exists($f, $body)) $data[$f] = $body[$f];
    }
    if ($data) Database::update('exchange_rates', $data, 'id = 1');
    jsonResponse(['status' => 'ok']);
  }
}

// ── LIVE CHAT ─────────────────────────────────────────────
if ($route === 'live-chat') {
  if ($method === 'GET') {
    jsonResponse(['config' => Database::fetch("SELECT * FROM live_chat WHERE id = 1")]);
  }
  if ($method === 'POST' && hasPermission('live-chat')) {
    $data = [];
    foreach (['embed_code','enabled'] as $f) { if (array_key_exists($f, $body)) $data[$f] = $body[$f]; }
    if ($data) Database::update('live_chat', $data, 'id = 1');
    jsonResponse(['status' => 'ok']);
  }
}

// ── SECONDARY ADMINS ──────────────────────────────────────
if ($route === 'admins') {
  requireMainAdmin();
  if ($method === 'GET') {
    jsonResponse(['admins' => Database::fetchAll("SELECT sa.*, u.email FROM secondary_admins sa LEFT JOIN users u ON sa.user_id = u.id ORDER BY sa.created_at DESC")]);
  }
  if ($method === 'POST' && $sub === 'create') {
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $expiry = $body['expiry_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $perms = $body['permissions'] ?? defaultPermissions();
    if (strlen($password) < 6) jsonResponse(['error' => 'Password too short'], 400);
    $email = strtolower($username) . '@hasi.local';
    $existing = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) jsonResponse(['error' => 'Username exists'], 400);
    $uid = Database::insert('users', [
      'email' => $email, 'password_hash' => password_hash($password, PASSWORD_BCRYPT),
      'is_secondary_admin' => 1, 'referral_code' => genReferralCode(),
    ]);
    $id = Database::insert('secondary_admins', [
      'user_id' => $uid, 'username' => $username, 'expiry_date' => $expiry,
      'status' => 'active', 'permissions' => json_encode($perms),
    ]);
    jsonResponse(['id' => $id]);
  }
  if ($method === 'POST' && is_numeric($sub)) {
    $data = [];
    foreach (['expiry_date','status','permissions'] as $f) { if (array_key_exists($f, $body)) $data[$f] = is_array($body[$f]) ? json_encode($body[$f]) : $body[$f]; }
    if (!empty($body['password'])) {
      $admin = Database::fetch("SELECT user_id FROM secondary_admins WHERE id = ?", [(int)$sub]);
      if ($admin) Database::update('users', ['password_hash' => password_hash($body['password'], PASSWORD_BCRYPT)], 'id = ?', [$admin['user_id']]);
    }
    if ($data) Database::update('secondary_admins', $data, 'id = ?', [(int)$sub]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'delete') {
    $admin = Database::fetch("SELECT user_id FROM secondary_admins WHERE id = ?", [(int)$body['id']]);
    Database::delete('secondary_admins', 'id = ?', [(int)$body['id']]);
    if ($admin) Database::delete('users', 'id = ?', [$admin['user_id']]);
    jsonResponse(['status' => 'ok']);
  }
}

// ── API PROVIDERS ─────────────────────────────────────────
if ($route === 'api-providers') {
  requirePermission('api-management');
  if ($method === 'GET') {
    jsonResponse(['providers' => Database::fetchAll("SELECT * FROM api_providers ORDER BY created_at DESC")]);
  }
  if ($method === 'POST' && $sub === 'create') {
    $id = Database::insert('api_providers', [
      'name' => $body['name'], 'api_url' => rtrim($body['api_url'] ?? '', '/'),
      'api_key' => $body['api_key'], 'profit_percent' => (float)($body['profit_percent'] ?? 0),
      'status' => $body['status'] ?? 'active',
      'auto_sync_enabled' => $body['auto_sync_enabled'] ?? 0,
      'auto_sync_interval_minutes' => (int)($body['auto_sync_interval_minutes'] ?? 20),
      'auto_import_enabled' => $body['auto_import_enabled'] ?? 0,
    ]);
    jsonResponse(['id' => $id]);
  }
  if ($method === 'POST' && is_numeric($sub)) {
    $data = [];
    foreach (['name','api_url','api_key','profit_percent','status','auto_sync_enabled','auto_sync_interval_minutes','auto_import_enabled'] as $f) {
      if (array_key_exists($f, $body)) $data[$f] = $body[$f];
    }
    if ($data) Database::update('api_providers', $data, 'id = ?', [(int)$sub]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'delete') {
    Database::delete('api_providers', 'id = ?', [(int)$body['id']]);
    jsonResponse(['status' => 'ok']);
  }
  if ($method === 'POST' && $sub === 'sync-balance') {
    $provider = Database::fetch("SELECT * FROM api_providers WHERE id = ?", [(int)$body['provider_id']]);
    if (!$provider) jsonResponse(['error' => 'Not found'], 404);
    $result = callExternalApi($provider, '/balance.php', 'GET');
    if (!$result['success']) jsonResponse(['error' => $result['error']], 502);
    $balance = extractBalance($result['data']);
    Database::update('api_providers', ['balance' => $balance, 'balance_synced_at' => date('Y-m-d H:i:s')], 'id = ?', [$provider['id']]);
    jsonResponse(['balance' => $balance]);
  }
  if ($method === 'POST' && $sub === 'import') {
    $provider = Database::fetch("SELECT * FROM api_providers WHERE id = ?", [(int)$body['provider_id']]);
    if (!$provider) jsonResponse(['error' => 'Not found'], 404);
    $result = callExternalApi($provider, '/products.php', 'GET');
    if (!$result['success']) jsonResponse(['error' => $result['error']], 502);
    $products = extractProducts($result['data']);
    $profit = (float)$provider['profit_percent'];
    $panelsCreated = 0; $panelsUpdated = 0; $svcInserted = 0; $svcUpdated = 0;
    $groups = [];
    foreach ($products as $p) {
      $gk = $p['product_name'] ?? $p['name'] ?? "Service {$p['id']}";
      $groups[$gk][] = $p;
    }
    foreach ($groups as $pName => $variants) {
      $existing = Database::fetch("SELECT id, plans FROM panels WHERE api_provider_id = ? AND name = ?", [$provider['id'], $pName]);
      $effProfit = $existing ? (float)Database::fetch("SELECT profit_percent FROM panels WHERE id = ?", [$existing['id']])['profit_percent'] : $profit;
      $plans = [];
      foreach ($variants as $v) {
        $apiPrice = (float)$v['price'];
        $sell = round($apiPrice * (1 + $effProfit / 100), 2);
        $plans[] = ['key' => "api_{$v['id']}", 'label' => $v['variant_name'] ?? $v['name'], 'price' => $sell, 'api_price' => $apiPrice, 'api_service_id' => $v['id']];
      }
      if ($existing) {
        Database::update('panels', ['plans' => json_encode($plans)], 'id = ?', [$existing['id']]);
        $panelId = $existing['id'];
        $panelsUpdated++;
      } else {
        $panelId = Database::insert('panels', [
          'name' => $pName, 'status' => 'active', 'plans' => json_encode($plans),
          'price_currency' => 'USDT', 'api_provider_id' => $provider['id'], 'sort_order' => 999,
        ]);
        $panelsCreated++;
      }
      foreach ($variants as $v) {
        $apiPrice = (float)$v['price'];
        $sell = round($apiPrice * (1 + $effProfit / 100), 2);
        $svc = Database::fetch("SELECT id FROM api_services WHERE provider_id = ? AND api_service_id = ?", [$provider['id'], $v['id']]);
        $payload = ['name' => $v['name'], 'api_price' => $apiPrice, 'sell_price' => $sell, 'panel_id' => $panelId, 'last_synced_at' => date('Y-m-d H:i:s')];
        if ($svc) { Database::update('api_services', $payload, 'id = ?', [$svc['id']]); $svcUpdated++; }
        else { Database::insert('api_services', array_merge($payload, ['provider_id' => $provider['id'], 'api_service_id' => $v['id'], 'status' => 'active'])); $svcInserted++; }
      }
    }
    jsonResponse(['panels_created' => $panelsCreated, 'panels_updated' => $panelsUpdated, 'services_inserted' => $svcInserted, 'services_updated' => $svcUpdated, 'total_variants' => count($products)]);
  }
  if ($method === 'GET' && $sub === 'services') {
    $pid = (int)getQueryParam('provider_id', 0);
    jsonResponse(['services' => Database::fetchAll("SELECT * FROM api_services WHERE provider_id = ? ORDER BY name ASC", [$pid])]);
  }
}

// ── API ANALYTICS ─────────────────────────────────────────
if ($route === 'api-analytics' && $method === 'GET' && hasPermission('api-analytics')) {
  $providers = Database::fetchAll("SELECT * FROM api_providers ORDER BY created_at DESC");
  $orders = Database::fetchAll("SELECT provider_id, unit_sell, profit, status FROM api_orders");
  $totalSales = 0; $totalProfit = 0; $totalOrders = count($orders); $successOrders = 0; $failedOrders = 0;
  foreach ($orders as $o) {
    if ($o['status'] === 'success') { $totalSales += (float)$o['unit_sell']; $totalProfit += (float)$o['profit']; $successOrders++; }
    if ($o['status'] === 'failed') $failedOrders++;
  }
  $perProvider = [];
  foreach ($providers as $p) {
    $pOrders = array_filter($orders, fn($o) => $o['provider_id'] == $p['id']);
    $pSuccess = array_filter($pOrders, fn($o) => $o['status'] === 'success');
    $perProvider[] = [
      'provider' => $p,
      'sales' => array_sum(array_map(fn($o) => (float)$o['unit_sell'], $pSuccess)),
      'profit' => array_sum(array_map(fn($o) => (float)$o['profit'], $pSuccess)),
      'orders' => count($pOrders),
      'successful' => count($pSuccess),
    ];
  }
  jsonResponse([
    'totalSales' => $totalSales, 'totalProfit' => $totalProfit,
    'totalOrders' => $totalOrders, 'successfulOrders' => $successOrders,
    'failedOrders' => $failedOrders, 'perProvider' => $perProvider,
  ]);
}

// ── UPLOAD ────────────────────────────────────────────────
if ($route === 'upload' && $method === 'POST') {
  $user = requireAuth();
  $type = $body['type'] ?? $_GET['type'] ?? 'general';
  $subdirs = ['panel-thumbnails' => 'panels', 'promotion-images' => 'promotions', 'payment-logos' => 'payment', 'avatars' => 'avatars', 'branding' => 'branding'];
  $subdir = $subdirs[$type] ?? 'general';
  if (!isset($_FILES['file'])) jsonResponse(['error' => 'No file'], 400);
  $url = handleUpload($_FILES['file'], $subdir);
  if (!$url) jsonResponse(['error' => 'Upload failed'], 400);
  jsonResponse(['url' => $url]);
}

// ── REFERRAL ──────────────────────────────────────────────
if ($route === 'referral' && $method === 'GET') {
  $user = requireAuth();
  $code = $user['referral_code'];
  $count = (int)Database::scalar("SELECT COUNT(*) FROM users WHERE referred_by = ?", [$code]);
  $branding = getSiteBranding();
  $baseUrl = !empty($branding['site_url']) ? rtrim($branding['site_url'], '/') : (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
  jsonResponse(['code' => $code, 'link' => "{$baseUrl}/?ref={$code}", 'team_count' => $count]);
}

// ── MAINTENANCE ───────────────────────────────────────────
if ($route === 'maintenance' && $method === 'GET') {
  $controls = getAppControls();
  jsonResponse(['enabled' => (bool)($controls['maintenance_enabled'] ?? 0), 'ends_at' => $controls['maintenance_ends_at'] ?? null]);
}

// ── 404 ──────────────────────────────────────────────────
jsonResponse(['error' => 'Endpoint not found', 'route' => $route, 'sub' => $sub], 404);
