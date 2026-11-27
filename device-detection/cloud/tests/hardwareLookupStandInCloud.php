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

// A stand-in for the cloud service, served with PHP's built-in server for
// HardwareLookupStandInTests. It answers the accessible properties as the
// cloud service does for each resource key below, the evidence keys, a
// lookup for a key with the hardware lookup with no devices, and anything
// else with 404. It writes each request to standard error after
// REQUEST_MARKER, so a test can see what was asked.

const REQUEST_MARKER = 'Stand-in request: ';

// The resource keys whose accessible properties request fails with 500,
// the first time it is made or every time, and the error the stand-in
// gives. Any other key, and FAILS_ONCE_KEY after its failure, lacks the
// hardware lookup.
const FAILS_ONCE_KEY = 'fails-once-key';
const FAILS_ALWAYS_KEY = 'fails-always-key';
const FAILURE = 'Stand-in failure';

// The resource keys that have the hardware lookup, whose lookups match no
// device. The accessible properties request for the second fails with 500
// the first time it is made.
const NO_DEVICES_KEY = 'no-devices-key';
const FAILS_ONCE_WITH_LOOKUP_KEY = 'fails-once-with-lookup-key';

// The resource keys that have the hardware lookup.
const KEYS_WITH_LOOKUP = [NO_DEVICES_KEY, FAILS_ONCE_WITH_LOOKUP_KEY];

/**
 * Whether the accessible properties request for the key fails this time.
 * The first failure for a key that fails once is recorded in a file named
 * after the key in the document root, which each test makes empty.
 */
function failsNow(string $key): bool
{
    if ($key === FAILS_ALWAYS_KEY) {
        return true;
    }
    if ($key !== FAILS_ONCE_KEY && $key !== FAILS_ONCE_WITH_LOOKUP_KEY) {
        return false;
    }
    $failed = $_SERVER['DOCUMENT_ROOT'] . "/{$key}-failed";
    if (file_exists($failed)) {
        return false;
    }
    touch($failed);

    return true;
}

// The device product the cloud service lists for every key, cut down to
// one property. A key without the hardware lookup has only this product.
$device = [
    'DataTier' => 'CloudV5Basic',
    'Properties' => [[
        'Name' => 'IsMobile',
        'Type' => 'Boolean',
        'Category' => 'Device',
        'DelayExecution' => false
    ]]
];

// The hardware product the cloud service lists for a key that has the
// hardware lookup, cut down to the properties the examples print.
$hardware = [
    'DataTier' => 'CloudV5Bespoke',
    'Properties' => [[
        'Name' => 'Profiles',
        'Type' => 'Array',
        'Category' => '',
        'ItemProperties' => [
            [
                'Name' => 'HardwareVendor',
                'Type' => 'String',
                'Category' => 'Name',
                'DelayExecution' => false,
                'EvidenceProperties' => []
            ],
            [
                'Name' => 'HardwareModel',
                'Type' => 'String',
                'Category' => 'Name',
                'DelayExecution' => false,
                'EvidenceProperties' => []
            ],
            [
                'Name' => 'HardwareName',
                'Type' => 'Array',
                'Category' => 'Name',
                'DelayExecution' => false,
                'EvidenceProperties' => []
            ]
        ],
        'DelayExecution' => false,
        'EvidenceProperties' => []
    ]]
];

// Some of the evidence keys the cloud service lists, including the ones
// the TAC and native model lookups send.
$evidenceKeys = [
    'fiftyone.resource-key',
    'header.User-Agent',
    'query.User-Agent',
    'query.tac',
    'query.nativemodel'
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
file_put_contents('php://stderr', REQUEST_MARKER . "{$method} {$uri}\n");

$path = strtolower((string) parse_url($uri, PHP_URL_PATH));
header('Content-Type: application/json');

if ($method === 'GET' && $path === '/api/v4/accessibleproperties') {
    $key = (string) ($_GET['resource'] ?? '');
    if (failsNow($key)) {
        http_response_code(500);
        echo json_encode(['errors' => [FAILURE]]);

        return;
    }
    $products = ['device' => $device];
    if (in_array($key, KEYS_WITH_LOOKUP, true)) {
        $products['hardware'] = $hardware;
    }
    echo json_encode(['Products' => $products]);

    return;
}
if ($method === 'GET' && $path === '/api/v4/evidencekeys') {
    echo json_encode($evidenceKeys);

    return;
}
// The cloud service's answer to a lookup that matches no device.
if ($method === 'POST' &&
    preg_match('~^/api/v4/([^/]+)\.json$~', $path, $lookup) === 1 &&
    in_array($lookup[1], KEYS_WITH_LOOKUP, true)) {
    echo json_encode(['hardware' => ['profiles' => []]]);

    return;
}
http_response_code(404);
echo json_encode(['errors' => ['Not found']]);
