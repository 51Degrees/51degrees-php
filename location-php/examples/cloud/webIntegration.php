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

/** @example cloud/webIntegration.php
 *
 * @include{doc} example-web-integration-location.txt
 *
 * This example is available in full on [GitHub](https://github.com/51Degrees/location-php/blob/main/examples/cloud/webIntegration.php).
 *
 * @include{doc} example-require-resourcekey.txt
*/

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
    $message .= 'at https://configure.51degrees.com/v399y42f?utm_source=code&utm_medium=example&utm_campaign=location-php&utm_content=examples-cloud-webintegration.php&utm_term=resource-key-required' . '<br/>';
    $message .= 'Once complete, populate the environment variable ';
    $message .= 'mentioned at the start of this message with the key.' . '<br/>';
    $message .= "Make sure to include the Country, State, County, Town and ";
    $message .= "JavaScript properties as they are used by this example.<br />";
    echo $message;

    return;
}

// We create some settings for the JavaScriptBuilder,
// in this case an endpoint to call back to to retrieve additional
// properties populated by client side evidence
// this ?json endpoint is used later to serve results from a special
// json engine automatically included in the pipeline
$javascriptBuilderSettings = array(
    "endpoint" => "/?json"
);

// Make the Pipeline and add the JavaScript settings
$builder = new GeoLocationPipelineBuilder(array(
    "resourceKey" => $resourceKey,
    "locationProvider" => "fiftyonedegrees",
    "javascriptBuilderSettings" => $javascriptBuilderSettings
));

// To stop having to construct the pipeline
// and re-make cloud API requests used during construction on every page load,
// we recommend caching the serialized pipeline to a database or disk.
// Below we are using PHP's serialize and writing to a file if it doesn't exist

$serializedPipelineFile = __DIR__ . "web_integration_pipeline.pipeline";
if(!file_exists($serializedPipelineFile)){
    $pipeline = $builder->build();
    file_put_contents($serializedPipelineFile, serialize($pipeline));
} else {
    $pipeline = unserialize(file_get_contents($serializedPipelineFile));
}

// We create the flowData object that is used to add evidence to and read
// data from
$flowData = $pipeline->createFlowData();

// We set headers, cookies and more information from the web request
$flowData->evidence->setFromWebRequest();

// Now we process the flowData
$result = $flowData->process();

// Following the JavaScript bundler settings where we created an endpoint called
// ?json, we now use this endpoint (if called) to send back JSON to the client side

if (isset($_GET["json"])) {
    header('Content-Type: application/json');

    echo json_encode($flowData->jsonbundler->json);
    
    return;
}

// In the standard call we check if the required properties exist
$properties = $pipeline->getElement("location")->getProperties();

if (!isset($properties["country"]) || !isset($properties["state"]) || 
  !isset($properties["county"]) || !isset($properties["town"]) || 
  !isset($properties["javascript"])) {
    echo "Make sure to include the Country, State, County, Town and " .
        "JavaScript properties as they are used by this example.\n<br />";
    return;
}

echo "<p> The following values are determined sever-side on the first request. 
As the server has no location information to work from, 
these values will all be unknown:</p>";

echo "<dl>";

echo "<dt>";
echo "<strong>Country</strong>";
echo "</dt>";
echo "<dd>";

if ($result->location->country->hasValue) {
    echo $result->location->country->value;
} else {
    echo "Unknown (" . $result->location->country->noValueMessage . ")";
}

echo "</dd>";

echo "<dt>";
echo "<strong>State</strong>";
echo "</dt>";
echo "<dd>";
if ($result->location->state->hasValue) {
    echo $result->location->state->value;
} else {
    echo "Unknown (" . $result->location->state->noValueMessage . ")";
}
echo "</dd>";

echo "<dt>";
echo "<strong>County</strong>";
echo "</dt>";
echo "<dd>";
if ($result->location->county->hasValue) {
    echo $result->location->county->value;
} else {
    echo "Unknown (" . $result->location->county->noValueMessage . ")";
}
echo "</dd>";


echo "<dt>";
echo "<strong>Town/City</strong>";
echo "</dt>";
echo "<dd>";
if ($result->location->town->hasValue) {
    echo $result->location->town->value;
} else {
    echo "Unknown (" . $result->location->town->noValueMessage . ")";
}
echo "</dd>";

echo "</dl>";

echo "
<p>
    When the button below is clicked, JavaScript running on the client-side 
    will be used to obtain additional evidence (i.e. the location information 
    from the device). If no additional information appears then it may 
    indicate an external problem such as JavaScript being disabled in 
    your browser.
</p>
<p>
    Note that the accuracy of the information is dependent on the accuracy 
    of the location data returned by your device. Any device that lacks GPS 
    is likely to return a highly inaccurate result. Among devices with GPS, 
    some have a significantly lower margin of error than others.
</p>
<button type='button' onclick='buttonClicked()'>Use my location</button>";
echo "<dl>";

echo "<dt>";
echo "<strong>Country</strong>";
echo "</dt>";
echo "<dd id='countryclient'>";
echo "</dd>";

echo "<dt>";
echo "<strong>State</strong>";
echo "</dt>";
echo "<dd id='stateclient'>";
echo "</dd>";

echo "<dt>";
echo "<strong>County</strong>";
echo "</dt>";
echo "<dd id='countyclient'>";
echo "</dd>";

echo "<dt>";
echo "<strong>Town/City</strong>";
echo "</dt>";
echo "<dd id='townclient'>";
echo "</dd>";

// We get any JavaScript that should be placed in the page and run it.
// This will help provide client side evidence and make a request to the JSON
// endpoint to update extra properties using this evidence.

echo "<script>" . $flowData->javascriptbuilder->javascript . "</script>";

?>

<!-- 
Now we add some additional JavaScript 
that will update the client side properties above if values exist for them 
in the JSON endpoint provided data 
-->

<script>    
    buttonClicked = function () {
        // This function will fire when the JSON data object is updated
        // with information from the server.
        // The sequence is:
        // 1. Response contains JavaScript property 'JavaScript'. This is not executed 
        // immediately on the client as it will prompt the user to allow access to location.
        // 2. When the button is clicked, the fod.complete function is called, passing 'location' 
        // as the second parameter. This lets the code know that we want to execute
        // any JavaScript needed to obtain the location data that is needed to determine the 
        // user's postal address details.
        // 3. The execution of the location JavaScript triggers a background callback 
        // to the webserver that includes the new evidence (i.e. lat/lon).
        // 4. The web server responds with new JSON data that contains the updated 
        // property values based on the new evidence.
        // 5. The JavaScript integrates the new JSON data and fires the 'complete' 
        // callback function below, which then displays the results.
        fod.complete(function (data) {
            if(data.location){
                document.getElementById('countryclient').innerHTML = data.location.country;
                document.getElementById('stateclient').innerHTML = data.location.state;
                document.getElementById('countyclient').innerHTML = data.location.county;
                document.getElementById('townclient').innerHTML = data.location.town;
            } else {
                document.getElementById('countryclient').innerHTML = 'Location data is empty. This probably means that something has gone wrong with the JavaScript evaluation.';
            }
       }, 'location');
    }
 </script>
