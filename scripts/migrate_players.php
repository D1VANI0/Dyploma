<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/db.php";
require_once __DIR__ . "/../reports/players_data.php";

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  country VARCHAR(80) NOT NULL DEFAULT '',
  birth_year SMALLINT NULL,
  academy VARCHAR(120) NOT NULL DEFAULT '',
  position VARCHAR(80) NOT NULL DEFAULT '',
  foot VARCHAR(20) NOT NULL DEFAULT '',
  height_cm SMALLINT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  verified_by INT NULL,
  verified_at DATETIME NULL,
  review_requested_to INT NULL,
  review_requested_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_players_user_id (user_id),
  KEY idx_players_status (status),
  KEY idx_players_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS player_stats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  player_id INT NOT NULL,
  matches SMALLINT NOT NULL DEFAULT 0,
  minutes SMALLINT NOT NULL DEFAULT 0,
  goals SMALLINT NOT NULL DEFAULT 0,
  assists SMALLINT NOT NULL DEFAULT 0,
  shots SMALLINT NOT NULL DEFAULT 0,
  shots_on_target SMALLINT NOT NULL DEFAULT 0,
  passes SMALLINT NOT NULL DEFAULT 0,
  pass_acc DECIMAL(5,2) NOT NULL DEFAULT 0,
  key_passes SMALLINT NOT NULL DEFAULT 0,
  crosses SMALLINT NOT NULL DEFAULT 0,
  tackles SMALLINT NOT NULL DEFAULT 0,
  interceptions SMALLINT NOT NULL DEFAULT 0,
  clearances SMALLINT NOT NULL DEFAULT 0,
  duels SMALLINT NOT NULL DEFAULT 0,
  duels_won DECIMAL(5,2) NOT NULL DEFAULT 0,
  cards_y SMALLINT NOT NULL DEFAULT 0,
  cards_r SMALLINT NOT NULL DEFAULT 0,
  saves SMALLINT NOT NULL DEFAULT 0,
  clean_sheets SMALLINT NOT NULL DEFAULT 0,
  conceded SMALLINT NOT NULL DEFAULT 0,
  rating DECIMAL(3,1) NOT NULL DEFAULT 0,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  submitted_by INT NULL,
  verified_by INT NULL,
  verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_player_stats_player_status (player_id, status),
  CONSTRAINT fk_player_stats_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_password_resets_token (token_hash),
  KEY idx_password_resets_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$roleFix = $pdo->prepare("UPDATE users SET role = 'scout' WHERE role = 'skaut'");
$roleFix->execute();

$cols = $pdo->query("SHOW COLUMNS FROM players")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array("review_requested_to", $cols, true)) {
  $pdo->exec("ALTER TABLE players ADD COLUMN review_requested_to INT NULL AFTER verified_at");
}
if (!in_array("review_requested_at", $cols, true)) {
  $pdo->exec("ALTER TABLE players ADD COLUMN review_requested_at DATETIME NULL AFTER review_requested_to");
}

$findUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$findPlayer = $pdo->prepare("SELECT id FROM players WHERE user_id <=> ? OR (first_name = ? AND last_name = ? AND birth_year <=> ?) LIMIT 1");
$insertPlayer = $pdo->prepare("
  INSERT INTO players
    (user_id, first_name, last_name, country, birth_year, academy, position, foot, height_cm, status, verified_at)
  VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
");
$insertStats = $pdo->prepare("
  INSERT INTO player_stats
    (player_id, matches, minutes, goals, assists, shots, shots_on_target, passes, pass_acc,
     key_passes, crosses, tackles, interceptions, clearances, duels, duels_won, cards_y, cards_r,
     saves, clean_sheets, conceded, rating, status, verified_at)
  VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
");

$value = static function(array $stats, string $key): float {
  return (float)($stats[$key] ?? 0);
};

$seeded = 0;
foreach ($PLAYERS as $p) {
  $email = (string)($p["email"] ?? "");
  $userId = null;
  if ($email !== "") {
    $findUser->execute([$email]);
    $userId = $findUser->fetchColumn();
    $userId = $userId !== false ? (int)$userId : null;
  }

  $first = (string)($p["firstName"] ?? "");
  $last = (string)($p["lastName"] ?? "");
  $birthYear = isset($p["birthYear"]) ? (int)$p["birthYear"] : null;

  $findPlayer->execute([$userId, $first, $last, $birthYear]);
  $playerId = $findPlayer->fetchColumn();
  if ($playerId === false) {
    $insertPlayer->execute([
      $userId,
      $first,
      $last,
      (string)($p["country"] ?? ""),
      $birthYear,
      (string)($p["academy"] ?? ""),
      (string)($p["position"] ?? ""),
      (string)($p["foot"] ?? ""),
      isset($p["heightCm"]) ? (int)$p["heightCm"] : null,
    ]);
    $playerId = (int)$pdo->lastInsertId();
    $seeded++;
  } else {
    $playerId = (int)$playerId;
  }

  $count = $pdo->prepare("SELECT COUNT(*) FROM player_stats WHERE player_id = ?");
  $count->execute([$playerId]);
  if ((int)$count->fetchColumn() > 0) {
    continue;
  }

  $s = is_array($p["stats"] ?? null) ? $p["stats"] : [];
  $insertStats->execute([
    $playerId,
    (int)$value($s, "Mecze"),
    (int)$value($s, "Minuty"),
    (int)$value($s, "Gole"),
    (int)$value($s, "Asysty"),
    (int)$value($s, "Strzały"),
    (int)$value($s, "Strzały celne"),
    (int)$value($s, "Podania"),
    $value($s, "Celność podań (%)"),
    (int)$value($s, "Kluczowe podania"),
    (int)$value($s, "Dośrodkowania"),
    (int)$value($s, "Odbiory"),
    (int)$value($s, "Przechwyty"),
    (int)$value($s, "Wybicia"),
    (int)$value($s, "Pojedynki"),
    $value($s, "Wygrane pojedynki (%)"),
    (int)$value($s, "Kartki żółte"),
    (int)$value($s, "Kartki czerwone"),
    (int)$value($s, "Obrony"),
    (int)$value($s, "Czyste konta"),
    (int)$value($s, "Stracone bramki"),
    $value($s, "Ocena"),
  ]);
}

echo "OK seeded={$seeded}\n";
