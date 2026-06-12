<?php
/** @var string $pageContent */
/** @var string $title */
/** @var string $active */
$active = $active ?? '';
$nav = [
    'dashboard' => ['/',         'home',     'Dashboard'],
    'control'   => ['/control',  'settings', 'Control'],
    'istoric'   => ['/istoric',  'chart',    'Istoric'],
    'raport'    => ['/raport',   'sparkles', 'Rapoarte AI'],
    'despre'    => ['/despre',   'info',     'Despre'],
];
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2D4A2B">
    <meta name="description" content="AquaSmart — sistem inteligent de irigare cu apă de ploaie">
    <meta property="og:title" content="AquaSmart — irigare inteligentă cu apă de ploaie">
    <meta property="og:description" content="Dashboard live + fuzzy Mamdani + raport AI. Proiect de licență UPT.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="/img/og.png">
    <title>AquaSmart · <?= $e($title ?? 'Dashboard') ?></title>

    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232D4A2B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M11 20A7 7 0 0 1 4 13c0-7.5 7-12 16-12-1 8-7.5 18-9 19z'/><path d='M2 21c0-3 1.85-5.36 5.08-6'/></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css">
    <script>
    /* Aplica tema inainte de prima pictare ca sa evitam flash-ul (FOUC). */
    (function(){
        try {
            var m = localStorage.getItem('aq-theme') || 'system';
            var dark = m === 'dark' || (m === 'system' && window.matchMedia &&
                       matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('theme-dark', dark);
        } catch (_) {}
    })();
    </script>
</head>
<body>
<a class="skip-link" href="#main">Sari la conținut</a>

<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="/">
            <span class="brand-leaf" aria-hidden="true"><?= icon('leaf', 22) ?></span>
            <span class="brand-name">AquaSmart</span>
        </a>
        <button class="nav-toggle" aria-expanded="false" aria-controls="mainnav" aria-label="Meniu">
            <span></span><span></span><span></span>
        </button>
        <nav id="mainnav" class="mainnav" aria-label="Navigare principală">
            <?php foreach ($nav as $key => [$href, $icoName, $label]): ?>
                <a href="<?= $e($href) ?>"
                   class="navlink<?= $active === $key ? ' is-active' : '' ?>"
                   <?= $active === $key ? 'aria-current="page"' : '' ?>>
                    <?= icon($icoName, 18) ?> <?= $e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <button class="theme-toggle" id="themeToggle" type="button"
                aria-label="Comută tema" title="Comută tema">
            <span class="theme-ico" data-ico="light"><?= icon('sun', 18) ?></span>
            <span class="theme-ico" data-ico="dark"><?= icon('moon', 18) ?></span>
        </button>
    </div>
</header>

<main id="main" class="wrap page">
    <?= $pageContent ?? '' ?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <span>&copy; <?= date('Y') ?> Patricia Simedre · Proiect de licență UPT</span>
        <span class="muted">Construit pentru un viitor mai sustenabil</span>
    </div>
</footer>

<script src="/js/app.js" defer></script>
</body>
</html>
