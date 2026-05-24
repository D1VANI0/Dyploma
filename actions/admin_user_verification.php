<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_login();
require_once __DIR__ . "/../partials/user_verification.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("account.php");
}

verify_csrf_or_fail();

if (!can_admin(current_user_role())) {
  redirect_to("account.php?err=" . urlencode("Brak uprawnien administratora."));
}

$id = (int)($_POST["user_id"] ?? 0);
$decision = (string)($_POST["decision"] ?? "");
$status = $decision === "approve" ? "approved" : ($decision === "reject" ? "rejected" : "");

if ($id <= 0 || $status === "" || $id === current_user_id()) {
  redirect_to("account.php?err=" . urlencode("Nieprawidlowa decyzja."));
}

try {
  ensure_user_verification_columns();

  $stmt = db()->prepare("
    UPDATE users
    SET verification_status = ?, verified_at = NOW(), verified_by = ?
    WHERE id = ?
      AND role IN ('coach', 'scout', 'skaut')
  ");
  $stmt->execute([$status, current_user_id(), $id]);

  redirect_to("account.php?ok=" . urlencode($status === "approved" ? "Konto zostalo potwierdzone." : "Konto zostalo odrzucone."));
} catch (Throwable $e) {
  redirect_to("account.php?err=" . urlencode("Nie udalo sie zmienic statusu konta."));
}
