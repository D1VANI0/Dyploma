<?php $pageTitle = "ScoutHub • Zawodnicy"; include "partials/head.php"; ?>
<?php include "partials/navbar.php"; ?>
<?php
require_once __DIR__ . "/partials/players_repository.php";
$canComparePlayers = normalize_role(current_user_role()) === "scout";
?>
<main>
<div class="container my-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <h1 class="h4 fw-semibold m-0">Wyszukiwarka zawodników</h1>
      <div class="text-muted small">Wyniki: <span id="resultsCount">0</span></div>
    </div>
    <a class="btn btn-outline-success pill" href="watchlist.php">
      <i class="bi bi-bookmark-heart"></i> Obserwowani
    </a>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4 col-xl-3">
      <div class="card-soft p-4">
        <div class="fw-semibold mb-3"><i class="bi bi-funnel"></i> Filtry</div>

        <form id="filtersForm" class="filters-grid">
          <div class="filter-group">
            <label class="filter-label" for="q">Szukaj</label>
            <input id="q" class="form-control" placeholder="Imię, nazwisko, akademia...">
          </div>

          <div class="filter-group">
            <label class="filter-label" for="fCountry">Kraj</label>
            <select id="fCountry" class="form-select"></select>
          </div>

          <div class="filter-group">
            <label class="filter-label" for="fPosition">Pozycja</label>
            <select id="fPosition" class="form-select"></select>
          </div>

          <div class="filter-group">
            <label class="filter-label" for="fFoot">Noga</label>
            <select id="fFoot" class="form-select"></select>
          </div>

          <div class="filter-group">
            <label class="filter-label" for="fAcademy">Akademia</label>
            <select id="fAcademy" class="form-select"></select>
          </div>

          <div class="filter-group">
            <label class="filter-label">Wiek</label>
            <div class="range-row">
              <input id="fAgeMin" type="number" class="form-control" placeholder="min" min="0" max="60" value="0">
              <input id="fAgeMax" type="number" class="form-control" placeholder="max" min="0" max="60" value="99">
            </div>
          </div>

          <div class="filter-group">
            <label class="filter-label">Wzrost (cm)</label>
            <div class="range-row">
              <input id="fHeightMin" type="number" class="form-control" placeholder="min" min="0" max="250" value="0">
              <input id="fHeightMax" type="number" class="form-control" placeholder="max" min="0" max="250" value="300">
            </div>
          </div>

          <button id="btnReset" type="button" class="btn btn-light pill border w-100 mt-1">
            Resetuj
          </button>
        </form>

      </div>
    </div>

    <div class="col-12 col-lg-8 col-xl-9">
      <?php if ($canComparePlayers): ?>
        <div class="card-soft compare-panel p-4 mb-3" id="comparePanel">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
              <div class="fw-semibold"><i class="bi bi-columns-gap"></i> Porównywarka skauta</div>
              <div class="text-muted small">Wybrani zawodnicy: <span id="compareCount">0</span></div>
            </div>
            <button class="btn btn-outline-secondary pill" type="button" id="compareClear">
              <i class="bi bi-x-circle"></i> Wyczyść
            </button>
          </div>
          <div id="compareSelected" class="compare-selected mb-3"></div>
          <div id="compareTableRoot"></div>
        </div>
      <?php endif; ?>
      <div class="row g-3" id="playersList"></div>
    </div>
  </div>
</div>
</main>

<?php include "partials/footer.php"; ?>
