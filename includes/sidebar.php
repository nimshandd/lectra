<?php
$isHod = ($_SESSION['user']['role'] ?? '') === 'hod';
$home = $isHod ? '/hod/dashboard.php' : '/lecturer/dashboard.php';
$current = static function (string $path): bool {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (str_ends_with($requestPath, $path)) return true;
    if ($path === '/hod/attendance.php' && str_ends_with($requestPath, '/hod/session.php')) return true;
    if ($path === '/lecturer/history.php' && str_ends_with($requestPath, '/lecturer/session.php')) return true;
    return str_ends_with($path, '/index.php') && str_contains($requestPath, substr($path, 0, -strlen('index.php')));
};
$links = $isHod ? [
    ['/hod/dashboard.php', 'Dashboard'], ['/hod/batches/index.php', 'Batches'], ['/hod/students/index.php', 'Students'],
    ['/hod/lecturers/index.php', 'Lecturers'], ['/hod/subjects/index.php', 'Subjects'], ['/hod/attendance.php', 'Attendance View'],
    ['/hod/eligibility.php', 'Eligibility Report'], ['/hod/medical.php', 'Medical Adjustments'], ['/hod/clear_records.php', 'Clear Records'],
] : [
    ['/lecturer/dashboard.php', 'Dashboard'], ['/lecturer/classes.php', 'My Classes'],
    ['/lecturer/attendance.php', 'Take Attendance'], ['/lecturer/history.php', 'Attendance History'],
];
$icons = [
    'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
    'batches' => '<rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/>',
    'students' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'attendance' => '<path d="m5 12 4 4L19 6"/>',
    'classes' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'history' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
];
$icon = static function (string $name) use ($icons): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.($icons[$name] ?? $icons['batches']).'</svg>';
};
?>
<aside class="sidebar" id="app-sidebar" aria-label="Main navigation" data-sidebar>
  <button class="sidebar-close" type="button" aria-label="Close navigation" data-nav-close>×</button>
  <a class="brand" href="<?= baseUrl($home) ?>"><img src="<?= baseUrl('/assets/images/lectra-logo.png') ?>" alt="Lectra Attendance Management System"></a>
  <nav class="desktop-nav">
    <?php foreach ($links as [$path, $label]): ?>
      <a href="<?= baseUrl($path) ?>"<?= $current($path) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= baseUrl('/logout.php') ?>">Sign out</a>
  </nav>
</aside>
<nav class="mobile-nav" aria-label="Primary navigation">
  <?php if ($isHod): ?>
    <?php foreach ([['/hod/dashboard.php','Home','home'], ['/hod/batches/index.php','Batches','batches'], ['/hod/students/index.php','Students','students'], ['/hod/attendance.php','Attendance','attendance']] as [$path,$label,$symbol]): ?>
      <a href="<?= baseUrl($path) ?>"<?= $current($path) ? ' aria-current="page"' : '' ?>><b><?= $icon($symbol) ?></b><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
  <?php else: ?>
    <?php foreach ([['/lecturer/dashboard.php','Home','home'], ['/lecturer/classes.php','Classes','classes'], ['/lecturer/attendance.php','Attendance','attendance'], ['/lecturer/history.php','History','history']] as [$path,$label,$symbol]): ?>
      <a href="<?= baseUrl($path) ?>"<?= $current($path) ? ' aria-current="page"' : '' ?>><b><?= $icon($symbol) ?></b><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
  <?php endif; ?>
</nav>
