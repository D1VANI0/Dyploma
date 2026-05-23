<?php
declare(strict_types=1);
require_once __DIR__ . "/partials/auth.php";
require_login();
require_once __DIR__ . "/partials/db.php";

$pageTitle = "ScoutHub • Wiadomości";
require_once __DIR__ . "/partials/head.php";
require_once __DIR__ . "/partials/navbar.php";
$userId = (int)($_SESSION["user_id"] ?? 0);
if ($userId <= 0) {
  redirect_to("login.php");
}

$pdo = db();

$sql = "
SELECT
  t.other_user_id,
  u.email,
  u.first_name,
  u.last_name,
  t.last_message_at,
  t.last_subject,
  t.last_body,
  t.unread_count
FROM (
  SELECT
    other_user_id,
    MAX(created_at) AS last_message_at,
    SUBSTRING_INDEX(GROUP_CONCAT(subject ORDER BY created_at DESC SEPARATOR '||'), '||', 1) AS last_subject,
    SUBSTRING_INDEX(GROUP_CONCAT(body ORDER BY created_at DESC SEPARATOR '||'), '||', 1) AS last_body,
    SUM(unread_flag) AS unread_count
  FROM (
    SELECT
      CASE
        WHEN sender_user_id = :me1 THEN recipient_user_id
        ELSE sender_user_id
      END AS other_user_id,
      created_at,
      subject,
      body,
      CASE
        WHEN recipient_user_id = :me2 AND is_read = 0 THEN 1 ELSE 0
      END AS unread_flag
    FROM messages
    WHERE sender_user_id = :me3 OR recipient_user_id = :me4
  ) x
  GROUP BY other_user_id
) t
JOIN users u ON u.id = t.other_user_id
ORDER BY t.last_message_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':me1' => $userId,
  ':me2' => $userId,
  ':me3' => $userId,
  ':me4' => $userId,
]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$toId = (int)($_GET["to"] ?? 0);
$toUser = null;
if ($toId > 0) {
  $u = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ? LIMIT 1");
  $u->execute([$toId]);
  $toUser = $u->fetch(PDO::FETCH_ASSOC) ?: null;
}

$toPrefill = "";
if ($toUser) {
  $toPrefill = (string)($toUser["email"] ?? "");
}
if ($toPrefill === "" && isset($_GET["toEmail"])) {
  $toPrefill = trim((string)$_GET["toEmail"]);
}
?>

<main>

<div class="container my-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h1 class="h4 fw-semibold m-0">Wiadomości</h1>
      <div class="text-muted small">Twoja skrzynka</div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-5 col-xl-4">
      <div class="card-soft p-4">
        <div class="fw-semibold mb-3"><i class="bi bi-inbox"></i> Wątki</div>

        <?php if (!$threads): ?>
          <div class="text-muted">Brak wiadomości.</div>
        <?php else: ?>
          <div class="vstack gap-2">
            <?php foreach ($threads as $t): ?>
              <?php
                $name = trim(($t["first_name"] ?? "") . " " . ($t["last_name"] ?? ""));
                if ($name === "") $name = $t["email"] ?? ("Użytkownik #" . (int)$t["other_user_id"]);
              ?>
              <a class="text-decoration-none" href="thread.php?u=<?= (int)$t["other_user_id"] ?>">
                <div class="card-soft p-3" style="box-shadow:none;">
                  <div class="d-flex justify-content-between gap-2">
                    <div class="fw-semibold"><?= htmlspecialchars($name) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars((string)$t["last_message_at"]) ?></div>
                  </div>
                  <div class="text-muted small">
                    <?= htmlspecialchars((string)$t["last_subject"]) ?>
                    <?php if ((int)$t["unread_count"] > 0): ?>
                      <span class="badge badge-soft pill ms-2">Nieprzeczytane: <?= (int)$t["unread_count"] ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-7 col-xl-8">
      <div class="card-soft p-4">
        <div class="fw-semibold mb-3"><i class="bi bi-pencil-square"></i> Nowa wiadomość</div>

        <form id="msgForm" class="vstack gap-2">
          <?= csrf_input() ?>
          <div>
            <label class="filter-label">Do</label>
            <input id="recipientQuery" class="form-control" type="text" name="recipient_query"
                   placeholder="Wpisz email / imię / nazwisko"
                   value="<?= htmlspecialchars($toPrefill) ?>">
            <div class="text-muted small mt-1">Najpewniej działa po emailu (dokładnie).</div>
          </div>

          <div>
            <label class="filter-label">Temat</label>
            <input class="form-control" name="subject" type="text" maxlength="160" placeholder="Temat wiadomości">
          </div>

          <div>
            <label class="filter-label">Treść</label>
            <textarea class="form-control" name="body" rows="5" placeholder="Napisz wiadomość..."></textarea>
          </div>

          <button class="btn btn-success pill" type="submit">Wyślij</button>
          <div id="msgInfo" class="small"></div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const form = document.getElementById("msgForm");
  const info = document.getElementById("msgInfo");
  if (!form) return;

  function setInfo(text, ok){
    if (!info) return;
    info.textContent = text || "";
    info.className = ok ? "small text-success" : "small text-danger";
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    setInfo("", true);

    const fd = new FormData(form);

    fd.set("csrf_token", String(window.__CSRF_TOKEN__ || ""));

    const res = await fetch("actions/message_send.php", {
      method: "POST",
      headers: {
        "Accept": "application/json"
      },
      body: fd,
      credentials: "same-origin",
    });

    const json = await res.json().catch(() => null);

    if (!res.ok || !json || !json.ok) {
      setInfo((json && (json.message || json.error)) ? (json.message || json.error) : "Nie udało się wysłać.", false);
      return;
    }

    setInfo(json.message || "Wiadomość została wysłana.", true);

    form.querySelector('[name="subject"]').value = "";
    form.querySelector('[name="body"]').value = "";
  });
})();
</script>

</main>
<?php require_once __DIR__ . "/partials/footer.php"; ?>
