<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_once __DIR__ . "/../partials/password_reset_repository.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("forgot_password.php");
}

$token = (string)($_POST["token"] ?? "");
$password = (string)($_POST["password"] ?? "");
$repeat = (string)($_POST["password_repeat"] ?? "");

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
  redirect_to("forgot_password.php?err=" . urlencode("Nieprawidlowy link resetujacy."));
}
if (strlen($password) < 10 || strlen($password) > 200) {
  redirect_to("reset_password.php?token=" . urlencode($token) . "&err=" . urlencode("Haslo musi miec minimum 10 znakow."));
}
if ($password !== $repeat) {
  redirect_to("reset_password.php?token=" . urlencode($token) . "&err=" . urlencode("Hasla nie sa takie same."));
}

try {
  if (!complete_password_reset($token, $password)) {
    redirect_to("forgot_password.php?err=" . urlencode("Link jest nieprawidlowy albo wygasl."));
  }

  redirect_to("login.php?ok=" . urlencode("Haslo zostalo zmienione. Zaloguj sie nowym haslem."));
} catch (Throwable $e) {
  redirect_to("forgot_password.php?err=" . urlencode("Nie udalo sie zmienic hasla."));
}
