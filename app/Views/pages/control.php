<?php
/** @var array $settings */
/** @var array $profiles */
/** @var array|null $active_profile */
$prag       = (float) ($settings['prag_umiditate_aer']    ?? 60);
$intv       = (int)   ($settings['interval_minim_udare']  ?? 360);
$auto       = (int)   ($settings['mod_automat']           ?? 1) === 1;
$pragSol    = (int)   ($settings['prag_sol_uscat']        ?? 30);
$pragTds    = (int)   ($settings['prag_tds_maxim']        ?? 800);
$pragTApaLo = (int)   ($settings['prag_temp_apa_min']     ?? 5);
$pragTApaHi = (int)   ($settings['prag_temp_apa_max']     ?? 40);
$pragTurb   = (int)   ($settings['prag_turbiditate_maxim'] ?? 50);
$activeId   = $active_profile ? (int) $active_profile['id'] : 0;
$esc        = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<section class="page-head">
    <h1>Control</h1>
    <p class="lead">Pornește pompa manual sau lasă logica fuzzy să decidă udarea.</p>
</section>

<div class="grid grid-control">

    <article class="card" aria-labelledby="lblUda">
        <h2 id="lblUda" class="card-title"><?= icon('droplet') ?> Udă acum</h2>
        <p class="muted">Trimite o comandă manuală de udare către ESP32.</p>

        <fieldset class="seg" id="durataGrup">
            <legend>Durată</legend>
            <label class="seg-opt"><input type="radio" name="durata" value="2"> 2s</label>
            <label class="seg-opt"><input type="radio" name="durata" value="3" checked> 3s</label>
            <label class="seg-opt"><input type="radio" name="durata" value="5"> 5s</label>
            <label class="seg-opt"><input type="radio" name="durata" value="10"> 10s</label>
            <label class="seg-opt"><input type="radio" name="durata" value="15"> 15s</label>
            <label class="seg-opt"><input type="radio" name="durata" value="30"> 30s</label>
            <label class="seg-opt"><input type="radio" name="durata" value="60"> 60s</label>
        </fieldset>

        <div class="row">
            <button class="btn btn-primary" id="btnUda"><?= icon('droplet', 16) ?> Udă acum</button>
            <button class="btn btn-ghost" id="btnStop"><?= icon('square', 16) ?> Oprește</button>
        </div>
        <p class="form-msg" id="udaMsg" role="status" aria-live="polite"></p>
    </article>

    <article class="card" aria-labelledby="lblAuto">
        <h2 id="lblAuto" class="card-title"><?= icon('sparkles') ?> Mod automat (fuzzy)</h2>
        <p class="muted">Când e activ, sistemul decide singur când și cât udă.</p>

        <label class="switch">
            <input type="checkbox" id="modAutomat" <?= $auto ? 'checked' : '' ?>>
            <span class="switch-track" aria-hidden="true"></span>
            <span class="switch-text" id="modAutomatTxt"><?= $auto ? 'Activat' : 'Dezactivat' ?></span>
        </label>

        <p class="next-water">Următoarea udare estimată:
            <strong id="nextWater" class="mono">—</strong></p>
    </article>

    <article class="card card-wide" aria-labelledby="lblProfile">
        <h2 id="lblProfile" class="card-title"><?= icon('sprout') ?> Profil plantă</h2>
        <p class="muted">Alege un profil — pragurile fuzzy se setează automat. Custom păstrează configurarea ta curentă.</p>

        <div class="profile-grid" id="profileGrid" role="radiogroup" aria-label="Profil plantă">
            <?php foreach ($profiles as $p):
                $isActive = (int) $p['id'] === $activeId;
            ?>
                <button type="button" class="profile-card<?= $isActive ? ' is-active' : '' ?>"
                        data-profile-id="<?= (int) $p['id'] ?>"
                        data-sol="<?= (int) $p['sol_min'] ?>"
                        data-tds="<?= (int) $p['tds_max'] ?>"
                        data-intv="<?= (int) $p['interval_min'] ?>"
                        data-durata-max="<?= (int) $p['durata_max'] ?>"
                        data-nume="<?= $esc($p['nume']) ?>"
                        data-descriere="<?= $esc($p['descriere']) ?>"
                        role="radio" aria-checked="<?= $isActive ? 'true' : 'false' ?>">
                    <span class="profile-check" aria-hidden="true"><?= icon('check', 14) ?></span>
                    <div class="profile-icon" aria-hidden="true"><?= plantIcon($p['nume'], 36) ?></div>
                    <div class="profile-name"><?= $esc($p['nume']) ?></div>
                    <div class="profile-desc"><?= $esc($p['descriere']) ?></div>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="profile-info" id="profileInfo" aria-live="polite">
            <?php if ($active_profile): ?>
                <span class="profile-info-icon" aria-hidden="true"><?= plantIcon($active_profile['nume'], 24) ?></span>
                <b id="profInfoNume"><?= $esc($active_profile['nume']) ?></b> —
                <span id="profInfoDesc"><?= $esc($active_profile['descriere']) ?></span>
                <div class="meta">
                    <span>sol uscat &lt; <b id="profInfoSol"><?= (int) $active_profile['sol_min'] ?></b>%</span>
                    <span>TDS &le; <b id="profInfoTds"><?= (int) $active_profile['tds_max'] ?></b> ppm</span>
                    <span>interval &ge; <b id="profInfoIntv"><?= (int) $active_profile['interval_min'] ?></b> min</span>
                    <span>durată max <b id="profInfoDur"><?= (int) $active_profile['durata_max'] ?></b> s</span>
                </div>
            <?php else: ?>
                <span class="muted">Niciun profil activ — alege unul de mai sus.</span>
            <?php endif; ?>
        </div>
    </article>

    <article class="card card-wide" aria-labelledby="lblPraguri">
        <h2 id="lblPraguri" class="card-title"><?= icon('settings') ?> Praguri fuzzy</h2>
        <p class="muted small" style="margin-bottom:1rem">
            Pragurile se aplică <b>instant</b> la salvare — următoarea evaluare fuzzy
            (la max 30s, când ESP32 trimite o citire nouă) folosește noile valori.
            Mod manual funcționează oricând, indiferent de praguri.
            Vezi efectul în <i>Decizie fuzzy curentă</i> din partea de jos a paginii.
        </p>

        <div class="slider">
            <label for="pragSol">Prag sol uscat
                <span class="slider-val mono"><span id="pragSolVal"><?= $pragSol ?></span> %</span>
            </label>
            <input type="range" id="pragSol" min="0" max="60" step="1" value="<?= $pragSol ?>">
            <p class="muted small">Sub această valoare, solul e considerat uscat → udare pornește.</p>
            <p class="warn" id="pragSolWarn" role="status" aria-live="polite"></p>
        </div>

        <div class="slider">
            <label for="pragTds">TDS maxim acceptat
                <span class="slider-val mono"><span id="pragTdsVal"><?= $pragTds ?></span> ppm</span>
            </label>
            <input type="range" id="pragTds" min="400" max="1500" step="10" value="<?= $pragTds ?>">
            <p class="muted small">Peste această valoare, apa e prea mineralizată pentru plante → udare blocată.</p>
        </div>

        <div class="slider">
            <label for="pragTurb">Turbiditate maximă acceptată
                <span class="slider-val mono"><span id="pragTurbVal"><?= $pragTurb ?></span> NTU</span>
            </label>
            <input type="range" id="pragTurb" min="10" max="200" step="5" value="<?= $pragTurb ?>">
            <p class="muted small">Peste această valoare, apa e considerată prea tulbure (mâl/sedimente) → udare blocată pentru a proteja pompa și picurătorii. Peste 200 NTU se blochează automat (hard-coded).</p>
        </div>

        <div class="slider">
            <label for="pragTApaLo">Temperatură apă minimă
                <span class="slider-val mono"><span id="pragTApaLoVal"><?= $pragTApaLo ?></span> °C</span>
            </label>
            <input type="range" id="pragTApaLo" min="1" max="20" step="1" value="<?= $pragTApaLo ?>">
            <p class="muted small">Sub această valoare, apa stresează rădăcinile → udare blocată.</p>
        </div>

        <div class="slider">
            <label for="pragTApaHi">Temperatură apă maximă
                <span class="slider-val mono"><span id="pragTApaHiVal"><?= $pragTApaHi ?></span> °C</span>
            </label>
            <input type="range" id="pragTApaHi" min="25" max="50" step="1" value="<?= $pragTApaHi ?>">
            <p class="muted small">Peste această valoare, apa e prea caldă pentru rădăcini → udare blocată.</p>
        </div>

        <div class="slider">
            <label for="pragUm">Prag umiditate aer (legacy)
                <span class="slider-val mono"><span id="pragUmVal"><?= $prag ?></span> %</span>
            </label>
            <input type="range" id="pragUm" min="0" max="100" step="1" value="<?= $prag ?>">
            <p class="muted small">Folosit pentru reducerea durată când aerul e foarte umed.</p>
            <p class="warn" id="pragUmWarn" role="status" aria-live="polite"></p>
        </div>

        <div class="slider">
            <label for="intvMin">Interval minim între udări
                <span class="slider-val mono"><span id="intvMinVal"><?= $intv ?></span> min</span>
            </label>
            <input type="range" id="intvMin" min="0" max="1440" step="15" value="<?= $intv ?>">
            <p class="muted small">Timp minim obligatoriu între două udări consecutive.</p>
            <p class="warn" id="intvWarn" role="status" aria-live="polite"></p>
        </div>

        <div class="row">
            <button class="btn btn-primary" id="btnSalveaza">Salvează pragurile</button>
            <span class="form-msg" id="setMsg" role="status" aria-live="polite"></span>
        </div>
    </article>

    <article class="card card-wide" aria-labelledby="lblFuzzy">
        <h2 id="lblFuzzy" class="card-title"><?= icon('cpu') ?> Decizie fuzzy curentă</h2>
        <p class="muted">Vezi în dashboard pentru detaliile complete (tabel intrări + reguli active).</p>
        <p class="stat-value mono"><span id="fzDurata">—</span><span class="unit">s</span></p>
        <p id="fzExplica" class="form-msg" role="status" aria-live="polite"></p>
        <div class="row">
            <button class="btn btn-ghost" id="btnFzRefresh"><?= icon('refresh', 14) ?> Reevaluează</button>
        </div>
    </article>

</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script src="/js/control.js" defer></script>
