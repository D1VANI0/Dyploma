<?php
declare(strict_types=1);
require_once __DIR__ . "/partials/auth.php";
require_login();
require_once __DIR__ . "/partials/db.php";

$pageTitle = "ScoutHub • Wątek";
require_once __DIR__ . "/partials/head.php";
require_once __DIR__ . "/partials/navbar.php";
$userId = (int)($_SESSION["user_id"] ?? 0);
if ($userId <= 0) {
  redirect_to("login.php");
}

$otherId = (int)($_GET["u"] ?? 0);
if ($otherId <= 0 || $otherId === $userId) {
  redirect_to("messages.php");
}

$pdo = db();

$u = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ? LIMIT 1");
$u->execute([$otherId]);
$other = $u->fetch(PDO::FETCH_ASSOC);
if (!$other) {
  redirect_to("messages.php");
}

$name = trim(($other["first_name"] ?? "") . " " . ($other["last_name"] ?? ""));
if ($name === "") $name = $other["email"] ?? ("Użytkownik #" . $otherId);

$access = $pdo->prepare("
  SELECT 1
  FROM messages
  WHERE (sender_user_id = ? AND recipient_user_id = ?)
     OR (sender_user_id = ? AND recipient_user_id = ?)
  LIMIT 1
");
$access->execute([$userId, $otherId, $otherId, $userId]);
if (!$access->fetchColumn()) {
  redirect_to("messages.php?err=" . urlencode("Brak dostępu do wątku."));
}

$mark = $pdo->prepare("
  UPDATE messages
  SET is_read = 1
  WHERE recipient_user_id = ? AND sender_user_id = ? AND is_read = 0
");
$mark->execute([$userId, $otherId]);

$stmt = $pdo->prepare("
  SELECT id, sender_user_id, recipient_user_id, subject, body, created_at, is_read
  FROM messages
  WHERE (sender_user_id = ? AND recipient_user_id = ?)
     OR (sender_user_id = ? AND recipient_user_id = ?)
  ORDER BY created_at ASC
");
$stmt->execute([$userId, $otherId, $otherId, $userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container my-4">
  <a class="text-decoration-none text-muted small" href="messages.php">
    <i class="bi bi-arrow-left"></i> Wróć do wiadomości
  </a>

  <div class="d-flex justify-content-between align-items-end gap-2 mt-2 mb-3">
    <div>
      <h1 class="h4 fw-semibold m-0"><?= htmlspecialchars($name) ?></h1>
      <div class="text-muted small">Wątek</div>
    </div>
  </div>

  <div class="card-soft p-4">
    <?php if (!$rows): ?>
      <div class="text-muted">Brak wiadomości w tym wątku.</div>
    <?php else: ?>
      <div class="vstack gap-2">
        <?php foreach ($rows as $m): ?>
          <?php $mine = ((int)$m["sender_user_id"] === $userId); ?>
          <div class="d-flex <?= $mine ? "justify-content-end" : "justify-content-start" ?>">
            <div class="card-soft p-3" style="max-width: 720px; box-shadow:none; <?= $mine ? "border-color: rgba(25,135,84,.25);" : "" ?>">
              <div class="text-muted small mb-1">
                <?= htmlspecialchars((string)$m["subject"]) ?> • <?= htmlspecialchars((string)$m["created_at"]) ?>
              </div>
              <div><?= nl2br(htmlspecialchars((string)$m["body"])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <hr class="my-4">

    <form id="replyForm" class="vstack gap-2">
      <?= csrf_input() ?>
      <input type="hidden" name="recipient_user_id" value="<?= (int)$otherId ?>">

      <div>
        <label class="filter-label">Temat</label>
        <input class="form-control" name="subject" type="text" maxlength="160" placeholder="Temat">
      </div>

      <div>
        <label class="filter-label">Treść</label>
        <textarea class="form-control" name="body" rows="4" placeholder="Napisz odpowiedź..."></textarea>
      </div>

      <button class="btn btn-success pill" type="submit">Wyślij</button>
      <div id="replyInfo" class="text-muted small"></div>
    </form>
  </div>
</div>

<script>
(() => {
  const form = document.getElementById("replyForm");
  const info = document.getElementById("replyInfo");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    info.textContent = "";

    const fd = new FormData(form);
    // CSRF pewniak (token z footer.php)
    fd.set("csrf_token", String(window.__CSRF_TOKEN__ || ""));

    const res = await fetch("actions/message_send.php", {
      method: "POST",
      headers: { "Accept": "application/json" },
      body: fd,
      credentials: "same-origin"
    });

    const json = await res.json().catch(() => null);

    if (!res.ok || !json || !json.ok) {
      info.textContent = (json && (json.message || json.error)) ? (json.message || json.error) : "Błąd wysyłania.";
      return;
    }

    location.reload();
  });
})();
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
