<?php
// ============================================================
// Hasi Panel Store — Main Entry Point (SPA Shell)
// Loads site branding from DB and serves the HTML shell.
// All routing is done client-side via JavaScript.
// ============================================================

require_once __DIR__ . '/helpers.php';

$branding = getSiteBranding();
$controls = getAppControls();
$siteName = sanitize($branding['site_name'] ?: SITE_NAME);
$siteTitle = sanitize($branding['site_title'] ?: SITE_TITLE);
$siteDesc = sanitize($branding['site_description'] ?: SITE_DESCRIPTION);
$favicon = $branding['favicon_url'] ?: '';
$logoUrl = $branding['logo_url'] ?: '';
$ogImage = $branding['og_image_url'] ?: $logoUrl ?: $favicon;
$defaultTheme = sanitize($branding['default_theme'] ?? $controls['default_theme'] ?? 'midnight');
$siteUrl = '';
if (!empty($branding['site_url'])) $siteUrl = $branding['site_url'];
else $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$maintenanceEnabled = (bool)($controls['maintenance_enabled'] ?? false);
$maintenanceEnds = $controls['maintenance_ends_at'] ?? null;
$maintenanceExpired = $maintenanceEnabled && $maintenanceEnds && strtotime($maintenanceEnds) < time();
$showMaintenance = $maintenanceEnabled && !$maintenanceExpired;
?>
<!doctype html>
<html lang="en" data-hps-theme="<?= $defaultTheme ?>">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/png" sizes="32x32" href="<?= sanitize($favicon) ?>" />
  <link rel="icon" type="image/png" sizes="16x16" href="<?= sanitize($favicon) ?>" />
  <link rel="apple-touch-icon" href="<?= sanitize($favicon) ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title><?= $siteTitle ?></title>
  <meta name="description" content="<?= $siteDesc ?>" />
  <meta name="theme-color" content="#0f172a" />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="<?= $siteName ?>" />
  <meta property="og:title" content="<?= $siteTitle ?>" />
  <meta property="og:description" content="<?= $siteDesc ?>" />
  <meta property="og:image" content="<?= sanitize($ogImage) ?>" />
  <meta property="og:url" content="<?= sanitize($siteUrl) ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= $siteTitle ?>" />
  <meta name="twitter:description" content="<?= $siteDesc ?>" />
  <meta name="twitter:image" content="<?= sanitize($ogImage) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <div id="root"></div>
  <div id="modal-root"></div>
  <script>
    window.HASI_CONFIG = {
      apiUrl: 'api/api.php',
      siteName: '<?= $siteName ?>',
      defaultTheme: '<?= $defaultTheme ?>',
      maintenance: <?= $showMaintenance ? 'true' : 'false' ?>,
      maintenanceEnds: <?= $maintenanceEnds ? json_encode($maintenanceEnds) : 'null' ?>,
    };
  </script>
  <script src="assets/js/app.js"></script>
</body>
</html>
