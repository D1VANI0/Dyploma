<?php
require_once __DIR__ . "/partials/auth.php";
require_login();
require_once __DIR__ . "/partials/db.php";

$pageTitle = "ScoutHub • Wiadomości";
include "partials/head.php";
include "partials/navbar.php";
if (current_user_role() === 'admin') {
  echo '<div class="container my-4"><div class="card-soft p-4">Administrator nie ma dostępu do treści wiadomości.</div></div>';
  include "partials/footer.php";
  exit;
}

$uid = current_user_id();
$pdo = db();

$stmt = $pdo->prepare("
  SELECT m.id, m.subject, m.created_at, m.is_read,
         u.first_name, u.last_name
  FROM messages m
  JOIN users u ON u.id = m.sender_user_id
  WHERE m.recipient_user_id = ?
  ORDER BY m.created_at DESC
  LIMIT 50
");
$stmt->execute([$uid]);
$rows = $stmt->fetchAll();
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h1 class="h4 fw-semibold m-0">Wiadomości</h1>
      <div class="text-muted small">Odebrane</div>
    </div>
  </div>

  <div class="card-soft p-3">
    <?php if (!$rows): ?>
      <div class="p-3 text-muted">Brak wiadomości.</div>
    <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($rows as $r): ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
             href="thread.php?id=<?= (int)$r['id'] ?>">
            <div>
              <div class="fw-semibold mb-0">
                <?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?>
                <?php if (!(int)$r['is_read']): ?>
                  <span class="badge badge-soft pill ms-2">Nowe</span>
                <?php endif; ?>
              </div>
              <div class="text-muted small"><?= htmlspecialchars($r['subject']) ?></div>
            </div>
            <div class="text-muted small"><?= htmlspecialchars(substr((string)$r['created_at'], 0, 16)) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include "partials/footer.php"; ?>
