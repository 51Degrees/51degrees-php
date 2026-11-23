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

namespace fiftyone\pipeline\geolocation\tests;

// Fake remote address for web integration
$_SERVER['REMOTE_ADDR'] = '0.0.0.0';

use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\geolocation\examples\cloud\classes\EnvironmentVariable;
use fiftyone\pipeline\geolocation\GeoLocationPipelineBuilder;
use fiftyone\pipeline\geolocation\tests\classes\TestKeys;
use PHPUnit\Framework\TestCase;

class GeoLocationTests extends TestCase
{
    /**
     * The timeout a test gives a request to the cloud service. It is far
     * above anything measured on a runner, because a test must not fail
     * when the service or the network is slow. The package's own default of
     * 2 seconds, which the specification sets, is left as it is.
     */
    private const CLOUD_TIMEOUT_SECONDS = 30;

    protected $iPhoneUA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114';
    protected $testLat = '51.4578261';
    protected $testLon = '-0.975922996290084';

    // For some reason, the value of the 'county' property returned
    // by the call to the cloud is always null on the build agent,
    // despite working correctly locally.
    // After spending the best part of a day trying to resolve this,
    // we've  decided to exclude the county property from this test
    // for the moment.
    protected $expectedProperties = [
        'location' => [
            'building',
            'streetnumber',
            'road',
            'town',
            // "county",
            'region',
            'state',
            'zipcode',
            'country',
            'countrycode',
            'javascript'
        ],
        'location_digitalelement' => [
            'town',
            'county',
            'region',
            'state',
            'zipcode',
            'country',
            'countrycode',
            'javascript'
        ]
    ];

    public function testAvailableProperties51Degrees()
    {
        $pipeline = new GeoLocationPipelineBuilder([
            'resourceKey' => $this->getResourceKey(),
            'timeoutSeconds' => self::CLOUD_TIMEOUT_SECONDS,
            'locationProvider' => 'fiftyonedegrees'
        ]);

        $pipeline = $pipeline->build();

        $flowData = $pipeline->createFlowData();

        $flowData->evidence->set('query.51D_Pos_latitude', $this->testLat);
        $flowData->evidence->set('query.51D_Pos_longitude', $this->testLon);

        $result = $flowData->process();

        $this->availableProperties(
            $result->location,
            $pipeline->getElement('location')->getProperties(),
            'location'
        );
    }

    public function testValueTypes51Degrees()
    {
        $this->testValueTypes('fiftyonedegrees', 'location');
    }

    private function getResourceKey()
    {
        $resourceKey = EnvironmentVariable::read(
            TestKeys::RESOURCE_KEY_VARIABLE
        );

        if ($resourceKey === '') {
            $this->fail('Set ' . TestKeys::RESOURCE_KEY_VARIABLE . ' to a ' .
            'resource key created at https://51degrees.com/configure?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=tests-geolocationtests.php&utm_term=resource-key-required ' .
            'to run these tests.');
        }

        return $resourceKey;
    }

    private function testValueTypes($locationProvider, $elementKey)
    {
        $pipeline = new GeoLocationPipelineBuilder([
            'resourceKey' => $this->getResourceKey(),
            'timeoutSeconds' => self::CLOUD_TIMEOUT_SECONDS,
            'locationProvider' => $locationProvider
        ]);

        $pipeline = $pipeline->build();

        $flowData = $pipeline->createFlowData();

        $flowData->evidence->set('query.51D_Pos_latitude', $this->testLat);
        $flowData->evidence->set('query.51D_Pos_longitude', $this->testLon);

        $result = $flowData->process();

        $properties = $pipeline->getElement($elementKey)->getProperties();

        $this->valueTypes(
            $properties,
            $result->get($elementKey),
            $this->expectedProperties[$elementKey]
        );
    }

    /**
     * Checks that each expected property the resource key includes has a
     * value, or a reason it has none. The cloud service returns only the
     * properties the key includes, so the others are not checked.
     *
     * @param ElementData $result The element's data
     * @param array<string, array<string, mixed>> $keyProperties The
     *   properties the cloud service lists for the key, by lower case name
     * @param string $dataKey The element's data key
     */
    private function availableProperties($result, array $keyProperties, $dataKey)
    {
        $checked = 0;
        foreach ($this->expectedProperties[$dataKey] as $property) {
            if (!isset($keyProperties[$property])) {
                continue;
            }
            ++$checked;

            $apv = $result->getInternal($property);

            $this->assertNotNull($apv, $property);

            if ($apv->hasValue) {
                $this->assertNotNull($apv->value, $property);
            } else {
                $this->assertNotNull($apv->noValueMessage, $property);
            }
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'The resource key in ' . TestKeys::RESOURCE_KEY_VARIABLE .
            ' includes none of the ' . $dataKey . ' properties the test checks.'
        );
    }

    private function valueTypes($properties, $result, $expectedProps)
    {
        foreach ($properties as &$property) {
            $key = strtolower($property['name']);

            if (in_array($key, $expectedProps)) {
                $apv = $result->getInternal($key);

                $expectedType = $property['type'];

                $this->assertNotNull($apv, $key);

                $value = $apv->value;

                switch ($expectedType) {
                    case 'Boolean':
                        $this->assertIsBool($value, $key);
                        break;
                    case 'JavaScript':
                    case 'String':
                        $this->assertIsString($value, $key);
                        break;
                    case 'Int32':
                        $this->assertIsInt($value, $key);
                        break;
                    case 'Double':
                        $this->assertIsFloat($value, $key);
                        break;
                    case 'Array':
                        $this->assertIsArray($value, $key);
                        break;
                    default:
                        $this->fail('expected type for ' . $key . ' was ' . $expectedType);
                }
            }
        }
    }
}
