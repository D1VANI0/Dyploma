<?php
declare(strict_types=1);

$pageTitle = "ScoutHub • Logowanie";
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
        <h1 class="h5 fw-semibold m-0">Logowanie</h1>
        <div class="text-muted small">Zaloguj się do ScoutHub</div>
      </div>

      <?php if ($err !== ""): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>
      <?php if ($ok !== ""): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($ok) ?></div>
      <?php endif; ?>

      <form class="vstack gap-2" method="post" action="login_post.php" autocomplete="off">
        <?= csrf_input() ?>
        <div>
          <label class="filter-label">E-mail</label>
          <input class="form-control" type="email" name="email" required>
        </div>

        <div>
          <label class="filter-label">Hasło</label>
          <input class="form-control" type="password" name="password" required>
        </div>

        <button class="btn btn-success pill w-100" type="submit">Zaloguj</button>

        <div class="text-center">
          <a class="text-muted small text-decoration-none" href="forgot_password.php">Nie pamietasz hasla?</a>
        </div>

        <div class="text-center">
          <a class="text-muted small text-decoration-none" href="register.php">Nie masz konta? Zarejestruj się</a>
        </div>
      </form>
    </div>
  </div>
</main>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
