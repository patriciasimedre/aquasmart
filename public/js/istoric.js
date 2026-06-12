/* AquaSmart — istoric & grafice (Chart.js) */
(function () {
  "use strict";
  const $ = (s) => document.querySelector(s);
  const GREEN = "#4F7942", LIME = "#90A955", DARK = "#2D4A2B", SAND = "#C9A227", SKY = "#3F89B5";
  let chartClima = null, chartSol = null, chartApa = null;

  function baseOpts(y1Label, y2Label) {
    return {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      scales: {
        y:  { position: "left",  title: { display: true, text: y1Label } },
        y2: { position: "right", title: { display: true, text: y2Label }, grid: { drawOnChartArea: false } },
      },
      plugins: { legend: { labels: { boxWidth: 12 } } },
    };
  }

  function line(label, data, color, axis) {
    return {
      label, data, borderColor: color, backgroundColor: color + "33",
      yAxisID: axis, tension: .3, pointRadius: 0, borderWidth: 2, fill: false,
    };
  }

  function tdsBadgeHtml(v) {
    if (v == null) return '<span class="muted">—</span>';
    if (v < 500)  return '<span class="badge badge-sm badge-ok">' + v + '</span>';
    if (v <= 800) return '<span class="badge badge-sm badge-warn">' + v + '</span>';
    return '<span class="badge badge-sm badge-bad">' + v + '</span>';
  }

  // Aceleasi praguri vizuale ca pe dashboard: <10 clara, 10-50 acceptabila, >50 tulbure.
  function turbBadgeHtml(v) {
    if (v == null) return '<span class="muted">—</span>';
    if (v < 10)  return '<span class="badge badge-sm badge-ok">'   + v + ' NTU</span>';
    if (v <= 50) return '<span class="badge badge-sm badge-warn">' + v + ' NTU</span>';
    return '<span class="badge badge-sm badge-bad">' + v + ' NTU</span>';
  }

  function nivelHtml(p) {
    const cm  = p.nivel_cm  == null ? null : Number(p.nivel_cm).toFixed(1);
    const cat = p.nivel || "—";
    return cm == null
      ? '<span class="mono">' + cat + '</span>'
      : '<span class="mono">' + cm + ' cm</span> · <span class="muted">' + cat + '</span>';
  }

  function fillReadings(points) {
    const tb = $("#tblReadings tbody");
    if (!points.length) {
      tb.innerHTML = '<tr><td colspan="8" class="muted center">Fără citiri în interval.</td></tr>';
      return;
    }
    // afisam ultimele 100 (cele mai recente in capul tabelului)
    const slice = points.slice(-100).reverse();
    tb.innerHTML = slice.map((p) =>
      "<tr><td class='mono'>" + AQ.dt(p.t) + "</td>" +
      "<td class='mono'>" + (p.umiditate_sol == null ? "—" : p.umiditate_sol + "%") + "</td>" +
      "<td class='mono'>" + (p.umiditate_aer == null ? "—" : Number(p.umiditate_aer).toFixed(0) + "%") + "</td>" +
      "<td class='mono'>" + (p.temp_aer == null ? "—" : Number(p.temp_aer).toFixed(1) + "°C") + "</td>" +
      "<td class='mono'>" + (p.temp_apa == null ? "—" : Number(p.temp_apa).toFixed(1) + "°C") + "</td>" +
      "<td>" + nivelHtml(p) + "</td>" +
      "<td>" + tdsBadgeHtml(p.tds) + "</td>" +
      "<td>" + turbBadgeHtml(p.turbiditate) + "</td></tr>"
    ).join("");
  }

  function fillEvents(events) {
    const tb = $("#tblEvents tbody");
    if (!events || !events.length) {
      tb.innerHTML = '<tr><td colspan="5" class="muted center">Nicio udare în interval.</td></tr>';
      return;
    }
    tb.innerHTML = events.map((e) =>
      "<tr><td class='mono'>" + AQ.dt(e.timestamp_start) + "</td>" +
      "<td>" + (e.durata_secunde || 0) + "s</td>" +
      "<td><span class='badge badge-sm'>" + (e.motiv || "") + "</span></td>" +
      "<td>" + (e.nivel_inainte || "—") + "</td>" +
      "<td class='mono'>" + (e.temp_aer_inainte != null ? e.temp_aer_inainte + "°C" : "—") + "</td></tr>"
    ).join("");
  }

  async function load(range) {
    const d = await AQ.get("/api/v1/history?range=" + encodeURIComponent(range));
    const labels = d.points.map((p) => AQ.dt(p.t));

    const climaDs = [
      line("Temp. aer (°C)", d.points.map((p) => p.temp_aer), GREEN, "y"),
      line("Umiditate aer (%)", d.points.map((p) => p.umiditate_aer), LIME, "y2"),
    ];
    const solDs = [
      line("Umiditate sol (%)", d.points.map((p) => p.umiditate_sol), GREEN, "y"),
      line("Nivel rezervor (cm)", d.points.map((p) => p.nivel_cm), SKY, "y2"),
    ];
    const apaDs = [
      line("Temp. apă (°C)", d.points.map((p) => p.temp_apa), DARK, "y"),
      line("TDS (ppm)", d.points.map((p) => p.tds), SAND, "y2"),
    ];

    if (chartClima) chartClima.destroy();
    if (chartSol)   chartSol.destroy();
    if (chartApa)   chartApa.destroy();
    chartClima = new Chart($("#chartClima"), { type: "line", data: { labels, datasets: climaDs }, options: baseOpts("°C", "%") });
    chartSol   = new Chart($("#chartSol"),   { type: "line", data: { labels, datasets: solDs },   options: baseOpts("%",  "cm") });
    chartApa   = new Chart($("#chartApa"),   { type: "line", data: { labels, datasets: apaDs },   options: baseOpts("°C", "ppm") });

    fillReadings(d.points);
    fillEvents(d.events);
  }

  document.querySelectorAll(".seg-range .seg-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".seg-range .seg-btn").forEach((b) => b.classList.remove("is-active"));
      btn.classList.add("is-active");
      load(btn.dataset.range).catch((e) => console.error(e));
    });
  });

  function start() {
    if (typeof Chart === "undefined") { setTimeout(start, 120); return; }
    load("24h").catch((e) => {
      $("#tblReadings tbody").innerHTML =
        '<tr><td colspan="8" class="muted center">Eroare: ' + e.message + "</td></tr>";
    });
  }
  start();
})();
