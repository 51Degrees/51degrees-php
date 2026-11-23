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
 * A page for the Accept-CH tests in UACHCloudTests. It asks the cloud
 * service for the one property named in the 'property' query parameter, with
 * the license key in _51DEGREES_LICENSE_KEY_BESPOKE and no resource key, and
 * sets the response headers the answer asks for, as the User Agent Client
 * Hints example does. A failure answers 500 with its message, which the cloud
 * request engine has masked any license key in, so the test can show it.
 *
 * Run it with the PHP built-in server, from the package folder:
 *
 *     php -S localhost:3001 tests/accept-ch-license-server.php
 */

require __DIR__ . '/../vendor/autoload.php';

use fiftyone\pipeline\core\Utils;
use fiftyone\pipeline\devicedetection\DeviceDetectionPipelineBuilder;

$licenseKey = getenv('_51DEGREES_LICENSE_KEY_BESPOKE');
$property = $_GET['property'] ?? '';

if ($licenseKey === false || $licenseKey === '' || $property === '') {
    http_response_code(400);
    echo 'Set _51DEGREES_LICENSE_KEY_BESPOKE and name a property in the '
        . "'property' query parameter.";

    return;
}

try {
    $settings = [
        'licenseKey' => $licenseKey,
        'requestedProperties' => [$property],
        'suppressProcessExceptions' => false
    ];
    $endpoint = getenv('_51DEGREES_CLOUD_ENDPOINT');
    if ($endpoint !== false && $endpoint !== '') {
        $settings['cloudEndPoint'] = $endpoint;
    }
    $pipeline = (new DeviceDetectionPipelineBuilder($settings))->build();
    $flowData = $pipeline->createFlowData();
    $flowData->evidence->setFromWebRequest();
    $flowData->process();
    Utils::setResponseHeader($flowData);
    echo 'Asked for ' . htmlspecialchars($property);
} catch (\Throwable $e) {
    http_response_code(500);
    echo $e->getMessage();
}
