<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";require_login();
require_once __DIR__ . "/../partials/db.php";

header("Content-Type: application/json; charset=utf-8");

$userId = (int)($_SESSION["user_id"] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(["ok" => false, "error" => "NOT_LOGGED_IN"]);
  exit;
}

$q = trim((string)($_GET["q"] ?? ""));
$pdo = db();

try {
  $sql = "
    SELECT id, first_name, last_name, email
    FROM users
    WHERE id <> ?
      AND (
        first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
        OR CONCAT(first_name,' ',last_name) LIKE ?
      )
    ORDER BY last_name, first_name
    LIMIT 30
  ";

  $like = "%" . $q . "%";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$userId, $like, $like, $like, $like]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(["ok" => true, "users" => $rows]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "SERVER_ERROR"]);
  exit;
}
