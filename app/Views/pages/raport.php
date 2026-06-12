<?php
/** @var array $recente */
$esc = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<section class="page-head">
    <h1>Rapoarte AI</h1>
    <p class="lead">Sumar generat la cerere despre o perioadă, cu recomandări.</p>
</section>

<div class="grid grid-raport">

    <article class="card card-wide" aria-labelledby="lblGen">
        <h2 id="lblGen" class="card-title"><?= icon('sparkles') ?> Generează raport</h2>
        <div class="row">
            <label for="raportRange" class="muted">Perioadă:</label>
            <select id="raportRange" class="select">
                <option value="24h">Ultimele 24 ore</option>
                <option value="7d" selected>Ultimele 7 zile</option>
                <option value="30d">Ultimele 30 zile</option>
            </select>
            <button class="btn btn-primary" id="btnRaport">Generează</button>
        </div>
        <p class="form-msg" id="raportMsg" role="status" aria-live="polite"></p>

        <div class="report-out" id="raportOut" hidden>
            <div class="report-meta">
                <span class="badge" id="raportModel">—</span>
                <span class="muted" id="raportPeriod"></span>
            </div>
            <div class="report-body" id="raportBody"></div>
        </div>
    </article>

    <aside class="card" aria-labelledby="lblRec">
        <h2 id="lblRec" class="card-title"><?= icon('library') ?> Rapoarte recente</h2>
        <?php if (empty($recente)): ?>
            <p class="muted">Niciun raport generat încă.</p>
        <?php else: ?>
            <ul class="rec-list">
                <?php foreach ($recente as $r): ?>
                    <li>
                        <span class="mono"><?= $esc(substr((string) $r['perioada_start'], 0, 10)) ?>
                            → <?= $esc(substr((string) $r['perioada_end'], 0, 10)) ?></span>
                        <span class="badge badge-sm"><?= $esc($r['model']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>

</div>

<script src="/js/raport.js" defer></script>
