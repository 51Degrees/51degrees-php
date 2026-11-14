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
 * @example onpremise/manualDataUpdate.php
 *
 * This example shows how to get the device detection engine to refresh
 * its internal data structures when a new data file is available.
 *
 * This can be done asynchronously, without the need to restart the machine.
 *
 * This example is available in full on [GitHub](https://github.com/51Degrees/51degrees-php/blob/main/device-detection/onpremise/examples/onpremise/manualDataUpdate.php).
 *
 * In this example we create an on premise 51Degrees device detection pipeline.
 * In order to do this you will need a copy of the 51Degrees on-premise library
 * and need to make the following additions to your php.ini file
 *
 * ```
 * FiftyOneDegreesHashEngine.data_file = // location of the data file
 * ```
 *
 * When running under a process manager such as Apache MPM or php-fpm, the
 * engine must load the whole data file into memory. This is now enforced
 * automatically (the MaxPerformance profile is always used), so no extra
 * configuration is required. More details can be found in <a href="https://github.com/51Degrees/51degrees-php/blob/main/device-detection/onpremise/readme.md">README</a>.
 *
 * Under process managers, refreshing internal data will not work as it is required
 * to be performed on both main process and child processes. There is not a proven
 * solution to do so yet, so we recommend a full server restart to be performed instead.
 *
 * Expected output:
 *
 * ```
 * Is User-Agent 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114' a mobile?
 * true
 * Is User-Agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36' a mobile?
 * false
 * Reloading data file...
 * Is User-Agent 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114' a mobile?
 * true
 * Is User-Agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36' a mobile?
 * false
 * ```
 *
 * Each lookup also shows the Hardware Vendor, which only the Enterprise data
 * file has. With the free Lite data file the example says it needs the
 * Enterprise data file, which also brings daily automatic updates, and gives
 * a link to ask for a copy.
 */

require __DIR__ . '/../../vendor/autoload.php';

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise;
use fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils;

// The example uses the engine the extension builds once, when PHP starts,
// from the FiftyOneDegreesHashEngine.data_file php.ini setting. Without it,
// the example runs again in a PHP started with a data file, the free Lite
// file unless another is named, downloaded when it is missing.
\fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils::startWithEngine($argv ?? []);

$deviceEngine = new DeviceDetectionOnPremise();

$pipeline = new PipelineBuilder();

$pipeline = $pipeline->add($deviceEngine)->build();

// Here we create a function that checks if a supplied User-Agent is a mobile device
function manualDataUpdate_checkifmobile($pipeline, $userAgent = '')
{
    // We create the flowData object that is used to add evidence to and read data from.
    // It is closed in the finally block, which releases the results of the
    // detection straight away. Results that are not released keep the data
    // they were read from in memory, even after the data has been refreshed.
    $flowData = $pipeline->createFlowData();
    try {
        // Add the User-Agent as evidence

        $flowData->evidence->set('header.user-agent', $userAgent);

        // Now we process the flowData
        $result = $flowData->process();

        // First we check if the property we're looking for has a meaningful result

        echo "Is User-Agent '<b>" . $userAgent . "</b>' a mobile device?:";
        echo "</br>\n";

        if ($result->device->ismobile->hasValue) {
            if ($result->device->ismobile->value) {
                echo 'Yes';
            } else {
                echo 'No';
            }
        } else {
            // If it doesn't have a meaningful result, we echo out the reason why
            // it wasn't meaningful
            echo $result->device->ismobile->noValueMessage;
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
    $desktopUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36';
    $iPhoneUA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114';

    manualDataUpdate_checkifmobile($pipeline, $desktopUA);
    manualDataUpdate_checkifmobile($pipeline, $iPhoneUA);
    echo 'Reloading data file...';
    // Update the device detection engine with the data file from the
    // same location.
    // In this example, we haven't actually copied a new data file in,
    // so this will have no effect.
    // In practice, this function should be called after the data file
    // on disk has been overwritten with a newer one.
    // There are variations, which allow refreshing from a different
    // file path or from an in-memory representation of the data file.
    $deviceEngine->refreshData();
    manualDataUpdate_checkifmobile($pipeline, $desktopUA);
    manualDataUpdate_checkifmobile($pipeline, $iPhoneUA);

    // Daily automatic updates come with the Enterprise data file.
    if (!ExampleUtils::hasProperty($deviceEngine, 'hardwarevendor')) {
        echo 'This example reloaded the free Lite data file. The Enterprise ' .
            'data file adds daily automatic updates. ';
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
