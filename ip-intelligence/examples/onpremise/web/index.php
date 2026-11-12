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
 * IP intelligence web integration example (on-premise), with the ordered
 * country dropdown. Mirrors ip-intelligence-dotnet-examples
 * OnPremise/GettingStarted-Web: a pipeline of the IP intelligence engine
 * followed by the two country translation engines, rendered with the
 * 51Degrees template. The dropdown lists countries most probable first, then
 * every other country alphabetically, localized by the request's
 * Accept-Language header.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\translation\CountriesTranslationEngine;
use fiftyone\pipeline\ipintelligence\translation\CountryCodeTranslationEngine;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;

// Where to ask for a copy of the Enterprise data file, tagged with this
// example.
$contactUs = 'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-onpremise-web-index.php&utm_term=enterprise-data-file';

$ip = isset($_GET['client-ip']) && $_GET['client-ip'] !== '' ? trim((string) $_GET['client-ip']) : '8.8.8.8';
$acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

$error = null;
$rows = [];
$dropdown = [];
$areas = '';
$latitude = '';
$longitude = '';
$countriesNeedEnterprise = false;

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

function apvStr($data, string $key): string
{
    try {
        $apv = $data->get($key);
        if (!($apv instanceof \fiftyone\pipeline\core\AspectPropertyValue) || !$apv->hasValue) {
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

if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
    $error = 'The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.';
} else {
    try {
        // IpIntelligenceOnPremise uses the engine the extension builds once,
        // when PHP starts, from the FiftyOneDegreesIpIntelligenceEngine.data_file
        // php.ini setting (see OnPremise.md), so no request loads the data
        // file.
        $pipeline = (new PipelineBuilder([]))
            ->add(new IpIntelligenceOnPremise())
            ->add(new CountryCodeTranslationEngine())
            ->add(new CountriesTranslationEngine())
            ->build();

        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip', $ip);
        if ($acceptLanguage !== '') {
            $flowData->evidence->set('header.accept-language', $acceptLanguage);
        }
        $result = $flowData->process();

        // A property the data file does not have needs the Enterprise data
        // file, and its value is not read.
        $ipData = $result->ip;
        $ipEngine = $pipeline->getElement('ip');
        foreach ($displayProperties as $label => $key) {
            $rows[$label] = ExampleUtils::hasProperty($ipEngine, $key)
                ? apvStr($ipData, $key)
                : ExampleUtils::NEEDS_ENTERPRISE;
        }
        $located = static fn (string $value): string =>
            in_array($value, ['Unknown', ExampleUtils::NEEDS_ENTERPRISE], true) ? '' : $value;
        $areas = $located($rows['Areas']);
        $latitude = $located($rows['Latitude']);
        $longitude = $located($rows['Longitude']);

        // The ordered dropdown, built by the countries translation engine
        // from the country codes, which only the Enterprise data file has.
        $countriesNeedEnterprise =
            !ExampleUtils::hasProperty($ipEngine, 'CountryCodesGeographical');
        $translated = $result->countrynamestranslated;
        $names = $translated->get('countrynamesgeographicalalltranslated');
        $codes = $translated->get('countrycodesgeographicalall');
        if (!$countriesNeedEnterprise && $names->hasValue && $codes->hasValue) {
            $nameList = $names->value;
            $codeList = $codes->value;
            for ($i = 0, $n = min(count($nameList), count($codeList)); $i < $n; ++$i) {
                $dropdown[] = ['code' => $codeList[$i], 'name' => $nameList[$i]];
            }
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
            within a simple PHP web project, on-premise from a local data file. The country dropdown
            is ordered most probable first and localized using your browser's Accept-Language header.
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

            <h3 class="c-eg-page__heading">Country (most probable first)</h3>
            <p class="c-eg-page__lead">
                Geographical countries ordered by probability, then every other country alphabetically.
            </p>
            <?php if ($countriesNeedEnterprise): ?>
                <p class="c-eg-page__lead">
                    The country list <?= h(ExampleUtils::NEEDS_ENTERPRISE) ?>.
                    <a href="<?= h($contactUs) ?>">Contact us</a> for a copy.
                </p>
            <?php else: ?>
            <select id="CountryNamesGeographicalAllTranslated"
                    name="CountryNamesGeographicalAllTranslated" class="b-input">
                <?php foreach ($dropdown as $entry): ?>
                    <option value="<?= h($entry['code']) ?>"><?= h($entry['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <h3 class="c-eg-page__heading">Detection results</h3>
            <table class="c-eg-table">
                <thead class="c-eg-table__head">
                    <tr class="c-eg-table__row">
                        <th class="c-eg-table__cell">Property</th>
                        <th class="c-eg-table__cell">Value</th>
                    </tr>
                </thead>
                <tbody><?= renderRows($rows) ?></tbody>
            </table>
            <?php if (in_array(ExampleUtils::NEEDS_ENTERPRISE, $rows, true)): ?>
                <div class="c-eg-message">
                    <p class="c-eg-message__text">Some of these properties need the Enterprise data file. <a href="<?= h($contactUs) ?>">Contact us</a> for a copy.</p>
                </div>
            <?php endif; ?>

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
                    <tr class="c-eg-table__row c-eg-table__row--used">
                        <td class="c-eg-table__cell c-eg-table__cell--key">header.accept-language</td>
                        <td class="c-eg-table__cell"><?= h($acceptLanguage) ?></td>
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
