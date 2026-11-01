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
* @example cloud/configurefromfile.php
* This example shows how to configure a pipeline from a configuration file
* using the pipelinebuilder's buildFromConfig method.
*
* This example is available in full on [GitHub](https://github.com/51Degrees/location-php/blob/master/examples/cloud/configurefromfile.php).
* To run this example, you will need to create a **resource key**.
* The resource key is used as short-hand to store the particular set of
* properties you are interested in as well as any associated license keys
* that entitle you to increased request limits and/or paid-for properties.
* You can create a resource key using the 51Degrees [Configurator](https://configure.51degrees.com/gGc3T5pT?utm_source=code&utm_medium=example&utm_campaign=location-php&utm_content=examples-cloud-configurefromfile.php&utm_term=header). 
* 
* This example uses the 'Country' property, which is pre-populated 
* when creating a key using the link above.
* 
* The configuration file used here is:
*
* ```
*{
*  "PipelineOptions": {
*    "Elements": [
*      {
*        "BuilderName": "fiftyone\\pipeline\\cloudrequestengine\\CloudRequestEngine",
*        "BuildParameters": {
*          "resourceKey": "!!YOUR_RESOURCE_KEY!!"
*        }
*      },
*      {
*        "BuilderName": "fiftyone\\pipeline\\geolocation\\GeoLocationCloud"
*      }
*    ]
*  }
* }
* ```
*
* Expected output
* ```
* Which country is the location [51.458048,-0.9822207999999999] in?
* UK
* ```
*/

require(__DIR__ . "/../../vendor/autoload.php");

use fiftyone\pipeline\core\PipelineBuilder;

$builder = new PipelineBuilder();

$configFile = __DIR__ . "/pipeline.json";

// Check if config file exists
if (!file_exists($configFile)) {
    echo "Config file not found at " . $configFile;
    return;
}

// Check if config file resource key has been filled in
$configFileJSON = json_decode(file_get_contents($configFile), true);

$resourceKey = $configFileJSON["PipelineOptions"]["Elements"][0]["BuildParameters"]["resourceKey"];
if (substr($resourceKey, 0, 2) === "!!") {
    echo "You need to create a resource key at " .
    "https://configure.51degrees.com/gGc3T5pT?utm_source=code&utm_medium=example&utm_campaign=location-php&utm_content=examples-cloud-configurefromfile.php&utm_term=resource-key-required and paste it into " . 
    "your config file, replacing !!YOUR_RESOURCE_KEY!!.";
    return;
}

$pipeline = $builder->buildFromConfig($configFile);

$flowData = $pipeline->createFlowData();

$latitude = "51.458048";
$longitude = "-0.9822207999999999";

// Example longitude and latitude data to look up
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
