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
// cloud service does for a resource key without the hardware lookup, and
// the evidence keys, and anything else with 404. It writes each request to
// standard error after REQUEST_MARKER, so a test can see what was asked.

const REQUEST_MARKER = 'Stand-in request: ';

// The cloud service's answer for a resource key with no hardware product,
// cut down to one device property.
$accessibleProperties = [
    'Products' => [
        'device' => [
            'DataTier' => 'CloudV5Basic',
            'Properties' => [[
                'Name' => 'IsMobile',
                'Type' => 'Boolean',
                'Category' => 'Device',
                'DelayExecution' => false
            ]]
        ]
    ]
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
    echo json_encode($accessibleProperties);

    return;
}
if ($method === 'GET' && $path === '/api/v4/evidencekeys') {
    echo json_encode($evidenceKeys);

    return;
}
http_response_code(404);
echo json_encode(['errors' => ['Not found']]);
