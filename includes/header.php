<?php require_once __DIR__ . '/auth.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title><?= e($title ?? 'Lectra') ?></title>
  <link rel="stylesheet" href="<?= baseUrl('/assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
  <link rel="stylesheet" href="<?= baseUrl('/assets/css/attendance-toggle.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/attendance-toggle.css') ?>">
  <link rel="stylesheet" href="<?= baseUrl('/assets/css/mobile-app.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/mobile-app.css') ?>">
  <?php if (($title ?? '') === 'Eligibility Report'): ?>
    <link rel="stylesheet" href="<?= baseUrl('/assets/css/eligibility-report.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/eligibility-report.css') ?>">
  <?php endif; ?>
</head>
<body>
<?php if (!empty($_SESSION['user'])): ?>
  <?php require __DIR__ . '/sidebar.php'; ?>
  <button class="nav-backdrop" type="button" aria-label="Close navigation" data-nav-close tabindex="-1"></button>
<?php endif; ?>
<main class="main <?= empty($_SESSION['user']) ? 'auth-main' : '' ?>">
  <header class="topbar">
    <?php if (!empty($_SESSION['user'])): ?>
      <button class="menu-button" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="app-sidebar" data-nav-toggle>Menu</button>
    <?php endif; ?>
    <h1><?= e($title ?? 'Lectra') ?></h1>
    <?php if (!empty($_SESSION['user'])): ?><span title="Signed in as <?= e($_SESSION['user']['name'] ?? '') ?>"><?= e($_SESSION['user']['name'] ?? '') ?></span><?php endif; ?>
  </header>
  <?php showFlash(); ?>
  <div class="page-content">
