<?php $pageTitle = "ScoutHub • Strona główna"; include "partials/head.php"; ?>
<?php include "partials/navbar.php"; ?>

<main>
<section class="section section--waves-top">
  <div class="sh-hero-whiteveil"></div>
  <div class="container hero">
    <div class="row align-items-center g-4">
      <div class="col-12 col-lg-7">
        <div class="text-muted small mb-2"><i class="bi bi-shield-check"></i> Platforma scoutingowa dla akademii i klubów </div>
        <h1 class="display-6 fw-semibold mb-3">
          Znajdź talenty piłkarskie i analizuj statystyki meczowe
        </h1>
        <p class="text-muted mb-4">
          Wyszukuj zawodników według kryteriów (kraj, wiek, pozycja, noga, wzrost, akademia, rocznik), 
          przeglądaj profile oraz statystyki z meczów i zapisuj obserwowanych.
        </p>

        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-success pill px-4" href="players.php"><i class="bi bi-search"></i> Szukaj zawodników</a>
        </div>

        <div class="row g-2 mt-4">
          <div class="col-12 col-md-4"><div class="kpi"><div class="text-muted small">Profile</div><div class="fw-semibold">Zawodnicy</div></div></div>
          <div class="col-12 col-md-4"><div class="kpi"><div class="text-muted small">Statystyki</div><div class="fw-semibold">Mecze</div></div></div>
          <div class="col-12 col-md-4"><div class="kpi"><div class="text-muted small">Scouting</div><div class="fw-semibold">Obserwowani</div></div></div>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="card-soft p-4">
          <div class="fw-semibold mb-2">Jak to działa</div>
          <div class="text-muted small mb-3">Skauci i trenerzy mogą filtrować zawodników, porównywać wyniki, zapisywać obserwowanych oraz kontaktować się bezpośrednio przez platformę.</div>
          <ol class="text-muted small mb-0">
            <li>Wyszukaj zawodników i ustaw filtry.</li>
            <li>Otwórz profil i przejrzyj statystyki oraz media.</li>
            <li>Dodaj do obserwowanych i nawiąż kontakt.</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="container mt-4">
  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card-soft p-4 h-100">
        <div class="fw-semibold mb-2"><i class="bi bi-person-badge"></i> Zorientowane dla: </div>
        <div class="text-muted small"> Trenerów • Skautów </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card-soft p-4 h-100">
        <div class="fw-semibold mb-2"><i class="bi bi-bar-chart"></i> Statystyki</div>
        <div class="text-muted small">Statystyki z meczów akademii: minuty, bramki, asysty, oceny, pozycja, i więcej (docelowo wykresy).</div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card-soft p-4 h-100">
        <div class="fw-semibold mb-2"><i class="bi bi-filetype-pdf"></i> Raporty</div>
        <div class="text-muted small">Generowanie raportów zawodnika (PDF) i eksport danych (plików EXCEL) — do analizy i archiwizacji.</div>
      </div>
    </div>
  </div>
</section>
</main>

<?php include "partials/footer.php"; ?>
