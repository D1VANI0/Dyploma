<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php";
require_login();
require_once __DIR__ . "/../partials/players_repository.php";
require_once __DIR__ . "/../partials/mailer.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  redirect_to("account.php");
}

verify_csrf_or_fail();

$email = trim((string)($_POST["reviewer_email"] ?? ""));
if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_to("account.php?err=" . urlencode("Podaj poprawny e-mail trenera lub skauta."));
}

try {
  $pdo = db();
  $userId = current_user_id();
  $player = fetch_player_for_user($userId);
  if (!$player) {
    redirect_to("account.php?err=" . urlencode("Najpierw uzupełnij profil zawodnika."));
  }

  $reviewerStmt = $pdo->prepare("
    SELECT id, email, role, first_name, last_name
    FROM users
    WHERE email = ?
    LIMIT 1
  ");
  $reviewerStmt->execute([$email]);
  $reviewer = $reviewerStmt->fetch(PDO::FETCH_ASSOC);

  if (!$reviewer || !can_review_players(normalize_role((string)$reviewer["role"]))) {
    redirect_to("account.php?err=" . urlencode("Nie znaleziono trenera, skauta lub admina z takim e-mailem."));
  }

  if ((int)$reviewer["id"] === $userId) {
    redirect_to("account.php?err=" . urlencode("Nie możesz wysłać prośby do siebie."));
  }

  $pdo->beginTransaction();

  $update = $pdo->prepare("
    UPDATE players
    SET review_requested_to = ?, review_requested_at = NOW()
    WHERE id = ? AND user_id = ?
  ");
  $update->execute([(int)$reviewer["id"], (int)$player["id"], $userId]);

  $playerName = trim((string)$player["first_name"] . " " . (string)$player["last_name"]);
  $senderName = trim((string)($_SESSION["first_name"] ?? ""));
  $subject = "Prośba o potwierdzenie danych zawodnika";
  $body = "Zawodnik {$playerName} prosi o potwierdzenie profilu i statystyk.\n\nWejdź w Konto w ScoutHub, aby sprawdzić zgłoszenie.";

  $msg = $pdo->prepare("
    INSERT INTO messages (sender_user_id, recipient_user_id, subject, body, created_at, is_read)
    VALUES (?, ?, ?, ?, NOW(), 0)
  ");
  $msg->execute([$userId, (int)$reviewer["id"], $subject, $body]);

  $pdo->commit();
  send_app_mail((string)$reviewer["email"], "ScoutHub - prosba o potwierdzenie danych", $body . "\n\nPanel konta: " . absolute_app_url("account.php"));
  redirect_to("account.php?ok=" . urlencode("Prośba została wysłana do: " . $email));
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  redirect_to("account.php?err=" . urlencode("Nie udało się wysłać prośby o potwierdzenie."));
}
