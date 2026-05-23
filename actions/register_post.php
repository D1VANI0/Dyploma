<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_once __DIR__ . "/../partials/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("register.php?err=" . urlencode("Nieprawidłowa metoda."));
}

verify_csrf_or_fail();

$email = trim((string)($_POST["email"] ?? ""));
$pass  = (string)($_POST["password"] ?? "");
$first = trim((string)($_POST["first_name"] ?? ""));
$last  = trim((string)($_POST["last_name"] ?? ""));
$role  = trim((string)($_POST["role"] ?? "player"));
$role = $role === "skaut" ? "scout" : $role;

$allowedRoles = ["player", "coach", "scout"];
if (!in_array($role, $allowedRoles, true)) {
  redirect_to("register.php?err=" . urlencode("Nieprawidłowa rola konta."));
}

if ($first === "" || $last === "" || $email === "" || $pass === "") {
  redirect_to("register.php?err=" . urlencode("Uzupełnij wszystkie pola."));
}
if (strlen($first) < 2 || strlen($first) > 50 || strlen($last) < 2 || strlen($last) > 70) {
  redirect_to("register.php?err=" . urlencode("Imię i nazwisko muszą mieć poprawną długość."));
}
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to("register.php?err=" . urlencode("Podaj poprawny adres e-mail."));
}
if (strlen($pass) < 8 || strlen($pass) > 200) {
  redirect_to("register.php?err=" . urlencode("Hasło musi mieć minimum 8 znaków."));
}

$pdo = db();

try {
  $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
  $chk->execute([$email]);
  if ($chk->fetch()) {
    redirect_to("register.php?err=" . urlencode("Taki e-mail już istnieje."));
  }

  $hash = password_hash($pass, PASSWORD_DEFAULT);

  $ins = $pdo->prepare("
    INSERT INTO users (first_name, last_name, email, role, password_hash)
    VALUES (?, ?, ?, ?, ?)
  ");
  $ins->execute([$first, $last, $email, $role, $hash]);

  $userId = (int)$pdo->lastInsertId();

  if ($role === "player") {
    $player = $pdo->prepare("
      INSERT INTO players (user_id, first_name, last_name, status)
      VALUES (?, ?, ?, 'pending')
    ");
    $player->execute([$userId, $first, $last]);
  }

  $_SESSION["user_id"] = $userId;
  $_SESSION["role"] = $role;
  $_SESSION["user_role"] = $role;

  redirect_to("index.php");

} catch (Throwable $e) {
  redirect_to("register.php?err=" . urlencode("Błąd serwera. Spróbuj ponownie."));
}
