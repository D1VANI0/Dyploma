<?php
declare(strict_types=1);

require_once __DIR__ . "/partials/auth.php";
require_login();
require_once __DIR__ . "/partials/db.php";

$pageTitle = "ScoutHub - Watek";
require_once __DIR__ . "/partials/head.php";
require_once __DIR__ . "/partials/navbar.php";

$userId = current_user_id();
$pdo = db();

$otherId = (int)($_GET["u"] ?? 0);
$messageId = (int)($_GET["id"] ?? 0);

if ($otherId <= 0 && $messageId > 0) {
  $stmt = $pdo->prepare("
    SELECT sender_user_id, recipient_user_id
    FROM messages
    WHERE id = ? AND (sender_user_id = ? OR recipient_user_id = ?)
    LIMIT 1
  ");
  $stmt->execute([$messageId, $userId, $userId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $otherId = ((int)$row["sender_user_id"] === $userId) ? (int)$row["recipient_user_id"] : (int)$row["sender_user_id"];
  }
}

if ($otherId <= 0 || $otherId === $userId) {
  redirect_to("messages.php");
}

$userStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$otherId]);
$other = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$other) {
  redirect_to("messages.php");
}

$name = trim((string)($other["first_name"] ?? "") . " " . (string)($other["last_name"] ?? ""));
if ($name === "") {
  $name = (string)($other["email"] ?? "Uzytkownik");
}

$access = $pdo->prepare("
  SELECT 1
  FROM messages
  WHERE (sender_user_id = ? AND recipient_user_id = ?)
     OR (sender_user_id = ? AND recipient_user_id = ?)
  LIMIT 1
");
$access->execute([$userId, $otherId, $otherId, $userId]);
if (!$access->fetchColumn()) {
  redirect_to("messages.php");
}

$mark = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE recipient_user_id = ? AND sender_user_id = ? AND is_read = 0");
$mark->execute([$userId, $otherId]);

$stmt = $pdo->prepare("
  SELECT id, sender_user_id, recipient_user_id, subject, body, created_at, is_read
  FROM messages
  WHERE (sender_user_id = ? AND recipient_user_id = ?)
     OR (sender_user_id = ? AND recipient_user_id = ?)
  ORDER BY created_at ASC, id ASC
");
$stmt->execute([$userId, $otherId, $otherId, $userId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
  <div class="container my-4">
    <a class="text-decoration-none text-muted small" href="messages.php">
      <i class="bi bi-arrow-left"></i> Wroc do wiadomosci
    </a>

    <div class="d-flex justify-content-between align-items-end gap-2 mt-2 mb-3">
      <div>
        <h1 class="h4 fw-semibold m-0"><?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?></h1>
        <div class="text-muted small">Watek rozmowy</div>
      </div>
    </div>

    <div class="card-soft p-4">
      <div class="vstack gap-2">
        <?php foreach ($messages as $message): ?>
          <?php $mine = (int)$message["sender_user_id"] === $userId; ?>
          <div class="d-flex <?= $mine ? "justify-content-end" : "justify-content-start" ?>">
            <div class="card-soft p-3" style="max-width: 720px; box-shadow:none; <?= $mine ? "border-color: rgba(25,135,84,.35);" : "" ?>">
              <div class="text-muted small mb-1">
                <?= htmlspecialchars((string)$message["subject"], ENT_QUOTES, "UTF-8") ?>
                -
                <?= htmlspecialchars(substr((string)$message["created_at"], 0, 16), ENT_QUOTES, "UTF-8") ?>
              </div>
              <div><?= nl2br(htmlspecialchars((string)$message["body"], ENT_QUOTES, "UTF-8")) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <hr class="my-4">

      <form id="replyForm" class="vstack gap-2">
        <?= csrf_input() ?>
        <input type="hidden" name="recipient_user_id" value="<?= (int)$otherId ?>">

        <div>
          <label class="filter-label">Temat</label>
          <input class="form-control" name="subject" type="text" maxlength="160" placeholder="Temat">
        </div>

        <div>
          <label class="filter-label">Tresc</label>
          <textarea class="form-control" name="body" rows="4" placeholder="Napisz odpowiedz..." required></textarea>
        </div>

        <button class="btn btn-success pill" type="submit">Wyslij</button>
        <div id="replyInfo" class="small"></div>
      </form>
    </div>
  </div>
</main>

<script>
(() => {
  const form = document.getElementById("replyForm");
  const info = document.getElementById("replyInfo");
  if (!form) return;

  function setInfo(text, ok) {
    info.textContent = text || "";
    info.className = ok ? "small text-success" : "small text-danger";
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    setInfo("", true);

    const response = await fetch("message_send.php", {
      method: "POST",
      headers: { "Accept": "application/json" },
      body: new FormData(form),
      credentials: "same-origin"
    });
    const data = await response.json().catch(() => null);

    if (!response.ok || !data || !data.ok) {
      setInfo(data && data.message ? data.message : "Nie udalo sie wyslac wiadomosci.", false);
      return;
    }

    window.location.reload();
  });
})();
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
