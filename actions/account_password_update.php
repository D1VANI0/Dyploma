<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_login();
require_once __DIR__ . "/../partials/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("account.php");
}

verify_csrf_or_fail();

$current = (string)($_POST["current_password"] ?? "");
$new = (string)($_POST["new_password"] ?? "");
$repeat = (string)($_POST["repeat_password"] ?? "");

if (strlen($new) < 10 || strlen($new) > 200) {
  redirect_to("account.php?err=" . urlencode("Nowe hasło musi mieć minimum 10 znaków."));
}
if ($new !== $repeat) {
  redirect_to("account.php?err=" . urlencode("Powtórzone hasło nie jest takie samo."));
}

try {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([current_user_id()]);
  $hash = (string)($stmt->fetchColumn() ?: "");

  if ($hash === "" || !password_verify($current, $hash)) {
    redirect_to("account.php?err=" . urlencode("Obecne hasło jest nieprawidłowe."));
  }

  $newHash = password_hash($new, PASSWORD_DEFAULT);
  $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
  $update->execute([$newHash, current_user_id()]);

  redirect_to("account.php?ok=" . urlencode("Hasło zostało zmienione."));
} catch (Throwable $e) {
  redirect_to("account.php?err=" . urlencode("Nie udało się zmienić hasła."));
}
