<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_once __DIR__ . "/../partials/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("login.php?err=" . urlencode("Nieprawidłowa metoda."));
}

verify_csrf_or_fail();

$email = trim((string)($_POST["email"] ?? ""));
$pass  = (string)($_POST["password"] ?? "");

if ($email === "" || $pass === "") {
  redirect_to("login.php?err=" . urlencode("Uzupełnij e-mail i hasło."));
}
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to("login.php?err=" . urlencode("Podaj poprawny adres e-mail."));
}
if (strlen($pass) < 6 || strlen($pass) > 200) {
  redirect_to("login.php?err=" . urlencode("Nieprawidłowe hasło."));
}

$pdo = db();

try {
  $stmt = $pdo->prepare("SELECT id, email, role, password_hash FROM users WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user || !password_verify($pass, (string)$user["password_hash"])) {
    redirect_to("login.php?err=" . urlencode("Niepoprawny e-mail lub hasło."));
  }

  $_SESSION["user_id"] = (int)$user["id"];
  $role = (string)($user["role"] ?? "player");
  $role = $role === "skaut" ? "scout" : $role;

  $_SESSION["role"] = $role;
  $_SESSION["user_role"] = $_SESSION["role"];

  redirect_to("index.php");

} catch (Throwable $e) {
  redirect_to("login.php?err=" . urlencode("Błąd serwera. Spróbuj ponownie."));
}
