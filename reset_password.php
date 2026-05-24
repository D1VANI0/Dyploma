<?php
declare(strict_types=1);

require_once __DIR__ . "/partials/auth.php";
require_once __DIR__ . "/partials/db.php";
require_once __DIR__ . "/partials/password_reset_repository.php";

$token = (string)($_GET["token"] ?? "");
$err = (string)($_GET["err"] ?? "");
$valid = false;

if ($token !== "") {
  $valid = password_reset_token_is_valid($token);
}

$pageTitle = "ScoutHub - Nowe hasło";
require_once __DIR__ . "/partials/head.php";
require_once __DIR__ . "/partials/navbar.php";
?>

<main>
  <div class="container my-5" style="max-width: 560px;">
    <div class="card-soft p-4">
      <div class="text-center mb-3">
        <div class="brand-mark mx-auto mb-2">S</div>
        <h1 class="h5 fw-semibold m-0">Ustaw nowe hasło</h1>
      </div>

      <?php if ($err !== ""): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <?php if (!$valid): ?>
        <div class="alert alert-danger py-2">Link jest nieprawidłowy albo wygasł.</div>
        <a class="btn btn-success pill w-100" href="forgot_password.php">Wyślij nowy link</a>
      <?php else: ?>
        <form class="vstack gap-2" method="post" action="password_reset_confirm.php">
          <?= csrf_input() ?>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, "UTF-8") ?>">

          <div>
            <label class="filter-label">Nowe hasło</label>
            <input class="form-control" type="password" name="password" minlength="10" autocomplete="new-password" required>
          </div>

          <div>
            <label class="filter-label">Powtórz nowe hasło</label>
            <input class="form-control" type="password" name="password_repeat" minlength="10" autocomplete="new-password" required>
          </div>

          <button class="btn btn-success pill w-100" type="submit">Zmień hasło</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
