<?php
declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../partials/players_repository.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) { http_response_code(400); exit("Brak ID"); }

$player = fetch_player_row($id, can_review_players(current_user_role()));
if (!$player) { http_response_code(404); exit("Nie znaleziono zawodnika"); }

function ageFromBirthYear(int $birthYear): int {
  return max(0, (int)date("Y") - $birthYear);
}

$age = ageFromBirthYear((int)$player["birth_year"]);
$labels = player_stat_labels();

$rows = "";
foreach (player_stats_columns() as $key) {
  $rows .= "<tr><td>".htmlspecialchars($labels[$key] ?? $key)."</td><td>".htmlspecialchars((string)($player[$key] ?? ""))."</td></tr>";
}

$fullName = htmlspecialchars($player["first_name"]." ".$player["last_name"]);
$generatedAt = date("Y-m-d H:i");

$html = <<<HTML
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
    h1 { font-size: 16px; margin: 0 0 6px 0; }
    .muted { color: #666; }
    table { width:100%; border-collapse: collapse; margin-top: 10px; }
    td, th { border: 1px solid #ccc; padding: 6px; }
    th { background: #eee; }
  </style>
</head>
<body>
  <h1>ScoutHub — Raport zawodnika</h1>
  <div class="muted">Wygenerowano: {$generatedAt}</div>
  <hr>
  <div><strong>{$fullName}</strong> (ID: {$id})</div>
  <div class="muted">Wiek: {$age}</div>

  <h2 style="font-size:14px;margin-top:14px;">Statystyki</h2>
  <table>
    <thead><tr><th>Parametr</th><th>Wartość</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</body>
</html>
HTML;

$options = new Options();
$options->set("defaultFont", "DejaVu Sans");

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, "UTF-8");
$dompdf->setPaper("A4", "portrait");
$dompdf->render();

header("Content-Type: application/pdf");
header('Content-Disposition: attachment; filename="scouthub_player_'.$id.'.pdf"');
echo $dompdf->output();
exit;
