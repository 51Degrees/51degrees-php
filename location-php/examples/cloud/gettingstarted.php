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
 * @example cloud/gettingStarted.php
 * This example shows how a simple location pipeline can be built
 * that looks up an address from longitude and latitude
 *
 * This example is available in full on [GitHub](https://github.com/51Degrees/location-php/blob/main/examples/cloud/gettingstarted.php).
 *
 * To run this example, you will need to create a **resource key**.
 * The resource key is used as short-hand to store the particular set of
 * properties you are interested in as well as any associated license keys
 * that entitle you to increased request limits and/or paid-for properties.
 * You can create a resource key using the 51Degrees [Configurator](https://configure.51degrees.com/gGc3T5pT?utm_source=code&utm_medium=example&utm_campaign=location-php&utm_content=examples-cloud-gettingstarted.php&utm_term=header).
 * 
 * This example uses the 'Country' property, which is pre-populated 
 * when creating a key using the link above.
 * 
 * Expected output
 * ```
 * Which country is the location [51.458048,-0.9822207999999999] in?
 * UK
 *
 * ```
 *
 */

// First we include the GeolocationPipelineBuilder

require(__DIR__ . "/../../vendor/autoload.php");

use fiftyone\pipeline\geolocation\examples\cloud\classes\Constants;
use fiftyone\pipeline\geolocation\GeoLocationPipelineBuilder;

// Check if there is a resource key in the environment variable and use
// it if there is one. (This is used for automated testing)
if (isset($_ENV[Constants::RESOURCE_KEY_ENV_VAR])) {
    $resourceKey = $_ENV[Constants::RESOURCE_KEY_ENV_VAR];
} else {
    $resourceKey = "!!YOUR_RESOURCE_KEY!!";
}

if (substr($resourceKey, 0, 2) === "!!") {
    $message = 'No resource key specified in the environment variable ';
    $message .= '"' . Constants::RESOURCE_KEY_ENV_VAR . '"' . '<br/>';
    $message .= 'Create a resource key with the properties required by this example';
    $message .= 'at https://configure.51degrees.com/gGc3T5pT?utm_source=code&utm_medium=example&utm_campaign=location-php&utm_content=examples-cloud-gettingstarted.php&utm_term=resource-key-required' . '<br/>';
    $message .= 'Once complete, populate the environment variable ';
    $message .= 'mentioned at the start of this message with the key.' . '<br/>';
    echo $message;

    return;
}

// Create settings for the pipeline builder
// This includes:
// * The resource key
// * The location provider: "digitalelement" or "fiftyonedegrees" 
// (If digital element is used, you will need a resource key that allows you 
// access to the appropriate property. You can do this via the following
// link https://configure.51degrees.com/QMWqcX9n?utm_source=code&utm_medium=example&utm_campaign=location-php&utm_content=examples-cloud-gettingstarted.php&utm_term=top)
$settings = array(
    "resourceKey" => $resourceKey,
    "locationProvider" => "fiftyonedegrees"
);

$builder = new GeoLocationPipelineBuilder($settings);

// We then create a pipeline with the builder. 
$pipeline = $builder->build();

$flowData = $pipeline->createFlowData();

// Example longitude and latitude data to look up
$latitude = "51.458048";
$longitude = "-0.9822207999999999";

$flowData->evidence->set('query.51D_Pos_latitude', $latitude);
$flowData->evidence->set('query.51D_Pos_longitude', $longitude);

$result = $flowData->process();

// Show all the properties in the device element

$country = $flowData->location->country;

if ($country->hasValue) {
    echo "Which country is the location [" . $latitude . "," . $longitude . "] in?";
    echo "<br />";
    echo $flowData->location->country->value;
} else {
    // Echo out why the value isn't meaningful
    echo $country->noValueMessage;
}
