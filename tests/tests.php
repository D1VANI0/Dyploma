<?php
declare(strict_types=1);

require_once __DIR__ . "/partials/auth.php";
require_once __DIR__ . "/partials/players_repository.php";

function test_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

function source_contains(string $path, array $fragments): bool {
  $source = file_get_contents(__DIR__ . "/" . $path);
  if ($source === false) {
    return false;
  }

  foreach ($fragments as $fragment) {
    if (!str_contains($source, $fragment)) {
      return false;
    }
  }
  return true;
}

$tests = [
  "Sesja przechowuje ID i role uzytkownika" => function (): void {
    $_SESSION = ["user_id" => 21, "role" => "coach"];
    $_COOKIE = [];

    test_assert(current_user_id() === 21, "Nie odczytano ID z sesji.");
    test_assert(current_user_role() === "coach", "Nie odczytano roli z sesji.");
  },

  "Podpisane cookie logowania jest akceptowane" => function (): void {
    $_SESSION = [];
    $_COOKIE["scouthub_auth"] = "7|scout|" . auth_signature(7, "scout");

    test_assert(current_user_id() === 7, "Poprawne cookie zostalo odrzucone.");
    test_assert(current_user_role() === "scout", "Nie odczytano roli z cookie.");
  },

  "Zmodyfikowane cookie logowania jest odrzucane" => function (): void {
    $_SESSION = [];
    $_COOKIE["scouthub_auth"] = "7|admin|niepoprawny-podpis";

    test_assert(current_user_id() === 0, "Niepoprawne cookie zostalo zaakceptowane.");
    test_assert(current_user_role() === "", "Odczytano role z niepoprawnego cookie.");
  },

  "Uprawnienia rol sa poprawnie ograniczone" => function (): void {
    test_assert(can_review_players("admin"), "Administrator powinien zatwierdzac zawodnikow.");
    test_assert(can_review_players("coach"), "Trener powinien zatwierdzac zawodnikow.");
    test_assert(can_review_players("scout"), "Skaut powinien zatwierdzac zawodnikow.");
    test_assert(!can_review_players("player"), "Zawodnik nie powinien zatwierdzac zawodnikow.");
    test_assert(can_admin("admin") && !can_admin("coach"), "Dostep administratora jest niepoprawny.");
    test_assert(normalize_role("skaut") === "scout", "Rola skaut nie zostala znormalizowana.");
  },

  "Dane zawodnika sa mapowane do formatu frontendu" => function (): void {
    $player = player_row_to_js([
      "id" => "5",
      "first_name" => "Jan",
      "last_name" => "Kowalski",
      "birth_year" => "2004",
      "goals" => "12",
      "pass_acc" => "88.5",
      "rating" => "7.4",
    ]);

    test_assert($player["id"] === 5, "ID zawodnika ma niepoprawny typ lub wartosc.");
    test_assert($player["firstName"] === "Jan", "Imie zawodnika nie zostalo zmapowane.");
    test_assert($player["stats"]["goals"] === 12, "Liczba goli nie zostala zmapowana.");
    test_assert($player["stats"]["passAcc"] === 88.5, "Celnosc podan nie zostala zmapowana.");
  },

  "Wartosci formularza statystyk sa normalizowane" => function (): void {
    $_POST = ["matches" => "-3", "rating" => "7,8"];

    test_assert(int_post("matches") === 0, "Ujemna liczba nie zostala ograniczona do zera.");
    test_assert(float_post("rating") === 7.8, "Przecinek dziesietny nie zostal obsluzony.");
  },

  "Polaczenie PDO ma bezpieczne ustawienia" => function (): void {
    test_assert(source_contains("partials/db.php", [
      "new PDO(",
      "PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION",
      "PDO::ATTR_EMULATE_PREPARES => false",
      'charset={$charset}',
    ]), "Brakuje oczekiwanej konfiguracji PDO.");
  },

  "Raport zawodnika jest generowany jako PDF" => function (): void {
    test_assert(source_contains("reports/player_pdf.php", [
      'function make_pdf(array $lines): string',
      'header("Content-Type: application/pdf")',
      'echo make_pdf($lines)',
      "%%EOF",
    ]), "Brakuje elementow odpowiedzialnych za generowanie PDF.");
  },

  "Wysylanie wiadomosci ma walidacje i zapis do bazy" => function (): void {
    test_assert(source_contains("message_send.php", [
      'strlen($subject) > 160',
      'strlen($body) > 5000',
      '$recipientId === $userId',
      "INSERT INTO messages",
      "send_app_mail(",
    ]), "Brakuje walidacji, zapisu lub powiadomienia wiadomosci.");
  },

  "Wszystkie pliki PHP maja poprawna skladnie" => function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
    foreach ($iterator as $file) {
      if (!$file->isFile() || strtolower($file->getExtension()) !== "php") {
        continue;
      }

      $path = str_replace("\\", "/", $file->getPathname());
      if (str_contains($path, "/vendor/") || str_contains($path, "/cache/")) {
        continue;
      }

      $output = [];
      $exitCode = 0;
      exec(escapeshellarg(PHP_BINARY) . " -l " . escapeshellarg($file->getPathname()), $output, $exitCode);
      test_assert($exitCode === 0, "Blad skladni w pliku: " . $file->getPathname());
    }
  },
];

$passed = 0;
$failed = 0;

foreach ($tests as $name => $test) {
  try {
    $test();
    $passed++;
    echo "[OK] {$name}" . PHP_EOL;
  } catch (Throwable $error) {
    $failed++;
    echo "[BLAD] {$name}: {$error->getMessage()}" . PHP_EOL;
  }
}

echo PHP_EOL . "Wynik: {$passed} OK, {$failed} bledow." . PHP_EOL;
exit($failed === 0 ? 0 : 1);
