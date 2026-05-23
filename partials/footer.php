<?php require_once __DIR__ . "/auth.php"; ?>

<footer class="section section--waves-bottom mt-5">
  <div class="container py-5">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
      <div>
        <div class="fw-semibold">ScoutHub</div>
        <div class="text-muted small"><b>Platforma do wyszukiwania talentów i analizy statystyk meczowych w akademiach piłkarskich.</b></div>
      </div>
      <div class="text-muted small">
        <b>ScoutHub • Platforma scoutingowa</b>
      </div>
    </div>
  </div>
</footer>

<script>
  window.__CSRF_TOKEN__ = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  window.__CURRENT_ROLE__ = <?= json_encode(current_user_role() === "skaut" ? "scout" : current_user_role(), JSON_UNESCAPED_UNICODE) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/vendor/chart.umd.min.js"></script>
<script src="js/data.js"></script>
<script src="js/app.js"></script>
</body>
</html>
