<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function baseUrl(string $path = ''): string {
	$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
	$projectRoot = dirname(__DIR__);
	$base = $documentRoot && realpath($documentRoot) === realpath($projectRoot) ? '' : '/attendance_web';
	return $base . $path;
}
function redirect(string $path): never { header('Location: ' . baseUrl($path)); exit; }
function requireLogin(): void { if (empty($_SESSION['user'])) redirect('/login.php'); }
function requireHOD(): void { requireLogin(); if ($_SESSION['user']['role'] !== 'hod') redirect('/lecturer/dashboard.php'); }
function requireLecturer(): void { requireLogin(); if ($_SESSION['user']['role'] !== 'lecturer') redirect('/hod/dashboard.php'); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verifyCsrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(403); exit('Invalid form token.'); } }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = [$message, $type]; }
function showFlash(): void { if (!empty($_SESSION['flash'])) { [$m,$t] = $_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="alert '.e($t).'">'.e($m).'</div>'; } }
