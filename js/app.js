(function () {
  let data = window.ScoutHubData?.players || [];
  let watchedIds = new Set();
  let compareIds = new Set();
  const canComparePlayers = window.__CURRENT_ROLE__ === "scout";

  function qs(sel) { return document.querySelector(sel); }
  function qsa(sel) { return Array.from(document.querySelectorAll(sel)); }

  function getAgeFromBirthYear(birthYear) {
    const y = new Date().getFullYear();
    return Math.max(0, y - birthYear);
  }

  function getQueryParam(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }

  function escHtml(v) {
    return String(v ?? "").replace(/[&<>"']/g, (m) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[m]));
  }

  async function loadWatchlistIds() {
    try {
      const res = await fetch("actions/watchlist_ids.php", { credentials: "same-origin" });
      if (!res.ok) return;

      const json = await res.json().catch(() => null);
      if (!json || !json.ok) return;

      watchedIds = new Set((json.ids || []).map(Number));
    } catch (_) {}
  }

  async function loadPlayersData() {
    try {
      const res = await fetch("actions/players_json.php", {
        headers: { "Accept": "application/json" },
        credentials: "same-origin"
      });
      const json = await res.json().catch(() => null);
      if (res.ok && json?.ok && Array.isArray(json.players)) {
        data = json.players;
      }
    } catch (_) {}
  }

  function watchBtnHtml(playerId, withText = false) {
    const saved = watchedIds.has(Number(playerId));
    const btnClass = `btn pill js-watch ${saved ? "btn-success" : "btn-outline-success"}`;
    const icon = saved ? "bi-bookmark-check-fill" : "bi-bookmark-plus";
    const label = saved ? "Obserwowany" : "Obserwuj";

    return `
      <button class="${btnClass}" data-player-id="${Number(playerId)}" type="button">
        <i class="bi ${icon}"></i>
        ${withText ? `<span class="ms-2">${label}</span>` : ``}
      </button>
    `;
  }

  function compareBtnHtml(playerId, withText = false) {
    if (!canComparePlayers) return "";

    const selected = compareIds.has(Number(playerId));
    const btnClass = `btn pill js-compare ${selected ? "btn-success" : "btn-outline-success"}`;
    const icon = selected ? "bi-check2-square" : "bi-square";
    const label = selected ? "W porównaniu" : "Porównaj";

    return `
      <button class="${btnClass}" data-player-id="${Number(playerId)}" type="button">
        <i class="bi ${icon}"></i>
        ${withText ? `<span class="ms-2">${label}</span>` : ``}
      </button>
    `;
  }

  function approvedBadgeHtml(player) {
    if (player?.status !== "approved" && player?.statsStatus !== "approved") return "";
    return `<span class="badge badge-approved pill"><i class="bi bi-patch-check-fill"></i> Approved</span>`;
  }

  function renderPlayers(list) {
    const root = qs("#playersList");
    if (!root) return;

    root.innerHTML = list.map(p => {
      const age = getAgeFromBirthYear(p.birthYear);
      return `
        <a href="player.php?id=${p.id}" class="card-soft p-3 d-flex align-items-center gap-3 text-decoration-none text-reset">
          <div class="player-avatar">
            ${escHtml(p.firstName?.[0] || "")}${escHtml(p.lastName?.[0] || "")}
          </div>
          <div class="flex-grow-1">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <div class="fw-semibold">${escHtml(p.firstName)} ${escHtml(p.lastName)}</div>
              ${approvedBadgeHtml(p)}
              <span class="badge badge-soft pill">Ocena: ${Number(p.stats?.rating ?? 0).toFixed(1)}</span>
            </div>
            <div class="text-muted small">
              ${escHtml(p.position)} • ${escHtml(p.country)} • ${age} lat • ${escHtml(p.heightCm)} cm • Noga: ${escHtml(p.foot)}
            </div>
            <div class="text-muted small">
              Akademia: ${escHtml(p.academy)} • Rocznik: ${escHtml(p.birthYear)}
            </div>
          </div>
          <div class="d-flex gap-2">
            ${compareBtnHtml(p.id, false)}
            ${watchBtnHtml(p.id, false)}
            <span class="btn btn-outline-secondary pill">
              <i class="bi bi-chevron-right"></i>
            </span>
          </div>
        </a>
      `;
    }).join("");

    bindWatchButtons();
    bindCompareButtons();
    renderComparePanel();
  }

  function applyFilters() {
    const q = (qs("#q")?.value || "").toLowerCase().trim();
    const country = (qs("#fCountry")?.value || "").trim();
    const position = (qs("#fPosition")?.value || "").trim();
    const academy = (qs("#fAcademy")?.value || "").trim();
    const foot = (qs("#fFoot")?.value || "").trim();

    const ageMin = Number(qs("#fAgeMin")?.value || 0);
    const ageMax = Number(qs("#fAgeMax")?.value || 0);
    const heightMin = Number(qs("#fHeightMin")?.value || 0);
    const heightMax = Number(qs("#fHeightMax")?.value || 0);

    const filtered = data.filter(p => {
      const name = `${p.firstName} ${p.lastName}`.toLowerCase();
      const okQ = !q || name.includes(q) || String(p.academy || "").toLowerCase().includes(q);

      const okCountry = !country || p.country === country;
      const okPos = !position || p.position === position;
      const okAcad = !academy || p.academy === academy;
      const okFoot = !foot || p.foot === foot;

      const age = getAgeFromBirthYear(p.birthYear);
      const okAge = (!ageMin || age >= ageMin) && (!ageMax || age <= ageMax);

      const h = Number(p.heightCm || 0);
      const okHeight = (!heightMin || h >= heightMin) && (!heightMax || h <= heightMax);

      return okQ && okCountry && okPos && okFoot && okAcad && okAge && okHeight;
    });

    renderPlayers(filtered);

    const counter = qs("#resultsCount");
    if (counter) counter.textContent = filtered.length;
  }

  function initPlayersPage() {
    if (!qs("#playersList")) return;

    const uniq = (arr) => Array.from(new Set(arr)).sort((a,b)=>a.localeCompare(b,"pl"));
    const countries = uniq(data.map(p => p.country));
    const positions = uniq(data.map(p => p.position));
    const academies = uniq(data.map(p => p.academy));
    const feet = uniq(data.map(p => p.foot));

    function fillSelect(id, values) {
      const el = qs(id);
      if (!el) return;
      el.innerHTML = `<option value="">Wszystkie</option>` + values.map(v => `<option value="${escHtml(v)}">${escHtml(v)}</option>`).join("");
    }

    fillSelect("#fCountry", countries);
    fillSelect("#fPosition", positions);
    fillSelect("#fAcademy", academies);
    fillSelect("#fFoot", feet);

    qsa("#filtersForm input, #filtersForm select").forEach(el => {
      el.addEventListener("input", applyFilters);
      el.addEventListener("change", applyFilters);
    });

    qs("#btnReset")?.addEventListener("click", () => {
      qs("#filtersForm")?.reset();
      applyFilters();
    });

    applyFilters();
  }

function initPlayerPage() {
  const root = qs("#playerDetails");
  if (!root) return;

  const id = Number(getQueryParam("id") || 0);
  const p = data.find(x => x.id === id) || data[0];
  const age = getAgeFromBirthYear(p.birthYear);

  const isGK = (p.position || "").toLowerCase().includes("bramkarz");
  const email = (p.email || "").trim();
  const toEmail = encodeURIComponent(email);

  root.innerHTML = `
    <div class="card-soft p-4">
      <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
        <div class="player-avatar" style="width:84px;height:84px;border-radius:26px;font-size:22px;">
          ${escHtml(p.firstName?.[0] || "")}${escHtml(p.lastName?.[0] || "")}
        </div>

        <div class="flex-grow-1">
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <h1 class="h4 m-0 fw-semibold">${escHtml(p.firstName)} ${escHtml(p.lastName)}</h1>
            ${approvedBadgeHtml(p)}
            <span class="badge badge-soft pill">Ocena: ${Number(p.stats?.rating ?? 0).toFixed(1)}</span>
          </div>
          <div class="text-muted mt-1">
            ${escHtml(p.position)} • ${escHtml(p.country)} • ${age} lat • ${escHtml(p.heightCm)} cm • Noga: ${escHtml(p.foot)}
          </div>
          <div class="text-muted small">
            Akademia: ${escHtml(p.academy)} • Rocznik: ${escHtml(p.birthYear)}
            ${email ? ` • Kontakt: ${escHtml(email)}` : ``}
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
          ${watchBtnHtml(p.id, true)}
          ${compareBtnHtml(p.id, true)}

          <a class="btn btn-success pill ${email ? "" : "disabled"}"
             href="${email ? `messages.php?toEmail=${toEmail}` : "#"}"
             ${email ? "" : 'aria-disabled="true" tabindex="-1"'}
          >
            Napisz wiadomość
          </a>

          <a class="btn btn-outline-success pill"
             href="reports/player_pdf.php?id=${p.id}"
             target="_blank"
          >
            Pobierz raport PDF
          </a>
        </div>
      </div>
    </div>

    <div class="card-soft p-4 mt-3">
      <div class="fw-semibold mb-2">Wartość rynkowa</div>
      <div class="d-flex flex-wrap gap-4 align-items-end">
        <div>
          <div class="text-muted small">PLN</div>
          <div class="fw-semibold" id="mvPLN">—</div>
        </div>
        <div>
          <div class="text-muted small">EUR (kurs NBP)</div>
          <div class="fw-semibold" id="mvEUR">Ładowanie…</div>
          <div class="text-muted small" id="mvRateInfo"></div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-12">
        <div class="card-soft p-4 h-100">
          <div class="fw-semibold mb-2">Statystyki</div>

          <div class="row g-3 mb-3">
            <div class="col-12 col-lg-6">
              <div class="kpi h-100">
                <div class="text-muted small mb-2">Profil (0–100)</div>
                <canvas id="playerRadarChart" height="240"></canvas>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="kpi h-100">
                <div class="text-muted small mb-2">Wolumen (wartości)</div>
                <canvas id="playerBarChart" height="240"></canvas>
              </div>
            </div>
          </div>

          <div class="row g-2">
            <div class="col-6"><div class="kpi"><div class="text-muted small">Mecze</div><div class="fw-semibold">${p.stats?.matches ?? "-"}</div></div></div>
            <div class="col-6"><div class="kpi"><div class="text-muted small">Minuty</div><div class="fw-semibold">${p.stats?.minutes ?? "-"}</div></div></div>

            <div class="col-6"><div class="kpi"><div class="text-muted small">Gole</div><div class="fw-semibold">${p.stats?.goals ?? "-"}</div></div></div>
            <div class="col-6"><div class="kpi"><div class="text-muted small">Asysty</div><div class="fw-semibold">${p.stats?.assists ?? "-"}</div></div></div>

            ${isGK ? `
              <div class="col-6"><div class="kpi"><div class="text-muted small">Obrony</div><div class="fw-semibold">${p.stats?.saves ?? "-"}</div></div></div>
              <div class="col-6"><div class="kpi"><div class="text-muted small">Czyste konta</div><div class="fw-semibold">${p.stats?.cleanSheets ?? "-"}</div></div></div>

              <div class="col-6"><div class="kpi"><div class="text-muted small">Stracone</div><div class="fw-semibold">${p.stats?.conceded ?? "-"}</div></div></div>
              <div class="col-6"><div class="kpi"><div class="text-muted small">Celność podań</div><div class="fw-semibold">${p.stats?.passAcc != null ? p.stats.passAcc + "%" : "-"}</div></div></div>
            ` : `
              <div class="col-6"><div class="kpi"><div class="text-muted small">Strzały</div><div class="fw-semibold">${p.stats?.shots ?? "-"}</div></div></div>
              <div class="col-6"><div class="kpi"><div class="text-muted small">Celne</div><div class="fw-semibold">${p.stats?.shotsOnTarget ?? "-"}</div></div></div>

              <div class="col-6"><div class="kpi"><div class="text-muted small">Podania</div><div class="fw-semibold">${p.stats?.passes ?? "-"}</div></div></div>
              <div class="col-6"><div class="kpi"><div class="text-muted small">Celność podań</div><div class="fw-semibold">${p.stats?.passAcc != null ? p.stats.passAcc + "%" : "-"}</div></div></div>

              <div class="col-6"><div class="kpi"><div class="text-muted small">Kluczowe podania</div><div class="fw-semibold">${p.stats?.keyPasses ?? "-"}</div></div></div>
              <div class="col-6"><div class="kpi"><div class="text-muted small">Dośrodkowania</div><div class="fw-semibold">${p.stats?.crosses ?? "-"}</div></div></div>

              <div class="col-6"><div class="kpi"><div class="text-muted small">Odbiory</div><div class="fw-semibold">${p.stats?.tackles ?? "-"}</div></div></div>
              <div class="col-6"><div class="kpi"><div class="text-muted small">Przechwyty</div><div class="fw-semibold">${p.stats?.interceptions ?? "-"}</div></div></div>

              <div class="col-6"><div class="kpi"><div class="text-muted small">Pojedynki</div><div class="fw-semibold">${p.stats?.duels ?? "-"}</div></div></div>
              <div class="col-6"><div class="kpi"><div class="text-muted small">Wygrane</div><div class="fw-semibold">${p.stats?.duelsWon != null ? p.stats.duelsWon + "%" : "-"}</div></div></div>

              <div class="col-6"><div class="kpi"><div class="text-muted small">Wybicia</div><div class="fw-semibold">${p.stats?.clearances ?? "-"}</div></div></div>
              <div class="col-6"><div class="kpi"><div class="text-muted small">Kartki (Ż)</div><div class="fw-semibold">${p.stats?.cardsY ?? "-"}</div></div></div>
            `}

            <div class="col-6"><div class="kpi"><div class="text-muted small">Ocena</div><div class="fw-semibold">${Number(p.stats?.rating ?? 0).toFixed(1)}</div></div></div>
            <div class="col-6"><div class="kpi"><div class="text-muted small">Kartki (C)</div><div class="fw-semibold">${p.stats?.cardsR ?? "-"}</div></div></div>
          </div>
        </div>
      </div>
    </div>
  `;

  renderPlayerCharts(p, isGK);

  bindWatchButtons();
  bindCompareButtons();

  renderMarketValue(p);
}

  function statNumber(player, key) {
    const value = Number(player?.stats?.[key]);
    return Number.isFinite(value) ? value : 0;
  }

  function playerMarketValue(player) {
    if (typeof window.computeMarketValuePLN === "function") {
      return window.computeMarketValuePLN(player);
    }
    return computeMarketValuePLN(player);
  }

  function metricValue(player, metric) {
    if (metric.type === "profile") return metric.get(player);
    if (metric.type === "stat") return statNumber(player, metric.key);
    return player?.[metric.key] ?? "";
  }

  function formatCompareValue(value, metric) {
    if (metric.format === "rating") return Number(value || 0).toFixed(1);
    if (metric.format === "percent") return `${Number(value || 0).toFixed(1)}%`;
    if (metric.format === "money") return formatPLN(Number(value || 0));
    return escHtml(value === "" || value == null ? "-" : value);
  }

  function bestCompareValue(players, metric) {
    if (!metric.highlight) return null;
    const values = players.map(p => Number(metricValue(p, metric))).filter(Number.isFinite);
    if (!values.length) return null;
    return metric.highlight === "low" ? Math.min(...values) : Math.max(...values);
  }

  function compareMetrics() {
    return [
      { label: "Pozycja", key: "position" },
      { label: "Kraj", key: "country" },
      { label: "Akademia", key: "academy" },
      { label: "Wiek", type: "profile", get: p => getAgeFromBirthYear(p.birthYear), highlight: "low" },
      { label: "Rocznik", key: "birthYear", highlight: "high" },
      { label: "Wzrost", key: "heightCm", highlight: "high" },
      { label: "Noga", key: "foot" },
      { label: "Wartość rynkowa", type: "profile", get: playerMarketValue, format: "money", highlight: "high" },
      { label: "Ocena", type: "stat", key: "rating", format: "rating", highlight: "high" },
      { label: "Mecze", type: "stat", key: "matches", highlight: "high" },
      { label: "Minuty", type: "stat", key: "minutes", highlight: "high" },
      { label: "Gole", type: "stat", key: "goals", highlight: "high" },
      { label: "Asysty", type: "stat", key: "assists", highlight: "high" },
      { label: "Strzały", type: "stat", key: "shots", highlight: "high" },
      { label: "Strzały celne", type: "stat", key: "shotsOnTarget", highlight: "high" },
      { label: "Podania", type: "stat", key: "passes", highlight: "high" },
      { label: "Celność podań", type: "stat", key: "passAcc", format: "percent", highlight: "high" },
      { label: "Kluczowe podania", type: "stat", key: "keyPasses", highlight: "high" },
      { label: "Dośrodkowania", type: "stat", key: "crosses", highlight: "high" },
      { label: "Odbiory", type: "stat", key: "tackles", highlight: "high" },
      { label: "Przechwyty", type: "stat", key: "interceptions", highlight: "high" },
      { label: "Wybicia", type: "stat", key: "clearances", highlight: "high" },
      { label: "Pojedynki", type: "stat", key: "duels", highlight: "high" },
      { label: "Wygrane pojedynki", type: "stat", key: "duelsWon", format: "percent", highlight: "high" },
      { label: "Kartki żółte", type: "stat", key: "cardsY", highlight: "low" },
      { label: "Kartki czerwone", type: "stat", key: "cardsR", highlight: "low" },
      { label: "Obrony", type: "stat", key: "saves", highlight: "high" },
      { label: "Czyste konta", type: "stat", key: "cleanSheets", highlight: "high" },
      { label: "Stracone bramki", type: "stat", key: "conceded", highlight: "low" }
    ];
  }

  function renderComparePanel() {
    if (!canComparePlayers) return;

    const count = qs("#compareCount");
    const selectedRoot = qs("#compareSelected");
    const tableRoot = qs("#compareTableRoot");
    if (!selectedRoot || !tableRoot) return;

    const players = Array.from(compareIds)
      .map(id => data.find(p => Number(p.id) === Number(id)))
      .filter(Boolean);

    if (count) count.textContent = players.length;

    if (!players.length) {
      selectedRoot.innerHTML = `<div class="compare-empty">Zaznacz zawodników przyciskiem <i class="bi bi-square"></i> na liście.</div>`;
      tableRoot.innerHTML = "";
      return;
    }

    selectedRoot.innerHTML = players.map(p => `
      <button class="compare-chip js-compare-remove" data-player-id="${Number(p.id)}" type="button">
        <span>${escHtml(p.firstName)} ${escHtml(p.lastName)}</span>
        <i class="bi bi-x-lg"></i>
      </button>
    `).join("");

    if (players.length < 2) {
      tableRoot.innerHTML = `<div class="compare-empty">Wybierz co najmniej dwóch zawodników.</div>`;
      bindCompareRemoveButtons();
      return;
    }

    const rows = compareMetrics().map(metric => {
      const best = bestCompareValue(players, metric);
      const cells = players.map(player => {
        const value = metricValue(player, metric);
        const numeric = Number(value);
        const isBest = best !== null && Number.isFinite(numeric) && numeric === best;
        return `<td class="${isBest ? "compare-best" : ""}">${formatCompareValue(value, metric)}</td>`;
      }).join("");

      return `<tr><th scope="row">${escHtml(metric.label)}</th>${cells}</tr>`;
    }).join("");

    tableRoot.innerHTML = `
      <div class="compare-table-wrap">
        <table class="compare-table">
          <thead>
            <tr>
              <th>Parametr</th>
              ${players.map(p => `
                <th>
                  <a class="compare-player-link" href="player.php?id=${Number(p.id)}">
                    <span class="compare-avatar">${escHtml(p.firstName?.[0] || "")}${escHtml(p.lastName?.[0] || "")}</span>
                    <span>${escHtml(p.firstName)} ${escHtml(p.lastName)}</span>
                  </a>
                </th>
              `).join("")}
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    `;

    bindCompareRemoveButtons();
  }

  function renderPlayerCharts(player, isGK) {
    if (!window.Chart) return;

    const s = player?.stats || {};
    const num = (x) => {
      const v = Number(x);
      return Number.isFinite(v) ? v : 0;
    };

    function toPct(value, maxValue) {
      const v = num(value);
      if (!maxValue || maxValue <= 0) return 0;
      const pct = (v / maxValue) * 100;
      return Math.max(0, Math.min(100, Math.round(pct)));
    }

    function clamp01to100(v) {
      const n = num(v);
      return Math.max(0, Math.min(100, Math.round(n)));
    }

    function invertPct(pct) {
      const n = clamp01to100(pct);
      return 100 - n;
    }

    function destroyChart(canvas) {
      if (canvas && canvas._chart) {
        canvas._chart.destroy();
        canvas._chart = null;
      }
    }

    const radarCanvas = qs("#playerRadarChart");
    if (radarCanvas) {
      destroyChart(radarCanvas);

      if (isGK) {
        const labels = [
          "Obrony",
          "Czyste konta",
          "Stracone (mniej=lepiej)",
          "Celność podań (%)",
          "Minuty",
          "Ocena"
        ];

        const limits = { saves: 150, cleanSheets: 30, conceded: 80, minutes: 3000, rating: 10 };

        const values = [
          toPct(s.saves, limits.saves),
          toPct(s.cleanSheets, limits.cleanSheets),
          invertPct(toPct(s.conceded, limits.conceded)),
          clamp01to100(s.passAcc),
          toPct(s.minutes, limits.minutes),
          toPct(s.rating, limits.rating)
        ];

        radarCanvas._chart = new Chart(radarCanvas, {
          type: "radar",
          data: {
            labels,
            datasets: [{ label: "Profil bramkarza (0–100)", data: values }]
          },
          options: {
            responsive: true,
            scales: { r: { suggestedMin: 0, suggestedMax: 100, ticks: { stepSize: 20 } } }
          }
        });
      } else {
        const labels = [
          "Gole",
          "Asysty",
          "Strzały",
          "Kluczowe podania",
          "Dośrodkowania",
          "Odbiory",
          "Przechwyty",
          "Wygrane pojedynki (%)",
          "Celność podań (%)"
        ];

        const limits = {
          goals: 20,
          assists: 20,
          shots: 80,
          keyPasses: 60,
          crosses: 120,
          tackles: 120,
          interceptions: 120
        };

        const values = [
          toPct(s.goals, limits.goals),
          toPct(s.assists, limits.assists),
          toPct(s.shots, limits.shots),
          toPct(s.keyPasses, limits.keyPasses),
          toPct(s.crosses, limits.crosses),
          toPct(s.tackles, limits.tackles),
          toPct(s.interceptions, limits.interceptions),
          clamp01to100(s.duelsWon),
          clamp01to100(s.passAcc)
        ];

        radarCanvas._chart = new Chart(radarCanvas, {
          type: "radar",
          data: {
            labels,
            datasets: [{ label: "Profil zawodnika (0–100)", data: values }]
          },
          options: {
            responsive: true,
            scales: { r: { suggestedMin: 0, suggestedMax: 100, ticks: { stepSize: 20 } } }
          }
        });
      }
    }

    const barCanvas = qs("#playerBarChart");
    if (barCanvas) {
      destroyChart(barCanvas);

      const labels = isGK
        ? ["Mecze", "Minuty", "Obrony", "Czyste konta", "Stracone"]
        : ["Mecze", "Minuty", "Podania", "Strzały", "Pojedynki"];

      const values = isGK
        ? [num(s.matches), num(s.minutes), num(s.saves), num(s.cleanSheets), num(s.conceded)]
        : [num(s.matches), num(s.minutes), num(s.passes), num(s.shots), num(s.duels)];

      barCanvas._chart = new Chart(barCanvas, {
        type: "bar",
        data: { labels, datasets: [{ label: "Statystyki (wartości)", data: values }] },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true } }
        }
      });
    }
  }

 function initWatchlistPage() {
  const root = qs("#watchlistRoot");
  if (!root) return;

  const list = data.filter(p => watchedIds.has(Number(p.id)));

  if (!list.length) {
    root.innerHTML = `
      <div class="card-soft p-4 text-center">
        <div class="fw-semibold">Nie obserwujesz jeszcze żadnych zawodników</div>
        <div class="text-muted">Wejdź w Zawodnicy i kliknij „Obserwuj”.</div>
      </div>
    `;
    return;
  }

  root.innerHTML = `
    <div class="row g-3">
      ${list.map(p => {
        const age = getAgeFromBirthYear(p.birthYear);
        return `
          <div class="col-12 col-md-6 col-xl-4">
            <a href="player.php?id=${p.id}" class="card-soft p-3 h-100 d-block text-decoration-none text-reset">
              <div class="d-flex gap-3 align-items-center">
                <div class="player-avatar">
                  ${escHtml(p.firstName?.[0] || "")}${escHtml(p.lastName?.[0] || "")}
                </div>

                <div class="flex-grow-1">
                  <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="fw-semibold">${escHtml(p.firstName)} ${escHtml(p.lastName)}</div>
                    ${approvedBadgeHtml(p)}
                  </div>
                  <div class="text-muted small">
                    ${escHtml(p.position)} • ${escHtml(p.country)} • ${age} lat
                  </div>
                  <div class="text-muted small">
                    Akademia: ${escHtml(p.academy)} • Rocznik: ${escHtml(p.birthYear)}
                  </div>
                </div>

                <div class="ms-auto">
                  ${watchBtnHtml(p.id, false)}
                </div>
              </div>
            </a>
          </div>
        `;
      }).join("")}
    </div>
  `;

  bindWatchButtons();
}

  async function toggleWatch(playerId) {
  try {
    const body = new URLSearchParams();
    body.set("player_id", String(playerId));
    body.set("csrf_token", String(window.__CSRF_TOKEN__ || ""));

    const res = await fetch("actions/watchlist_toggle.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "Accept": "application/json"
      },
      body: body.toString(),
      credentials: "same-origin"
    });

    const json = await res.json().catch(() => null);
    if (!json?.ok) return null;

    if (json.saved) watchedIds.add(Number(playerId));
    else watchedIds.delete(Number(playerId));

    return json.saved;
  } catch (_) {
    return null;
  }
}

  function bindWatchButtons() {
    qsa(".js-watch").forEach(btn => {
      if (btn._bound) return;
      btn._bound = true;

      btn.addEventListener("click", async (e) => {
        e.preventDefault();
        e.stopPropagation();

        const playerId = Number(btn.getAttribute("data-player-id") || 0);
        if (!playerId) return;

        const saved = await toggleWatch(playerId);
        if (saved === null) return;

        const withText = !!btn.querySelector("span");
        btn.outerHTML = watchBtnHtml(playerId, withText);
        bindWatchButtons();
      });
    });
  }

  function syncCompareButtons() {
    qsa(".js-compare").forEach(btn => {
      const playerId = Number(btn.getAttribute("data-player-id") || 0);
      const withText = !!btn.querySelector("span");
      btn.outerHTML = compareBtnHtml(playerId, withText);
    });
    bindCompareButtons();
  }

  function bindCompareButtons() {
    if (!canComparePlayers) return;

    qsa(".js-compare").forEach(btn => {
      if (btn._bound) return;
      btn._bound = true;

      btn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();

        const playerId = Number(btn.getAttribute("data-player-id") || 0);
        if (!playerId) return;

        if (compareIds.has(playerId)) compareIds.delete(playerId);
        else compareIds.add(playerId);

        syncCompareButtons();
        renderComparePanel();
      });
    });

    const clearBtn = qs("#compareClear");
    if (clearBtn && !clearBtn._bound) {
      clearBtn._bound = true;
      clearBtn.addEventListener("click", () => {
        compareIds.clear();
        syncCompareButtons();
        renderComparePanel();
      });
    }
  }

  function bindCompareRemoveButtons() {
    if (!canComparePlayers) return;

    qsa(".js-compare-remove").forEach(btn => {
      if (btn._bound) return;
      btn._bound = true;

      btn.addEventListener("click", () => {
        const playerId = Number(btn.getAttribute("data-player-id") || 0);
        if (!playerId) return;

        compareIds.delete(playerId);
        syncCompareButtons();
        renderComparePanel();
      });
    });
  }

  (async function boot() {
    await loadPlayersData();
    await loadWatchlistIds();
    initPlayersPage();
    initPlayerPage();
    initWatchlistPage();
  })();

})();

