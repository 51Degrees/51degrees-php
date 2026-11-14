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
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 *
 * If using the Work as, or as part of, a network application, by
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading,
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */

/*
 * Combined device detection and IP intelligence (on-premise) web example.
 * Mirrors the design of ip-intelligence-dotnet-examples and
 * ip-intelligence-java-examples (same 51Degrees examples-main.min.css and
 * two-column layout).
 *
 * One pipeline holds the Device Detection on-premise engine and the IP
 * Intelligence on-premise engine. Each uses the engine its extension builds
 * once, when PHP starts, from the data file its php.ini setting names, so no
 * request loads either data file.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;

// Where to ask for a copy of the Enterprise data file, tagged with this
// example.
$contactUs = 'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-onpremise-web-mixed-index.php&utm_term=enterprise-data-file';

// ---- Inputs -------------------------------------------------------------
$ip = isset($_GET['client-ip']) && $_GET['client-ip'] !== '' ? trim((string) $_GET['client-ip']) : '8.8.8.8';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

$error = null;
$dd = [];   // device detection results (label => value)
$ipi = [];  // ip intelligence results (label => value)
$map = ['Areas' => '', 'Latitude' => '', 'Longitude' => ''];
$evidence = [
    'header.user-agent' => $userAgent,
    'query.client-ip' => $ip,
];

// The properties shown, by label.
$deviceProperties = [
    'Hardware Vendor' => 'HardwareVendor',
    'Hardware Name' => 'HardwareName',
    'Device Type' => 'DeviceType',
    'Platform Name' => 'PlatformName',
    'Platform Version' => 'PlatformVersion',
    'Browser Name' => 'BrowserName',
    'Browser Version' => 'BrowserVersion',
    'Screen Width (pixels)' => 'ScreenPixelsWidth',
    'Screen Height (pixels)' => 'ScreenPixelsHeight',
];
$ipProperties = [
    'Registered Name' => 'RegisteredName',
    'Registered Owner' => 'RegisteredOwner',
    'Registered Country' => 'RegisteredCountry',
    'IP Range Start' => 'IpRangeStart',
    'IP Range End' => 'IpRangeEnd',
    'Country' => 'Country',
    'Country Code' => 'CountryCode',
    'Country Code 3' => 'CountryCode3',
    'Region' => 'Region',
    'State' => 'State',
    'Town' => 'Town',
    'Latitude' => 'Latitude',
    'Longitude' => 'Longitude',
    'Areas' => 'Areas',
    'Accuracy Radius' => 'AccuracyRadiusMin',
    'Time Zone Offset' => 'TimeZoneOffset',
    'Location Confidence' => 'LocationConfidence',
    'Connection Type' => 'ConnectionType',
    'Human Probability' => 'HumanProbability',
    'Hardware Diversity' => 'HardwareDiversity',
    'Browser Diversity' => 'BrowserDiversity',
    'Platform Diversity' => 'PlatformDiversity',
];

/**
 * A property's value as the console examples write it, each weighted value
 * followed by its weighting, or Unknown when it has none.
 *
 * @param mixed $data The engine's element data
 */
function valStr($data, string $name): string
{
    try {
        $apv = $data->get(strtolower($name));
    } catch (\Throwable $e) {
        return 'Unknown';
    }
    if (!($apv instanceof AspectPropertyValue) || !$apv->hasValue) {
        return 'Unknown';
    }

    return ExampleUtils::formatValue($apv);
}

/**
 * A location property's value for the map, the most likely one of a
 * weighted value, or empty when the data file does not have it.
 *
 * @param mixed $engine
 * @param mixed $data
 */
function mapValue($engine, $data, string $name): string
{
    if (!ExampleUtils::hasProperty($engine, $name)) {
        return '';
    }
    try {
        $apv = $data->get(strtolower($name));
    } catch (\Throwable $e) {
        return '';
    }
    if (!($apv instanceof AspectPropertyValue) || !$apv->hasValue) {
        return '';
    }
    $value = $apv->value;
    if (is_array($value)) {
        $first = reset($value);
        $value = is_array($first) ? ($first['value'] ?? '') : $first;
    }

    return (string) $value;
}

/**
 * Each property's value by label, or that it needs the Enterprise data file
 * when the engine's data file does not have it.
 *
 * @param mixed $engine
 * @param mixed $data
 * @param array<string, string> $properties
 * @return array<string, string>
 */
function rows($engine, $data, array $properties): array
{
    $rows = [];
    foreach ($properties as $label => $name) {
        $rows[$label] = ExampleUtils::hasProperty($engine, $name)
            ? valStr($data, $name)
            : ExampleUtils::NEEDS_ENTERPRISE;
    }

    return $rows;
}

if (!ExampleUtils::findDeviceDetectionOnPremise()) {
    $error = 'The device detection on-premise package is not installed.';
} elseif (!extension_loaded('FiftyOneDegreesHashEngine')) {
    $error = 'The FiftyOneDegreesHashEngine (device detection) native extension is not loaded.';
} elseif (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
    $error = 'The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.';
} else {
    try {
        $deviceClass = ExampleUtils::DEVICE_DETECTION_ONPREMISE_CLASS;
        $deviceEngine = new $deviceClass();
        $ipEngine = new IpIntelligenceOnPremise();
        $pipeline = (new PipelineBuilder([]))
            ->add($deviceEngine)
            ->add($ipEngine)
            ->build();

        $flowData = $pipeline->createFlowData();
        $flowData->evidence->setArray($evidence);
        $result = $flowData->process();

        $dd = rows($deviceEngine, $result->device, $deviceProperties);
        $dd['Device Id'] = valStr($result->device, 'DeviceId');
        $ipi = rows($ipEngine, $result->ip, $ipProperties);
        foreach (array_keys($map) as $name) {
            $map[$name] = mapValue($ipEngine, $result->ip, $name);
        }
    } catch (\Throwable $e) {
        // The message can carry server side detail such as data file paths.
        error_log('IP intelligence example failed: ' . $e->getMessage());
        $error = 'The lookup could not be completed. See the server log for details.';
    } finally {
        // Closing the FlowData, once the values needed have been read, and
        // then the pipeline releases the results of the lookup straight away.
        if (isset($flowData)) {
            $flowData->close();
        }
        if (isset($pipeline)) {
            $pipeline->close();
        }
    }
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape a value for a <script> block. json_encode emits its own quotes, so
 * the call site must not add any.
 */
function js(?string $s): string
{
    return json_encode(
        (string) $s,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
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
            <?php if (in_array(ExampleUtils::NEEDS_ENTERPRISE, array_merge($dd, $ipi), true)): ?>
                <div class="c-eg-message">
                    <p class="c-eg-message__text">Some of these properties need the Enterprise data file. <a href="<?= h($contactUs) ?>">Contact us</a> for a copy.</p>
                </div>
            <?php endif; ?>

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

        <?= \fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils::getFindOutMoreHtml(__FILE__) ?>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/wellknown@0.5.0/wellknown.js"></script>
    <script src="js/examples.min.js"></script>
    <script>
        window.onload = function () {
            if (window.fodExamples && fodExamples.initLocationMap) {
                fodExamples.initLocationMap({
                    areasWkt: <?= js($map['Areas']) ?>,
                    latitude: <?= js($map['Latitude']) ?>,
                    longitude: <?= js($map['Longitude']) ?>
                });
            }
        };
    </script>
</body>
</html>
