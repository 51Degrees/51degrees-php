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
 * @example CloudEngine.php
 *
 * This example creates a cloud engine, being a flow element whose results
 * come from a call to a remote service. The Cloud Request Engine sends the
 * evidence to the service, and the star sign engine below reads its part
 * of the answer, so the engine gives the star sign for a date of birth
 * with no star sign logic of its own.
 *
 * The service is StarSignService.php beside this example, which answers in
 * the shape the 51Degrees cloud service does. The example starts it with
 * PHP's built-in web server on a free port of this machine and stops it at
 * the end, so the example needs no settings, no resource key and no
 * network. A cloud engine for a 51Degrees product leaves cloudEndPoint
 * unset, so that the Cloud Request Engine calls the 51Degrees cloud
 * service, and uses a resource key made in the 51Degrees configurator.
 *
 * Run it from the package folder after composer install.
 *
 *     php examples/CloudEngine.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\core\PipelineBuilder;

// The evidence holding the date of birth as YYYY-MM-DD, which is the
// evidence the star sign service asks for.
const DATE_OF_BIRTH = 'query.dateOfBirth';

// The Cloud Request Engine needs a resource key or a license key. The
// example's service takes any key.
const EXAMPLE_RESOURCE_KEY = 'starsign-example';

// How long to wait for the service to answer once it is started, and how
// long to wait between tries.
const SERVICE_START_SECONDS = 10;
const SERVICE_TRY_MICROSECONDS = 100000;

//! [class]
class StarSignCloudEngine extends CloudEngine
{
    // The results are under this key in the flow data, and the engine reads
    // the part of the service's answer under the same key. CloudEngine does
    // the rest, making a property of each value in that part, and a
    // property with no value of each value the service gives a reason for
    // instead.
    public string $dataKey = 'starsign';
}
//! [class]

/**
 * Starts StarSignService.php with PHP's built-in web server on a free port
 * of this machine, and waits until it answers.
 *
 * @return array{0: resource, 1: string} The server and its address
 * @throws Exception The service did not start
 */
function startStarSignService(): array
{
    // Port 0 asks for any free port, which the server then takes.
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $error);
    if ($socket === false) {
        throw new Exception("No free port was found. {$error}");
    }
    $address = (string) stream_socket_get_name($socket, false);
    fclose($socket);

    $nowhere = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $server = proc_open(
        [PHP_BINARY, '-S', $address, __DIR__ . '/StarSignService.php'],
        [0 => ['file', $nowhere, 'r'], 1 => ['file', $nowhere, 'w'], 2 => ['file', $nowhere, 'w']],
        $pipes
    );
    if (!is_resource($server)) {
        throw new Exception('The star sign service could not be started.');
    }

    [$host, $port] = explode(':', $address);
    $deadline = microtime(true) + SERVICE_START_SECONDS;
    // Until the service listens, each try gives a warning and false. The
    // last warning is the reason given when the service does not start.
    $reason = '';
    set_error_handler(
        static function (int $level, string $message) use (&$reason): bool {
            $reason = $message;
            return true;
        },
        E_WARNING
    );
    try {
        while (($connection = fsockopen(
            $host,
            (int) $port,
            $errorNumber,
            $error,
            SERVICE_START_SECONDS
        )) === false) {
            if (!proc_get_status($server)['running'] || microtime(true) > $deadline) {
                stopStarSignService($server);
                throw new Exception(
                    "The star sign service did not start at {$address}. {$reason}"
                );
            }
            usleep(SERVICE_TRY_MICROSECONDS);
        }
    } finally {
        restore_error_handler();
    }
    fclose($connection);

    return [$server, "http://{$address}/"];
}

/**
 * @param resource $server The server startStarSignService started
 */
function stopStarSignService($server): void
{
    proc_terminate($server);
    proc_close($server);
}

$status = 0;
try {
    [$server, $endPoint] = startStarSignService();
    echo "The star sign service is running at {$endPoint}.\n";
    try {
        //! [usage]
        // The Cloud Request Engine sends the evidence to the service named
        // by cloudEndPoint, and the star sign engine reads the answer, so
        // the Cloud Request Engine comes first in the pipeline.
        $pipeline = (new PipelineBuilder())
            ->add(new CloudRequestEngine([
                'resourceKey' => EXAMPLE_RESOURCE_KEY,
                'cloudEndPoint' => $endPoint
            ]))
            ->add(new StarSignCloudEngine())
            ->build();

        $dateOfBirth = '1992-12-18';
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set(DATE_OF_BIRTH, $dateOfBirth);
        $flowData->process();
        $starSign = $flowData->starsign->starsign;
        echo "With a date of birth of {$dateOfBirth}, the star sign is " .
            "{$starSign->value}.\n";

        // With no date of birth the service gives no value and says why.
        // Reading value where there is none raises an exception with the
        // same message, so check hasValue first.
        $flowData = $pipeline->createFlowData();
        $flowData->process();
        $starSign = $flowData->starsign->starsign;
        if (!$starSign->hasValue) {
            echo 'With no date of birth, the star sign has no value. ' .
                "{$starSign->noValueMessage}\n";
        }
        //! [usage]
    } finally {
        stopStarSignService($server);
        echo "The star sign service has stopped.\n";
    }
} catch (Throwable $e) {
    echo "The example stopped. {$e->getMessage()}\n";
    $status = 1;
}

// Find out more, the links for the person running this example to follow
// up.
$findOutMore = [
    'This example' =>
        'https://github.com/51Degrees/51degrees-php/tree/main/pipeline/cloudrequestengine',
    'A cloud engine for device detection' =>
        'https://github.com/51Degrees/51degrees-php/tree/main/device-detection/cloud',
    'The Pipeline API specification' =>
        'https://github.com/51Degrees/specifications/tree/main/pipeline-specification',
    'Creating a cloud engine' =>
        'https://51degrees.com/documentation/_pipeline_api__examples__custom_element__cloud.html?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-cloudengine.php&utm_term=find-out-more-cloud-engine',
    'Contact us' =>
        'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-cloudengine.php&utm_term=find-out-more-contact'
];
echo "\nFind out more\n-------------\n";
foreach ($findOutMore as $label => $url) {
    echo "{$label}: {$url}\n";
}
exit($status);
