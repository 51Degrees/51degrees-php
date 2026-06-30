<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2026 51 Degrees Mobile Experts Limited, Davidson House,
 * Forbury Square, Reading, Berkshire, United Kingdom RG1 3EU.
 *
 * This Original Work is licensed under the European Union Public Licence
 * (EUPL) v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 * ********************************************************************* */

/*
 * Combined device detection + IP intelligence (on-premise) web example.
 * Mirrors the design of ip-intelligence-dotnet-examples and
 * ip-intelligence-java-examples (same 51Degrees examples-main.min.css and
 * two-column layout).
 *
 * Device detection is performed by the native FiftyOneDegreesHashEngine
 * extension (from a .hash data file) and IP intelligence by the native
 * FiftyOneDegreesIpIntelligenceEngine extension (from a .ipi data file).
 * Both look-ups use the single-value process() overload (User-Agent string /
 * IP string) which is the safe path through the SWIG PHP binding.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;

// ---- Inputs -------------------------------------------------------------
$ip = isset($_GET['client-ip']) && $_GET['client-ip'] !== '' ? trim((string) $_GET['client-ip']) : '8.8.8.8';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

$ddDataFile = getenv('DEVICEDETECTIONDATAFILE') ?: 'D:/WorkSpace/TAC-HashV41.hash';
$ipiDataFile = ExampleUtils::getDataFile();

$error = null;
$dd = [];   // device detection results (label => value)
$ipi = [];  // ip intelligence results (label => value)
$evidence = [
    'header.user-agent' => $userAgent,
    'query.client-ip' => $ip,
];

/**
 * Read a SWIG Value wrapper as a display string.
 */
function valStr($value): string
{
    try {
        if ($value->hasValue()) {
            $v = $value->getValue();
            if (is_bool($v)) {
                return $v ? 'True' : 'False';
            }
            return (string) $v;
        }
    } catch (\Throwable $e) {
        // fall through
    }
    return 'Unknown';
}

