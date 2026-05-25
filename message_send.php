<?php
declare(strict_types=1);

require_once __DIR__ . "/partials/auth.php";
require_login();
require_once __DIR__ . "/partials/db.php";
require_once __DIR__ . "/partials/mailer.php";

header("Content-Type: application/json; charset=utf-8");

function json_response(int $status, array $payload): never {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function ensure_messages_table(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS messages (
      id INT AUTO_INCREMENT PRIMARY KEY,
      sender_user_id INT NOT NULL,
      recipient_user_id INT NOT NULL,
      subject VARCHAR(160) NOT NULL DEFAULT 'Wiadomosc',
      body TEXT NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      INDEX idx_messages_sender (sender_user_id),
      INDEX idx_messages_recipient (recipient_user_id),
      INDEX idx_messages_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");
}

function user_display_name(array $user): string {
  $name = trim((string)($user["first_name"] ?? "") . " " . (string)($user["last_name"] ?? ""));
  return $name !== "" ? $name : (string)($user["email"] ?? "Uzytkownik");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  json_response(405, ["ok" => false, "message" => "Nieprawidlowa metoda."]);
}

verify_csrf_or_fail();

$userId = current_user_id();
if ($userId <= 0) {
  json_response(401, ["ok" => false, "message" => "Zaloguj sie, aby wysylac wiadomosci."]);
}

$subject = trim((string)($_POST["subject"] ?? ""));
$body = trim((string)($_POST["body"] ?? ""));
if ($subject === "") {
  $subject = "Wiadomosc";
}

if (strlen($subject) > 160) {
  json_response(400, ["ok" => false, "message" => "Temat moze miec maks. 160 znakow."]);
}
if ($body === "") {
  json_response(400, ["ok" => false, "message" => "Tresc wiadomosci nie moze byc pusta."]);
}
if (strlen($body) > 5000) {
  json_response(400, ["ok" => false, "message" => "Tresc jest za dluga. Maksymalnie 5000 znakow."]);
}

try {
  $pdo = db();
  ensure_messages_table($pdo);

  $recipientId = (int)($_POST["recipient_user_id"] ?? 0);
  if ($recipientId <= 0) {
    $query = trim((string)($_POST["recipient_query"] ?? ""));
    if ($query === "") {
      json_response(400, ["ok" => false, "message" => "Podaj odbiorce. Najlepiej wpisz pelny e-mail."]);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$query]);
    $recipientId = (int)($stmt->fetchColumn() ?: 0);

    if ($recipientId <= 0) {
      $like = "%" . $query . "%";
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
      $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
      if (count($matches) === 1) {
        $recipientId = (int)$matches[0];
      } elseif (count($matches) > 1) {
        json_response(409, ["ok" => false, "message" => "Znaleziono kilku uzytkownikow. Wpisz pelny e-mail."]);
      }
    }
  }

  if ($recipientId <= 0) {
    json_response(404, ["ok" => false, "message" => "Nie znaleziono odbiorcy."]);
  }
  if ($recipientId === $userId) {
    json_response(400, ["ok" => false, "message" => "Nie mozesz wyslac wiadomosci do siebie."]);
  }

  $recipientStmt = $pdo->prepare("SELECT id, email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
  $recipientStmt->execute([$recipientId]);
  $recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC);
  if (!$recipient) {
    json_response(404, ["ok" => false, "message" => "Nie znaleziono odbiorcy."]);
  }

  $senderStmt = $pdo->prepare("SELECT id, email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
  $senderStmt->execute([$userId]);
  $sender = $senderStmt->fetch(PDO::FETCH_ASSOC) ?: [];

  $insert = $pdo->prepare("
    INSERT INTO messages (sender_user_id, recipient_user_id, subject, body, created_at, is_read)
    VALUES (?, ?, ?, ?, NOW(), 0)
  ");
  $insert->execute([$userId, $recipientId, $subject, $body]);
  $messageId = (int)$pdo->lastInsertId();

  $senderName = user_display_name($sender);
  $threadUrl = absolute_app_url("thread.php?u=" . $userId);
  $mailBody = "Masz nowa wiadomosc w ScoutHub.\n\n"
    . "Od: {$senderName}\n"
    . "Temat: {$subject}\n\n"
    . "{$body}\n\n"
    . "Odpowiedz na platformie: {$threadUrl}";
  $mailSent = send_app_mail((string)$recipient["email"], "ScoutHub - nowa wiadomosc", $mailBody);

  json_response(200, [
    "ok" => true,
    "message" => $mailSent
      ? "Wiadomosc zostala wyslana na platformie i mailem."
      : "Wiadomosc zostala wyslana na platformie, ale mail nie zostal wyslany.",
    "message_id" => $messageId,
    "recipient_user_id" => $recipientId,
    "mail_sent" => $mailSent,
  ]);
} catch (Throwable $e) {
  json_response(500, ["ok" => false, "message" => "Blad serwera. Sprobuj ponownie."]);
}
