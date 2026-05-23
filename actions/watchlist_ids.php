<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";require_login();
require_once __DIR__ . "/../partials/db.php";

header("Content-Type: application/json; charset=utf-8");

$userId = (int)($_SESSION["user_id"] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(["ok" => false, "error" => "NOT_LOGGED_IN", "ids" => []]);
  exit;
}

$pdo = db();

$stmt = $pdo->prepare("SELECT player_id FROM watchlist WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$ids = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));

echo json_encode(["ok" => true, "ids" => $ids]);