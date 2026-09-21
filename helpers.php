<?php
// ============================================================
// Helper Functions
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── JSON Response ─────────────────────────────────────────
function jsonResponse($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function corsHeaders(): void {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Client-Info, Apikey');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
  }
}

// ── Input Parsing ─────────────────────────────────────────
function getRequestBody(): array {
  $raw = file_get_contents('php://input');
  if (empty($raw)) return $_POST ?: [];
  $json = json_decode($raw, true);
  return is_array($json) ? $json : [];
}

function getQueryParam(string $key, $default = null) {
  return $_GET[$key] ?? $default;
}

function getBearerToken(): ?string {
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return trim($m[1]);
  return null;
}

// ── Simple JWT-like Token (HMAC) ──────────────────────────
function createToken(int $userId, bool $isAdmin = false): string {
  $payload = base64_encode(json_encode([
    'uid' => $userId,
    'adm' => $isAdmin,
    'exp' => time() + SESSION_LIFETIME,
  'nonce' => bin2hex(random_bytes(8)),
  ]));
  $sig = hash_hmac('sha256', $payload, JWT_SECRET);
  return $payload . '.' . $sig;
}

function verifyToken(?string $token): ?array {
  if (!$token) return null;
  $parts = explode('.', $token);
  if (count($parts) !== 2) return null;
  [$payload, $sig] = $parts;
  if (!hash_equals(hash_hmac('sha256', $payload, JWT_SECRET), $sig)) return null;
  $data = json_decode(base64_decode($payload), true);
  if (!is_array($data)) return null;
  if (($data['exp'] ?? 0) < time()) return null;
  return $data;
}

// ── Auth ──────────────────────────────────────────────────
function currentUser(): ?array {
  static $user = null;
  static $loaded = false;
  if ($loaded) return $user;
  $loaded = true;
  $token = getBearerToken();
  $payload = verifyToken($token);
  if (!$payload) return null;
  $user = Database::fetch("SELECT * FROM users WHERE id = ? AND status = 'active'", [$payload['uid']]);
  return $user;
}

function requireAuth(): array {
  $user = currentUser();
  if (!$user) jsonResponse(['error' => 'Not authenticated'], 401);
  return $user;
}

function requireAdmin(): array {
  $user = requireAuth();
  if (!$user['is_admin'] && !$user['is_secondary_admin']) {
    jsonResponse(['error' => 'Not authorized'], 403);
  }
  // Check secondary admin expiry
  if ($user['is_secondary_admin'] && !$user['is_admin']) {
    $sec = Database::fetch("SELECT * FROM secondary_admins WHERE user_id = ?", [$user['id']]);
    if (!$sec || $sec['status'] !== 'active' || strtotime($sec['expiry_date']) < time()) {
      jsonResponse(['error' => 'Admin access expired'], 403);
    }
  }
  return $user;
}

function requireMainAdmin(): array {
  $user = requireAuth();
  if (!$user['is_admin']) jsonResponse(['error' => 'Main admin only'], 403);
  return $user;
}

function hasPermission(string $feature): bool {
  $user = currentUser();
  if (!$user) return false;
  if ($user['is_admin']) return true;
  if ($user['is_secondary_admin']) {
    $sec = Database::fetch("SELECT * FROM secondary_admins WHERE user_id = ?", [$user['id']]);
    if (!$sec || $sec['status'] !== 'active' || strtotime($sec['expiry_date']) < time()) return false;
    $perms = json_decode($sec['permissions'] ?? '{}', true);
    return ($perms[$feature] ?? 'false') === 'true';
  }
  return false;
}

function requirePermission(string $feature): array {
  $user = requireAuth();
  if ($user['is_admin']) return $user;
  if (!hasPermission($feature)) jsonResponse(['error' => 'No permission for this feature'], 403);
  return $user;
}

