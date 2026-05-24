<?php
declare(strict_types=1);

require_once __DIR__ . "/partials/auth.php";
require_login();
require_once __DIR__ . "/partials/players_repository.php";
require_once __DIR__ . "/partials/user_verification.php";

$pdo = db();
ensure_user_verification_columns();
$userId = current_user_id();
$role = normalize_role(current_user_role());

$u = $pdo->prepare("SELECT id, email, role, verification_status, first_name, last_name FROM users WHERE id = ? LIMIT 1");
$u->execute([$userId]);
$user = $u->fetch(PDO::FETCH_ASSOC);
if (!$user) {
  redirect_to("logout.php");
}

$err = (string)($_GET["err"] ?? "");
$ok = (string)($_GET["ok"] ?? "");

$pageTitle = "ScoutHub - Konto";
require_once __DIR__ . "/partials/head.php";
require_once __DIR__ . "/partials/navbar.php";

function h(mixed $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function stat_value(?array $player, string $key): string {
  if (!$player || !array_key_exists($key, $player)) return "";
  return h($player[$key]);
}

function render_stat_inputs(?array $player): void {
  $labels = player_stat_labels();
  foreach (player_stats_columns() as $col) {
    $step = in_array($col, ["pass_acc", "duels_won", "rating"], true) ? "0.1" : "1";
    $max = $col === "rating" ? "10" : "";
    ?>
    <div class="col-6 col-lg-3">
      <label class="filter-label"><?= h($labels[$col] ?? $col) ?></label>
      <input class="form-control" type="number" name="<?= h($col) ?>" min="0" <?= $max !== "" ? 'max="'.h($max).'"' : "" ?> step="<?= h($step) ?>" value="<?= stat_value($player, $col) ?>">
    </div>
    <?php
  }
}

$ownPlayer = fetch_player_for_user($userId);
$editPlayer = null;
if (can_admin($role) && (int)($_GET["player_id"] ?? 0) > 0) {
  $editPlayer = fetch_player_row((int)$_GET["player_id"], true);
}
$profilePlayer = can_admin($role) ? $editPlayer : $ownPlayer;
?>

<main>
  <div class="container my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
      <div>
        <h1 class="h4 fw-semibold m-0">Konto</h1>
        <div class="text-muted small"><?= h(role_label($role)) ?> - <?= h((string)$user["email"]) ?></div>
      </div>
      <a class="btn btn-outline-success pill" href="logout.php">Wyloguj</a>
    </div>

    <?php if ($err !== ""): ?>
      <div class="alert alert-danger"><?= h($err) ?></div>
    <?php endif; ?>
    <?php if ($ok !== ""): ?>
      <div class="alert alert-success"><?= h($ok) ?></div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-12 col-xl-4">
        <div class="card-soft p-4 h-100">
          <div class="fw-semibold mb-3">Dane konta</div>
          <form class="vstack gap-2" method="post" action="actions/account_update.php">
            <?= csrf_input() ?>
            <div>
              <label class="filter-label">Imię</label>
              <input class="form-control" name="first_name" value="<?= h($user["first_name"] ?? "") ?>" required>
            </div>
            <div>
              <label class="filter-label">Nazwisko</label>
              <input class="form-control" name="last_name" value="<?= h($user["last_name"] ?? "") ?>" required>
            </div>
            <div>
              <label class="filter-label">E-mail</label>
              <input class="form-control" type="email" name="email" value="<?= h($user["email"] ?? "") ?>" required>
            </div>
            <button class="btn btn-success pill" type="submit">Zapisz konto</button>
          </form>

          <form class="vstack gap-2 mt-4 pt-3 border-top" method="post" action="actions/account_password_update.php">
            <?= csrf_input() ?>
            <div class="fw-semibold">Zmiana hasła</div>
            <div>
              <label class="filter-label">Obecne hasło</label>
              <input class="form-control" type="password" name="current_password" autocomplete="current-password" required>
            </div>
            <div>
              <label class="filter-label">Nowe hasło</label>
              <input class="form-control" type="password" name="new_password" minlength="10" autocomplete="new-password" required>
            </div>
            <div>
              <label class="filter-label">Powtórz nowe hasło</label>
              <input class="form-control" type="password" name="repeat_password" minlength="10" autocomplete="new-password" required>
            </div>
            <button class="btn btn-outline-success pill" type="submit">Zmień hasło</button>
          </form>
        </div>
      </div>

      <?php if ($role === "player" || can_admin($role)): ?>
        <div class="col-12 col-xl-8">
          <div class="card-soft p-4">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
              <div>
                <div class="fw-semibold"><?= can_admin($role) ? "Edycja profilu zawodnika" : "Mój profil zawodnika" ?></div>
                <div class="text-muted small">
                  <?php if ($profilePlayer): ?>
                    Status profilu: <?= h($profilePlayer["status"] ?? "pending") ?>,
                    status statystyk: <?= h($profilePlayer["stats_status"] ?? "pending") ?>
                  <?php else: ?>
                    Uzupełnij profil, aby trafił do zatwierdzenia.
                  <?php endif; ?>
                </div>
              </div>
              <?php if (can_admin($role) && !$profilePlayer): ?>
                <div class="text-muted small">Wybierz zawodnika z listy niżej.</div>
              <?php endif; ?>
            </div>

            <?php if (!can_admin($role) || $profilePlayer): ?>
              <form class="row g-2" method="post" action="actions/player_profile_save.php">
                <?= csrf_input() ?>
                <input type="hidden" name="player_id" value="<?= h($profilePlayer["id"] ?? 0) ?>">

                <div class="col-12 col-md-6">
                  <label class="filter-label">Imię</label>
                  <input class="form-control" name="first_name" value="<?= h($profilePlayer["first_name"] ?? $user["first_name"] ?? "") ?>" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="filter-label">Nazwisko</label>
                  <input class="form-control" name="last_name" value="<?= h($profilePlayer["last_name"] ?? $user["last_name"] ?? "") ?>" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="filter-label">Kraj</label>
                  <input class="form-control" name="country" value="<?= h($profilePlayer["country"] ?? "") ?>">
                </div>
                <div class="col-12 col-md-6">
                  <label class="filter-label">Akademia</label>
                  <input class="form-control" name="academy" value="<?= h($profilePlayer["academy"] ?? "") ?>">
                </div>
                <div class="col-12 col-md-4">
                  <label class="filter-label">Rocznik</label>
                  <input class="form-control" type="number" name="birth_year" min="1990" max="<?= (int)date("Y") ?>" value="<?= h($profilePlayer["birth_year"] ?? "") ?>">
                </div>
                <div class="col-12 col-md-4">
                  <label class="filter-label">Pozycja</label>
                  <input class="form-control" name="position" value="<?= h($profilePlayer["position"] ?? "") ?>">
                </div>
                <div class="col-12 col-md-2">
                  <label class="filter-label">Noga</label>
                  <input class="form-control" name="foot" value="<?= h($profilePlayer["foot"] ?? "") ?>">
                </div>
                <div class="col-12 col-md-2">
                  <label class="filter-label">Wzrost</label>
                  <input class="form-control" type="number" name="height_cm" min="0" max="250" value="<?= h($profilePlayer["height_cm"] ?? "") ?>">
                </div>

                <div class="col-12 mt-2">
                  <div class="fw-semibold">Statystyki</div>
                </div>
                <?php render_stat_inputs($profilePlayer); ?>

                <div class="col-12">
                  <button class="btn btn-success pill w-100" type="submit">
                    <?= can_admin($role) ? "Zapisz i zatwierdź" : "Wyślij do potwierdzenia" ?>
                  </button>
                </div>
              </form>
              <?php if ($role === "player" && $profilePlayer): ?>
                <form class="row g-2 mt-3 pt-3 border-top" method="post" action="actions/player_review_request.php">
                  <?= csrf_input() ?>
                  <div class="col-12 col-md-8">
                    <label class="filter-label">E-mail trenera/skauta do potwierdzenia</label>
                    <input class="form-control" type="email" name="reviewer_email" placeholder="np. trener@club.test" required>
                  </div>
                  <div class="col-12 col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-success pill w-100" type="submit">Wyślij prośbę</button>
                  </div>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php if (can_review_players($role)): ?>
      <?php
        $pendingPlayers = array_filter(fetch_player_rows(true), function($p) use ($role, $userId) {
          $isPending = ($p["status"] ?? "") !== "approved" || ($p["stats_status"] ?? "") !== "approved";
          if (!$isPending) return false;
          if (can_admin($role)) return true;
          $requestedTo = (int)($p["review_requested_to"] ?? 0);
          return $requestedTo === 0 || $requestedTo === $userId;
        });
      ?>
      <div class="card-soft p-4 mt-3">
        <div class="fw-semibold mb-3">Dane zawodników do potwierdzenia</div>
        <?php if (!$pendingPlayers): ?>
          <div class="text-muted">Brak zgłoszeń do sprawdzenia.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Zawodnik</th><th>Profil</th><th>Statystyki</th><th>Prośba</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($pendingPlayers as $p): ?>
                <tr>
                  <td><?= h(($p["first_name"] ?? "") . " " . ($p["last_name"] ?? "")) ?></td>
                  <td><?= h($p["status"] ?? "") ?></td>
                  <td><?= h($p["stats_status"] ?? "") ?></td>
                  <td><?= !empty($p["review_requested_at"]) ? h($p["review_requested_at"]) : "—" ?></td>
                  <td class="text-end">
                    <form class="d-inline" method="post" action="actions/player_review.php">
                      <?= csrf_input() ?>
                      <input type="hidden" name="player_id" value="<?= (int)$p["id"] ?>">
                      <button class="btn btn-sm btn-success pill" name="decision" value="approve">Potwierdź</button>
                      <button class="btn btn-sm btn-outline-danger pill" name="decision" value="reject">Odrzuć</button>
                    </form>
                    <?php if (can_admin($role)): ?>
                      <a class="btn btn-sm btn-outline-secondary pill" href="account.php?player_id=<?= (int)$p["id"] ?>">Edytuj</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (can_admin($role)): ?>
      <?php
        $pendingStaff = $pdo->query("
          SELECT id, email, role, verification_status, first_name, last_name, created_at
          FROM users
          WHERE role IN ('coach', 'scout', 'skaut')
            AND verification_status = 'pending'
          ORDER BY created_at ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        $users = $pdo->query("SELECT id, email, role, verification_status, first_name, last_name, created_at FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $players = fetch_player_rows(true);
      ?>

      <div class="card-soft p-4 mt-3">
        <div class="fw-semibold mb-3">Admin - konta trenerow i skautow do potwierdzenia</div>
        <?php if (!$pendingStaff): ?>
          <div class="text-muted">Brak kont oczekujacych na potwierdzenie.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Uzytkownik</th><th>E-mail</th><th>Rola</th><th>Data</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($pendingStaff as $row): ?>
                <tr>
                  <td><?= h(($row["first_name"] ?? "") . " " . ($row["last_name"] ?? "")) ?></td>
                  <td><?= h($row["email"] ?? "") ?></td>
                  <td><?= h(role_label(normalize_role((string)$row["role"]))) ?></td>
                  <td><?= h($row["created_at"] ?? "") ?></td>
                  <td class="text-end">
                    <form class="d-inline" method="post" action="admin_user_verification.php">
                      <?= csrf_input() ?>
                      <input type="hidden" name="user_id" value="<?= (int)$row["id"] ?>">
                      <button class="btn btn-sm btn-success pill" name="decision" value="approve">Potwierdz</button>
                      <button class="btn btn-sm btn-outline-danger pill" name="decision" value="reject">Odrzuc</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="card-soft p-4 mt-3">
        <div class="fw-semibold mb-3">Admin - użytkownicy</div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>ID</th><th>Użytkownik</th><th>E-mail</th><th>Rola</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
              <tr>
                <td><?= (int)$row["id"] ?></td>
                <td><?= h(($row["first_name"] ?? "") . " " . ($row["last_name"] ?? "")) ?></td>
                <td><?= h($row["email"] ?? "") ?></td>
                <td>
                  <form class="d-flex gap-2" method="post" action="actions/admin_user_update.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$row["id"] ?>">
                    <select class="form-select form-select-sm" name="role">
                      <?php foreach (["admin", "coach", "scout", "player", "agent"] as $r): ?>
                        <option value="<?= h($r) ?>" <?= normalize_role((string)$row["role"]) === $r ? "selected" : "" ?>><?= h(role_label($r)) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-success pill" type="submit">Zapisz</button>
                  </form>
                </td>
                <td>
                  <div class="small"><?= h(verification_label((string)($row["verification_status"] ?? "approved"))) ?></div>
                  <?php if (role_requires_admin_verification((string)$row["role"]) && (int)$row["id"] !== $userId): ?>
                    <form class="d-flex gap-2 mt-1" method="post" action="admin_user_verification.php">
                      <?= csrf_input() ?>
                      <input type="hidden" name="user_id" value="<?= (int)$row["id"] ?>">
                      <button class="btn btn-sm btn-outline-success pill" name="decision" value="approve">Potwierdz</button>
                      <button class="btn btn-sm btn-outline-danger pill" name="decision" value="reject">Odrzuc</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if ((int)$row["id"] !== $userId): ?>
                    <form method="post" action="actions/admin_user_delete.php" onsubmit="return confirm('Na pewno usunąć to konto i powiązane dane?');">
                      <?= csrf_input() ?>
                      <input type="hidden" name="user_id" value="<?= (int)$row["id"] ?>">
                      <button class="btn btn-sm btn-outline-danger pill" type="submit">Usuń</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card-soft p-4 mt-3">
        <div class="fw-semibold mb-3">Admin - zawodnicy</div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>ID</th><th>Zawodnik</th><th>Status</th><th>Statystyki</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($players as $p): ?>
              <tr>
                <td><?= (int)$p["id"] ?></td>
                <td><?= h(($p["first_name"] ?? "") . " " . ($p["last_name"] ?? "")) ?></td>
                <td><?= h($p["status"] ?? "") ?></td>
                <td><?= h($p["stats_status"] ?? "") ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-success pill" href="account.php?player_id=<?= (int)$p["id"] ?>">Edytuj</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
