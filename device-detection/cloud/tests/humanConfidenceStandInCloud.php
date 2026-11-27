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
// key's JSON path with the members the cloud service gives such keys.

// The answer for each resource key. The cloud service also gives a null
// reason beside a null value, which the example does not read, so the
// stand-in leaves it out.
$answers = [
    // A key whose products offer HumanConfidenceSource, such as Pro or
    // Bespoke, asked before any browser tests have run.
    'source-key' => ['derived' => [
        'humanconfidence' => 'Medium',
        'humanconfidencesource' => 'Passive'
    ]],
    // A Basic key, as the Basic product offers neither property.
    'basic-key' => ['derived' => [
        'humanconfidence' => null,
        'humanconfidencesource' => null
    ]],
    // A key whose own property list holds HumanConfidence alone, as keys
    // made before the source existed do. Its products offer the source, so
    // a request that names it gets it, and one that does not gets no
    // source member.
    'level-only-key' => ['derived' => [
        'humanconfidence' => 'Medium'
    ]]
];

// The source each key gets when the request names it.
$named = [
    'level-only-key' => ['humanconfidencesource' => 'Passive']
];

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
header('Content-Type: application/json');

if ($path === '/api/v4/evidencekeys') {
    echo json_encode(['header.user-agent', 'query.client-ip']);

    return;
}
if (preg_match('~^/api/v4/([^/]+)\.json$~', $path, $match) === 1 && isset($answers[$match[1]])) {
    $answer = $answers[$match[1]];
    $values = array_map('strtolower', array_map('trim', explode(',', (string) ($_POST['values'] ?? ''))));
    if (isset($named[$match[1]]) && in_array('derived.humanconfidencesource', $values, true)) {
        $answer['derived'] += $named[$match[1]];
    }
    echo json_encode($answer);

    return;
}
http_response_code(404);
echo json_encode(['errors' => ['Not found']]);