if (!extension_loaded('FiftyOneDegreesHashEngine')) {
    $error = 'The FiftyOneDegreesHashEngine (device detection) native extension is not loaded.';
} elseif (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
    $error = 'The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.';
} else {
    try {
        // ---- Device detection -------------------------------------------
        // The device detection extension builds its engine once at module load
        // from the FiftyOneDegreesHashEngine.data_file php.ini setting, with the
        // correct graph configuration. Reuse that global engine via engine_get()
        // rather than constructing another (direct construction needs the full
        // graph config and is not the supported PHP path).
        $ddEngine = \engine_get();
        $ddResults = $ddEngine->process($userAgent);

        $dd = [
            'Hardware Vendor' => valStr($ddResults->getValueAsString('HardwareVendor')),
            'Hardware Name' => str_replace('|', ', ', valStr($ddResults->getValueAsString('HardwareName'))),
            'Device Type' => valStr($ddResults->getValueAsString('DeviceType')),
            'Platform Name' => valStr($ddResults->getValueAsString('PlatformName')),
            'Platform Version' => valStr($ddResults->getValueAsString('PlatformVersion')),
            'Browser Name' => valStr($ddResults->getValueAsString('BrowserName')),
            'Browser Version' => valStr($ddResults->getValueAsString('BrowserVersion')),
            'Screen Width (pixels)' => valStr($ddResults->getValueAsString('ScreenPixelsWidth')),
            'Screen Height (pixels)' => valStr($ddResults->getValueAsString('ScreenPixelsHeight')),
            'Device Id' => $ddResults->getDeviceId(),
        ];

        // ---- IP intelligence --------------------------------------------
        // Reuse the global engine built once at module load (ipi_engine_get)
        // so the multi-gigabyte data file is not re-opened on every request.
        // Fall back to direct construction when the global engine is not
        // configured (the FiftyOneDegreesIpIntelligenceEngine.data_file ini is
        // not set).
        if (function_exists('ipi_engine_get') && \ipi_engine_get() !== null) {
            $ipiEngine = \ipi_engine_get();
        } else {
            $ipiConfig = new \ConfigIpiSwig();
            $ipiConfig->setLowMemory();
            $ipiConfig->setUseUpperPrefixHeaders(false);
            $ipiEngine = new \EngineIpiSwig($ipiDataFile, $ipiConfig, new \RequiredPropertiesConfigSwig(new \VectorStringSwig()));
        }
        $ipiResults = $ipiEngine->process($ip);

        $ipi = [
            'Registered Name' => valStr($ipiResults->getValueAsString('RegisteredName')),
            'Registered Owner' => valStr($ipiResults->getValueAsString('RegisteredOwner')),
            'Registered Country' => valStr($ipiResults->getValueAsString('RegisteredCountry')),
            'IP Range Start' => valStr($ipiResults->getValueAsString('IpRangeStart')),
            'IP Range End' => valStr($ipiResults->getValueAsString('IpRangeEnd')),
            'Country' => valStr($ipiResults->getValueAsString('Country')),
            'Country Code' => valStr($ipiResults->getValueAsString('CountryCode')),
            'Country Code 3' => valStr($ipiResults->getValueAsString('CountryCode3')),
            'Region' => valStr($ipiResults->getValueAsString('Region')),
            'State' => valStr($ipiResults->getValueAsString('State')),
            'Town' => valStr($ipiResults->getValueAsString('Town')),
            'Latitude' => valStr($ipiResults->getValueAsString('Latitude')),
            'Longitude' => valStr($ipiResults->getValueAsString('Longitude')),
            'Areas' => valStr($ipiResults->getValueAsString('Areas')),
            'Accuracy Radius' => valStr($ipiResults->getValueAsString('AccuracyRadiusMin')),
            'Time Zone Offset' => valStr($ipiResults->getValueAsString('TimeZoneOffset')),
            'Location Confidence' => valStr($ipiResults->getValueAsString('LocationConfidence')),
            'Connection Type' => valStr($ipiResults->getValueAsString('ConnectionType')),
            'Human Probability' => valStr($ipiResults->getValueAsString('HumanProbability')),
            'Hardware Diversity' => valStr($ipiResults->getValueAsString('HardwareDiversity')),
            'Browser Diversity' => valStr($ipiResults->getValueAsString('BrowserDiversity')),
            'Platform Diversity' => valStr($ipiResults->getValueAsString('PlatformDiversity')),
        ];
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Render a Property/Value table body with alternating row classes, matching
 * the 51Degrees example template.
 *
 * @param array<string, string> $rows
 */
function renderRows(array $rows): string
{
    $out = '';
    $i = 0;
    foreach ($rows as $label => $value) {
        $alt = ($i % 2 === 0) ? ' c-eg-table__row--alt' : '';
        $out .= '<tr class="c-eg-table__row' . $alt . '">'
            . '<td class="c-eg-table__cell c-eg-table__cell--key">' . h($label) . ':</td>'
            . '<td class="c-eg-table__cell">' . h($value) . '</td></tr>';
        ++$i;
    }
    return $out;
}

$evidenceRows = '';
$ei = 0;
foreach ($evidence as $k => $v) {
    $alt = ($ei % 2 === 0) ? ' c-eg-table__row--alt' : '';
    $evidenceRows .= '<tr class="c-eg-table__row' . $alt . '"><td class="c-eg-table__cell c-eg-table__cell--key">' . h($k) . '</td><td class="c-eg-table__cell">' . h($v) . '</td></tr>';
    ++$ei;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Detection + IP Intelligence Example</title>
    <link rel="stylesheet" href="css/examples-main.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div class="c-eg-page">
        <h2 class="c-eg-page__title">Combined device detection and IP intelligence example</h2>

        <p class="c-eg-page__lead">
            This example demonstrates the use of the Pipeline API to perform both device detection and
            IP intelligence lookups within a single PHP web project. It highlights device detection
            using User-Agent and Client Hints, IP-based geolocation and network information, and
            on-premise processing of both engines for optimal performance.
        </p>

        <?php if ($error): ?>
            <div class="c-eg-alert"><?= h($error) ?></div>
        <?php else: ?>
        <div id="content">
            <form method="get" action="" class="c-eg-form">
                <div class="c-eg-form__row">
                    <label class="b-label" for="client-ip">IP Address Lookup:</label>
                    <input class="b-input" type="text" id="client-ip" name="client-ip" value="<?= h($ip) ?>"
                           placeholder="e.g., 8.8.8.8">
                    <button class="b-btn" type="submit">Look Up</button>
                </div>
            </form>

            <div class="c-eg-columns">
                <div>
                    <h3 class="c-eg-page__heading">Device detection results</h3>
                    <table class="c-eg-table">
                        <thead class="c-eg-table__head">
                            <tr class="c-eg-table__row">
                                <th class="c-eg-table__cell">Property</th>
                                <th class="c-eg-table__cell">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?= renderRows($dd) ?>
                        </tbody>
                    </table>
                </div>

                <div>
                    <h3 class="c-eg-page__heading">IP intelligence results</h3>
                    <table class="c-eg-table">
                        <thead class="c-eg-table__head">
                            <tr class="c-eg-table__row">
                                <th class="c-eg-table__cell">Property</th>
                                <th class="c-eg-table__cell">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?= renderRows($ipi) ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="map-section" class="c-eg-map" style="display: none;">
                <h3 class="c-eg-map__title">Location map</h3>
                <div id="map" class="c-eg-map__canvas"></div>
            </div>

            <details class="c-eg-details">
                <summary>Evidence used</summary>
                <p class="c-eg-page__lead">Evidence collected from the current request</p>
                <table class="c-eg-table">
                    <thead class="c-eg-table__head">
                        <tr class="c-eg-table__row">
                            <th class="c-eg-table__cell">Key</th>
                            <th class="c-eg-table__cell">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?= $evidenceRows ?>
                    </tbody>
                </table>
            </details>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/wellknown@0.5.0/wellknown.js"></script>
    <script src="js/examples.min.js"></script>
    <script>
        window.onload = function () {
            if (window.fodExamples && fodExamples.initLocationMap) {
                fodExamples.initLocationMap({
                    areasWkt: '<?= addslashes($ipi['Areas'] ?? '') ?>',
                    latitude: '<?= addslashes($ipi['Latitude'] ?? '') ?>',
                    longitude: '<?= addslashes($ipi['Longitude'] ?? '') ?>'
                });
            }
        };
    </script>
</body>
</html>