// ── Utility ───────────────────────────────────────────────
function genReferralCode(): string {
  return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function convertToUSDT(float $amount, string $fromCurrency, array $rates): float {
  if ($amount <= 0) return 0;
  $cur = strtoupper($fromCurrency ?: 'USDT');
  switch ($cur) {
    case 'USDT': return $amount;
    case 'PKR': return $rates['usdt_to_pkr'] > 0 ? $amount / $rates['usdt_to_pkr'] : $amount;
    case 'INR': return $rates['usdt_to_inr'] > 0 ? $amount / $rates['usdt_to_inr'] : $amount;
    case 'BDT': return $rates['usdt_to_bdt'] > 0 ? $amount / $rates['usdt_to_bdt'] : $amount;
    case 'LKR': return $rates['usdt_to_lkr'] > 0 ? $amount / $rates['usdt_to_lkr'] : $amount;
    case 'NPR': return $rates['usdt_to_npr'] > 0 ? $amount / $rates['usdt_to_npr'] : $amount;
    case 'BRL': return $rates['usdt_to_brl'] > 0 ? $amount / $rates['usdt_to_brl'] : $amount;
    default: return $amount;
  }
}

function getExchangeRates(): array {
  $row = Database::fetch("SELECT * FROM exchange_rates WHERE id = 1");
  return $row ?: [
    'usdt_to_pkr' => 280, 'usdt_to_inr' => 83, 'usdt_to_bdt' => 120,
    'usdt_to_lkr' => 300, 'usdt_to_npr' => 133, 'usdt_to_brl' => 5.5, 'usdt_to_usdt' => 1,
  ];
}

function getAppControls(): array {
  $row = Database::fetch("SELECT * FROM app_controls WHERE id = 1");
  return $row ?: [];
}

function getSiteBranding(): array {
  $row = Database::fetch("SELECT * FROM site_branding WHERE id = 1");
  return $row ?: [
    'site_name' => SITE_NAME, 'site_title' => SITE_TITLE,
    'site_description' => SITE_DESCRIPTION, 'favicon_url' => null,
    'logo_url' => null, 'og_image_url' => null, 'site_url' => null,
  ];
}

function getSupportLinks(): array {
  $row = Database::fetch("SELECT * FROM support_links WHERE id = 1");
  return $row ?: [];
}

function poolFromLabel(string $label, string $fallback): string {
  $l = strtolower($label);
  $hasDay = str_contains($l, 'day') || str_contains($l, 'key');
  $hasHour = str_contains($l, 'hour') || str_contains($l, 'hr');
  if (str_contains($l, '30') && $hasDay) return '30_days';
  if (str_contains($l, '15') && $hasDay) return '15_days';
  if (str_contains($l, '7') && $hasDay) return '7_days';
  if (str_contains($l, '3') && $hasDay) return '3_days';
  if (str_contains($l, '1') && $hasDay) return '1_day';
  if (str_contains($l, '12') && $hasHour) return '12_hour';
  if (str_contains($l, '6') && $hasHour) return '6_hour';
  if (str_contains($l, '3') && $hasHour) return '3_hour';
  if (str_contains($l, '1') && $hasHour) return '1_hour';
  return $fallback;
}

function durationDays(string $pool, string $label, array $durations, ?array $plan = null): int {
  if ($plan && isset($plan['duration_days']) && $plan['duration_days'] > 0) return (int)$plan['duration_days'];
  $custom = null;
  foreach ($durations as $d) { if ($d['key'] === $pool) { $custom = $d; break; } }
  if ($custom) return max(1, (int)ceil($custom['value_hours'] / 24));
  $l = strtolower($label);
  $hasHour = str_contains($l, 'hour') || str_contains($l, 'hr');
  if ($hasHour) return 0;
  $hasDay = str_contains($l, 'day') || str_contains($l, 'key');
  if ($hasDay) {
    if (str_contains($l, '30')) return 30;
    if (str_contains($l, '15')) return 15;
    if (str_contains($l, '7')) return 7;
    if (str_contains($l, '3')) return 3;
    if (str_contains($l, '1')) return 1;
  }
  $m = []; if (preg_match('/(\d+)/', $pool, $m)) return (int)$m[1];
  return 30;
}

function durationHours(string $pool, string $label, array $durations, ?array $plan = null): ?int {
  if ($plan && isset($plan['duration_hours']) && $plan['duration_hours'] > 0) return (int)$plan['duration_hours'];
  $custom = null;
  foreach ($durations as $d) { if ($d['key'] === $pool) { $custom = $d; break; } }
  if ($custom) return null;
  $l = strtolower($label);
  $hasHour = str_contains($l, 'hour') || str_contains($l, 'hr');
  if ($hasHour) {
    if (str_contains($l, '12')) return 12;
    if (str_contains($l, '6')) return 6;
    if (str_contains($l, '3')) return 3;
    if (str_contains($l, '1')) return 1;
  }
  return null;
}

function formatMoney($amount): string {
  return '₨' . number_format((float)($amount ?? 0), 2);
}

function formatCompact($n): string {
  return number_format((float)($n ?? 0), 2);
}

function sanitize($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function slugify(string $s): string {
  $s = strtolower(trim($s));
  $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
  $s = preg_replace('/[\s_-]+/', '-', $s);
  $s = trim($s, '-');
  return $s ?: 'panel';
}

function getVideoEmbedUrl(?string $url): ?array {
  if (!$url || !trim($url)) return null;
  $v = trim($url);
  if (str_contains($v, 'youtube.com/watch') || str_contains($v, 'youtu.be/')) {
    $id = '';
    if (str_contains($v, 'youtu.be/')) $id = explode('youtu.be/', $v)[1] ?? '';
    else $id = explode('v=', $v)[1] ?? '';
    $id = explode('&', $id)[0];
    if ($id) return ['type' => 'iframe', 'src' => "https://www.youtube.com/embed/{$id}?autoplay=1&mute=1&playsinline=1&rel=0&modestbranding=1"];
  }
  if (str_contains($v, 'youtube.com/shorts/')) {
    $id = explode('/shorts/', $v)[1] ?? '';
    $id = explode('?', $id)[0];
    if ($id) return ['type' => 'iframe', 'src' => "https://www.youtube.com/embed/{$id}?autoplay=1&mute=1&playsinline=1&rel=0&modestbranding=1"];
  }
  if (str_contains($v, 'vimeo.com/')) {
    $id = explode('vimeo.com/', $v)[1] ?? '';
    $id = explode('?', $id)[0];
    if ($id) return ['type' => 'iframe', 'src' => "https://player.vimeo.com/video/{$id}?autoplay=1&controls=1"];
  }
  if (preg_match('/\.(mp4|webm|ogg|mov)$/i', $v)) return ['type' => 'video', 'src' => $v];
  return null;
}

function getVideoThumbnailUrl(?string $url): ?string {
  if (!$url || !trim($url)) return null;
  $v = trim($url);
  $ytId = null;
  if (str_contains($v, 'youtu.be/')) $ytId = explode('youtu.be/', $v)[1] ?? null;
  elseif (str_contains($v, 'youtube.com/watch')) $ytId = explode('v=', $v)[1] ?? null;
  elseif (str_contains($v, 'youtube.com/shorts/')) $ytId = explode('/shorts/', $v)[1] ?? null;
  if ($ytId) {
    $ytId = explode('&', explode('?', $ytId)[0])[0];
    return "https://img.youtube.com/vi/{$ytId}/hqdefault.jpg";
  }
  return null;
}

function defaultPermissions(): array {
  $map = [];
  foreach ($GLOBALS['ADMIN_FEATURES'] as $f) $map[$f['key']] = 'false';
  return $map;
}

// ── File Upload ───────────────────────────────────────────
function handleUpload(array $file, string $subdir = ''): ?string {
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] > MAX_UPLOAD_SIZE) return null;
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif', 'ico'])) return null;
  $dir = UPLOAD_DIR . $subdir;
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $filename = time() . '.' . $ext;
  $dest = $dir . '/' . $filename;
  if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
  return UPLOAD_URL . ($subdir ? $subdir . '/' : '') . $filename;
}

