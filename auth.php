<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
session_start();

function currentUser(): ?array {
  return $_SESSION['user'] ?? null;
}
function requireLogin(): array {
  $u = currentUser();
  if (!$u) {
    header('Location: login.php');
    exit;
  }
  return $u;
}
function isAdmin(array $u): bool {
  return ($u['role'] ?? '') === 'admin';
}
function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function requireAdmin(): array {
  $u = requireLogin();
  if (!isAdmin($u)) {
    http_response_code(403);
    echo "Forbidden (admin only)";
    exit;
  }
  return $u;
}

function csrfToken(): string {
  if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
  if (!is_string($token) || $token === '') {
    return false;
  }
  $sessionToken = $_SESSION['csrf_token'] ?? '';
  if (!is_string($sessionToken) || $sessionToken === '') {
    return false;
  }
  return hash_equals($sessionToken, $token);
}

function setFlash(string $key, string $message): void {
  $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string {
  $message = $_SESSION['flash'][$key] ?? null;
  if ($message !== null) {
    unset($_SESSION['flash'][$key]);
  }
  return is_string($message) ? $message : null;
}
