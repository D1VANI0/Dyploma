<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php"; require_login();
require_once __DIR__ . "/../partials/db.php";

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok" => false, "error" => "METHOD_NOT_ALLOWED", "message" => "Nieprawidłowa metoda."]);
  exit;
}

verify_csrf_or_fail();

$userId = current_user_id();
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(["ok" => false, "error" => "NOT_LOGGED_IN", "message" => "Zaloguj się."]);
  exit;
}

$playerId = (int)($_POST["player_id"] ?? 0);
if ($playerId <= 0) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "INVALID_PLAYER_ID", "message" => "Nieprawidłowy zawodnik."]);
  exit;
}

try {
  $pdo = db();

  $stmt = $pdo->prepare("SELECT 1 FROM watchlist WHERE user_id = ? AND player_id = ? LIMIT 1");
  $stmt->execute([$userId, $playerId]);
  $exists = (bool)$stmt->fetchColumn();

  if ($exists) {
    $del = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND player_id = ?");
    $del->execute([$userId, $playerId]);
    echo json_encode(["ok" => true, "saved" => false]);
    exit;
  }

  $ins = $pdo->prepare("INSERT INTO watchlist(user_id, player_id, created_at) VALUES(?,?,NOW())");
  $ins->execute([$userId, $playerId]);
  echo json_encode(["ok" => true, "saved" => true]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "SERVER_ERROR", "message" => "Błąd serwera. Spróbuj ponownie."]);
  exit;
}