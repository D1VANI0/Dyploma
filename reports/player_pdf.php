<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_login();
require_once __DIR__ . "/../partials/players_repository.php";

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  exit("Brak ID");
}

$role = normalize_role(current_user_role());
$player = fetch_player_row($id, can_review_players($role));
if (!$player) {
  http_response_code(404);
  exit("Nie znaleziono zawodnika");
}

function pdf_text(string $text): string {
  $text = str_replace(["\r\n", "\r"], "\n", $text);
  $text = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $text);
  $text = $text === false ? "" : $text;
  return str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $text);
}

function pdf_line(string $label, mixed $value): string {
  $value = trim((string)$value);
  if ($value === "") {
    $value = "-";
  }
  return $label . ": " . $value;
}

function make_pdf(array $lines): string {
  $objects = [];
  $content = "BT\n/F1 11 Tf\n14 TL\n50 790 Td\n";

  foreach ($lines as $index => $line) {
    if ($index > 0) {
      $content .= "T*\n";
    }
    $content .= "(" . pdf_text($line) . ") Tj\n";
  }

  $content .= "ET\n";

  $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
  $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
  $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
  $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
  $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

  $pdf = "%PDF-1.4\n";
  $offsets = [0];
  foreach ($objects as $i => $object) {
    $offsets[] = strlen($pdf);
    $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
  }

  $xref = strlen($pdf);
  $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
  $pdf .= "0000000000 65535 f \n";
  for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
  }
  $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
  $pdf .= "startxref\n" . $xref . "\n%%EOF";

  return $pdf;
}

$fullName = trim((string)$player["first_name"] . " " . (string)$player["last_name"]);
$age = ((int)($player["birth_year"] ?? 0) > 0) ? max(0, (int)date("Y") - (int)$player["birth_year"]) : null;
$labels = player_stat_labels();

$lines = [
  "ScoutHub - raport zawodnika",
  "Wygenerowano: " . date("Y-m-d H:i"),
  "",
  pdf_line("Zawodnik", $fullName),
  pdf_line("ID", $id),
  pdf_line("E-mail", $player["email"] ?? ""),
  pdf_line("Kraj", $player["country"] ?? ""),
  pdf_line("Akademia", $player["academy"] ?? ""),
  pdf_line("Pozycja", $player["position"] ?? ""),
  pdf_line("Noga", $player["foot"] ?? ""),
  pdf_line("Wzrost", (($player["height_cm"] ?? "") !== "" ? (string)$player["height_cm"] . " cm" : "")),
  pdf_line("Rok urodzenia", $player["birth_year"] ?? ""),
  pdf_line("Wiek", $age === null ? "" : $age),
  pdf_line("Status profilu", $player["status"] ?? ""),
  pdf_line("Status statystyk", $player["stats_status"] ?? ""),
  "",
  "Statystyki",
];

foreach (player_stats_columns() as $key) {
  $lines[] = pdf_line($labels[$key] ?? $key, $player[$key] ?? "");
}

$filename = "scouthub_player_" . $id . ".pdf";
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Cache-Control: private, max-age=0, must-revalidate");
echo make_pdf($lines);
exit;
