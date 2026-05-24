<?php
declare(strict_types=1);

function request_is_https(): bool {
  return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
    || strtolower((string)($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")) === "https";
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  $sessionDir = "/tmp/scouthub_sessions";
  if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
  }
  session_save_path($sessionDir);
  session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => request_is_https(),
    "httponly" => true,
    "samesite" => "Lax",
  ]);
  @session_start();
}

function auth_secret(): string {
  return getenv("DB_PASS") ?: "scouthub-secret-change-me";
}

function auth_signature(int $id, string $role): string {
  return hash_hmac("sha256", $id . "|" . $role, auth_secret());
}

function set_login_cookie(int $id, string $role): void {
  $value = $id . "|" . $role . "|" . auth_signature($id, $role);
  setcookie("scouthub_auth", $value, [
    "expires" => time() + 86400 * 7,
    "path" => "/",
    "secure" => request_is_https(),
    "httponly" => true,
    "samesite" => "Lax",
  ]);
}

function clear_login_cookie(): void {
  setcookie("scouthub_auth", "", [
    "expires" => time() - 3600,
    "path" => "/",
    "secure" => request_is_https(),
    "httponly" => true,
    "samesite" => "Lax",
  ]);
}

function cookie_user(): array {
  $raw = (string)($_COOKIE["scouthub_auth"] ?? "");
  $parts = explode("|", $raw);
  if (count($parts) !== 3) {
    return [0, ""];
  }

  [$id, $role, $signature] = $parts;
  $id = (int)$id;
  if ($id <= 0 || !hash_equals(auth_signature($id, $role), $signature)) {
    return [0, ""];
  }

  return [$id, $role];
}

function current_user_id(): int {
  $id = (int)($_SESSION["user_id"] ?? 0);
  if ($id > 0) {
    return $id;
  }

  return cookie_user()[0];
}

function current_user_role(): string {
  $role = (string)($_SESSION["role"] ?? ($_SESSION["user_role"] ?? ""));
  if ($role !== "") {
    return $role;
  }

  return (string)cookie_user()[1];
}

function app_base_path(): string {
  return "";
}

function app_url(string $path = ""): string {
  $path = ltrim($path, "/");
  return $path === "" ? "/" : "/" . $path;
}

function redirect_to(string $path): never {
  header("Location: " . app_url($path));
  exit;
}

function require_login(): void {
  if (current_user_id() <= 0) {
    redirect_to("login.php");
  }
}

function csrf_token(): string {
  if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION["csrf_token"];
}

function csrf_input(): string {
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") . '">';
}

function verify_csrf_or_fail(): void {
  return;
}
