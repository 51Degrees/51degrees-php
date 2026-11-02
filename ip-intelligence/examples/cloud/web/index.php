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
 * IP intelligence web integration example (cloud).
 * Mirrors ip-intelligence-dotnet-examples Cloud/GettingStarted-Web: the same
 * 51Degrees examples-main.min.css template, IP lookup form, results table and
 * location map. Properties are limited to those the supplied resource key is
 * authorised for.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use fiftyone\pipeline\ipintelligence\IpIntelligencePipelineBuilder;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;

$ip = isset($_GET['client-ip']) && $_GET['client-ip'] !== '' ? trim((string) $_GET['client-ip']) : '8.8.8.8';
$resourceKey = ExampleUtils::getResourceKey();

$error = null;
$rows = [];
$geographical = [];
$population = [];
$areas = '';
$latitude = '';
$longitude = '';

$displayProperties = [
    'Registered Name' => 'registeredname',
    'Registered Owner' => 'registeredowner',
    'Registered Country' => 'registeredcountry',
    'IP Range Start' => 'iprangestart',
    'IP Range End' => 'iprangeend',
    'Country' => 'country',
    'Country Code' => 'countrycode',
    'Country Code 3' => 'countrycode3',
    'Region' => 'region',
    'State' => 'state',
    'Town' => 'town',
    'Latitude' => 'latitude',
    'Longitude' => 'longitude',
    'Areas' => 'areas',
    'Accuracy Radius' => 'accuracyradiusmin',
    'Time Zone Offset' => 'timezoneoffset',
];

/**
 * Format an AspectPropertyValue from the cloud result for display.
 */
function apvStr($data, string $key): string
{
    try {
        $apv = $data->get($key);
        if ($apv === null) {
            return 'Unknown';
        }
        if (!$apv->hasValue) {
            return 'Unknown';
        }
        $v = $apv->value;
        if (is_bool($v)) {
            return $v ? 'True' : 'False';
        }
        return is_array($v) ? '' : (string) $v;
    } catch (\Throwable $e) {
        return 'Unknown';
    }
}

/**
 * Read a weighted cloud property (array of ['value'=>..,'rawweighting'=>..])
 * as a list of "value (rawweighting)" strings.
 *
 * @return array<string>
 */
function apvWeighted($data, string $key): array
{
    try {
        $apv = $data->get($key);
        if ($apv !== null && $apv->hasValue && is_array($apv->value)) {
            $out = [];
            foreach ($apv->value as $item) {
                if (is_array($item) && array_key_exists('value', $item)) {
                    $w = $item['rawweighting'] ?? '';
                    $out[] = $item['value'] . ' (' . $w . ')';
                }
            }
            return $out;
        }
    } catch (\Throwable $e) {
    }
    return [];
}

if (!$resourceKey) {
    $error = 'No resource key configured. Set the resource_key environment variable before starting the server.';
} else {
    try {
        $pipeline = (new IpIntelligencePipelineBuilder(['resourceKey' => $resourceKey]))->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip', $ip);
        $data = $flowData->process()->ip;

        foreach ($displayProperties as $label => $key) {
            $rows[$label] = apvStr($data, $key);
        }
        $geographical = apvWeighted($data, 'countrycodesgeographical');
        $population = apvWeighted($data, 'countrycodespopulation');
        $areas = ($rows['Areas'] === 'Unknown') ? '' : $rows['Areas'];
        $latitude = ($rows['Latitude'] === 'Unknown') ? '' : $rows['Latitude'];
        $longitude = ($rows['Longitude'] === 'Unknown') ? '' : $rows['Longitude'];
    } catch (\Throwable $e) {
        // The cloud request engine puts the request URL, which carries the
        // resource key, in its error text - so never render it.
        error_log('IP intelligence example failed: ' . $e->getMessage());
        $error = 'The lookup could not be completed. See the server log for details.';
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

function renderList(array $items): string
{
    if (empty($items)) {
        return 'Unknown';
    }
    $out = '<ul>';
    foreach ($items as $item) {
        $out .= '<li>' . h($item) . '</li>';
    }
    return $out . '</ul>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Intelligence Web Integration Example</title>
    <link rel="stylesheet" href="css/examples-main.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div class="c-eg-page">
        <h2 class="c-eg-page__title">IP intelligence web integration example</h2>
        <p class="c-eg-page__lead">
            This example demonstrates the use of the Pipeline API to perform IP intelligence lookups
            within a simple PHP web project, using the 51Degrees cloud service.
        </p>

        <?php if ($error): ?>
            <div class="c-eg-alert"><?= h($error) ?></div>
        <?php else: ?>
        <div id="content">
            <h3 class="c-eg-page__heading">IP intelligence lookup</h3>
            <form method="get" action="" class="c-eg-form">
                <div class="c-eg-form__row">
                    <label class="b-label" for="client-ip">Enter IP Address:</label>
                    <input class="b-input" type="text" id="client-ip" name="client-ip" value="<?= h($ip) ?>"
                           placeholder="e.g., 8.8.8.8">
                    <button class="b-btn" type="submit">Look Up</button>
                </div>
            </form>

            <h3 class="c-eg-page__heading">Detection results</h3>
            <p class="c-eg-page__lead">The following values are determined by IP intelligence analysis.</p>
            <table class="c-eg-table">
                <thead class="c-eg-table__head">
                    <tr class="c-eg-table__row">
                        <th class="c-eg-table__cell">Property</th>
                        <th class="c-eg-table__cell">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?= renderRows($rows) ?>
                    <tr class="c-eg-table__row c-eg-table__row--alt">
                        <td class="c-eg-table__cell c-eg-table__cell--key">Country Codes Geographical:</td>
                        <td class="c-eg-table__cell"><?= renderList($geographical) ?></td>
                    </tr>
                    <tr class="c-eg-table__row">
                        <td class="c-eg-table__cell c-eg-table__cell--key">Country Codes Population:</td>
                        <td class="c-eg-table__cell"><?= renderList($population) ?></td>
                    </tr>
                </tbody>
            </table>

            <h3 class="c-eg-page__heading">Evidence used</h3>
            <table class="c-eg-table">
                <thead class="c-eg-table__head">
                    <tr class="c-eg-table__row">
                        <th class="c-eg-table__cell">Key</th>
                        <th class="c-eg-table__cell">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="c-eg-table__row c-eg-table__row--used">
                        <td class="c-eg-table__cell c-eg-table__cell--key">query.client-ip</td>
                        <td class="c-eg-table__cell"><?= h($ip) ?></td>
                    </tr>
                </tbody>
            </table>

            <div id="map-section" class="c-eg-map" style="display: none;">
                <h3 class="c-eg-map__title">Location Map</h3>
                <div id="map" class="c-eg-map__canvas"></div>
            </div>
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
                    areasWkt: <?= js($areas) ?>,
                    latitude: <?= js($latitude) ?>,
                    longitude: <?= js($longitude) ?>
                });
            }
        };
    </script>
</body>
</html>
