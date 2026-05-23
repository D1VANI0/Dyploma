<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

$cacheDir = __DIR__ . "/../cache";
$cacheFile = $cacheDir . "/nbp_eur_rate.json";
$ttlSeconds = 6 * 60 * 60; // 6h

if (!is_dir($cacheDir)) {
  @mkdir($cacheDir, 0775, true);
}

// jeśli cache świeży → zwróć
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttlSeconds)) {
  $raw = @file_get_contents($cacheFile);
  $json = $raw ? json_decode($raw, true) : null;
  if (is_array($json) && isset($json["rate"])) {
    echo json_encode($json, JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// NBP API (tabela A)
$url = "https://api.nbp.pl/api/exchangerates/rates/a/eur/?format=json";

$ctx = stream_context_create([
  "http" => [
    "method" => "GET",
    "timeout" => 8,
    "header" => "Accept: application/json\r\nUser-Agent: ScoutHub/1.0\r\n"
  ]
]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
  http_response_code(502);
  echo json_encode([
    "ok" => false,
    "error" => "NBP_FETCH_FAILED",
    "message" => "Nie udało się pobrać kursu NBP."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$data = json_decode($raw, true);
$rate = $data["rates"][0]["mid"] ?? null;
$effectiveDate = $data["rates"][0]["effectiveDate"] ?? null;

if (!is_numeric($rate)) {
  http_response_code(502);
  echo json_encode([
    "ok" => false,
    "error" => "NBP_BAD_RESPONSE",
    "message" => "Niepoprawna odpowiedź z NBP."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$out = [
  "ok" => true,
  "currency" => "EUR",
  "rate" => (float)$rate,
  "effectiveDate" => (string)$effectiveDate,
  "source" => "nbp",
  "cachedAt" => date("c")
];

@file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo json_encode($out, JSON_UNESCAPED_UNICODE);