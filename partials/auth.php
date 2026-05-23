<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

function current_user_id(): int {
  return (int)($_SESSION["user_id"] ?? 0);
}

function current_user_role(): string {
  return (string)($_SESSION["role"] ?? ($_SESSION["user_role"] ?? ""));
}

function app_base_path(): string {
  static $base = null;
  if ($base !== null) return $base;

  $docRoot = str_replace("\\", "/", realpath((string)($_SERVER["DOCUMENT_ROOT"] ?? "")) ?: "");
  $projectRoot = str_replace("\\", "/", realpath(__DIR__ . "/..") ?: "");

  if ($docRoot !== "" && $projectRoot !== "" && str_starts_with($projectRoot, $docRoot)) {
    $base = "/" . trim(substr($projectRoot, strlen($docRoot)), "/");
    return $base === "/" ? "" : $base;
  }

  $script = str_replace("\\", "/", (string)($_SERVER["SCRIPT_NAME"] ?? ""));
  $parts = explode("/", trim($script, "/"));
  $base = isset($parts[0]) && $parts[0] !== "" ? "/" . $parts[0] : "";
  return $base;
}

function app_url(string $path = ""): string {
  $path = ltrim($path, "/");
  $base = app_base_path();
  return $path === "" ? ($base === "" ? "/" : $base . "/") : $base . "/" . $path;
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
  return $_SESSION["csrf_token"];
}

function csrf_input(): string {
  $t = htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8");
  return '<input type="hidden" name="csrf_token" value="'.$t.'">';
}

function verify_csrf_or_fail(): void {
  $sessionToken = (string)($_SESSION["csrf_token"] ?? "");
  $postToken = (string)($_POST["csrf_token"] ?? "");
  $headerToken = (string)($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "");

  $token = $postToken !== "" ? $postToken : $headerToken;

  if ($sessionToken !== "" && $token !== "" && hash_equals($sessionToken, $token)) {
    return;
  }

  http_response_code(403);

  $isAjax = ($headerToken !== "") || (strtolower((string)($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "")) === "xmlhttprequest");
  $accept = strtolower((string)($_SERVER["HTTP_ACCEPT"] ?? ""));

  if ($isAjax || str_contains($accept, "application/json")) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
      "ok" => false,
      "error" => "CSRF_INVALID",
      "message" => "Niepoprawny token bezpieczeństwa (CSRF). Odśwież stronę i spróbuj ponownie."
    ]);
    exit;
  }

  redirect_to("login.php?err=" . urlencode("Błąd bezpieczeństwa (CSRF). Odśwież stronę i spróbuj ponownie."));
}
