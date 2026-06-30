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
 * IP intelligence web integration example (on-premise).
 * Mirrors ip-intelligence-dotnet-examples OnPremise/GettingStarted-Web: the
 * same 51Degrees examples-main.min.css template, IP lookup form, results
 * table, evidence table and location map.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;

$ip = isset($_GET['client-ip']) && $_GET['client-ip'] !== '' ? trim((string) $_GET['client-ip']) : '8.8.8.8';
$ipiDataFile = ExampleUtils::getDataFile();

$error = null;
$rows = [];
$geographical = [];
$population = [];
$areas = '';
$latitude = '';
$longitude = '';

function valStr($value): string
{
    try {
        if ($value->hasValue()) {
            $v = $value->getValue();
            return is_bool($v) ? ($v ? 'True' : 'False') : (string) $v;
        }
    } catch (\Throwable $e) {
    }
    return 'Unknown';
}

/**
 * Convert a SWIG weighted string list to an array of "value (weighting)" strings.
 *
 * @return array<string>
 */
function weightedList($value): array
{
    $out = [];
    try {
        if ($value->hasValue()) {
            $list = $value->getValue();
            for ($i = 0; $i < $list->size(); ++$i) {
                $item = $list->get($i);
                $out[] = $item->getValue() . ' (' . $item->getWeight() . ')';
            }
        }
    } catch (\Throwable $e) {
    }
    return $out;
}

if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
    $error = 'The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.';
} else {
    try {
        if (function_exists('ipi_engine_get') && \ipi_engine_get() !== null) {
            $engine = \ipi_engine_get();
        } else {
            if (!$ipiDataFile) {
                throw new \Exception('No .ipi data file found.');
            }
            $config = new \ConfigIpiSwig();
            $config->setLowMemory();
            $config->setUseUpperPrefixHeaders(false);
            $engine = new \EngineIpiSwig($ipiDataFile, $config, new \RequiredPropertiesConfigSwig(new \VectorStringSwig()));
        }

        $results = $engine->process($ip);

        $rows = [
            'Registered Name' => valStr($results->getValueAsString('RegisteredName')),
            'Registered Owner' => valStr($results->getValueAsString('RegisteredOwner')),
            'Registered Country' => valStr($results->getValueAsString('RegisteredCountry')),
            'IP Range Start' => valStr($results->getValueAsString('IpRangeStart')),
            'IP Range End' => valStr($results->getValueAsString('IpRangeEnd')),
            'Country' => valStr($results->getValueAsString('Country')),
            'Country Code' => valStr($results->getValueAsString('CountryCode')),
            'Country Code 3' => valStr($results->getValueAsString('CountryCode3')),
            'Region' => valStr($results->getValueAsString('Region')),
            'State' => valStr($results->getValueAsString('State')),
            'Town' => valStr($results->getValueAsString('Town')),
            'Latitude' => valStr($results->getValueAsString('Latitude')),
            'Longitude' => valStr($results->getValueAsString('Longitude')),
            'Areas' => valStr($results->getValueAsString('Areas')),
            'Accuracy Radius' => valStr($results->getValueAsString('AccuracyRadiusMin')),
            'Time Zone Offset' => valStr($results->getValueAsString('TimeZoneOffset')),
        ];
        $geographical = weightedList($results->getValuesAsWeightedStringList('CountryCodesGeographical'));
        $population = weightedList($results->getValuesAsWeightedStringList('CountryCodesPopulation'));
        $areas = $rows['Areas'] === 'Unknown' ? '' : $rows['Areas'];
        $latitude = $rows['Latitude'] === 'Unknown' ? '' : $rows['Latitude'];
        $longitude = $rows['Longitude'] === 'Unknown' ? '' : $rows['Longitude'];
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
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
            within a simple PHP web project, on-premise from a local data file.
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
                    areasWkt: '<?= addslashes($areas) ?>',
                    latitude: '<?= addslashes($latitude) ?>',
                    longitude: '<?= addslashes($longitude) ?>'
                });
            }
        };
    </script>
</body>
</html>
