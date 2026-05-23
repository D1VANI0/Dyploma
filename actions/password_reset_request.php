<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_once __DIR__ . "/../partials/db.php";
require_once __DIR__ . "/../partials/mailer.php";
require_once __DIR__ . "/../partials/password_reset_repository.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("forgot_password.php");
}

verify_csrf_or_fail();

$email = trim((string)($_POST["email"] ?? ""));
if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to("forgot_password.php?err=" . urlencode("Podaj poprawny adres e-mail."));
}

$genericOk = "Jeśli konto istnieje, wysłaliśmy link do resetu hasła.";

try {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT id, email, first_name FROM users WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    redirect_to("forgot_password.php?ok=" . urlencode($genericOk));
  }

  $token = create_password_reset_token((int)$user["id"]);
  $absoluteLink = absolute_app_url("reset_password.php?token=" . $token);

  $subject = "ScoutHub - reset hasła";
  $message = "Cześć,\n\nKliknij link, aby ustawić nowe hasło w ScoutHub:\n{$absoluteLink}\n\nLink wygaśnie za 1 godzinę.\nJeśli to nie Ty, zignoruj tę wiadomość.";
  $sent = send_app_mail((string)$user["email"], $subject, $message);

  if (!$sent) {
    $cacheDir = __DIR__ . "/../cache";
    if (is_dir($cacheDir) && is_writable($cacheDir)) {
      file_put_contents($cacheDir . "/last_password_reset_link.txt", $absoluteLink . PHP_EOL);
    }
  }

  $host = strtolower((string)($_SERVER["HTTP_HOST"] ?? ""));
  if (!$sent && (str_starts_with($host, "localhost") || str_starts_with($host, "127.0.0.1"))) {
    redirect_to("forgot_password.php?ok=" . urlencode($genericOk . " Lokalnie e-mail nie zostal wyslany, link testowy: " . $absoluteLink));
  }

  redirect_to("forgot_password.php?ok=" . urlencode($genericOk));
} catch (Throwable $e) {
  redirect_to("forgot_password.php?err=" . urlencode("Nie udało się przygotować resetu hasła."));
}
