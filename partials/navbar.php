<?php
require_once __DIR__ . "/auth.php";
$logged = current_user_id() > 0;
?>

<nav class="navbar bg-white sticky-top border-bottom sh-navbar">
  <div class="container-fluid sh-nav-grid sh-nav-wide">

    <div class="sh-nav-left">
      <a class="btn btn-sm btn-outline-success rounded-pill px-4" href="players.php">Zawodnicy</a>

      <?php if ($logged): ?>
        <a class="btn btn-sm btn-outline-success rounded-pill px-4" href="watchlist.php">Obserwowani</a>
      <?php endif; ?>
    </div>

    <a class="sh-logo-link" href="index.php" aria-label="ScoutHub">
      <img src="<?= htmlspecialchars(app_url("assets/logo.png"), ENT_QUOTES, "UTF-8") ?>" alt="ScoutHub" class="brand-logo">
    </a>

    <div class="sh-nav-right">
      <?php if ($logged): ?>
        <a class="btn btn-sm btn-outline-success rounded-pill px-4" href="messages.php">Wiadomości</a>
        <a class="btn btn-sm btn-success rounded-pill px-4" href="account.php">Konto</a>
      <?php else: ?>
        <a class="btn btn-sm btn-outline-success rounded-pill px-4" href="login.php">Logowanie</a>
        <a class="btn btn-sm btn-success rounded-pill px-4" href="register.php">Rejestracja</a>
      <?php endif; ?>
    </div>

  </div>
</nav>
