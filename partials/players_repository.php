<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

function can_review_players(string $role): bool {
  return in_array($role, ["admin", "coach", "scout"], true);
}

function can_admin(string $role): bool {
  return $role === "admin";
}

function normalize_role(string $role): string {
  return $role === "skaut" ? "scout" : $role;
}

function role_label(string $role): string {
  return [
    "admin" => "Admin",
    "coach" => "Trener",
    "scout" => "Skaut",
    "player" => "Zawodnik",
    "agent" => "Agent",
  ][$role] ?? "Użytkownik";
}

function player_stats_columns(): array {
  return [
    "matches", "minutes", "goals", "assists", "shots", "shots_on_target", "passes", "pass_acc",
    "key_passes", "crosses", "tackles", "interceptions", "clearances", "duels", "duels_won",
    "cards_y", "cards_r", "saves", "clean_sheets", "conceded", "rating",
  ];
}

function player_stat_labels(): array {
  return [
    "matches" => "Mecze",
    "minutes" => "Minuty",
    "goals" => "Gole",
    "assists" => "Asysty",
    "shots" => "Strzały",
    "shots_on_target" => "Strzały celne",
    "passes" => "Podania",
    "pass_acc" => "Celność podań (%)",
    "key_passes" => "Kluczowe podania",
    "crosses" => "Dośrodkowania",
    "tackles" => "Odbiory",
    "interceptions" => "Przechwyty",
    "clearances" => "Wybicia",
    "duels" => "Pojedynki",
    "duels_won" => "Wygrane pojedynki (%)",
    "cards_y" => "Kartki żółte",
    "cards_r" => "Kartki czerwone",
    "saves" => "Obrony",
    "clean_sheets" => "Czyste konta",
    "conceded" => "Stracone bramki",
    "rating" => "Ocena",
  ];
}

function player_row_to_js(array $row): array {
  $stats = [
    "matches" => (int)($row["matches"] ?? 0),
    "minutes" => (int)($row["minutes"] ?? 0),
    "goals" => (int)($row["goals"] ?? 0),
    "assists" => (int)($row["assists"] ?? 0),
    "shots" => (int)($row["shots"] ?? 0),
    "shotsOnTarget" => (int)($row["shots_on_target"] ?? 0),
    "passes" => (int)($row["passes"] ?? 0),
    "passAcc" => (float)($row["pass_acc"] ?? 0),
    "keyPasses" => (int)($row["key_passes"] ?? 0),
    "crosses" => (int)($row["crosses"] ?? 0),
    "tackles" => (int)($row["tackles"] ?? 0),
    "interceptions" => (int)($row["interceptions"] ?? 0),
    "clearances" => (int)($row["clearances"] ?? 0),
    "duels" => (int)($row["duels"] ?? 0),
    "duelsWon" => (float)($row["duels_won"] ?? 0),
    "cardsY" => (int)($row["cards_y"] ?? 0),
    "cardsR" => (int)($row["cards_r"] ?? 0),
    "saves" => (int)($row["saves"] ?? 0),
    "cleanSheets" => (int)($row["clean_sheets"] ?? 0),
    "conceded" => (int)($row["conceded"] ?? 0),
    "rating" => (float)($row["rating"] ?? 0),
  ];

  return [
    "id" => (int)$row["id"],
    "firstName" => (string)$row["first_name"],
    "lastName" => (string)$row["last_name"],
    "email" => (string)($row["email"] ?? ""),
    "country" => (string)($row["country"] ?? ""),
    "birthYear" => (int)($row["birth_year"] ?? 0),
    "academy" => (string)($row["academy"] ?? ""),
    "position" => (string)($row["position"] ?? ""),
    "foot" => (string)($row["foot"] ?? ""),
    "heightCm" => (int)($row["height_cm"] ?? 0),
    "status" => (string)($row["status"] ?? "pending"),
    "statsStatus" => (string)($row["stats_status"] ?? "pending"),
    "stats" => $stats,
  ];
}

function fetch_player_rows(bool $includePending = false, ?int $userId = null): array {
  $pdo = db();
  $where = [];
  $params = [];

  if (!$includePending) {
    $where[] = "p.status = 'approved'";
  }
  if ($userId !== null) {
    $where[] = "p.user_id = ?";
    $params[] = $userId;
  }

  $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
  $sql = "
    SELECT
      p.*,
      u.email,
      ps.status AS stats_status,
      ps.matches, ps.minutes, ps.goals, ps.assists, ps.shots, ps.shots_on_target,
      ps.passes, ps.pass_acc, ps.key_passes, ps.crosses, ps.tackles, ps.interceptions,
      ps.clearances, ps.duels, ps.duels_won, ps.cards_y, ps.cards_r, ps.saves,
      ps.clean_sheets, ps.conceded, ps.rating
    FROM players p
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN player_stats ps ON ps.id = (
      SELECT ps2.id
      FROM player_stats ps2
      WHERE ps2.player_id = p.id
      ORDER BY (ps2.status = 'approved') DESC, ps2.created_at DESC, ps2.id DESC
      LIMIT 1
    )
    {$whereSql}
    ORDER BY p.last_name, p.first_name
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_players_for_js(bool $includePending = false): array {
  return array_map("player_row_to_js", fetch_player_rows($includePending));
}

function fetch_player_row(int $id, bool $includePending = false): ?array {
  foreach (fetch_player_rows($includePending) as $row) {
    if ((int)$row["id"] === $id) {
      return $row;
    }
  }
  return null;
}

function fetch_player_for_user(int $userId): ?array {
  $rows = fetch_player_rows(true, $userId);
  return $rows[0] ?? null;
}

function int_post(string $key, int $default = 0): int {
  return max(0, (int)($_POST[$key] ?? $default));
}

function float_post(string $key, float $default = 0): float {
  return max(0, (float)str_replace(",", ".", (string)($_POST[$key] ?? $default)));
}
