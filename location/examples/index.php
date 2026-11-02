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



require(__DIR__ . "/../vendor/autoload.php");

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\geolocation\examples\cloud\classes\Constants;
use fiftyone\pipeline\geolocation\GeoLocationPipelineBuilder;
$params = array(
    "resourceKey" => $_ENV[Constants::RESOURCE_KEY_ENV_VAR],
    "locationProvider" => "fiftyonedegrees");

if ($params["resourceKey"] === "!!YOUR_RESOURCE_KEY!!") {
    $this->fail("You need to create a resource key at " .
    "https://configure.51degrees.com?utm_source=code&utm_medium=example&utm_campaign=location-php&utm_content=examples-index.php&utm_term=resource-key-required and paste it into the " .
    "phpunit.xml config file, " .
    "replacing !!YOUR_RESOURCE_KEY!!.");
}

$geolocationPipeline = new GeoLocationPipelineBuilder($params);

$geolocationPipeline = $geolocationPipeline->build();

$flowData = $geolocationPipeline->createFlowData();

$flowData->evidence->setFromWebRequest();

$pipelineResult = $flowData->process();

$javascript = $pipelineResult->get("javascriptbuilder")->get("javascript");

echo "<script>";
echo $javascript;
echo "</script>";
