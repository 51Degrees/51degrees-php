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
 * @example cloud/gettingStartedWeb.php
 *
 * @include{doc} example-getting-started-web.txt
 *
 * This example is available in full on [GitHub](https://github.com/51Degrees/51degrees-php/blob/main/device-detection/cloud/examples/cloud/gettingStartedWeb.php).
 *
 * @include{doc} example-require-resourcekey.txt
 *
 * Required Composer Dependencies:
 * - [51degrees/fiftyone.devicedetection](https://packagist.org/packages/51degrees/fiftyone.devicedetection)
 *
 * ## Overview
 *
 * The `DeviceDetectionPipelineBuilder` class is used to create a Pipeline instance from the configuration
 * that is supplied.
 * The fiftyone\pipeline\core\Utils module contains helpers which deal with
 * automatically populating evidence from a web request.
 * ```{php}
 * $flowdata->evidence->setFromWebRequest()
 * ```
 *
 * The module can also handling setting response headers (e.g. Accept-CH for User-Agent
 * Client Hints) and serving requests for client-side JavaScript and JSON resources.
 * ```{php}
 * Utils::setResponseHeader($flowdata);
 * ```
 *
 * The results of detection can be accessed by through the flowdata object once
 * processed. This can then be used to interrogate the data.
 * ```{php}
 * $flowdata->process();
 * $device = $flowdata->device;
 * $hardwareVendor = $device->hardwarevendor;
 * ```
 *
 * Results can also be accessed in client-side code by using the `fod` object. See the
 * [JavaScriptBuilderElement](https://51degrees.com/pipeline-php/4.5/classfiftyone_1_1pipeline_1_1core_1_1_javascript_builder_element.html?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-cloud-classes-gettingstartedweb.php&utm_term=header)
 * for details on available settings such as changing the `fod` name.
 * ```{js}
 * window.onload = function () {
 *     fod.complete(function(data) {
 *         var hardwareName = data.device.hardwarename;
 *         alert(hardwareName.join(", "));
 *     }
 * }
 * ```
 *
 * ## View
 * @include static/page.php
 *
 * ## Class
 */

namespace fiftyone\pipeline\devicedetection\examples\cloud\classes;

use fiftyone\pipeline\core\Utils;
use fiftyone\pipeline\devicedetection\DeviceDetectionPipelineBuilder;

class GettingStartedWeb
{
    public function run($resourceKey, $logger, callable $output)
    {
        $javascriptBuilderSettings = [
            'endpoint' => '/json',
            'minify' => true,
            // The enableCookies setting is needed if you want to work with results from client-side
            // evidence on the server. For example, precise Apple models or screen dimensions.
            // This will store the results of client-side detection scripts on the client as cookies.
            // On subsequent requests, these cookies will be included in the payload and will be
            // used by the device detection API when it runs.
            'enableCookies' => true
        ];

        $builderSettings = [
            'resourceKey' => $resourceKey,
            'javascriptBuilderSettings' => $javascriptBuilderSettings,
            // Set to true so that if the underlying cloud service fails during request
            // processing the pipeline degrades gracefully instead of returning a 500.
            // Use false while developing to surface mistakes loudly.
            'suppressProcessExceptions' => true];
        // If a cloud endpoint is set in the environment, point the pipeline at it.
        $cloudEndpoint = ExampleUtils::getCloudEndpoint();
        if ($cloudEndpoint) {
            $builderSettings['cloudEndPoint'] = $cloudEndpoint;
        }
        $builder = new DeviceDetectionPipelineBuilder($builderSettings);

        // PHP keeps nothing from one request to the next, so the pipeline is
        // built for each request. A pipeline saved to a file and read back
        // would keep the resource key it was built with.
        $pipeline = $builder->build();

        $this->processRequest($pipeline, $output);
    }

    private function processRequest($pipeline, callable $output)
    {
        // The .NET and Java APIs have web integration packages which intercept
        // requests for the pipeline resources. There is no equivalent for PHP, and
        // the built-in PHP server routes every request to this single script, so
        // the example dispatches on the request path itself.
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if ($path === '/51Degrees.core.js' || $path === '/json') {
            // Create the flowdata object, and add any information from the
            // request (headers, cookies and additional client side provided
            // information).
            $flowdata = $pipeline->createFlowData();
            $flowdata->evidence->setFromWebRequest();

            // Serve the client-side JavaScript built by the JavaScriptBuilder
            // element at /51Degrees.core.js, and at /json the JSON encoded
            // property database the client side calls for, using any
            // additional evidence provided by the client. The path of the
            // script matches the one used by the .NET, Java and Rust examples
            // so that the page can reference the script by name instead of
            // inlining it. Utils processes the flowdata itself and sets the
            // cache headers, so a browser revalidating its copy gets a 304
            // without any processing.
            $response = $path === '/json'
                ? Utils::jsonResponse($flowdata)
                : Utils::javascriptResponse($flowdata);
            $response->send($output);

            return;
        }

        // Create the flowdata object, add the evidence from the request and
        // process it. Utils keeps the processed flowdata for the rest of the
        // request, so any other part of the application can read the results
        // with Utils::getFlowData() without being handed it.
        $flowdata = Utils::processWebRequest($pipeline);

        // Some browsers require that extra HTTP headers are explicitly
        // requested. So set whatever headers are required by the browser in
        // order to return the evidence needed by the pipeline.
        // More info on this can be found at
        // https://51degrees.com/blog/user-agent-client-hints?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-cloud-classes-gettingstartedweb.php&utm_term=processrequest
        Utils::setResponseHeader($flowdata);

        include_once __DIR__ . '/../static/page.php';
    }
}
