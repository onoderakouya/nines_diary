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
