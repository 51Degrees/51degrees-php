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

/**
 * @example onpremise/failureToMatch.php
 *
 * This example shows how the hasValue function can help make sure
 * that meaningful values are returned when checking properties
 * returned from the device detection engine.
 *
 * This example is available in full on [GitHub](https://github.com/51Degrees/51degrees-php/blob/main/device-detection/onpremise/examples/onpremise/failureToMatch.php).
 *
 * In this example we create an on premise 51Degrees device detection pipeline,
 * in order to do this you will need a copy of the 51Degrees on-premise library
 * and need to make the following additions to your php.ini file
 *
 * ```
 * FiftyOneDegreesHashEngine.data_file = // location of the data file
 * FiftyOneDegreesHashEngine.allow_unmatched = false
 * ```
 *
 * When running under a process manager such as Apache MPM or php-fpm, the
 * engine must load the whole data file into memory. This is now enforced
 * automatically (the MaxPerformance profile is always used), so no extra
 * configuration is required. More details can be found in <a href="https://github.com/51Degrees/51degrees-php/blob/main/device-detection/onpremise/readme.md">README</a>.
 *
 * Expected output:
 *
 * ```
 * Does User-Agent 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114' represent a mobile device?:
 * Yes
 *
 * Does User-Agent 'nonsense ua...' represent a mobile device?:
 * We don't know for sure. The reason given is:
 * The results contained a null profile for the component which the required property belongs to.
 * ```
 *
 * Each lookup also shows the Hardware Vendor, which only the Enterprise data
 * file has. With the free Lite data file the example says it needs the
 * Enterprise data file and gives a link to ask for a copy.
 */

// First we include the deviceDetectionPipelineBuilder

require __DIR__ . '/../../vendor/autoload.php';

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise;
use fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils;

// The example uses the engine the extension builds once, when PHP starts,
// from the FiftyOneDegreesHashEngine.data_file php.ini setting. Without it,
// the example runs again in a PHP started with a data file, the free Lite
// file unless another is named, downloaded when it is missing.
ExampleUtils::startWithEngine($argv ?? []);

$device = new DeviceDetectionOnPremise();

$pipeline = new PipelineBuilder();

$pipeline = $pipeline->add($device)->build();

// Here we create a function that checks if a supplied User-Agent is a
// mobile device

function failuretomatch_checkifmobile($pipeline, $userAgent = '')
{
    // We create the flowData object that is used to add evidence to and read data from.
    // It is closed in the finally block, which releases the results of the
    // detection straight away.
    $flowData = $pipeline->createFlowData();
    try {
        // Add the User-Agent as evidence

        $flowData->evidence->set('header.user-agent', $userAgent);

        // Now we process the flowData
        $result = $flowData->process();

        // First we check if the property we're looking for has a meaningful result

        echo "Does User-Agent '<b>" . $userAgent . "</b>' represent a mobile device?:";
        echo "</br>\n";

        if ($result->device->ismobile->hasValue) {
            if ($result->device->ismobile->value) {
                echo 'Yes';
            } else {
                echo 'No';
            }
        } else {
            echo "We don't know for sure. The reason given is:";
            echo "</br>\n";
            // If it doesn't have a meaningful result, we echo out the reason why
            // it wasn't meaningful
            echo $result->device->ismobile->noValueMessage;
            echo "</br>\n";
        }

        // HardwareVendor is only in the Enterprise data file. The value is
        // read only when the data file in use has the property.
        echo "</br>\n";
        echo 'Hardware Vendor: ' . ExampleUtils::getEnterpriseValue(
            $pipeline->getElement('device'),
            $result->device,
            'hardwarevendor'
        );

        echo "</br>\n";
        echo "</br>\n";
    } finally {
        $flowData->close();
    }
}

// Some example User-Agents to test. The pipeline is closed in the finally
// block once they have been.

try {
    $iPhoneUA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114';
    failuretomatch_checkifmobile($pipeline, $iPhoneUA);

    $badUserAgent = 'nonsense ua...';
    failuretomatch_checkifmobile($pipeline, $badUserAgent);

    if (!ExampleUtils::hasProperty($device, 'hardwarevendor')) {
        echo ExampleUtils::getEnterpriseNote(ExampleUtils::getTaggedLink(
            'contact-us',
            __FILE__,
            'enterprise-data-file'
        ));
        echo "</br>\n";
    }
} finally {
    $pipeline->close();
}

ExampleUtils::printFindOutMore(__FILE__);
