<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_login();
require_once __DIR__ . "/../partials/players_repository.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("account.php");
}

verify_csrf_or_fail();

$pdo = db();
$userId = current_user_id();
$role = current_user_role();
$playerId = (int)($_POST["player_id"] ?? 0);

$first = trim((string)($_POST["first_name"] ?? ""));
$last = trim((string)($_POST["last_name"] ?? ""));
$country = trim((string)($_POST["country"] ?? ""));
$academy = trim((string)($_POST["academy"] ?? ""));
$position = trim((string)($_POST["position"] ?? ""));
$foot = trim((string)($_POST["foot"] ?? ""));
$birthYear = (int)($_POST["birth_year"] ?? 0);
$heightCm = (int)($_POST["height_cm"] ?? 0);

if ($first === "" || $last === "") {
  redirect_to("account.php?err=" . urlencode("Imię i nazwisko zawodnika są wymagane."));
}

try {
  $pdo->beginTransaction();

  $ownerUserId = $userId;
  if ($playerId > 0) {
    $check = $pdo->prepare("SELECT user_id FROM players WHERE id = ? LIMIT 1");
    $check->execute([$playerId]);
    $owner = $check->fetchColumn();
    if ($owner === false) {
      throw new RuntimeException("Nie znaleziono profilu.");
    }
    if (!can_admin($role) && (int)$owner !== $userId) {
      throw new RuntimeException("Brak uprawnień.");
    }
    $ownerUserId = $owner !== null ? (int)$owner : $userId;
  }

  $status = can_admin($role) ? "approved" : "pending";
  if ($playerId > 0) {
    $stmt = $pdo->prepare("
      UPDATE players
      SET first_name = ?, last_name = ?, country = ?, birth_year = ?, academy = ?,
          position = ?, foot = ?, height_cm = ?, status = ?,
          verified_by = CASE WHEN ? = 'approved' THEN ? ELSE verified_by END,
          verified_at = CASE WHEN ? = 'approved' THEN NOW() ELSE verified_at END
      WHERE id = ?
    ");
    $stmt->execute([
      $first, $last, $country, $birthYear ?: null, $academy, $position, $foot, $heightCm ?: null,
      $status, $status, $userId, $status, $playerId
    ]);
  } else {
    $stmt = $pdo->prepare("
      INSERT INTO players
        (user_id, first_name, last_name, country, birth_year, academy, position, foot, height_cm, status, verified_by, verified_at)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 'approved' THEN NOW() ELSE NULL END)
    ");
    $stmt->execute([
      $ownerUserId, $first, $last, $country, $birthYear ?: null, $academy, $position,
      $foot, $heightCm ?: null, $status, $status === "approved" ? $userId : null, $status
    ]);
    $playerId = (int)$pdo->lastInsertId();
  }

  $cols = player_stats_columns();
  $values = [];
  foreach ($cols as $col) {
    $values[$col] = in_array($col, ["pass_acc", "duels_won", "rating"], true)
      ? float_post($col)
      : int_post($col);
  }

  $sql = "
    INSERT INTO player_stats
      (player_id, " . implode(", ", $cols) . ", status, submitted_by, verified_by, verified_at)
    VALUES
      (:" . "player_id, :" . implode(", :", $cols) . ", :status, :submitted_by, :verified_by, :verified_at)
  ";
  $stmt = $pdo->prepare($sql);
  $params = [
    "player_id" => $playerId,
    "status" => $status,
    "submitted_by" => $userId,
    "verified_by" => $status === "approved" ? $userId : null,
    "verified_at" => $status === "approved" ? date("Y-m-d H:i:s") : null,
  ];
  foreach ($values as $key => $value) {
    $params[$key] = $value;
  }
  $stmt->execute($params);

  $pdo->commit();
  redirect_to("account.php?ok=" . urlencode($status === "approved" ? "Profil zapisany." : "Profil wysłany do potwierdzenia."));
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  redirect_to("account.php?err=" . urlencode("Nie udało się zapisać profilu zawodnika."));
}
