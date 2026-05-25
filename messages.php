<?php
declare(strict_types=1);

require_once __DIR__ . "/partials/auth.php";
require_login();
require_once __DIR__ . "/partials/db.php";

$pageTitle = "ScoutHub - Wiadomosci";
require_once __DIR__ . "/partials/head.php";
require_once __DIR__ . "/partials/navbar.php";

$userId = current_user_id();
$pdo = db();

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

$stmt = $pdo->prepare("
  SELECT
    x.other_user_id,
    u.email,
    u.first_name,
    u.last_name,
    MAX(x.created_at) AS last_message_at,
    SUBSTRING_INDEX(GROUP_CONCAT(x.subject ORDER BY x.created_at DESC, x.id DESC SEPARATOR '||'), '||', 1) AS last_subject,
    SUBSTRING_INDEX(GROUP_CONCAT(x.body ORDER BY x.created_at DESC, x.id DESC SEPARATOR '||'), '||', 1) AS last_body,
    SUM(CASE WHEN x.recipient_user_id = ? AND x.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
  FROM (
    SELECT
      id,
      sender_user_id,
      recipient_user_id,
      CASE WHEN sender_user_id = ? THEN recipient_user_id ELSE sender_user_id END AS other_user_id,
      subject,
      body,
      created_at,
      is_read
    FROM messages
    WHERE sender_user_id = ? OR recipient_user_id = ?
  ) x
  JOIN users u ON u.id = x.other_user_id
  GROUP BY x.other_user_id, u.email, u.first_name, u.last_name
  ORDER BY last_message_at DESC
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$toId = (int)($_GET["to"] ?? 0);
$toPrefill = "";
if ($toId > 0) {
  $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
  $userStmt->execute([$toId]);
  $toPrefill = (string)($userStmt->fetchColumn() ?: "");
}
if ($toPrefill === "" && isset($_GET["toEmail"])) {
  $toPrefill = trim((string)$_GET["toEmail"]);
}

function display_user_name(array $row): string {
  $name = trim((string)($row["first_name"] ?? "") . " " . (string)($row["last_name"] ?? ""));
  return $name !== "" ? $name : (string)($row["email"] ?? "Uzytkownik");
}

function short_text(string $text, int $limit = 90): string {
  $text = trim(preg_replace("/\s+/", " ", $text) ?? "");
  if (strlen($text) <= $limit) return $text;
  return substr($text, 0, $limit - 3) . "...";
}
?>

<main>
  <div class="container my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
      <div>
        <h1 class="h4 fw-semibold m-0">Wiadomosci</h1>
        <div class="text-muted small">Chat na platformie i powiadomienia mailowe</div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-lg-5 col-xl-4">
        <div class="card-soft p-4">
          <div class="fw-semibold mb-3"><i class="bi bi-inbox"></i> Watki</div>

          <?php if (!$threads): ?>
            <div class="text-muted">Brak wiadomosci.</div>
          <?php else: ?>
            <div class="vstack gap-2">
              <?php foreach ($threads as $thread): ?>
                <?php
                  $name = display_user_name($thread);
                  $unread = (int)($thread["unread_count"] ?? 0);
                ?>
                <a class="text-decoration-none" href="thread.php?u=<?= (int)$thread["other_user_id"] ?>">
                  <div class="card-soft p-3" style="box-shadow:none;">
                    <div class="d-flex justify-content-between gap-2">
                      <div class="fw-semibold"><?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?></div>
                      <div class="text-muted small"><?= htmlspecialchars(substr((string)$thread["last_message_at"], 0, 16), ENT_QUOTES, "UTF-8") ?></div>
                    </div>
                    <div class="text-muted small">
                      <?= htmlspecialchars(short_text((string)($thread["last_subject"] ?? "")), ENT_QUOTES, "UTF-8") ?>
                      <?php if ($unread > 0): ?>
                        <span class="badge badge-soft pill ms-2">Nowe: <?= $unread ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="text-muted small"><?= htmlspecialchars(short_text((string)($thread["last_body"] ?? "")), ENT_QUOTES, "UTF-8") ?></div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12 col-lg-7 col-xl-8">
        <div class="card-soft p-4">
          <div class="fw-semibold mb-3"><i class="bi bi-pencil-square"></i> Nowa wiadomosc</div>

          <form id="msgForm" class="vstack gap-2">
            <?= csrf_input() ?>
            <div>
              <label class="filter-label">Do</label>
              <input class="form-control" type="text" name="recipient_query" placeholder="Wpisz e-mail, imie albo nazwisko" value="<?= htmlspecialchars($toPrefill, ENT_QUOTES, "UTF-8") ?>" required>
              <div class="text-muted small mt-1">Jesli jest kilka podobnych osob, wpisz pelny e-mail.</div>
            </div>

            <div>
              <label class="filter-label">Temat</label>
              <input class="form-control" name="subject" type="text" maxlength="160" placeholder="Temat wiadomosci">
            </div>

            <div>
              <label class="filter-label">Tresc</label>
              <textarea class="form-control" name="body" rows="6" placeholder="Napisz wiadomosc..." required></textarea>
            </div>

            <button class="btn btn-success pill" type="submit">Wyslij</button>
            <div id="msgInfo" class="small"></div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
(() => {
  const form = document.getElementById("msgForm");
  const info = document.getElementById("msgInfo");
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

    setInfo(data.message || "Wiadomosc wyslana.", true);
    form.querySelector('[name="subject"]').value = "";
    form.querySelector('[name="body"]').value = "";

    setTimeout(() => {
      window.location.href = "thread.php?u=" + encodeURIComponent(data.recipient_user_id);
    }, 500);
  });
})();
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
