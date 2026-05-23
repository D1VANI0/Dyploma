<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_login();
require_once __DIR__ . "/../partials/players_repository.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("account.php");
}

verify_csrf_or_fail();

$role = current_user_role();
if (!can_review_players($role)) {
  redirect_to("account.php?err=" . urlencode("Brak uprawnień."));
}

$playerId = (int)($_POST["player_id"] ?? 0);
$decision = (string)($_POST["decision"] ?? "");
$status = $decision === "approve" ? "approved" : ($decision === "reject" ? "rejected" : "");

if ($playerId <= 0 || $status === "") {
  redirect_to("account.php?err=" . urlencode("Nieprawidłowa decyzja."));
}

try {
  $pdo = db();
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    UPDATE players
    SET status = ?, verified_by = ?, verified_at = NOW()
    WHERE id = ?
  ");
  $stmt->execute([$status, current_user_id(), $playerId]);

  $stats = $pdo->prepare("
    UPDATE player_stats
    SET status = ?, verified_by = ?, verified_at = NOW()
    WHERE player_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 1
  ");
  $stats->execute([$status, current_user_id(), $playerId]);

  $pdo->commit();
  redirect_to("account.php?ok=" . urlencode($status === "approved" ? "Dane potwierdzone." : "Dane odrzucone."));
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  redirect_to("account.php?err=" . urlencode("Nie udało się zmienić statusu."));
}
