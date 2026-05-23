<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_login();
require_once __DIR__ . "/../partials/players_repository.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("account.php");
}

verify_csrf_or_fail();

if (!can_admin(current_user_role())) {
  redirect_to("account.php?err=" . urlencode("Brak uprawnień administratora."));
}

$id = (int)($_POST["user_id"] ?? 0);
$role = normalize_role(trim((string)($_POST["role"] ?? "")));
$allowed = ["admin", "coach", "scout", "player", "agent"];

if ($id <= 0 || !in_array($role, $allowed, true)) {
  redirect_to("account.php?err=" . urlencode("Nieprawidłowe dane użytkownika."));
}

try {
  $pdo = db();
  $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
  $stmt->execute([$role, $id]);
  redirect_to("account.php?ok=" . urlencode("Rola użytkownika została zmieniona."));
} catch (Throwable $e) {
  redirect_to("account.php?err=" . urlencode("Nie udało się zmienić roli."));
}
