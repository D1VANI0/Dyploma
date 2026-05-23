<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_login();
require_once __DIR__ . "/../partials/players_repository.php";

$userId = current_user_id();
$pdo = db();

$stmt = $pdo->prepare("SELECT player_id, created_at FROM watchlist WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byId = [];
foreach (fetch_player_rows(can_review_players(current_user_role())) as $p) {
  $byId[(int)$p["id"]] = $p;
}

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=watchlist.csv");

echo "\xEF\xBB\xBF";

$out = fopen("php://output", "w");
$delim = ";";
$enclosure = '"';
$escape = "\\";

fputcsv($out, [
  "Imie", "Nazwisko", "Pozycja", "Kraj", "Akademia", "Rocznik", "Noga", "Wzrost (cm)",
  "Mecze", "Minuty", "Gole", "Asysty", "Strzaly", "Strzaly celne", "Podania",
  "Celnosc podan (%)", "Kluczowe podania", "Dosrodkowania", "Odbiory", "Przechwyty",
  "Wybicia", "Pojedynki", "Wygrane pojedynki (%)", "Kartki zolte", "Kartki czerwone",
  "Ocena", "Dodano do obserwowanych"
], $delim, $enclosure, $escape);

foreach ($rows as $r) {
  $pid = (int)($r["player_id"] ?? 0);
  $p = $byId[$pid] ?? [];

  $added = (string)($r["created_at"] ?? "");
  if ($added !== "") {
    $ts = strtotime($added);
    if ($ts !== false) $added = date("Y-m-d H:i", $ts);
  }

  fputcsv($out, [
    (string)($p["first_name"] ?? ""),
    (string)($p["last_name"] ?? ""),
    (string)($p["position"] ?? ""),
    (string)($p["country"] ?? ""),
    (string)($p["academy"] ?? ""),
    (string)($p["birth_year"] ?? ""),
    (string)($p["foot"] ?? ""),
    (string)($p["height_cm"] ?? ""),
    (string)($p["matches"] ?? ""),
    (string)($p["minutes"] ?? ""),
    (string)($p["goals"] ?? ""),
    (string)($p["assists"] ?? ""),
    (string)($p["shots"] ?? ""),
    (string)($p["shots_on_target"] ?? ""),
    (string)($p["passes"] ?? ""),
    (string)($p["pass_acc"] ?? ""),
    (string)($p["key_passes"] ?? ""),
    (string)($p["crosses"] ?? ""),
    (string)($p["tackles"] ?? ""),
    (string)($p["interceptions"] ?? ""),
    (string)($p["clearances"] ?? ""),
    (string)($p["duels"] ?? ""),
    (string)($p["duels_won"] ?? ""),
    (string)($p["cards_y"] ?? ""),
    (string)($p["cards_r"] ?? ""),
    "'" . (string)($p["rating"] ?? ""),
    $added
  ], $delim, $enclosure, $escape);
}

fclose($out);
exit;
