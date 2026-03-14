<?php
/**
 * WP Umbrella → Anyboard Data Generator
 * Wird von GitHub Actions ausgeführt.
 * API-Key kommt aus der Umgebungsvariable WP_UMBRELLA_API_KEY (GitHub Secret).
 */

$apiKey     = getenv('WP_UMBRELLA_API_KEY') ?: '';
$apiBase    = 'https://app.wp-umbrella.com/api/v1';
$outputFile = __DIR__ . '/../docs/wp-umbrella-data.json';

if (!$apiKey) {
    fwrite(STDERR, "ERROR: WP_UMBRELLA_API_KEY ist nicht gesetzt.\n");
    exit(1);
}

// --- Hilfsfunktion ---

function fetchUmbrella(string $base, string $key, string $endpoint): ?array {
    $ch = curl_init($base . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }
    return json_decode($response, true);
}

// --- Sites laden ---

$raw = fetchUmbrella($apiBase, $apiKey, '/projects')
    ?? fetchUmbrella($apiBase, $apiKey, '/sites');

if ($raw === null) {
    fwrite(STDERR, "ERROR: WP Umbrella API nicht erreichbar oder Key ungültig.\n");
    exit(1);
}

$sites = $raw['data'] ?? $raw['projects'] ?? $raw ?? [];

// --- Daten aufbereiten ---

$totalOnline  = 0;
$totalOffline = 0;
$totalUpdates = 0;
$totalErrors  = 0;
$sitesOutput  = [];

foreach ($sites as $site) {
    $name       = $site['name']               ?? $site['url']      ?? 'Unbekannt';
    $url        = $site['url']                ?? $site['home_url'] ?? '';
    $wpVersion  = $site['wordpress_version']  ?? $site['wp_version'] ?? '—';
    $phpVersion = $site['php_version']        ?? '—';

    $isUp = ($site['uptime_status']             ?? '') === 'up'
         || ($site['is_online']                 ?? null) === true
         || ($site['status']                    ?? '') === 'up'
         || ($site['monitoring']['status']      ?? '') === 'up';

    $isDown = ($site['uptime_status']           ?? '') === 'down'
           || ($site['status']                  ?? '') === 'down'
           || ($site['is_online']               ?? null) === false;

    $status = $isDown ? 'Offline' : ($isUp ? 'Online' : '?');
    if ($isUp)   $totalOnline++;
    if ($isDown) $totalOffline++;

    $pluginUpdates = (int)($site['updates']['plugins']  ?? $site['plugin_updates_count'] ?? 0);
    $themeUpdates  = (int)($site['updates']['themes']   ?? $site['theme_updates_count']  ?? 0);
    $coreUpdate    = (!empty($site['updates']['core']) || !empty($site['core_update_available'])) ? 1 : 0;
    $siteUpdates   = $pluginUpdates + $themeUpdates + $coreUpdate;
    $totalUpdates += $siteUpdates;

    $phpErrors    = (int)($site['php_errors_count'] ?? $site['errors']['count'] ?? 0);
    $totalErrors += $phpErrors;

    $displayUrl = preg_replace('#^https?://#', '', rtrim($url, '/'));

    $sitesOutput[] = [
        'name'          => $name,
        'url'           => $displayUrl,
        'status'        => $status,
        'wp_version'    => $wpVersion,
        'php_version'   => $phpVersion,
        'updates_count' => $siteUpdates,
        'php_errors'    => $phpErrors,
    ];
}

// Offline zuerst, dann nach Updates sortieren
usort($sitesOutput, function ($a, $b) {
    if ($a['status'] === 'Offline' && $b['status'] !== 'Offline') return -1;
    if ($b['status'] === 'Offline' && $a['status'] !== 'Offline') return 1;
    return $b['updates_count'] - $a['updates_count'];
});

// --- JSON schreiben ---

$output = [
    'summary' => [
        'total'      => count($sites),
        'online'     => $totalOnline,
        'offline'    => $totalOffline,
        'updates'    => $totalUpdates,
        'php_errors' => $totalErrors,
    ],
    'sites'        => $sitesOutput,
    'generated_at' => date('c'),
];

if (!is_dir(dirname($outputFile))) {
    mkdir(dirname($outputFile), 0755, true);
}

file_put_contents(
    $outputFile,
    json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "OK: " . count($sites) . " Sites verarbeitet. Datei: $outputFile\n";
