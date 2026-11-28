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
// HumanConfidenceWebStandInTests. It answers the paths the Cloud Request
// Engine calls, and answers a resource key's JSON path with the members
// the cloud service gives such keys.
//
// A call that holds humanconfidence redeems it the way the cloud service
// answers in its derived member: HumanConfidenceSource is Active, and
// HumanConfidence is High where the call holds the License Key below and a
// sealed result made for its transaction, being "sealed-" followed by the
// transaction, or by nothing where the call holds none, and Medium for
// anything else.
//
// Each request's path and the names of its form fields are added to a
// file named after the stand-in's port in the temporary folder, so a test
// can see what was called.

// The License Key the stand-in redeems with.
const STAND_IN_LICENSE = 'standin-license';

// A License Key the stand-in refuses.
const STAND_IN_REFUSED_LICENSE = 'refused-license-WXYZ';

// The answer for each resource key.
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
        'humanconfidencenullreason' => 'HumanConfidence is not in the Basic product.',
        'humanconfidencesource' => null,
        'humanconfidencesourcenullreason' => 'HumanConfidenceSource is not in the Basic product.'
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
file_put_contents(
    sys_get_temp_dir() . '/hc-stand-in-' . (int) ($_SERVER['SERVER_PORT'] ?? 0) . '.log',
    $path . ' ' . implode(',', array_keys($_POST)) . "\n",
    FILE_APPEND | LOCK_EX
);
header('Content-Type: application/json');

// A License Key the cloud service refuses, naming it as its errors can.
if (($_POST['license'] ?? null) === STAND_IN_REFUSED_LICENSE) {
    http_response_code(400);
    echo json_encode(['errors' => ['The License Key ' . STAND_IN_REFUSED_LICENSE . ' is not valid.']]);

    return;
}

if ($path === '/api/v4/evidencekeys') {
    echo json_encode(['header.user-agent', 'query.client-ip']);

    return;
}
if (strcasecmp($path, '/api/v4/accessibleProperties') === 0) {
    echo json_encode(['Products' => ['derived' => [
        'DataTier' => 'stand-in',
        'Properties' => [
            ['Name' => 'HumanConfidence', 'Type' => 'String', 'Category' => 'Bot Detection'],
            ['Name' => 'HumanConfidenceSource', 'Type' => 'String', 'Category' => 'Bot Detection']
        ]
    ]]]);

    return;
}
if (preg_match('~^/api/v4/([^/]+)\.json$~', $path, $match) === 1 && isset($answers[$match[1]])) {
    $sealed = $_POST['humanconfidence'] ?? null;
    if (is_string($sealed) && $sealed !== '') {
        // A sealed result made with a transaction redeems only with it, and
        // one made with none only with none.
        $redeemed = ($_POST['license'] ?? null) === STAND_IN_LICENSE &&
            $sealed === 'sealed-' . (string) ($_POST['transaction'] ?? '');
        echo json_encode(['derived' => [
            'humanconfidence' => $redeemed ? 'High' : 'Medium',
            'humanconfidencesource' => 'Active'
        ]]);

        return;
    }
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
