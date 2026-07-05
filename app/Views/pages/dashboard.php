<section class="page-head">
    <h1>Monitorizare live</h1>
    <p class="lead">Starea curentă a sistemului de irigare, actualizată automat.</p>
    <div class="statusbar">
        <span class="sysdot" id="sysStatus" data-state="unknown" role="status" aria-live="polite">
            <span class="dot"></span><span class="txt">Conectare…</span>
        </span>
        <span class="muted" id="freshness" aria-live="polite"></span>
        <button class="btn btn-ghost btn-sm" id="btnRefresh" title="Forțează actualizarea acum">
            <?= icon('refresh', 14) ?> Actualizează
        </button>
    </div>
</section>

<!-- RANDUL 1: STARE CRITICA -->
<div class="grid-row1">
    <article class="card" aria-labelledby="lblSol">
        <h2 id="lblSol" class="card-title"><?= icon('sprout') ?> Umiditate sol</h2>
        <div class="circ-wrap">
            <svg class="circ-progress" viewBox="0 0 100 100" aria-hidden="true">
                <circle class="circ-bg"  cx="50" cy="50" r="42"></circle>
                <circle class="circ-val" cx="50" cy="50" r="42" id="solRing"></circle>
            </svg>
            <div class="circ-text">
                <div>
                    <div class="val"><span data-field="umiditate_sol" class="skeleton">··</span>%</div>
                    <div class="lbl" id="solLbl">se încarcă…</div>
                </div>
            </div>
        </div>
        <p class="card-foot muted center">Senzor capacitiv v2.0.0</p>
    </article>

    <article class="card card-reservoir" aria-labelledby="lblNivel">
        <h2 id="lblNivel" class="card-title"><?= icon('droplet') ?> Nivel rezervor</h2>
        <div class="reservoir" aria-hidden="true">
            <div class="reservoir-water" id="resWater" style="height:0%"></div>
            <div class="reservoir-label" id="resLabel">—</div>
        </div>
        <div class="reservoir-meta">
            <span class="cm"><span id="nivelCm" class="skeleton">··</span> cm</span>
            <span class="stat" id="nivelText">necunoscut</span>
        </div>
    </article>

    <article class="card" aria-labelledby="lblPump">
        <h2 id="lblPump" class="card-title"><?= icon('droplet') ?> Stare pompă</h2>
        <div class="pump-state" id="pumpState">
            <div class="pump-dot" aria-hidden="true"><?= icon('droplet', 28) ?></div>
            <div class="pump-label" id="pumpLabel">Așteptare</div>
            <div class="pump-countdown" id="pumpCountdown"></div>
        </div>
        <p class="card-foot muted center">Releu + pompă DC</p>
    </article>
</div>

<!-- RANDUL 2: SENZORI -->
<div class="grid-row2">
    <article class="card stat" aria-labelledby="lblTAer">
        <h2 id="lblTAer" class="card-title"><?= icon('thermo') ?> Temperatură aer</h2>
        <p class="stat-value mono"><span data-field="temp_aer" class="skeleton">··</span><span class="unit">°C</span></p>
        <p class="card-foot muted">DHT22</p>
    </article>

    <article class="card stat" aria-labelledby="lblUm">
        <h2 id="lblUm" class="card-title"><?= icon('wind') ?> Umiditate aer</h2>
        <p class="stat-value mono"><span data-field="umiditate_aer" class="skeleton">··</span><span class="unit">%</span></p>
        <p class="card-foot muted" id="umAerNote">DHT22</p>
    </article>

    <article class="card stat" aria-labelledby="lblTApa">
        <h2 id="lblTApa" class="card-title"><?= icon('waves') ?> Temperatură apă</h2>
        <p class="stat-value mono">
            <span data-field="temp_apa" class="skeleton">··</span><span class="unit">°C</span>
            <span class="warn-inline" id="tApaWarn" hidden>⚠</span>
        </p>
        <p class="card-foot muted" id="tApaNote">DS18B20</p>
    </article>

    <article class="card stat" aria-labelledby="lblTds">
        <h2 id="lblTds" class="card-title"><?= icon('flask') ?> Calitate apă (TDS)</h2>
        <p class="stat-value mono"><span data-field="tds" class="skeleton">···</span><span class="unit">ppm</span></p>
        <p class="card-foot"><span class="badge skeleton" id="tdsBadge">·····</span></p>
    </article>

    <article class="card stat" aria-labelledby="lblTurb">
        <h2 id="lblTurb" class="card-title"><?= icon('eye') ?> Claritate apă</h2>
        <p class="stat-value mono"><span data-field="turbiditate" class="skeleton">···</span><span class="unit">NTU</span></p>
        <p class="card-foot"><span class="badge skeleton" id="turbBadge">·····</span></p>
    </article>
</div>

<!-- RANDUL 3: CONTEXT -->
<div class="grid-row3">
    <article class="card" aria-labelledby="lblPloaie">
        <h2 id="lblPloaie" class="card-title"><?= icon('rain') ?> Ploaie</h2>
        <p class="stat-value"><span class="badge skeleton" id="ploaieBadge">·····</span></p>
        <p class="card-foot muted">Senzor MH-RD</p>
    </article>

    <article class="card card-weather" aria-labelledby="lblMeteo">
        <h2 id="lblMeteo" class="card-title"><?= icon('cloud-sun') ?> Prognoză meteo</h2>
        <p class="weather-main"><span id="wTemp" class="mono skeleton">··</span> <span id="wDesc" class="muted">—</span></p>
        <p class="card-foot">Ploaie 3h: <strong id="wProb3h">—</strong> · 24h: <strong id="wRain">—</strong> · <span id="wCity" class="muted"></span></p>
    </article>

    <article class="card card-last" aria-labelledby="lblLast">
        <h2 id="lblLast" class="card-title"><?= icon('clock') ?> Ultima udare</h2>
        <p class="stat-value mono skeleton" id="lastEvent">·····</p>
        <p class="card-foot muted" id="lastEventMeta"></p>
    </article>
</div>

<!-- RANDUL 4: DECIZIE FUZZY LIVE -->
<div class="grid-row4">
    <article class="card fuzzy-card" aria-labelledby="lblFuzzyLive">
        <div class="fuzzy-headline">
            <div>
                <h2 id="lblFuzzyLive" class="card-title"><?= icon('cpu') ?> Decizie fuzzy curentă</h2>
            </div>
            <div class="fuzzy-decision-text" id="fuzzyDecisionText">…</div>
        </div>
        <p class="fuzzy-explanation" id="fuzzyExplanation">Se evaluează…</p>

        <details>
            <summary>Intrări fuzzy &amp; apartenență</summary>
            <div class="table-wrap">
                <table class="fuzzy-table" id="fuzzyTable">
                    <thead>
                        <tr><th>Senzor</th><th>Valoare</th><th>Termen activ</th><th class="num">Apartenență</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </details>

        <details>
            <summary>Reguli active</summary>
            <ul class="fuzzy-rules" id="fuzzyRules"></ul>
        </details>

        <div class="row">
            <button class="btn btn-ghost btn-sm" id="btnFzReeval"><?= icon('refresh', 14) ?> Reevaluează</button>
            <span class="muted small" id="fuzzyAge"></span>
        </div>
    </article>
</div>

<script src="/js/dashboard.js" defer></script>
