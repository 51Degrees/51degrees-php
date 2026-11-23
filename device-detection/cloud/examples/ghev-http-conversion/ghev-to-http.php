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


// The examples' helper, loaded by itself so that this example needs no
// composer install.
require_once __DIR__ . '/../cloud/classes/ExampleUtils.php';

use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;

// Gives a value as an sf-string. An empty value is still quoted, as a
// browser with no model sends Sec-CH-UA-Model: "".
function qq(string $s): string {
    return '"' . trim($s) . '"';
}

function brandVersionArrayToHTTP(array $clientHints, string $property): string {
    $header = '';
    $brandVersionArray = null;
    if (isset($clientHints[$property])) {
        $brandVersionArray = $clientHints[$property];
    }
    if (is_array($brandVersionArray)) {
        foreach($brandVersionArray as $brandVersion) {
            if (!array_key_exists('brand', $brandVersion) || !array_key_exists('version', $brandVersion)) {
                continue;
            }
            if (strlen($header)) {
                $header .= ', ';
            }
            $header .= sprintf('"%s";v="%s"', $brandVersion['brand'], $brandVersion['version']);
        }
    }
    return $header;
}

function ghevToHTTPHeaders(array $clientHints): array {
    $ua = brandVersionArrayToHTTP($clientHints, 'brands');
    $fullVersionList = brandVersionArrayToHTTP($clientHints, 'fullVersionList');

    return [
        'Sec-CH-UA: ' . $ua,
        'Sec-CH-UA-Arch: ' . qq($clientHints['architecture']),
        'Sec-CH-UA-Bitness: ' . qq($clientHints['bitness']),
        'Sec-CH-UA-Full-Version-List: ' . $fullVersionList,
        'Sec-CH-UA-Mobile: ?' . (int) ($clientHints['mobile'] ?? 0),
        'Sec-CH-UA-Model: ' . qq($clientHints['model']),
        'Sec-CH-UA-Platform: ' . qq($clientHints['platform']),
        'Sec-CH-UA-Platform-Version: ' . qq($clientHints['platformVersion']),
    ];
}

/* 
    see https://developer.mozilla.org/en-US/docs/Web/API/NavigatorUAData/getHighEntropyValues
    you can obtain an example JSON like below by running this code in a Chromium-based browser: 
    `console.log(JSON.stringify(await navigator.userAgentData.getHighEntropyValues(['architecture', 'bitness', 'model', 'platformVersion', 'fullVersionList'])))`
*/

$ghev = json_decode('{"architecture":"x86","bitness":"64","brands":[{"brand":"Chromium","version":"116"},{"brand":"Not)A;Brand","version":"24"},{"brand":"Google Chrome","version":"116"}],"fullVersionList":[{"brand":"Chromium","version":"116.0.5845.187"},{"brand":"Not)A;Brand","version":"24.0.0.0"},{"brand":"Google Chrome","version":"116.0.5845.187"}],"mobile":false,"model":"","platform":"macOS","platformVersion":"13.5.2"}', true);

/**
 * Writes a heading underlined to its length, then each line.
 */
function printSection(string $heading, array $lines): void {
    echo $heading . "\n" . str_repeat('-', strlen($heading)) . "\n";
    foreach ($lines as $line) {
        echo $line . "\n";
    }
    echo "\n";
}

$headers = ghevToHTTPHeaders($ghev);

// 1. The origin server. A browser sends these headers itself once the
// server asks for them with the Accept-CH response header, which
// Utils::setResponseHeader sets from the pipeline's results, as the
// userAgentClientHints-Web.php example shows. Where the values were read
// with getHighEntropyValues() instead, they are sent in the same form.
printSection('HTTP headers for the origin server', $headers);

// 2. The cloud service, as headers. A request to the cloud service carries
// the headers as Evidence under header. keys, as the cloud request engine
// sends every header Evidence value it is given.
$headerEvidence = [];
foreach ($headers as $header) {
    [$name, $value] = explode(': ', $header, 2);
    $headerEvidence[] = 'header.' . strtolower($name) . ': ' . $value;
}
printSection('Evidence for the cloud service, as headers', $headerEvidence);

// 3. The cloud service, as the getHighEntropyValues() result itself. The
// JavaScript the JavaScript builder element serves calls
// getHighEntropyValues() and sends the result, as JSON encoded in base 64,
// under 51D_GetHighEntropyValues, and the cloud service converts it into
// the headers above. Set it as Evidence in the same way:
//
//     $flowData->evidence->set('query.51D_GetHighEntropyValues', $encoded);
$encoded = base64_encode(json_encode($ghev));
printSection(
    'Evidence for the cloud service, as the encoded getHighEntropyValues() result',
    ['query.51D_GetHighEntropyValues: ' . $encoded]
);

ExampleUtils::printFindOutMore(__FILE__);
