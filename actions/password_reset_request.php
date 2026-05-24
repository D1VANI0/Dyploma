<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_once __DIR__ . "/../partials/db.php";
require_once __DIR__ . "/../partials/mailer.php";
require_once __DIR__ . "/../partials/password_reset_repository.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("forgot_password.php");
}

$email = trim((string)($_POST["email"] ?? ""));
if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to("forgot_password.php?err=" . urlencode("Podaj poprawny adres e-mail."));
}

$genericOk = "Jesli konto istnieje, wyslalismy link do resetu hasla.";

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

  $subject = "ScoutHub - reset hasla";
  $message = "Czesc,\n\nKliknij link, aby ustawic nowe haslo w ScoutHub:\n{$absoluteLink}\n\nLink wygasnie za 1 godzine.\nJesli to nie Ty, zignoruj te wiadomosc.";

  if (!send_app_mail((string)$user["email"], $subject, $message)) {
    redirect_to("forgot_password.php?err=" . urlencode("Nie udalo sie wyslac maila. Sprawdz konfiguracje SMTP."));
  }

  redirect_to("forgot_password.php?ok=" . urlencode($genericOk));
} catch (Throwable $e) {
  redirect_to("forgot_password.php?err=" . urlencode("Nie udalo sie przygotowac resetu hasla."));
}
