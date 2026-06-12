/* AquaSmart — control manual + setări extinse */
(function () {
  "use strict";
  const $ = (s) => document.querySelector(s);

  function durata() {
    const r = document.querySelector('input[name="durata"]:checked');
    return r ? Number(r.value) : 3;
  }

  const btnUda = $("#btnUda");
  let disabledReason = "";
  // Cache stare ultimei citiri — pentru pre-checks inainte de udare manuala.
  let lastNivel    = null;
  let lastNivelCm  = null;
  let lastUmiditSol = null;
  let lastPragSol  = null;
  let lastProfileName = null;
  let lastDurataMax = null;

  // --- Udă acum / Oprește ---
  async function sendCmd(tip) {
    const msg = $("#udaMsg");
    if (tip === "udare") {
      const d = durata();

      // Pre-check 1: rezervor gol — firmware-ul va refuza, pompa nu va porni.
      if (lastNivel === "gol") {
        const ok = window.confirm(
          "⚠ ATENȚIE: rezervorul e GOL (" +
          (lastNivelCm != null ? lastNivelCm.toFixed(1) + " cm" : "nivel necunoscut") +
          ").\n\n" +
          "ESP32 va REFUZA comanda automat pentru a proteja pompa de funcționare în gol.\n\n" +
          "Trimit oricum?"
        );
        if (!ok) return;
      }
      // Pre-check 2: solul are deja suficienta apa.
      // Pragul de "uscat" e configurabil per profil. Daca umiditatea curenta e
      // peste prag, planta nu are nevoie acum — avertizam, dar lasam userul sa
      // decida (peste prag = poate uda preventiv inainte sa se usuce).
      else if (lastUmiditSol != null && lastPragSol != null && lastUmiditSol >= lastPragSol) {
        const profilTxt = lastProfileName ? " (profil: " + lastProfileName + ")" : "";
        const ok = window.confirm(
          "ℹ Solul are " + lastUmiditSol + "% umiditate, peste pragul de „uscat" + profilTxt +
          " (≥ " + lastPragSol + "%).\n\n" +
          "Planta nu pare să aibă nevoie de apă acum. Sistemul automat NU ar uda.\n\n" +
          "Trimit oricum o udare de " + d + "s?"
        );
        if (!ok) return;
      } else if (!window.confirm("Pornești pompa pentru " + d + " secunde?")) {
        return;
      }
    }
    const body = tip === "udare" ? { tip: "udare", durata: durata() } : { tip: "oprire" };
    try {
      AQ.msg(msg, "Se trimite…");
      const c = await AQ.post("/api/v1/commands", body);
      AQ.msg(msg, "Comandă trimisă (#" + c.id + ", " + c.tip +
        (c.durata ? " " + c.durata + "s" : "") + ").", "ok");
      refreshState();
    } catch (e) {
      AQ.msg(msg, e.message, "err");
    }
  }
  btnUda.addEventListener("click", () => sendCmd("udare"));
  $("#btnStop").addEventListener("click", () => sendCmd("oprire"));

  // Selecteaza un radio name="durata" cu value egal cu val daca exista.
  // Folosit pentru a sugera durata din profilul activ (Cactus -> 3s, Legume -> 30s).
  function setDuratePreset(val) {
    if (val == null) return;
    const radios = document.querySelectorAll('input[name="durata"]');
    // Cautam cea mai mare valoare disponibila care e <= profilul.durata_max
    let best = null;
    radios.forEach((r) => {
      const v = Number(r.value);
      if (v <= val && (best === null || v > best.value)) best = { el: r, value: v };
    });
    if (best) {
      radios.forEach((r) => { r.checked = false; });
      best.el.checked = true;
    }
  }

  // --- Disable "Udă" când ESP32 offline sau o udare e deja în curs ---
  function updateControlState(d) {
    const online = !!(d.system && d.system.online);
    const cmd = d.command;
    const pumpBusy = !!(cmd && cmd.tip === "udare");

    // Cache stare pentru sendCmd
    lastNivel       = (d.reading && d.reading.nivel) || null;
    lastNivelCm     = (d.reading && d.reading.nivel_cm != null) ? Number(d.reading.nivel_cm) : null;
    lastUmiditSol   = (d.reading && d.reading.umiditate_sol != null) ? Number(d.reading.umiditate_sol) : null;
    lastPragSol     = (d.settings && d.settings.prag_sol_uscat != null) ? Number(d.settings.prag_sol_uscat) : null;
    const prof      = d.active_profile;
    lastProfileName = prof ? prof.nume : null;
    // Daca profilul s-a schimbat, sugeram noua durata implicita.
    if (prof && prof.durata_max != null && prof.durata_max !== lastDurataMax) {
      lastDurataMax = prof.durata_max;
      setDuratePreset(prof.durata_max);
    }

    if (!online) disabledReason = "ESP32 e offline — nu poți porni pompa acum.";
    else if (pumpBusy) disabledReason = "O udare e deja " +
      (cmd.status === "executing" ? "în curs" : "în așteptare") + ".";
    else disabledReason = "";

    btnUda.disabled = disabledReason !== "";
    btnUda.title = disabledReason;
    const msg = $("#udaMsg");
    if (disabledReason) AQ.msg(msg, disabledReason);
    else if (lastNivel === "gol") {
      AQ.msg(msg, "Rezervor gol — pompa nu va porni (protecție).", "err");
    } else if (lastUmiditSol != null && lastPragSol != null && lastUmiditSol >= lastPragSol) {
      AQ.msg(msg, "Solul are " + lastUmiditSol + "% (≥ " + lastPragSol + "%) — nu necesită udare.", "ok");
    } else if (msg.textContent.indexOf("offline") >= 0 || msg.textContent.indexOf("deja") >= 0
            || msg.textContent.indexOf("gol") >= 0 || msg.textContent.indexOf("necesită") >= 0) {
      AQ.msg(msg, "");
    }
  }

  // Sistemul e REACTIV (evaluează la fiecare citire ESP32, max 30s), NU programat.
  // „interval_minim" e un COOLDOWN, nu o oră fixă. Afisam asta sincer.
  function updateNextWater(d) {
    const el = $("#nextWater");
    if (!el) return;
    const ev = d.last_event;
    const intv = Number(d.settings.interval_minim_udare || 0);

    if (!d.settings.mod_automat) {
      el.textContent = "Mod manual — udare doar la cerere";
      return;
    }
    if (!d.system || !d.system.online) {
      el.textContent = "ESP32 offline — aștept conexiune";
      return;
    }
    if (!ev) {
      el.textContent = "Gata pentru prima udare (când senzorii o cer)";
      return;
    }

    const lastTs = new Date(String(ev.timestamp_start).replace(" ", "T")).getTime();
    const cooldownEnd = lastTs + intv * 60 * 1000;
    const now = Date.now();

    if (now >= cooldownEnd) {
      // Cooldown trecut — sistemul evaluează la fiecare 30s, va uda dacă senzorii o cer
      el.textContent = "Gata — evaluare la fiecare 30s, udare când senzorii o cer";
    } else {
      // Cooldown activ — câte minute mai sunt
      const remMin = Math.ceil((cooldownEnd - now) / 60000);
      if (remMin < 60) {
        el.textContent = "Cooldown ~" + remMin + " min rămase";
      } else {
        const h = Math.floor(remMin / 60);
        const m = remMin % 60;
        el.textContent = "Cooldown ~" + h + "h " + m + "min rămase";
      }
    }
  }

  function refreshState() {
    AQ.get("/api/v1/dashboard/snapshot")
      .then((d) => { updateControlState(d); updateNextWater(d); })
      .catch(() => {});
  }

  // --- Slidere: valoare live + validare soft ---
  const sliders = {
    pragSol:   { val: "#pragSolVal" },
    pragTds:   { val: "#pragTdsVal" },
    pragTurb:  { val: "#pragTurbVal" },
    pragTApaLo:{ val: "#pragTApaLoVal" },
    pragTApaHi:{ val: "#pragTApaHiVal" },
    pragUm:    { val: "#pragUmVal" },
    intvMin:   { val: "#intvMinVal" },
  };
  Object.keys(sliders).forEach((id) => {
    const el = $("#" + id);
    if (!el) return;
    el.addEventListener("input", () => { $(sliders[id].val).textContent = el.value; runValidation(); });
  });

  function runValidation() {
    const sol  = Number($("#pragSol").value);
    const um   = Number($("#pragUm").value);
    const intv = Number($("#intvMin").value);

    $("#pragSolWarn").textContent =
      sol < 15 ? "⚠ Sub 15%: sistemul va uda doar la sol foarte uscat." :
      sol > 50 ? "⚠ Peste 50%: sistemul va uda foarte des." : "";
    $("#pragUmWarn").textContent =
      um < 30  ? "⚠ Sub 30%: aerul rar va declanșa reducerea." :
      um >= 90 ? "⚠ Peste 90%: reducerea aproape niciodată activă." : "";
    $("#intvWarn").textContent =
      intv === 0 ? "⚠ 0 minute: pompa poate fi suprasolicitată (udare la fiecare citire)." :
      intv < 30  ? "⚠ Sub 30 min: udări foarte dese." : "";
  }
  runValidation();

  // --- Salvare setări (toate cele 7 câmpuri) ---
  function buildSettingsPayload() {
    return {
      prag_umiditate_aer:     Number($("#pragUm").value),
      interval_minim_udare:   Number($("#intvMin").value),
      mod_automat:            $("#modAutomat").checked,
      prag_sol_uscat:         Number($("#pragSol").value),
      prag_tds_maxim:         Number($("#pragTds").value),
      prag_turbiditate_maxim: Number($("#pragTurb").value),
      prag_temp_apa_min:      Number($("#pragTApaLo").value),
      prag_temp_apa_max:      Number($("#pragTApaHi").value),
    };
  }

  // Verifica daca pragurile salvate diverg de profilul activ (Cactus, Legume, etc.)
  // Compara doar campurile profilului: sol_min, tds_max, interval_min.
  // Returneaza numele profilului divergent sau null.
  function profilDivergent(payload) {
    if (!lastProfileName || lastProfileName === "Custom") return null;
    // Cautam cardul cu profilul activ ca sa luam valorile lui originale.
    const card = document.querySelector('.profile-card.is-active');
    if (!card) return null;
    const profSol = Number(card.dataset.sol);
    const profTds = Number(card.dataset.tds);
    const profIntv = Number(card.dataset.intv);
    if (Number(payload.prag_sol_uscat)   !== profSol ||
        Number(payload.prag_tds_maxim)   !== profTds ||
        Number(payload.interval_minim_udare) !== profIntv) {
      return lastProfileName;
    }
    return null;
  }

  $("#btnSalveaza").addEventListener("click", async () => {
    const msg = $("#setMsg");
    const payload = buildSettingsPayload();
    try {
      AQ.msg(msg, "Se salvează…");
      await AQ.post("/api/v1/settings", payload);
      // Mesaj cu context: ce inseamna salvat pentru sistem.
      AQ.msg(msg, '✓ Salvate. Aplicate la următoarea evaluare (~30s). Vezi „Decizie fuzzy curentă" mai jos.', "ok");

      // Daca pragurile diferă de profilul activ -> avertizam non-blocant.
      const divergent = profilDivergent(payload);
      if (divergent) {
        toast('Pragurile diferă de profilul „' + divergent + '". Apasă „Custom" ca să sincronizezi.', "err");
      }

      loadFuzzy();      // afișează imediat noua decizie fuzzy
      refreshState();   // re-citește snapshot pentru cooldown text
    } catch (e) {
      AQ.msg(msg, e.message, "err");
    }
  });

  const sw = $("#modAutomat");
  sw.addEventListener("change", async () => {
    $("#modAutomatTxt").textContent = sw.checked ? "Activat" : "Dezactivat";
    try { await AQ.post("/api/v1/settings", { mod_automat: sw.checked }); refreshState(); } catch (_) {}
  });

  // --- Decizie fuzzy (preview) ---
  async function loadFuzzy() {
    const exp = $("#fzExplica");
    try {
      AQ.msg(exp, "Se evaluează…");
      const d = await AQ.get("/api/v1/fuzzy/preview");
      $("#fzDurata").textContent = d.durata_finala;
      AQ.msg(exp, d.explicatie, d.durata_finala > 0 ? "ok" : null);
    } catch (e) {
      AQ.msg(exp, e.message, "err");
    }
  }
  $("#btnFzRefresh").addEventListener("click", loadFuzzy);

  // --- Toast ---
  let toastTimer = null;
  function toast(text, kind) {
    const t = $("#toast");
    if (!t) return;
    t.textContent = text;
    t.classList.toggle("is-err", kind === "err");
    t.classList.add("is-show");
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove("is-show"), 2400);
  }

  // --- Profile plantă ---
  function setProfileSliders(p) {
    // Aplica valorile noi pe slidere si actualizeaza eticheta lor.
    $("#pragSol").value     = p.sol_min;     $("#pragSolVal").textContent  = p.sol_min;
    $("#pragTds").value     = p.tds_max;     $("#pragTdsVal").textContent  = p.tds_max;
    $("#intvMin").value     = p.interval_min;$("#intvMinVal").textContent  = p.interval_min;
    runValidation();
  }

  function refreshProfileCard(card) {
    document.querySelectorAll(".profile-card").forEach((c) => {
      const active = c === card;
      c.classList.toggle("is-active", active);
      c.setAttribute("aria-checked", active ? "true" : "false");
    });
  }

  function refreshProfileInfo(card) {
    const html = $("#profileInfo");
    if (!html) return;
    const d = card.dataset; // nume, descriere, sol, tds, intv, duratamax (data-durata-max)
    const durMax = d.duratamax || "—";
    // Cloneaza SVG-ul de pe cardul activ — pastreaza icon-ul fara
    // sa duplicam logica de mapare nume→SVG in JS.
    const iconNode = card.querySelector(".profile-icon .ic");
    const iconHtml = iconNode ? iconNode.outerHTML : "";
    html.innerHTML =
      '<span class="profile-info-icon" aria-hidden="true">' + iconHtml + '</span> ' +
      '<b>' + d.nume + '</b> — ' + d.descriere +
      '<div class="meta">' +
        '<span>sol uscat &lt; <b>' + d.sol  + '</b>%</span>' +
        '<span>TDS &le; <b>'      + d.tds  + '</b> ppm</span>' +
        '<span>interval &ge; <b>' + d.intv + '</b> min</span>' +
        '<span>durată max <b>'    + durMax + '</b> s</span>' +
      '</div>';
  }

  async function activateProfile(card) {
    const id = Number(card.dataset.profileId);
    try {
      const d = await AQ.post("/api/v1/profiles/activate", { profile_id: id });
      refreshProfileCard(card);
      refreshProfileInfo(card);
      setProfileSliders({
        sol_min:      Number(d.settings.prag_sol_uscat),
        tds_max:      Number(d.settings.prag_tds_maxim),
        interval_min: Number(d.settings.interval_minim_udare),
      });
      // Sugereaza durata implicita din profilul nou-activat (data-durata-max e in card)
      const durMax = Number(card.dataset.duratamax);
      if (durMax > 0) {
        setDuratePreset(durMax);
        lastDurataMax = durMax;
      }
      toast("Profil activat: " + card.dataset.nume + " ✓");
      loadFuzzy();      // pragurile noi se vad imediat in decizia fuzzy
      refreshState();   // updateaza pre-check (prag_sol_uscat nou)
    } catch (e) {
      toast("Eroare: " + e.message, "err");
    }
  }

  document.querySelectorAll(".profile-card").forEach((card) => {
    card.addEventListener("click", () => activateProfile(card));
    card.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") { e.preventDefault(); activateProfile(card); }
    });
  });

  refreshState();
  setInterval(refreshState, 10000);
  loadFuzzy();
})();
