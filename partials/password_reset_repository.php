<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

function ensure_password_reset_table(): void {
  db()->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      token_hash CHAR(64) NOT NULL,
      expires_at DATETIME NOT NULL,
      used_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_password_resets_token (token_hash),
      KEY idx_password_resets_user (user_id),
      KEY idx_password_resets_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");
}

function password_reset_token_is_valid(string $token): bool {
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    return false;
  }

  ensure_password_reset_table();
  $stmt = db()->prepare("
    SELECT id
    FROM password_resets
    WHERE token_hash = ?
      AND used_at IS NULL
      AND expires_at > NOW()
    LIMIT 1
  ");
  $stmt->execute([hash("sha256", $token)]);
  return (bool)$stmt->fetchColumn();
}

function create_password_reset_token(int $userId): string {
  ensure_password_reset_table();

  $pdo = db();
  $token = bin2hex(random_bytes(32));
  $tokenHash = hash("sha256", $token);

  try {
    $pdo->beginTransaction();

    $cleanup = $pdo->prepare("
      UPDATE password_resets
      SET used_at = NOW()
      WHERE user_id = ? AND used_at IS NULL
    ");
    $cleanup->execute([$userId]);

    $insert = $pdo->prepare("
      INSERT INTO password_resets (user_id, token_hash, expires_at)
      VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
    ");
    $insert->execute([$userId, $tokenHash]);

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }

  return $token;
}

function complete_password_reset(string $token, string $password): bool {
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    return false;
  }

  ensure_password_reset_table();
  $pdo = db();
  $tokenHash = hash("sha256", $token);

  try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
      SELECT id, user_id
      FROM password_resets
      WHERE token_hash = ?
        AND used_at IS NULL
        AND expires_at > NOW()
      LIMIT 1
      FOR UPDATE
    ");
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
      $pdo->rollBack();
      return false;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $updateUser = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $updateUser->execute([$hash, (int)$reset["user_id"]]);

    $updateReset = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
    $updateReset->execute([(int)$reset["id"]]);

    $invalidateOtherTokens = $pdo->prepare("
      UPDATE password_resets
      SET used_at = NOW()
      WHERE user_id = ? AND used_at IS NULL
    ");
    $invalidateOtherTokens->execute([(int)$reset["user_id"]]);

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }

  return true;
}
