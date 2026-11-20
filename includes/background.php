<?php
// Replace images/caz-background.jpg to change the artwork everywhere.
// Visual effects and surface transparency are in css/background-theme.css.
$cazBackgroundPrefix = in_array(basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')), ['pages','admin'], true) ? '../' : '';
$cazBackgroundFile = __DIR__ . '/../images/caz-background.jpg';
$cazBackgroundVersion = is_file($cazBackgroundFile) ? (string)filemtime($cazBackgroundFile) : '1';
$cazCaptureEffect = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'portal.php' || (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' && basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'pages');
?>
<link rel="stylesheet" href="<?php echo $cazBackgroundPrefix; ?>css/interaction-polish.css?v=<?php echo (int)filemtime(__DIR__ . '/../css/interaction-polish.css'); ?>">
<link rel="stylesheet" href="<?php echo $cazBackgroundPrefix; ?>css/navigation-camera.css?v=<?php echo (int)filemtime(__DIR__ . '/../css/navigation-camera.css'); ?>">
<link rel="stylesheet" href="<?php echo $cazBackgroundPrefix; ?>css/visual-update.css?v=<?php echo (int)filemtime(__DIR__ . '/../css/visual-update.css'); ?>">
<div class="caz-background" aria-hidden="true">
    <img id="caz-background-image" src="<?php echo $cazBackgroundPrefix; ?>images/caz-background.jpg?v=<?php echo $cazBackgroundVersion; ?>" alt="" decoding="async" fetchpriority="high" draggable="false">
    <div class="caz-background-tint"></div>
    <div class="caz-background-glow"></div>
    <?php if ($cazCaptureEffect): ?>
    <svg class="caz-camera-effects" viewBox="0 0 1672 941" preserveAspectRatio="xMidYMid slice" focusable="false" aria-hidden="true">
        <defs>
            <radialGradient id="caz-camera-light"><stop offset="0" stop-color="#fff0e9"/><stop offset=".2" stop-color="#ff303b" stop-opacity=".95"/><stop offset=".58" stop-color="#ff122b" stop-opacity=".7"/><stop offset="1" stop-color="#ff1230" stop-opacity="0"/></radialGradient>
            <linearGradient id="caz-camera-ray" gradientUnits="userSpaceOnUse" x1="1428" y1="224" x2="1020" y2="368"><stop offset="0" stop-color="#ff5268" stop-opacity=".95"/><stop offset=".35" stop-color="#ff3550" stop-opacity=".65"/><stop offset=".7" stop-color="#ff3550" stop-opacity=".2"/><stop offset="1" stop-color="#ff3550" stop-opacity="0"/></linearGradient>
            <filter id="caz-camera-soft-edge" filterUnits="userSpaceOnUse" x="920" y="130" width="610" height="350" color-interpolation-filters="sRGB"><feGaussianBlur stdDeviation="8"/></filter>
            <filter id="caz-camera-ray-glow" filterUnits="userSpaceOnUse" x="920" y="130" width="610" height="350" color-interpolation-filters="sRGB"><feGaussianBlur stdDeviation="2"/></filter>
        </defs>
        <g class="caz-camera-scan">
            <!-- The ray fades completely before its endpoint; no triangle or hard beam edge. -->
            <path d="M1428 224 L982 381" fill="none" stroke="url(#caz-camera-ray)" stroke-width="30" stroke-linecap="round" opacity=".28" filter="url(#caz-camera-soft-edge)"/>
            <path d="M1428 224 L982 381" fill="none" stroke="url(#caz-camera-ray)" stroke-width="8" stroke-linecap="round" opacity=".52" filter="url(#caz-camera-ray-glow)"/>
            <path d="M1428 224 L982 381" fill="none" stroke="url(#caz-camera-ray)" stroke-width="2" stroke-linecap="round"/>
        </g>
        <ellipse class="caz-camera-lens-glow" cx="1428" cy="224" rx="34" ry="48" fill="url(#caz-camera-light)" transform="rotate(-17 1428 224)"/>
        <circle class="caz-camera-record-light" cx="1428" cy="224" r="9" fill="#ff1738"/><circle cx="1426" cy="222" r="3" fill="#ffe5dc"/>
    </svg>
    <?php endif; ?>
    <svg class="caz-network-effects" viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid slice" focusable="false">
        <g class="caz-signal-paths" fill="none" stroke-linecap="round">
            <path d="M-80 170 L180 240 L350 110 L540 310 L680 270" />
            <path d="M1050 -40 L1190 130 L1440 250 L1690 190" />
            <path d="M-60 790 L190 660 L390 810 L710 970 L870 1100" />
            <path d="M1010 1110 L1140 830 L1370 740 L1500 490 L1690 560" />
        </g>
        <g class="caz-signal-nodes" fill="#acf0ff">
            <circle cx="180" cy="240" r="3" /><circle cx="540" cy="310" r="3" />
            <circle cx="1190" cy="130" r="3" /><circle cx="1370" cy="740" r="3" />
            <circle cx="190" cy="660" r="3" /><circle cx="390" cy="810" r="3" />
        </g>
        <g class="caz-signal-rings" fill="none" stroke="#8addff">
            <circle cx="180" cy="240" r="16" /><circle cx="1370" cy="740" r="22" />
            <circle cx="390" cy="810" r="18" />
        </g>
    </svg>
</div>
