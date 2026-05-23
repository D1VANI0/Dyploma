<?php
declare(strict_types=1);

require_once __DIR__ . "/../partials/auth.php"; require_login();
require_once __DIR__ . "/../partials/db.php";
require_once __DIR__ . "/../partials/mailer.php";

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok" => false, "error" => "METHOD_NOT_ALLOWED", "message" => "Nieprawidłowa metoda."]);
  exit;
}

verify_csrf_or_fail();

$userId = current_user_id();
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(["ok" => false, "error" => "NOT_LOGGED_IN", "message" => "Zaloguj się, aby wysyłać wiadomości."]);
  exit;
}

$subject = trim((string)($_POST["subject"] ?? ""));
$body    = trim((string)($_POST["body"] ?? ""));

if ($subject === "") $subject = "Wiadomość";

if (strlen($subject) > 160) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "SUBJECT_TOO_LONG", "message" => "Temat może mieć maks. 160 znaków."]);
  exit;
}
if ($body === "") {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "EMPTY_BODY", "message" => "Treść wiadomości nie może być pusta."]);
  exit;
}
if (strlen($body) > 5000) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "BODY_TOO_LONG", "message" => "Treść jest za długa (max 5000 znaków)."]);
  exit;
}

$pdo = db();

try {
  $recipientId = (int)($_POST["recipient_user_id"] ?? 0);

  if ($recipientId <= 0) {
    $q = trim((string)($_POST["recipient_query"] ?? ""));
    if ($q === "") {
      http_response_code(400);
      echo json_encode(["ok" => false, "error" => "MISSING_RECIPIENT", "message" => "Podaj odbiorcę (najlepiej pełny email)."]);
      exit;
    }
    if (strlen($q) > 254) {
      http_response_code(400);
      echo json_encode(["ok" => false, "error" => "RECIPIENT_TOO_LONG", "message" => "Zapytanie odbiorcy jest za długie."]);
      exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$q]);
    $recipientId = (int)($stmt->fetchColumn() ?: 0);

    if ($recipientId <= 0) {
      $like = "%" . $q . "%";
      $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE CONCAT_WS(' ', first_name, last_name) LIKE ?
           OR first_name LIKE ?
           OR last_name LIKE ?
           OR email LIKE ?
        ORDER BY id ASC
        LIMIT 2
      ");
      $stmt->execute([$like, $like, $like, $like]);
      $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

      if (count($rows) === 1) {
        $recipientId = (int)$rows[0];
      } elseif (count($rows) > 1) {
        http_response_code(409);
        echo json_encode(["ok" => false, "error" => "RECIPIENT_AMBIGUOUS", "message" => "Znaleziono kilku użytkowników. Wpisz pełny email."]);
        exit;
      }
    }
  }

  if ($recipientId <= 0) {
    http_response_code(404);
    echo json_encode(["ok" => false, "error" => "RECIPIENT_NOT_FOUND", "message" => "Nie znaleziono odbiorcy."]);
    exit;
  }
  if ($recipientId === $userId) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "INVALID_RECIPIENT", "message" => "Nie możesz wysłać wiadomości do siebie."]);
    exit;
  }

  $chk = $pdo->prepare("SELECT id, email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
  $chk->execute([$recipientId]);
  $recipient = $chk->fetch(PDO::FETCH_ASSOC);
  if (!$recipient) {
    http_response_code(404);
    echo json_encode(["ok" => false, "error" => "RECIPIENT_NOT_FOUND", "message" => "Nie znaleziono odbiorcy."]);
    exit;
  }

  $senderStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1");
  $senderStmt->execute([$userId]);
  $sender = $senderStmt->fetch(PDO::FETCH_ASSOC) ?: [];

  $ins = $pdo->prepare("
    INSERT INTO messages (sender_user_id, recipient_user_id, subject, body, created_at, is_read)
    VALUES (?, ?, ?, ?, NOW(), 0)
  ");
  $ins->execute([$userId, $recipientId, $subject, $body]);
  $messageId = (int)$pdo->lastInsertId();

  $senderName = trim((string)($sender["first_name"] ?? "") . " " . (string)($sender["last_name"] ?? ""));
  if ($senderName === "") $senderName = (string)($sender["email"] ?? "ScoutHub");
  $mailBody = "Masz nowa wiadomosc w ScoutHub od: {$senderName}\n\nTemat: {$subject}\n\n{$body}\n\nOtworz wiadomosci: " . absolute_app_url("messages.php");
  send_app_mail((string)($recipient["email"] ?? ""), "ScoutHub - nowa wiadomosc", $mailBody);

  echo json_encode([
    "ok" => true,
    "message" => "Wiadomość została wysłana.",
    "message_id" => $messageId,
    "recipient_user_id" => $recipientId
  ]);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "SERVER_ERROR", "message" => "Błąd serwera. Spróbuj ponownie."]);
  exit;
}
