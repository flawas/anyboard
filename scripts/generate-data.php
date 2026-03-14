<?php
/**
 * WP Umbrella → Anyboard Data Generator
 * Wird von GitHub Actions ausgeführt.
 * API-Key kommt aus der Umgebungsvariable WP_UMBRELLA_API_KEY (GitHub Secret).
 */

$apiKey     = getenv('WP_UMBRELLA_API_KEY') ?: '';
$apiBase    = 'https://public-api.wp-umbrella.com';
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

$raw = fetchUmbrella($apiBase, $apiKey, '/projects');

if ($raw === null) {
    fwrite(STDERR, "ERROR: WP Umbrella API nicht erreichbar oder Key ungültig.\n");
    exit(1);
}

$sites = $raw['data'] ?? [];

// --- Daten aufbereiten ---

$totalOnline  = 0;
$totalOffline = 0;
$totalUpdates = 0;
$totalErrors  = 0;
$sitesOutput  = [];

foreach ($sites as $site) {
    $name = $site['name'] ?? 'Unbekannt';
    $url  = $site['base_url'] ?? '';

    $isDown = (bool)($site['is_currently_down'] ?? false);

    if ($isDown) {
        $status    = 'Offline';
        $indicator = 'danger';
        $totalOffline++;
    } else {
        $status   = 'Online';
        $totalOnline++;
        $indicator = null;
    }

    $phpErrors     = (int)($site['count_php_issues'] ?? 0);
    $totalErrors  += $phpErrors;

    $updates = 0;
    foreach ($site['plugins'] ?? [] as $plugin) {
        if (!empty($plugin['need_update'])) {
            $updates++;
        }
    }
    foreach ($site['themes'] ?? [] as $theme) {
        if (!empty($theme['latest_version']) && !empty($theme['version']) && $theme['latest_version'] !== $theme['version']) {
            $updates++;
        }
    }
    $totalUpdates += $updates;

    if ($indicator === null && $phpErrors > 0) {
        $indicator = 'warning';
    }

    $displayUrl = preg_replace('#^https?://#', '', rtrim($url, '/'));

    $sitesOutput[] = [
        'indicator'  => $indicator,
        'name'       => $name,
        'url'        => $displayUrl,
        'status'     => $status,
        'php_errors' => $phpErrors,
        'updates'    => $updates,
    ];
}

// Offline zuerst, dann nach PHP-Fehlern sortieren
usort($sitesOutput, function ($a, $b) {
    if ($a['status'] === 'Offline' && $b['status'] !== 'Offline') {
        return -1;
    }
    if ($b['status'] === 'Offline' && $a['status'] !== 'Offline') {
        return 1;
    }
    return $b['php_errors'] - $a['php_errors'];
});

// --- JSON schreiben ---

$output = [
    'summary' => [
        'total'      => count($sites),
        'online'     => $totalOnline,
        'offline'    => $totalOffline,
        'php_errors' => $totalErrors,
        'updates'    => $totalUpdates,
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
