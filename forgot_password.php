<?php
declare(strict_types=1);

$pageTitle = "ScoutHub - Reset hasła";
require_once __DIR__ . "/partials/head.php";
require_once __DIR__ . "/partials/navbar.php";
require_once __DIR__ . "/partials/auth.php";

$err = (string)($_GET["err"] ?? "");
$ok = (string)($_GET["ok"] ?? "");
?>

<main>
  <div class="container my-5" style="max-width: 560px;">
    <div class="card-soft p-4">
      <div class="text-center mb-3">
        <div class="brand-mark mx-auto mb-2">S</div>
        <h1 class="h5 fw-semibold m-0">Reset hasła</h1>
        <div class="text-muted small">Wyślemy link do ustawienia nowego hasła</div>
      </div>

      <?php if ($err !== ""): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>
      <?php if ($ok !== ""): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($ok) ?></div>
      <?php endif; ?>

      <form class="vstack gap-2" method="post" action="actions/password_reset_request.php">
        <?= csrf_input() ?>
        <div>
          <label class="filter-label">E-mail</label>
          <input class="form-control" type="email" name="email" required>
        </div>

        <button class="btn btn-success pill w-100" type="submit">Wyślij link</button>

        <div class="text-center">
          <a class="text-muted small text-decoration-none" href="login.php">Wróć do logowania</a>
        </div>
      </form>
    </div>
  </div>
</main>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