// ── External API Call ─────────────────────────────────────
function callExternalApi(array $provider, string $path, string $method = 'GET', ?array $body = null): array {
  $baseUrl = rtrim($provider['api_url'], '/');
  $headers = [$provider['auth_header_name'] => $provider['api_key']];
  $opts = ['method' => $method, 'headers' => $headers, 'timeout' => API_PROXY_TIMEOUT];
  if ($method === 'POST' && $body) {
    $headers['Content-Type'] = 'application/x-www-form-urlencoded';
    $opts['headers'] = $headers;
    $opts['body'] = http_build_query($body);
  }
  $url = $baseUrl . $path;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => array_map(fn($k, $v) => "$k: $v", array_keys($headers), array_values($headers)),
    CURLOPT_TIMEOUT => API_PROXY_TIMEOUT,
  ]);
  if ($method === 'POST' && $body) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
  $resp = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);
  if ($err) return ['success' => false, 'error' => 'NETWORK_ERROR', 'help' => $err];
  $text = ltrim((string)$resp);
  if (str_starts_with($text, '<') || str_contains($text, 'Just a moment')) {
    return ['success' => false, 'error' => 'CLOUDFLARE_CHALLENGE', 'help' => 'Blocked by Cloudflare'];
  }
  $json = json_decode($text, true);
  if (!is_array($json)) return ['success' => false, 'error' => 'BAD_REPLY', 'help' => substr($text, 0, 200)];
  return ['success' => true, 'data' => $json];
}

