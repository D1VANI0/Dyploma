<?php
declare(strict_types=1);

$pageTitle = "ScoutHub • Rejestracja";
require_once __DIR__ . "/partials/head.php";
require_once __DIR__ . "/partials/navbar.php";
require_once __DIR__ . "/partials/auth.php";

$err = (string)($_GET["err"] ?? "");
?>

<main>
  <div class="container my-5" style="max-width: 720px;">
    <div class="card-soft p-4">
      <div class="text-center mb-3">
        <div class="brand-mark mx-auto mb-2">S</div>
        <h1 class="h5 fw-semibold m-0">Rejestracja</h1>
        <div class="text-muted small">Utwórz konto</div>
      </div>

      <?php if ($err !== ""): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <form class="row g-2" method="post" action="register_post.php" autocomplete="off">
        <?= csrf_input() ?>
        <div class="col-12">
          <label class="filter-label">Rola</label>
          <select class="form-select" name="role" required>
            <option value="player">Zawodnik</option>
            <option value="coach">Trener</option>
            <option value="scout">Skaut</option>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="filter-label">Imię</label>
          <input class="form-control" name="first_name" placeholder="Imię" required>
        </div>
        <div class="col-12 col-md-6">
          <label class="filter-label">Nazwisko</label>
          <input class="form-control" name="last_name" placeholder="Nazwisko" required>
        </div>

        <div class="col-12 col-md-6">
          <label class="filter-label">E-mail</label>
          <input class="form-control" type="email" name="email" placeholder="E-mail" required>
        </div>
        <div class="col-12 col-md-6">
          <label class="filter-label">Hasło</label>
          <input class="form-control" type="password" name="password" placeholder="Hasło" minlength="6" required>
        </div>

        <div class="col-12">
          <button class="btn btn-success pill w-100" type="submit">Utwórz konto</button>
        </div>
        <div class="col-12 text-center">
          <a class="text-muted small text-decoration-none" href="login.php">Masz konto? Zaloguj się</a>
        </div>
      </form>
    </div>
  </div>
</main>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
