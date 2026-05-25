<?php
declare(strict_types=1);

require_once __DIR__ . "/partials/auth.php";
require_once __DIR__ . "/partials/db.php";
require_once __DIR__ . "/partials/players_repository.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("login.php?err=" . urlencode("Nieprawidlowa metoda."));
}

$email = trim((string)($_POST["email"] ?? ""));
$pass = (string)($_POST["password"] ?? "");

if ($email === "" || $pass === "") {
  redirect_to("login.php?err=" . urlencode("Uzupelnij e-mail i haslo."));
}
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to("login.php?err=" . urlencode("Podaj poprawny adres e-mail."));
}
if (strlen($pass) < 6 || strlen($pass) > 200) {
  redirect_to("login.php?err=" . urlencode("Nieprawidlowe haslo."));
}

try {
  $stmt = db()->prepare("
    SELECT id, email, role, verification_status, password_hash
    FROM users
    WHERE email = ?
    LIMIT 1
  ");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user || !password_verify($pass, (string)$user["password_hash"])) {
    redirect_to("login.php?err=" . urlencode("Niepoprawny e-mail lub haslo."));
  }

  $verificationStatus = (string)($user["verification_status"] ?? "approved");
  if ($verificationStatus === "pending") {
    redirect_to("login.php?err=" . urlencode("Konto czeka na potwierdzenie przez administratora."));
  }
  if ($verificationStatus === "rejected") {
    redirect_to("login.php?err=" . urlencode("Konto trenera/skauta zostalo odrzucone przez administratora."));
  }

  $role = normalize_role((string)($user["role"] ?? "player"));

  $_SESSION["user_id"] = (int)$user["id"];
  $_SESSION["role"] = $role;
  $_SESSION["user_role"] = $role;
  set_login_cookie((int)$user["id"], $role);

  redirect_to("index.php");
} catch (Throwable $e) {
  error_log("ScoutHub login error: " . $e->getMessage());
  redirect_to("login.php?err=" . urlencode("Blad serwera. Sprobuj ponownie."));
}
