<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_login();
require_once __DIR__ . "/../partials/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("account.php");
}

verify_csrf_or_fail();

$first = trim((string)($_POST["first_name"] ?? ""));
$last = trim((string)($_POST["last_name"] ?? ""));
$email = trim((string)($_POST["email"] ?? ""));

if ($first === "" || $last === "" || $email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to("account.php?err=" . urlencode("Uzupełnij poprawnie dane konta."));
}

try {
  $pdo = db();
  $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
  $chk->execute([$email, current_user_id()]);
  if ($chk->fetchColumn()) {
    redirect_to("account.php?err=" . urlencode("Ten e-mail jest już zajęty."));
  }

  $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
  $stmt->execute([$first, $last, $email, current_user_id()]);

  redirect_to("account.php?ok=" . urlencode("Dane konta zapisane."));
} catch (Throwable $e) {
  redirect_to("account.php?err=" . urlencode("Nie udało się zapisać danych."));
}
