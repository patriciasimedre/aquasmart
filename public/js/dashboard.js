/* AquaSmart — dashboard live (4 randuri + decizie fuzzy) */
(function () {
  "use strict";
  const $ = (s) => document.querySelector(s);

  // ---- helpere generice -------------------------------------------------
  let lastOkAt = 0, lastSeenAge = null, snapshotTimer = null;
  let fuzzyLastOkAt = 0;

  function put(el, text) {
    if (!el) return;
    const wasSkel = el.classList.contains("skeleton");
    el.classList.remove("skeleton");
    if (!wasSkel && el.textContent !== String(text)) {
      el.classList.remove("flash");
      void el.offsetWidth;
      el.classList.add("flash");
    }
    el.textContent = text;
  }

  function setField(name, val, digits) {
    put(document.querySelector('[data-field="' + name + '"]'), AQ.num(val, digits));
  }

  function fmtAge(sec) {
    if (sec == null) return "—";
    if (sec < 60)   return Math.round(sec) + "s";
    if (sec < 3600) return Math.round(sec / 60) + " min";
    return Math.round(sec / 3600) + " h";
  }

  function setStatus(state, extra) {
    const s = $("#sysStatus");
    if (!s) return;
    s.dataset.state = state;
    const label = { online: "ESP32 online", stale: "Date vechi", offline: "ESP32 offline" }[state];
    s.querySelector(".txt").textContent = label + (extra ? " · " + extra : "");
  }

  function tickFreshness() {
    const f = $("#freshness");
    if (!f || !lastOkAt) return;
    const sinceFetch = Math.round((Date.now() - lastOkAt) / 1000);
    if (lastSeenAge == null) {
      f.textContent = "Actualizat acum " + sinceFetch + "s";
    } else {
      f.textContent = "Ultima citire acum " + fmtAge(lastSeenAge + sinceFetch) +
                      " · actualizat acum " + sinceFetch + "s";
    }
  }

  // ---- inel SVG (umiditate sol) ----------------------------------------
  const RING_R = 42;
  const RING_C = 2 * Math.PI * RING_R;

  function setSoilRing(pct) {
    const ring = $("#solRing");
    const lbl = $("#solLbl");
    if (!ring) return;
    const v = Math.max(0, Math.min(100, Number(pct) || 0));
    ring.setAttribute("stroke-dasharray", (RING_C * v / 100) + " " + RING_C);
    ring.classList.remove("is-ok", "is-warn", "is-bad");
    if (v < 30)      { ring.classList.add("is-bad");  if (lbl) lbl.textContent = "USCAT — necesar udare"; }
    else if (v < 55) { ring.classList.add("is-warn"); if (lbl) lbl.textContent = "Mediu"; }
    else             { ring.classList.add("is-ok");   if (lbl) lbl.textContent = "Umed — OK"; }
  }

  // ---- rezervor (cilindru animat cu procentaj REAL + cm + litri + status) -
  // Calibrare galeata: 15L total, senzor HC-SR04 in capac la ~30cm de fund;
  // util ~28cm (28 cm = apa pana la senzor = ~14L) — ajusteaza daca difera.
  const BUCKET_DEPTH    = 28;   // cm — adancime utila de la senzor la fund
  const BUCKET_USABLE_L = 14;   // L  — capacitate utila pana la senzor
  function setReservoir(nivel, cm) {
    let pct = 0, litri = null;
    if (cm != null && !isNaN(cm)) {
      const ratio = 1 - Number(cm) / BUCKET_DEPTH;
      pct   = Math.max(0, Math.min(100, ratio * 100));
      litri = Math.max(0, ratio * BUCKET_USABLE_L);
    } else {
      const map = { plin: 95, partial: 50, gol: 8 };
      pct = map[nivel] ?? 0;
    }
    const w = $("#resWater");
    if (w) w.style.height = pct.toFixed(1) + "%";
    const lbl = $("#resLabel");
    if (lbl) lbl.textContent = nivel ? nivel.toUpperCase() : "—";
    // #nivelCm contine doar numarul (HTML-ul are " cm" ca text static langa)
    put($("#nivelCm"), cm == null ? "—" : Number(cm).toFixed(1));
    // Status: "gol · ~2.0 L (14%)"
    const litriTxt = litri != null ? ` · ~${litri.toFixed(1)} L` : "";
    put($("#nivelText"),
      nivel ? `${nivel}${litriTxt} (${pct.toFixed(0)}%)` : "necunoscut");
  }

  // ---- starea pompei + countdown ---------------------------------------
  let pumpEndTs = null;

  // Mapeaza motivul tehnic din ESP32 la text user-friendly.
  const REFUSE_MESSAGES = {
    rezervor_gol:   "rezervor gol — pompa protejată",
    ploaie_activa:  "plouă acum",
  };

  function setPumpFromCommand(cmd, lastRefused) {
    const wrap = $("#pumpState");
    if (!wrap) return;
    const running = !!(cmd && cmd.tip === "udare" && cmd.status === "executing");
    wrap.classList.toggle("is-on", running);
    wrap.classList.toggle("is-refused", !running && !!lastRefused);
    if (running) {
      $("#pumpLabel").textContent = "Udare în curs";
    } else if (lastRefused) {
      const r = REFUSE_MESSAGES[lastRefused.refused_reason] || lastRefused.refused_reason;
      $("#pumpLabel").textContent = "Refuzată: " + r;
    } else {
      $("#pumpLabel").textContent = "Așteptare";
    }
    if (running && cmd.durata && cmd.timestamp) {
      const start = new Date(String(cmd.timestamp).replace(" ", "T"));
      pumpEndTs = start.getTime() + Number(cmd.durata) * 1000;
    } else {
      pumpEndTs = null;
      $("#pumpCountdown").textContent = "";
    }
  }

  function tickPumpCountdown() {
    if (!pumpEndTs) return;
    const remaining = Math.max(0, Math.round((pumpEndTs - Date.now()) / 1000));
    const el = $("#pumpCountdown");
    if (el) el.textContent = remaining > 0 ? remaining + "s rămași" : "se termină…";
  }

  // ---- TDS badge --------------------------------------------------------
  function setTdsBadge(tds) {
    const b = $("#tdsBadge");
    if (!b) return;
    b.classList.remove("skeleton", "badge-ok", "badge-warn", "badge-bad");
    if (tds == null) { b.textContent = "—"; return; }
    const n = Number(tds);
    if (n < 500)      { b.classList.add("badge-ok");   b.textContent = "BUNĂ"; }
    else if (n <= 800){ b.classList.add("badge-warn"); b.textContent = "ACCEPTABILĂ"; }
    else              { b.classList.add("badge-bad");  b.textContent = "SLABĂ"; }
  }

  // ---- Turbiditate badge (TS-300B) -------------------------------------
  // Praguri vizuale aliniate cu blocarea fuzzy:
  //   <10 NTU  = clară (apă potabilă-curată)
  //   10-50    = acceptabilă (sedimente fine, OK pentru irigare)
  //   >50      = tulbure (peste pragul implicit -> probabil blocata in fuzzy)
  function setTurbBadge(turb) {
    const b = $("#turbBadge");
    if (!b) return;
    b.classList.remove("skeleton", "badge-ok", "badge-warn", "badge-bad");
    if (turb == null) { b.textContent = "—"; return; }
    const n = Number(turb);
    if (n < 10)       { b.classList.add("badge-ok");   b.textContent = "CLARĂ"; }
    else if (n <= 50) { b.classList.add("badge-warn"); b.textContent = "ACCEPTABILĂ"; }
    else              { b.classList.add("badge-bad");  b.textContent = "TULBURE"; }
  }

  // ---- snapshot principal ----------------------------------------------
  async function tickSnapshot() {
    try {
      const d = await AQ.get("/api/v1/dashboard/snapshot");
      const r = d.reading;

      if (r) {
        setField("temp_aer", r.temp_aer);
        setField("temp_apa", r.temp_apa);
        setField("umiditate_aer", r.umiditate_aer);
        setField("umiditate_sol", r.umiditate_sol);
        setField("tds", r.tds, 0);
        setField("turbiditate", r.turbiditate, 0);
        setSoilRing(r.umiditate_sol);
        setReservoir(r.nivel, r.nivel_cm);
        setTdsBadge(r.tds);
        setTurbBadge(r.turbiditate);

        // Indicator temp apa out-of-range
        const tApa = Number(r.temp_apa);
        const tMin = d.settings && d.settings.prag_temp_apa_min != null
          ? Number(d.settings.prag_temp_apa_min) : 5;
        const tMax = d.settings && d.settings.prag_temp_apa_max != null
          ? Number(d.settings.prag_temp_apa_max) : 40;
        const ofr = !isNaN(tApa) && (tApa < tMin || tApa > tMax);
        const warnEl = $("#tApaWarn");
        if (warnEl) warnEl.hidden = !ofr;
        $("#tApaNote").textContent = ofr
          ? "DS18B20 · în afara intervalului " + tMin + "-" + tMax + "°C"
          : "DS18B20";

        // Indicator rain risk pentru umiditate aer
        const ua = Number(r.umiditate_aer);
        $("#umAerNote").textContent = !isNaN(ua) && ua > 75
          ? "DHT22 · ↑ risc ploaie" : "DHT22";

        const pb = $("#ploaieBadge");
        if (pb) {
          pb.classList.remove("skeleton", "badge-ok", "badge-warn", "badge-bad");
          const rain = Number(r.ploaie) === 1;
          pb.textContent = rain ? "Plouă acum" : "Fără ploaie";
          pb.classList.add(rain ? "badge-warn" : "badge-ok");
        }
      }

      const w = d.weather || {};
      put($("#wTemp"), w.temp != null ? w.temp + " °C" : "—");
      $("#wDesc").textContent = w.descriere || (w.available ? "" : "indisponibil");
      $("#wRain").textContent = w.rain_24h === true ? "DA" : (w.rain_24h === false ? "NU" : "necunoscut");
      const prob = w.prob_3h != null ? Math.round(w.prob_3h * 100) : null;
      const wp = $("#wProb3h");
      if (wp) {
        wp.textContent = prob != null ? prob + "%" : "necunoscut";
        wp.style.color = prob != null && prob > 50 ? "var(--danger)" : "";
      }
      $("#wCity").textContent = w.oras || "";

      const ev = d.last_event;
      if (ev) {
        put($("#lastEvent"), AQ.dt(ev.timestamp_start));
        $("#lastEventMeta").textContent =
          (ev.durata_secunde || 0) + "s · motiv: " + (ev.motiv || "—");
      } else {
        put($("#lastEvent"), "fără udări încă");
      }

      setPumpFromCommand(d.command, d.last_refused);

      lastOkAt = Date.now();
      lastSeenAge = (d.system && d.system.last_seen_age != null) ? Number(d.system.last_seen_age) : null;

      if (!r) setStatus("offline");
      else    setStatus(d.system && d.system.online ? "online" : "stale");
      tickFreshness();
    } catch (e) {
      setStatus("offline");
      const f = $("#freshness");
      if (f) f.textContent =
        "Sistemul nu primește date de la ESP32 — verifică alimentarea și conexiunea WiFi a dispozitivului.";
    }
  }

  // ---- decizie fuzzy live ----------------------------------------------
  const SENZORI = [
    { key: "sol",   label: "Umiditate sol",   src: "umiditate_sol", suf: "%"  },
    { key: "aer",   label: "Umiditate aer",   src: "umiditate_aer", suf: "%"  },
    { key: "temp",  label: "Temperatură aer", src: "temp_aer",      suf: "°C" },
    { key: "nivel", label: "Nivel rezervor", src: "nivel",         suf: ""   },
    { key: "tds",   label: "TDS",            src: "tds",           suf: " ppm"},
  ];

  function dominantTerm(mu) {
    if (!mu) return ["—", 0];
    let bestTerm = "—", bestVal = -1;
    Object.entries(mu).forEach(([t, v]) => { if (v > bestVal) { bestVal = v; bestTerm = t; } });
    return [bestTerm, bestVal];
  }

  function renderFuzzy(d) {
    const fz = d.fuzzy;
    const decizieMap = {
      nu_uda:    { text: "NU UDA",      cls: "is-skip" },
      uda_scurt: { text: "UDĂ "  + d.durata_finala + "s", cls: "" },
      uda_mediu: { text: "UDĂ "  + d.durata_finala + "s", cls: "" },
      uda_lung:  { text: "UDĂ "  + d.durata_finala + "s", cls: "" },
    };
    const dec = fz.blocat
      ? { text: "BLOCAT", cls: "is-block" }
      : (decizieMap[fz.decizie] || { text: "—", cls: "" });

    const txtEl = $("#fuzzyDecisionText");
    txtEl.textContent = dec.text;
    txtEl.className = "fuzzy-decision-text " + dec.cls;

    $("#fuzzyExplanation").textContent = d.explicatie || fz.explicatie || "";

    // Tabel intrari fuzzy
    const tbody = $("#fuzzyTable tbody");
    if (fz.blocat) {
      tbody.innerHTML = '<tr><td colspan="4" class="muted center">Inferența fuzzy nu rulează — sistemul e blocat crisp.</td></tr>';
    } else {
      tbody.innerHTML = SENZORI.map((s) => {
        const [term, val] = dominantTerm(fz.memberships ? fz.memberships[s.key] : null);
        const raw = d.intrari ? d.intrari[s.src] : null;
        const rawTxt = raw == null ? "—" :
          (typeof raw === "number" ? raw.toFixed(s.suf === "°C" ? 1 : 0) : raw) + s.suf;
        return "<tr><td>" + s.label + "</td>" +
               "<td class='num'>" + rawTxt + "</td>" +
               "<td class='term'>" + term + "</td>" +
               "<td class='num'>" + (val * 1).toFixed(2) + "</td></tr>";
      }).join("");
    }

    // Reguli active
    const reguli = fz.reguli_active || fz.reguli || [];
    const ul = $("#fuzzyRules");
    ul.innerHTML = reguli.length
      ? reguli.map((r) =>
          "<li><span class='rid'>R" + r.regula + "</span> → " +
          "<span class='iesire'>" + r.iesire + "</span>" +
          "<span class='taria'>tărie " + (r.taria * 1).toFixed(2) + "</span></li>"
        ).join("")
      : "<li class='muted'>Nicio regulă activă.</li>";

    fuzzyLastOkAt = Date.now();
  }

  async function tickFuzzy() {
    try {
      const d = await AQ.get("/api/v1/fuzzy/preview");
      renderFuzzy(d);
    } catch (e) {
      $("#fuzzyExplanation").textContent = "Eroare la evaluare: " + e.message;
    }
  }

  function tickFuzzyAge() {
    const el = $("#fuzzyAge");
    if (!el || !fuzzyLastOkAt) return;
    el.textContent = "Reevaluat acum " + Math.round((Date.now() - fuzzyLastOkAt) / 1000) + "s";
  }

  // ---- start ------------------------------------------------------------
  $("#btnRefresh").addEventListener("click", () => { if (snapshotTimer) clearInterval(snapshotTimer); tickSnapshot(); snapshotTimer = setInterval(tickSnapshot, 4000); });
  $("#btnFzReeval").addEventListener("click", tickFuzzy);

  tickSnapshot();
  snapshotTimer = setInterval(tickSnapshot, 4000);   // 4s (era 7s)
  tickFuzzy();
  setInterval(tickFuzzy, 15000);                    // 15s (era 30s)
  setInterval(tickFreshness, 1000);
  setInterval(tickPumpCountdown, 1000);
  setInterval(tickFuzzyAge, 1000);
})();
