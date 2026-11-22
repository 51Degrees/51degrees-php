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
// HumanConfidenceWebStandInTests. It answers the paths the Human
// Confidence web example calls in the passive step, and answers a resource
// key's JSON path in the shape the cloud service gives that kind of key.

// The answer for each resource key.
$answers = [
    // A key in the Bespoke product, asked before any browser tests have run.
    'bespoke-key' => ['derived' => [
        'humanconfidence' => 'Medium',
        'humanconfidencesource' => 'Passive'
    ]],
    // A key outside the Bespoke product that has neither property.
    'outside-bespoke-key' => ['derived' => [
        'humanconfidence' => null,
        'humanconfidencenullreason' => 'The key does not give this property a value.',
        'humanconfidencesource' => null,
        'humanconfidencesourcenullreason' => 'The key does not give this property a value.'
    ]],
    // A key outside the Bespoke product that has the level and no source.
    'outside-bespoke-level-key' => ['derived' => [
        'humanconfidence' => 'Medium',
        'humanconfidencesource' => null,
        'humanconfidencesourcenullreason' => 'The key does not give this property a value.'
    ]]
];

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
header('Content-Type: application/json');

if ($path === '/api/v4/evidencekeys') {
    echo json_encode(['header.user-agent', 'query.client-ip']);

    return;
}
if (preg_match('~^/api/v4/([^/]+)\.json$~', $path, $match) === 1 && isset($answers[$match[1]])) {
    echo json_encode($answers[$match[1]]);

    return;
}
http_response_code(404);
echo json_encode(['errors' => ['Not found']]);
