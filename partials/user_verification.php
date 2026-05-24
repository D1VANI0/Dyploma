<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/players_repository.php";

function ensure_user_verification_columns(): void {
  static $done = false;
  if ($done) return;

  $pdo = db();
  $columns = $pdo->query("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
  ")->fetchAll(PDO::FETCH_COLUMN);

  $columns = array_flip(array_map("strtolower", $columns ?: []));

  if (!isset($columns["verification_status"])) {
    $pdo->exec("
      ALTER TABLE users
      ADD COLUMN verification_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'
      AFTER role
    ");
  }
  if (!isset($columns["verified_at"])) {
    $pdo->exec("ALTER TABLE users ADD COLUMN verified_at DATETIME NULL AFTER verification_status");
  }
  if (!isset($columns["verified_by"])) {
    $pdo->exec("ALTER TABLE users ADD COLUMN verified_by INT NULL AFTER verified_at");
  }

  $pdo->exec("
    UPDATE users
    SET verification_status = 'approved'
    WHERE verification_status IS NULL OR verification_status = ''
  ");

  $done = true;
}

function role_requires_admin_verification(string $role): bool {
  return in_array(normalize_role($role), ["coach", "scout"], true);
}

function verification_label(string $status): string {
  return [
    "pending" => "Oczekuje",
    "approved" => "Potwierdzone",
    "rejected" => "Odrzucone",
  ][$status] ?? "Potwierdzone";
}
