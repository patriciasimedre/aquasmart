<?php
// Numele se mapeaza la SVG-uri prin plantIcon() — vezi app/Support/icons.php.
$plantProfiles = [
    ['nume' => 'Suculente/Cactus', 'sol' => 15, 'tds' => 600, 'intv' => 720, 'dur' => 3],
    ['nume' => 'Plante interior',  'sol' => 35, 'tds' => 700, 'intv' => 360, 'dur' => 10],
    ['nume' => 'Legume',           'sol' => 45, 'tds' => 500, 'intv' => 240, 'dur' => 30],
    ['nume' => 'Flori',            'sol' => 40, 'tds' => 600, 'intv' => 300, 'dur' => 15],
    ['nume' => 'Răsaduri',         'sol' => 50, 'tds' => 400, 'intv' => 180, 'dur' => 5],
    ['nume' => 'Custom',           'sol' => '—', 'tds' => '—', 'intv' => '—', 'dur' => '—'],
];
?>
<section class="page-head">
    <h1>Despre</h1>
    <p class="lead">Sistem IoT inteligent de irigare cu apă de ploaie.</p>
</section>

<div class="grid grid-despre">

    <article class="card card-wide">
        <h2 class="card-title"><?= icon('leaf') ?> Concept</h2>
        <p>AquaSmart colectează apa de ploaie într-un rezervor de 15&nbsp;L și o
        folosește pentru irigarea automată a câtorva ghivece. Decizia de udare e
        luată de un controller fuzzy <strong>Mamdani</strong> pe baza umidității
        solului, aerului, temperaturii, nivelului apei și calității apei
        (TDS + turbiditate), cu blocare crisp pentru ploaie și prognoză.</p>
    </article>

    <article class="card">
        <h2 class="card-title"><?= icon('plug') ?> Hardware</h2>
        <ul class="kv">
            <li><span>MCU</span><b>ESP32 CH340C</b></li>
            <li><span>Aer</span><b>DHT22</b></li>
            <li><span>Apă</span><b>DS18B20</b></li>
            <li><span>Sol</span><b>Capacitiv v2.0.0</b></li>
            <li><span>Nivel</span><b>HC-SR04</b></li>
            <li><span>TDS</span><b>TDS Meter V1.0</b></li>
            <li><span>Turbiditate</span><b>TS-300B</b></li>
            <li><span>Ploaie</span><b>MH-RD</b></li>
            <li><span>Pompă</span><b>DC 3-6V + releu</b></li>
            <li><span>Afișaj</span><b>OLED 0.96" I²C</b></li>
        </ul>
    </article>

    <article class="card">
        <h2 class="card-title"><?= icon('layers') ?> Stack</h2>
        <ul class="kv">
            <li><span>Frontend</span><b>HTML · CSS · JS · Chart.js</b></li>
            <li><span>Backend</span><b>PHP 8.2 · PDO · REST</b></li>
            <li><span>DB</span><b>Azure MySQL Flexible</b></li>
            <li><span>Cloud</span><b>Azure App Service</b></li>
            <li><span>AI</span><b>Anthropic Claude</b></li>
            <li><span>Meteo</span><b>OpenWeatherMap</b></li>
        </ul>
    </article>

    <article class="card card-wide">
        <h2 class="card-title"><?= icon('cpu') ?> Logica fuzzy Mamdani</h2>

        <p>Decizia automată se face în <strong>două etape</strong>: blocare crisp (oprire
        imediată dacă plouă, rezervor gol, TDS/turbiditate/temperatură în afara limitelor
        sau prognoză ploaie &gt;50%) urmată de inferență fuzzy cu 5 intrări × 21 reguli.</p>

        <div class="table-wrap">
            <table class="fuzzy-vars">
                <thead>
                    <tr><th>Intrare</th><th>Univers</th><th>Termeni</th></tr>
                </thead>
                <tbody>
                    <tr><td><b>Umiditate sol</b></td><td class="mono">0-100 %</td><td>USCAT · MEDIU · UMED</td></tr>
                    <tr><td>Umiditate aer</td><td class="mono">0-100 %</td><td>SCĂZUTĂ · MEDIE · RIDICATĂ</td></tr>
                    <tr><td>Temperatură aer</td><td class="mono">-20…50 °C</td><td>RECE · TEMPERATĂ · CALDĂ</td></tr>
                    <tr><td>Nivel rezervor</td><td class="mono">crisp</td><td>GOL · PARȚIAL · PLIN</td></tr>
                    <tr><td>TDS apă</td><td class="mono">0-2000 ppm</td><td>BUN · ACCEPTABIL · RAU</td></tr>
                </tbody>
            </table>
        </div>

        <p class="muted small" style="margin-top:.8rem">
            AND = min · agregare = max · defuzzificare = centroid pe 0-300 s,
            apoi snap la {0, 15, 30, 60, 120, 180, 300} s.
        </p>
    </article>

    <article class="card card-wide">
        <h2 class="card-title"><?= icon('sprout') ?> Profile de plante</h2>

        <div class="table-wrap">
            <table class="profiles-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Profil</th>
                        <th class="num">Sol uscat &lt;</th>
                        <th class="num">TDS max</th>
                        <th class="num">Interval min</th>
                        <th class="num">Durată max</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plantProfiles as $p): ?>
                        <tr>
                            <td class="plant-ic"><?= plantIcon($p['nume'], 22) ?></td>
                            <td><b><?= $p['nume'] ?></b></td>
                            <td class="num"><?= $p['sol'] ?><?= is_numeric($p['sol'])  ? ' %'  : '' ?></td>
                            <td class="num"><?= $p['tds'] ?><?= is_numeric($p['tds'])  ? ' ppm' : '' ?></td>
                            <td class="num"><?= $p['intv']?><?= is_numeric($p['intv']) ? ' min' : '' ?></td>
                            <td class="num"><?= $p['dur'] ?><?= is_numeric($p['dur'])  ? ' s'   : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="muted small" style="margin-top:.8rem">
            Surse: FAO Paper 56 (1998), USDA-NRCS, RHS, Mihăescu — „Bazele horticulturii" (2018),
            Capraro et al. — „Fuzzy Logic Controller for Drip Irrigation" (IEEE, 2015).
        </p>
    </article>

    <article class="card card-wide">
        <h2 class="card-title"><?= icon('map') ?> Arhitectură</h2>
        <div class="arch-svg-wrap">
        <svg class="arch-svg" viewBox="0 0 780 380" role="img"
             aria-label="Diagrama arhitecturii: senzori → ESP32 → Azure App Service → Azure MySQL, plus OpenWeatherMap și Claude">
            <defs>
                <marker id="ah" viewBox="0 0 10 10" refX="9" refY="5"
                        markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                    <path class="ah" d="M0,0 L10,5 L0,10 z"></path>
                </marker>
            </defs>

            <g>
                <rect class="chip" x="16"  y="60"  width="170" height="30" rx="8"></rect>
                <text class="sub" x="101" y="79" text-anchor="middle">DHT22 (aer)</text>
                <rect class="chip" x="16"  y="98"  width="170" height="30" rx="8"></rect>
                <text class="sub" x="101" y="117" text-anchor="middle">DS18B20 (apă)</text>
                <rect class="chip" x="16"  y="136" width="170" height="30" rx="8"></rect>
                <text class="sub" x="101" y="155" text-anchor="middle">Capacitiv sol v2.0.0</text>
                <rect class="chip" x="16"  y="174" width="170" height="30" rx="8"></rect>
                <text class="sub" x="101" y="193" text-anchor="middle">HC-SR04 (nivel)</text>
                <rect class="chip" x="16"  y="212" width="170" height="30" rx="8"></rect>
                <text class="sub" x="101" y="231" text-anchor="middle">TDS Meter V1.0</text>
                <rect class="chip" x="16"  y="250" width="170" height="30" rx="8"></rect>
                <text class="sub" x="101" y="269" text-anchor="middle">TS-300B (turbiditate)</text>
                <rect class="chip" x="16"  y="288" width="170" height="30" rx="8"></rect>
                <text class="sub" x="101" y="307" text-anchor="middle">MH-RD (ploaie)</text>

                <path class="flow" d="M186,75  L246,180"></path>
                <path class="flow" d="M186,113 L246,183"></path>
                <path class="flow" d="M186,151 L246,185"></path>
                <path class="flow" d="M186,189 L246,188"></path>
                <path class="flow" d="M186,227 L246,191"></path>
                <path class="flow" d="M186,265 L246,193"></path>
                <path class="flow" d="M186,303 L246,196" marker-end="url(#ah)"></path>
            </g>

            <rect class="core" x="250" y="150" width="120" height="70" rx="12"></rect>
            <text class="lbl-i" x="310" y="182" text-anchor="middle">ESP32</text>
            <text class="lbl-i" x="310" y="202" text-anchor="middle" style="font-size:11px">+ OLED</text>

            <path class="flow" d="M370,185 L432,185" marker-end="url(#ah)"></path>
            <text class="flow-l" x="401" y="178" text-anchor="middle">HTTPS REST</text>

            <rect class="box" x="440" y="150" width="160" height="70" rx="12"></rect>
            <text class="lbl" x="520" y="180" text-anchor="middle">Azure App Service</text>
            <text class="sub" x="520" y="200" text-anchor="middle">PHP 8.2 · fuzzy Mamdani</text>

            <path class="flow" d="M600,185 L652,185" marker-start="url(#ah)" marker-end="url(#ah)"></path>
            <text class="flow-l" x="626" y="178" text-anchor="middle">PDO</text>

            <rect class="box" x="660" y="150" width="104" height="70" rx="12"></rect>
            <text class="lbl" x="712" y="182" text-anchor="middle">Azure</text>
            <text class="lbl" x="712" y="200" text-anchor="middle">MySQL</text>

            <rect class="box" x="440" y="54" width="150" height="40" rx="10"></rect>
            <text class="sub" x="515" y="79" text-anchor="middle">OpenWeatherMap</text>
            <rect class="box" x="610" y="54" width="120" height="40" rx="10"></rect>
            <text class="sub" x="670" y="79" text-anchor="middle">Claude API</text>
            <path class="flow" d="M515,94 L515,150" marker-end="url(#ah)"></path>
            <path class="flow" d="M670,94 L560,150" marker-end="url(#ah)"></path>

            <path class="flow" d="M310,220 L310,290" marker-end="url(#ah)"></path>
            <rect class="box" x="250" y="290" width="120" height="46" rx="10"></rect>
            <text class="sub" x="310" y="317" text-anchor="middle">Pompă + releu</text>
            <path class="flow" d="M370,313 L432,313" marker-end="url(#ah)"></path>
            <rect class="chip" x="440" y="292" width="220" height="42" rx="10"></rect>
            <text class="sub" x="550" y="317" text-anchor="middle">Rezervor 15 L → furtun → ghivece</text>
        </svg>
        </div>
    </article>

    <article class="card card-wide author">
        <h2 class="card-title"><?= icon('user') ?> Autor</h2>
        <p><strong>Patricia Simedre</strong><br>
        Inginerie în Informatică · Universitatea Politehnica Timișoara<br>
        Proiect de licență · 2026</p>
    </article>

</div>
