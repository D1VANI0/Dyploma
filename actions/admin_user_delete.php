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

$deleteUserId = (int)($_POST["user_id"] ?? 0);
if ($deleteUserId <= 0) {
  redirect_to("account.php?err=" . urlencode("Nieprawidłowy użytkownik."));
}
if ($deleteUserId === current_user_id()) {
  redirect_to("account.php?err=" . urlencode("Nie możesz usunąć własnego konta."));
}

try {
  $pdo = db();

  $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
  $roleStmt->execute([$deleteUserId]);
  $deletedRole = normalize_role((string)($roleStmt->fetchColumn() ?: ""));
  if ($deletedRole === "") {
    redirect_to("account.php?err=" . urlencode("Nie znaleziono użytkownika."));
  }

  if ($deletedRole === "admin") {
    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount <= 1) {
      redirect_to("account.php?err=" . urlencode("Nie można usunąć ostatniego administratora."));
    }
  }

  $pdo->beginTransaction();

  $players = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
  $players->execute([$deleteUserId]);
  $playerIds = array_map("intval", $players->fetchAll(PDO::FETCH_COLUMN));

  foreach ($playerIds as $playerId) {
    $delWatch = $pdo->prepare("DELETE FROM watchlist WHERE player_id = ?");
    $delWatch->execute([$playerId]);
  }

  $delWatchUser = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ?");
  $delWatchUser->execute([$deleteUserId]);

  $delMessages = $pdo->prepare("DELETE FROM messages WHERE sender_user_id = ? OR recipient_user_id = ?");
  $delMessages->execute([$deleteUserId, $deleteUserId]);

  $clearReview = $pdo->prepare("UPDATE players SET review_requested_to = NULL, review_requested_at = NULL WHERE review_requested_to = ?");
  $clearReview->execute([$deleteUserId]);

  $delPlayers = $pdo->prepare("DELETE FROM players WHERE user_id = ?");
  $delPlayers->execute([$deleteUserId]);

  $delUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
  $delUser->execute([$deleteUserId]);

  $pdo->commit();
  redirect_to("account.php?ok=" . urlencode("Konto zostało usunięte."));
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  redirect_to("account.php?err=" . urlencode("Nie udało się usunąć konta."));
}