function extractBalance($data): float {
  if (is_array($data)) {
    foreach (['balance', 'Balance', 'BALANCE', 'credits', 'credit', 'amount'] as $k) {
      if (isset($data[$k]) && is_numeric($data[$k])) return (float)$data[$k];
    }
    foreach ($data as $v) {
      if (is_numeric($v) && (float)$v > 0) return (float)$v;
    }
  }
  return 0;
}

function extractProducts($data): array {
  $items = [];
  if (is_array($data)) {
    if (isset($data[0])) $items = $data;
    else {
      foreach (['products', 'data', 'items', 'result', 'services'] as $k) {
        if (isset($data[$k]) && is_array($data[$k])) { $items = $data[$k]; break; }
      }
    }
  }
  $products = [];
  foreach ($items as $item) {
    if (!is_array($item)) continue;
    $id = $item['variant_id'] ?? $item['id'] ?? $item['service_id'] ?? $item['product_id'] ?? null;
    if ($id === null) continue;
    $products[] = [
      'id' => (string)$id,
      'name' => (string)($item['name'] ?? $item['title'] ?? $item['product_name'] ?? "Service {$id}"),
      'price' => (float)($item['price'] ?? $item['cost'] ?? $item['rate'] ?? $item['amount'] ?? 0),
      'description' => isset($item['description']) ? (string)$item['description'] : null,
      'category' => isset($item['category']) ? (string)$item['category'] : null,
      'product_name' => isset($item['product_name']) ? (string)$item['product_name'] : null,
      'variant_name' => isset($item['variant_name']) ? (string)$item['variant_name'] : null,
    ];
  }
  return $products;
}

function extractKeyValue($data): ?string {
  if (!is_array($data)) return null;
  foreach (['key', 'keys', 'license_key', 'code', 'value', 'activation_key'] as $k) {
    if (isset($data[$k])) {
      if (is_string($data[$k]) && strlen($data[$k]) > 0) return $data[$k];
      if (is_array($data[$k]) && count($data[$k]) > 0) return $data[$k][0];
    }
  }
  foreach ($data as $v) {
    if (is_string($v) && strlen($v) > 10 && preg_match('/^[A-Za-z0-9\-_]+$/', $v)) return $v;
  }
  return null;
}

function extractOrderId($data): ?string {
  if (!is_array($data)) return null;
  foreach (['order_id', 'orderId', 'id', 'txn_id', 'transaction_id'] as $k) {
    if (isset($data[$k])) return (string)$data[$k];
  }
  return null;
}
