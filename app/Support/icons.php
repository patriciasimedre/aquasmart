<?php

declare(strict_types=1);

/**
 * Helper pentru iconite SVG inline, stil Lucide (https://lucide.dev).
 * Avantaje fata de emoji:
 *   - aspect minimalist, consistent in light/dark (folosesc currentColor),
 *   - nu depind de fonturile emoji ale sistemului,
 *   - usor de adaugat noi: pune doar continutul intern al <svg>.
 *
 * Folosire in view-uri:
 *   <?= icon('home') ?>           // 20x20 implicit
 *   <?= icon('droplet', 16) ?>    // dimensiune custom
 */
if (!function_exists('icon')) {
    function icon(string $name, int $size = 20): string
    {
        static $svgs = [
            // Navigatie + status
            'home'       => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2h-14a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'settings'   => '<line x1="21" y1="4" x2="14" y2="4"/><line x1="10" y1="4" x2="3" y2="4"/><line x1="21" y1="12" x2="12" y2="12"/><line x1="8" y1="12" x2="3" y2="12"/><line x1="21" y1="20" x2="16" y2="20"/><line x1="12" y1="20" x2="3" y2="20"/><line x1="14" y1="2" x2="14" y2="6"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="16" y1="18" x2="16" y2="22"/>',
            'chart'      => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
            'sparkles'   => '<path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5z"/><path d="M19 14l.8 2.4L22 17l-2.2.6L19 20l-.8-2.4L16 17l2.2-.6z"/><path d="M5 16l.6 1.8L7 18l-1.4.4L5 20l-.6-1.8L3 18l1.4-.4z"/>',
            'info'       => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
            // Tema
            'monitor'    => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
            'sun'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
            'moon'       => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
            // Carduri dashboard / control
            'droplet'    => '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>',
            'thermo'     => '<path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/>',
            'wind'       => '<path d="M9.59 4.59A2 2 0 1 1 11 8H2"/><path d="M17.73 2.27A2.5 2.5 0 1 1 19.5 6.5H2"/><path d="M14 14a2.5 2.5 0 1 1 2.5 2.5H2"/>',
            'waves'      => '<path d="M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 12c.6.5 1.2 1 2.5 1C7 13 7 11 9.5 11c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 18c.6.5 1.2 1 2.5 1C7 19 7 17 9.5 17c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/>',
            'flask'      => '<path d="M10 2v7.31"/><path d="M14 9.3V2"/><path d="M8.5 2h7"/><path d="M14 9.3a6.5 6.5 0 1 1-4 0"/>',
            'eye'        => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'rain'       => '<path d="M16 13v5M8 13v5M12 15v5"/><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25"/>',
            'cloud-sun'  => '<path d="M12 2v2M5.22 5.22l1.42 1.42M20 12h2"/><path d="M15.97 8.03a5 5 0 1 0-6.94 6.94"/><path d="M13 22H7a4 4 0 1 1 0-8 6 6 0 1 1 11.83 1.43"/>',
            'clock'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'square'     => '<rect x="5" y="5" width="14" height="14" rx="2"/>',
            'cpu'        => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="2" x2="9" y2="4"/><line x1="15" y1="2" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="22"/><line x1="15" y1="20" x2="15" y2="22"/><line x1="20" y1="9" x2="22" y2="9"/><line x1="20" y1="14" x2="22" y2="14"/><line x1="2" y1="9" x2="4" y2="9"/><line x1="2" y1="14" x2="4" y2="14"/>',
            'refresh'    => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
            'library'    => '<path d="M4 22V4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v18"/><line x1="6" y1="6" x2="18" y2="6"/><line x1="6" y1="10" x2="18" y2="10"/><line x1="6" y1="14" x2="18" y2="14"/><line x1="6" y1="18" x2="18" y2="18"/>',
            // Despre
            'leaf'       => '<path d="M11 20A7 7 0 0 1 4 13c0-7.5 7-12 16-12-1 8-7.5 18-9 19z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/>',
            'sprout'     => '<path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z"/>',
            'plug'       => '<path d="M12 22v-5M9 7V2M15 7V2"/><path d="M6 13V8a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4z"/>',
            'layers'     => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
            'map'        => '<path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3z"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/>',
            'user'       => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'arrow-left' => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
            'check'      => '<polyline points="20 6 9 17 4 12"/>',
            // Profile plante (SVG inline, fara emoji — consistent in light/dark, fara dependinta de fonturile sistemului)
            'cactus'     => '<rect x="9" y="3" width="6" height="16" rx="3"/><path d="M9 10a3 3 0 0 1-3-3V6"/><path d="M15 13a3 3 0 0 0 3-3V9"/><line x1="7" y1="21" x2="17" y2="21"/>',
            'houseplant' => '<path d="M8 22h8l-1-5H9z"/><path d="M12 17V9"/><path d="M12 9c-2-2-5-1-5-4"/><path d="M12 9c2-2 5-1 5-4"/><path d="M12 9c0-2 1.5-3 3-3"/>',
            'vegetable'  => '<path d="M3 21s8-3 11-6a4 4 0 0 0-5-5C6 13 3 21 3 21z"/><path d="M16 8l4-4"/><path d="M18 4l2 2"/><path d="M8 16l-1.5-1.5"/><path d="M13 13l-2-2"/>',
            'flower'     => '<circle cx="12" cy="12" r="2.5"/><path d="M12 9.5C12 7 13 5 15 5s2.5 1.5 2 3.5"/><path d="M14.5 14C16.5 14 19 13 19 11s-1.5-2.5-3.5-2"/><path d="M12 14.5C12 17 11 19 9 19s-2.5-1.5-2-3.5"/><path d="M9.5 10C7.5 10 5 11 5 13s1.5 2.5 3.5 2"/><line x1="12" y1="19" x2="12" y2="22"/>',
        ];
        $body = $svgs[$name] ?? '';
        return sprintf(
            '<svg class="ic" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
            $size,
            $size,
            $body
        );
    }
}

if (!function_exists('plantIcon')) {
    /**
     * Mapeaza numele unui profil de planta la iconita SVG corespunzatoare.
     * Folosit in control.php (carduri profil) si despre.php (tabel comparativ).
     */
    function plantIcon(string $plantName, int $size = 28): string
    {
        static $map = [
            'Suculente/Cactus' => 'cactus',
            'Plante interior'  => 'houseplant',
            'Legume'           => 'vegetable',
            'Flori'            => 'flower',
            'Răsaduri'         => 'sprout',
            'Custom'           => 'settings',
        ];
        $key = $map[$plantName] ?? 'sprout';
        return icon($key, $size);
    }
}
