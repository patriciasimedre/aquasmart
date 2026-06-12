<section class="page-head">
    <h1>Istoric</h1>
    <p class="lead">Evoluția senzorilor și a udărilor în timp.</p>
</section>

<div class="seg seg-range" role="group" aria-label="Interval">
    <button class="seg-btn is-active" data-range="24h">24 ore</button>
    <button class="seg-btn" data-range="7d">7 zile</button>
    <button class="seg-btn" data-range="30d">30 zile</button>
</div>

<div class="grid grid-charts">
    <article class="card card-wide">
        <h2 class="card-title"><?= icon('thermo') ?> Temperatură &amp; umiditate aer</h2>
        <div class="chart-box"><canvas id="chartClima" height="120"></canvas></div>
    </article>

    <article class="card card-wide">
        <h2 class="card-title"><?= icon('sprout') ?> Umiditate sol &amp; nivel rezervor</h2>
        <div class="chart-box"><canvas id="chartSol" height="120"></canvas></div>
    </article>

    <article class="card card-wide">
        <h2 class="card-title"><?= icon('waves') ?> Temperatură apă &amp; TDS</h2>
        <div class="chart-box"><canvas id="chartApa" height="120"></canvas></div>
    </article>
</div>

<article class="card">
    <h2 class="card-title"><?= icon('flask') ?> Citiri senzori</h2>
    <div class="table-wrap">
        <table class="tbl" id="tblReadings">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Sol %</th>
                    <th>Aer %</th>
                    <th>Temp aer</th>
                    <th>Temp apă</th>
                    <th>Nivel</th>
                    <th>TDS</th>
                    <th>Turbiditate</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="8" class="muted center">Se încarcă…</td></tr></tbody>
        </table>
    </div>
</article>

<article class="card">
    <h2 class="card-title"><?= icon('droplet') ?> Evenimente de udare</h2>
    <div class="table-wrap">
        <table class="tbl" id="tblEvents">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Durată</th>
                    <th>Motiv</th>
                    <th>Nivel înainte</th>
                    <th>Temp. aer</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="5" class="muted center">Se încarcă…</td></tr></tbody>
        </table>
    </div>
</article>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" defer></script>
<script src="/js/istoric.js" defer></script>
