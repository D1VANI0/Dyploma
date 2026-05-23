<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_once __DIR__ . "/../partials/players_repository.php";

header("Content-Type: application/json; charset=utf-8");

$includePending = can_review_players(current_user_role());

try {
  echo json_encode([
    "ok" => true,
    "players" => fetch_players_for_js($includePending),
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "SERVER_ERROR"], JSON_UNESCAPED_UNICODE);
}
