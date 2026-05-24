<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_once __DIR__ . "/../partials/user_verification.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("register.php?err=" . urlencode("Nieprawidlowa metoda."));
}

$email = trim((string)($_POST["email"] ?? ""));
$pass  = (string)($_POST["password"] ?? "");
$first = trim((string)($_POST["first_name"] ?? ""));
$last  = trim((string)($_POST["last_name"] ?? ""));
$role  = normalize_role(trim((string)($_POST["role"] ?? "player")));

$allowedRoles = ["player", "coach", "scout"];
if (!in_array($role, $allowedRoles, true)) {
  redirect_to("register.php?err=" . urlencode("Nieprawidlowa rola konta."));
}

if ($first === "" || $last === "" || $email === "" || $pass === "") {
  redirect_to("register.php?err=" . urlencode("Uzupelnij wszystkie pola."));
}
if (strlen($first) < 2 || strlen($first) > 50 || strlen($last) < 2 || strlen($last) > 70) {
  redirect_to("register.php?err=" . urlencode("Imie i nazwisko musza miec poprawna dlugosc."));
}
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to("register.php?err=" . urlencode("Podaj poprawny adres e-mail."));
}
if (strlen($pass) < 8 || strlen($pass) > 200) {
  redirect_to("register.php?err=" . urlencode("Haslo musi miec minimum 8 znakow."));
}

try {
  ensure_user_verification_columns();

  $pdo = db();
  $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
  $chk->execute([$email]);
  if ($chk->fetch()) {
    redirect_to("register.php?err=" . urlencode("Taki e-mail juz istnieje."));
  }

  $verificationStatus = role_requires_admin_verification($role) ? "pending" : "approved";
  $hash = password_hash($pass, PASSWORD_DEFAULT);

  $ins = $pdo->prepare("
    INSERT INTO users (first_name, last_name, email, role, verification_status, password_hash)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $ins->execute([$first, $last, $email, $role, $verificationStatus, $hash]);

  $userId = (int)$pdo->lastInsertId();

  if ($role === "player") {
    $player = $pdo->prepare("
      INSERT INTO players (user_id, first_name, last_name, status)
      VALUES (?, ?, ?, 'pending')
    ");
    $player->execute([$userId, $first, $last]);
  }

  if ($verificationStatus !== "approved") {
    redirect_to("login.php?ok=" . urlencode("Konto zostalo utworzone i czeka na potwierdzenie przez administratora."));
  }

  $_SESSION["user_id"] = $userId;
  $_SESSION["role"] = $role;
  $_SESSION["user_role"] = $role;

  if (function_exists("set_login_cookie")) {
    set_login_cookie($userId, $role);
  }

  redirect_to("index.php");
} catch (Throwable $e) {
  redirect_to("register.php?err=" . urlencode("Blad serwera. Sprobuj ponownie."));
}
