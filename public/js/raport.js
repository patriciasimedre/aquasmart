/* AquaSmart — generare raport AI */
(function () {
  "use strict";
  const $ = (s) => document.querySelector(s);

  function esc(s) {
    return String(s)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  }

  // Markdown minimal & SIGUR: escape întâi, apoi titluri / bold.
  function render(md) {
    return esc(md)
      .replace(/^##\s?(.*)$/gm, "<h2>$1</h2>")
      .replace(/^#\s?(.*)$/gm, "<h1>$1</h1>")
      .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
      .replace(/_(.+?)_/g, "<em>$1</em>");
  }

  const btn = $("#btnRaport");
  btn.addEventListener("click", async () => {
    const msg = $("#raportMsg"), out = $("#raportOut");
    const range = $("#raportRange").value;
    btn.disabled = true;
    AQ.msg(msg, "Se generează raportul… (poate dura câteva secunde)");
    try {
      const d = await AQ.post("/api/v1/ai/report", { range });
      $("#raportModel").textContent = d.model + (d.cached ? " · cache" : "");
      $("#raportPeriod").textContent = d.perioada.start + " → " + d.perioada.end;
      $("#raportBody").innerHTML = render(d.continut);
      out.hidden = false;
      AQ.msg(msg, "Raport generat.", "ok");
    } catch (e) {
      AQ.msg(msg, e.message, "err");
    } finally {
      btn.disabled = false;
    }
  });
})();
