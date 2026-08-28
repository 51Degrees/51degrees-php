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
 * Combined device detection + IP intelligence (cloud) web example.
 * Mirrors ip-intelligence-dotnet-examples Cloud/Mixed/GettingStarted-Web: one
 * cloud pipeline (a single CloudRequestEngine) feeds both the device detection
 * and IP intelligence cloud engines, rendered with the 51Degrees template.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use fiftyone\pipeline\devicedetection\DeviceDetectionPipelineBuilder;
use fiftyone\pipeline\ipintelligence\IpIntelligenceCloud;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;

$ip = isset($_GET['client-ip']) && $_GET['client-ip'] !== '' ? trim((string) $_GET['client-ip']) : '8.8.8.8';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
$resourceKey = ExampleUtils::getResourceKey();

$error = null;
$dd = [];
$ipi = [];
$evidence = ['header.user-agent' => $userAgent, 'query.client-ip' => $ip];

function apvStr($data, string $key): string
{
    try {
        $apv = $data->get($key);
        if ($apv === null || !$apv->hasValue) {
            return 'Unknown';
        }
        $v = $apv->value;
        if (is_bool($v)) {
            return $v ? 'True' : 'False';
        }
        return is_array($v) ? implode(', ', array_map(static fn ($x) => is_array($x) ? ($x['value'] ?? '') : (string) $x, $v)) : (string) $v;
    } catch (\Throwable $e) {
        return 'Unknown';
    }
}

if (!$resourceKey) {
    $error = 'No resource key configured. Set the resource_key environment variable before starting the server. It must be authorised for both device detection and IP intelligence.';
} else {
    try {
        $builder = new DeviceDetectionPipelineBuilder(['resourceKey' => $resourceKey]);
        $builder->add(new IpIntelligenceCloud());
        $pipeline = $builder->build();

        $flowData = $pipeline->createFlowData();
        $flowData->evidence->setArray($evidence);
        $result = $flowData->process();

        $device = $result->device;
        $dd = [
            'Hardware Vendor' => apvStr($device, 'hardwarevendor'),
            'Hardware Name' => apvStr($device, 'hardwarename'),
            'Device Type' => apvStr($device, 'devicetype'),
            'Platform Name' => apvStr($device, 'platformname'),
            'Platform Version' => apvStr($device, 'platformversion'),
            'Browser Name' => apvStr($device, 'browsername'),
            'Browser Version' => apvStr($device, 'browserversion'),
            'Screen Width (pixels)' => apvStr($device, 'screenpixelswidth'),
            'Screen Height (pixels)' => apvStr($device, 'screenpixelsheight'),
            'Device Id' => apvStr($device, 'deviceid'),
        ];

        $ipData = $result->ip;
        $ipi = [
            'Registered Name' => apvStr($ipData, 'registeredname'),
            'Registered Owner' => apvStr($ipData, 'registeredowner'),
            'Registered Country' => apvStr($ipData, 'registeredcountry'),
            'IP Range Start' => apvStr($ipData, 'iprangestart'),
            'IP Range End' => apvStr($ipData, 'iprangeend'),
            'Country' => apvStr($ipData, 'country'),
            'Country Code' => apvStr($ipData, 'countrycode'),
            'Country Code 3' => apvStr($ipData, 'countrycode3'),
            'Region' => apvStr($ipData, 'region'),
            'State' => apvStr($ipData, 'state'),
            'Town' => apvStr($ipData, 'town'),
            'Latitude' => apvStr($ipData, 'latitude'),
            'Longitude' => apvStr($ipData, 'longitude'),
            'Accuracy Radius' => apvStr($ipData, 'accuracyradiusmin'),
            'Time Zone Offset' => apvStr($ipData, 'timezoneoffset'),
        ];
    } catch (\Throwable $e) {
        // Do not render the exception message: the cloud request engine puts
        // the request URL in its error text ("Cloud service at '%s' returned
        // status code ..."), and that URL contains the resource key. Log the
        // detail server side and show the visitor a generic message.
        error_log('IP intelligence example failed: ' . $e->getMessage());
        $error = 'The lookup could not be completed. See the server log for details.';
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
</head>
<body>
    <div class="c-eg-page">
        <h2 class="c-eg-page__title">Combined device detection and IP intelligence example</h2>
        <p class="c-eg-page__lead">
            This example demonstrates the use of the Pipeline API to perform both device detection and
            IP intelligence lookups within a single PHP web project, using the 51Degrees cloud service.
            A single cloud request serves both engines.
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
                        <tbody><?= renderRows($dd) ?></tbody>
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
                        <tbody><?= renderRows($ipi) ?></tbody>
                    </table>
                </div>
            </div>

            <details class="c-eg-details">
                <summary>Evidence used</summary>
                <table class="c-eg-table">
                    <thead class="c-eg-table__head">
                        <tr class="c-eg-table__row">
                            <th class="c-eg-table__cell">Key</th>
                            <th class="c-eg-table__cell">Value</th>
                        </tr>
                    </thead>
                    <tbody><?= $evidenceRows ?></tbody>
                </table>
            </details>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
