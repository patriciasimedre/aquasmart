/* AquaSmart — shared helpers */
(function () {
  "use strict";

  async function request(method, url, body) {
    const opt = { method, headers: { "Accept": "application/json" } };
    if (body !== undefined) {
      opt.headers["Content-Type"] = "application/json";
      opt.body = JSON.stringify(body);
    }
    const res = await fetch(url, opt);
    let json = {};
    try { json = await res.json(); } catch (_) { /* ignore */ }
    if (!res.ok || json.success === false) {
      throw new Error(json.error || ("Eroare HTTP " + res.status));
    }
    return json.data;
  }

  const AQ = {
    get:  (u) => request("GET", u),
    post: (u, b) => request("POST", u, b || {}),

    num(v, digits) {
      if (v === null || v === undefined || v === "") return "—";
      const n = Number(v);
      return Number.isFinite(n) ? n.toFixed(digits ?? 1) : "—";
    },

    dt(iso) {
      if (!iso) return "—";
      const d = new Date(String(iso).replace(" ", "T"));
      if (isNaN(d)) return String(iso);
      return d.toLocaleString("ro-RO", {
        day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit"
      });
    },

    msg(el, text, kind) {
      if (!el) return;
      el.textContent = text;
      el.className = "form-msg" + (kind ? " " + kind : "");
    }
  };

  window.AQ = AQ;

  // Meniu mobil
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".nav-toggle");
    const nav = document.getElementById("mainnav");
    if (btn && nav) {
      const open = nav.classList.toggle("open");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
    }
  });
})();

/* --- Tema: 2 stari (light/dark), salvata in localStorage --- */
(function () {
  "use strict";
  const KEY = "aq-theme";
  const mql = window.matchMedia ? matchMedia("(prefers-color-scheme: dark)") : null;

  function stored() { try { return localStorage.getItem(KEY); } catch (_) { return null; } }
  function save(m)  { try { localStorage.setItem(KEY, m); } catch (_) {} }

  // Tema curenta: alegerea explicita din storage, altfel ce zice sistemul.
  function resolve() {
    const s = stored();
    if (s === "light" || s === "dark") return s;
    return mql && mql.matches ? "dark" : "light";
  }

  function apply(m) {
    document.documentElement.classList.toggle("theme-dark", m === "dark");
    const btn = document.getElementById("themeToggle");
    if (btn) {
      const txt = m === "dark" ? "Comută la tema clară" : "Comută la tema întunecată";
      btn.title = txt;
      btn.setAttribute("aria-label", txt);
    }
  }

  document.addEventListener("click", function (e) {
    if (!e.target.closest("#themeToggle")) return;
    const next = document.documentElement.classList.contains("theme-dark") ? "light" : "dark";
    save(next);
    apply(next);
  });

  // Daca utilizatorul nu a facut o alegere, urmeaza sistemul in timp real.
  if (mql) {
    const onChange = function () { if (!stored()) apply(mql.matches ? "dark" : "light"); };
    if (mql.addEventListener) mql.addEventListener("change", onChange);
    else mql.addListener(onChange);
  }

  apply(resolve());
})();
