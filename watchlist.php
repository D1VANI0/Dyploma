<?php
require_once __DIR__ . "/partials/auth.php";
require_login();

$pageTitle = "ScoutHub • Obserwowani";
include "partials/head.php";
include "partials/navbar.php";
?>

<main>
  <div class="container my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h1 class="h4 fw-semibold m-0">Obserwowani</h1>
        <div class="text-muted small">Twoja lista obserwowanych zawodników</div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-success pill" href="exports/watchlist_csv.php">
          <i class="bi bi-filetype-csv"></i> Pobierz CSV
        </a>

        <a class="btn btn-outline-success pill" href="players.php">
          <i class="bi bi-arrow-left"></i> Wróć do zawodników
        </a>
      </div>
    </div>

    <div id="watchlistRoot"></div>
  </div>
</main>

<?php include "partials/footer.php"; ?>
