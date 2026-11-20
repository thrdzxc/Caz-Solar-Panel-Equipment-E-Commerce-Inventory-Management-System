<?php
// Consistent, local SVG icons keep navigation independent of external icon services.
function caz_navigation_icon(string $name): string {
    $paths = [
        'Home'=>'<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>',
        'Overview'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'CCTV'=>'<path d="m3 6 15-2 3 7-15 3Z M9 14v4h8v3 M18 6l3-1 M7 9l5-1"/>',
        'Solar'=>'<path d="M5 11h14l2 9H3Z M8 11l-1 9 M16 11l1 9 M4 16h16 M12 2v2 M3 5l2 2 M21 5l-2 2 M8 8a4 4 0 0 1 8 0"/>',
        'About'=>'<circle cx="12" cy="12" r="9"/><path d="M12 11v6 M12 7h.01"/>',
        'Contact'=>'<path d="M20 16.5v3a2 2 0 0 1-2.2 2A18 18 0 0 1 2.5 6.2 2 2 0 0 1 4.5 4h3l1.5 4-2 2a14 14 0 0 0 7 7l2-2Z"/>',
        'Messages'=>'<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2V11.5A8.5 8.5 0 0 1 10.5 3h2a8.5 8.5 0 0 1 8.5 8.5Z M7 9h9 M7 13h6"/>',
        'Inventory'=>'<path d="m3 7 9-4 9 4v11l-9 4-9-4Z M3 7l9 4 9-4 M12 11v11 M8 5l9 4v5"/>',
        'Orders'=>'<path d="M8 4H5v17h14V4h-3 M9 10h6 M9 14h6 M9 18h3"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
        'Activity'=>'<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'Sales'=>'<path d="M3 3v18h18 M7 16v-4 M12 16V8 M17 16V5"/>',
        'Promotions'=>'<path d="m3 3 9 1 9 9-8 8-9-9Z"/><circle cx="8" cy="8" r="1"/>',
        'Store location'=>'<path d="M19 10c0 6-7 12-7 12S5 16 5 10a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'Verification'=>'<path d="m12 2 8 4v6c0 5-8 10-8 10s-8-5-8-10V6Z M8 12l3 3 5-6"/>',
        'Search'=>'<circle cx="10" cy="10" r="6.5"/><path d="m15 15 6 6"/>',
        'Cart'=>'<path d="M2 3h3l3 13h11l3-9H6 M9 21h.01 M18 21h.01"/>',
        'Account'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'Collapse'=>'<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 3v18 m7-13-4 4 4 4"/>',
        'Menu'=>'<path d="M4 6h16 M4 12h16 M4 18h16"/>',
    ];
    return '<svg class="caz-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? $paths['Overview']) . '</svg>';
}