function computeMarketValuePLN(player) {
  if (player && typeof player.marketValuePLN === "number" && Number.isFinite(player.marketValuePLN)) {
    return Math.max(0, Math.round(player.marketValuePLN));
  }

  const s = player?.stats || {};
  const rating = Number(s.rating ?? 0);
  const matches = Number(s.matches ?? 0);
  const minutes = Number(s.minutes ?? 0);

  const base = rating * 60000;
  const bonusMatches = matches * 2000;
  const bonusMinutes = (minutes / 90) * 400;

  const value = base + bonusMatches + bonusMinutes;
  return Math.max(0, Math.round(value / 1000) * 1000);
}

function formatPLN(n) {
  return new Intl.NumberFormat("pl-PL", { style: "currency", currency: "PLN", maximumFractionDigits: 0 }).format(n);
}

function formatEUR(n) {
  return new Intl.NumberFormat("pl-PL", { style: "currency", currency: "EUR", maximumFractionDigits: 0 }).format(n);
}

// ===== MARKET VALUE + NBP EUR RATE =====
async function fetchEurPlnRate() {
  try {
    const res = await fetch("actions/nbp_eur_rate.php", {
      method: "GET",
      headers: { "Accept": "application/json" },
      credentials: "same-origin"
    });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json || !json.ok) return null;
    return json; // oczekujemy: { ok:true, rate, effectiveDate, source, cachedAt }
  } catch (_) {
    return null;
  }
}

async function renderMarketValue(player) {
  const mvPLN = computeMarketValuePLN(player);

  const elPLN = document.getElementById("mvPLN");
  const elEUR = document.getElementById("mvEUR");
  const elInfo = document.getElementById("mvRateInfo");

  if (elPLN) elPLN.textContent = formatPLN(mvPLN);
  if (!elEUR) return;

  const eur = await fetchEurPlnRate();
  const rate = Number(eur?.rate);

  if (!eur || !Number.isFinite(rate) || rate <= 0) {
    elEUR.textContent = "Brak danych";
    if (elInfo) elInfo.textContent = "Nie udało się pobrać kursu NBP.";
    return;
  }

  const mvEUR = mvPLN / rate;

  elEUR.textContent = formatEUR(mvEUR);

  const eff = eur.effectiveDate ? String(eur.effectiveDate) : "";
  const src = eur.source ? String(eur.source) : "";
  const cachedAt = eur.cachedAt ? String(eur.cachedAt) : "";

  if (elInfo) {
    elInfo.textContent = `EUR/PLN: ${rate.toFixed(4)} • ${eff}${src ? " • źródło: " + src : ""}`;
  }
}
