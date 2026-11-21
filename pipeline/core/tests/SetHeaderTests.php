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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\Messages;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\SetHeaderElement;
use fiftyone\pipeline\core\tests\classes\Constants;
use fiftyone\pipeline\core\tests\classes\SetHeaderNamesElement;
use fiftyone\pipeline\core\tests\classes\TestDataDictionary;
use fiftyone\pipeline\core\tests\classes\TestPipeline;
use fiftyone\pipeline\core\Utils;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SetHeaderTests extends TestCase
{
    // Data Provider for testGetResponseHeaderValue
    public static function provider_testGetResponseHeaderValue()
    {
        return [
            [
                [
                    'device' => new TestDataDictionary(null, [
                        'setheaderbrowseraccept-ch' => new AspectPropertyValue(null, Constants::UNKNOWN),
                        'setheaderplatformaccept-ch' => new AspectPropertyValue(null, Constants::UNKNOWN),
                        'setheaderhardwareaccept-ch' => new AspectPropertyValue(null, Constants::UNKNOWN)
                    ])
                ],
                ''
            ],
            [
                [
                    'device' => new TestDataDictionary(null, [
                        'setheaderbrowseraccept-ch' => new AspectPropertyValue(null, Constants::ACCEPTCH_BROWSER_VALUE)
                    ])
                ],
                'SEC-CH-UA,SEC-CH-UA-Full-Version'
            ],
            [
                [
                    'device' => new TestDataDictionary(null, [
                        'setheaderplatformaccept-ch' => new AspectPropertyValue(null, Constants::ACCEPTCH_PLATFORM_VALUE),
                        'setheaderhardwareaccept-ch' => new AspectPropertyValue(null, Constants::ACCEPTCH_HARDWARE_VALUE)
                    ])
                ],
                'SEC-CH-UA-Model,SEC-CH-UA-Mobile,SEC-CH-UA-Arch,SEC-CH-UA-Platform,SEC-CH-UA-Platform-Version'
            ],
            [
                [
                    'device' => new TestDataDictionary(null, [
                        'setheaderbrowseraccept-ch' => new AspectPropertyValue(null, Constants::ACCEPTCH_BROWSER_VALUE),
                        'setheaderplatformaccept-ch' => new AspectPropertyValue(null, Constants::ACCEPTCH_PLATFORM_VALUE),
                        'setheaderhardwareaccept-ch' => new AspectPropertyValue(null, Constants::ACCEPTCH_HARDWARE_VALUE)
                    ])
                ],
                'SEC-CH-UA,SEC-CH-UA-Full-Version,SEC-CH-UA-Model,SEC-CH-UA-Mobile,SEC-CH-UA-Arch,SEC-CH-UA-Platform,SEC-CH-UA-Platform-Version'
            ],
            [
                // The values the cloud service returns today, where the
                // browser property asks for Sec-CH-UA-Mobile and
                // Sec-CH-UA-Platform and the hardware and platform
                // properties ask for them again. Each hint belongs in the
                // header once, which is what the .NET pipeline sends.
                [
                    'device' => new TestDataDictionary(null, [
                        'setheaderbrowseraccept-ch' => new AspectPropertyValue(
                            null,
                            'Sec-CH-UA,Sec-CH-UA-Full-Version-List,Sec-CH-UA-Mobile,Sec-CH-UA-Platform'
                        ),
                        'setheaderplatformaccept-ch' => new AspectPropertyValue(
                            null,
                            'Sec-CH-UA-Platform,Sec-CH-UA-Platform-Version'
                        ),
                        'setheaderhardwareaccept-ch' => new AspectPropertyValue(
                            null,
                            'Sec-CH-UA-Model,Sec-CH-UA-Mobile'
                        )
                    ])
                ],
                'Sec-CH-UA,Sec-CH-UA-Full-Version-List,Sec-CH-UA-Mobile,Sec-CH-UA-Platform,Sec-CH-UA-Model,Sec-CH-UA-Platform-Version'
            ],
            [
                // A value with spaces after the commas, which a header may
                // carry, gives the same header as one without.
                [
                    'device' => new TestDataDictionary(null, [
                        'setheaderbrowseraccept-ch' => new AspectPropertyValue(
                            null,
                            'Sec-CH-UA, Sec-CH-UA-Mobile'
                        ),
                        'setheaderhardwareaccept-ch' => new AspectPropertyValue(
                            null,
                            'Sec-CH-UA-Mobile'
                        )
                    ])
                ],
                'Sec-CH-UA,Sec-CH-UA-Mobile'
            ]
        ];
    }

    /**
     * Test response header value to be set for UACH.
     * 
     * @dataProvider provider_testGetResponseHeaderValue
     */
    #[DataProvider("provider_testGetResponseHeaderValue")]
    public function testGetResponseHeaderValue($device, $expectedValue)
    {
        $setHeaderPropertiesDict = [
            'device' => [
                'SetHeaderBrowserAccept-CH',
                'SetHeaderHardwareAccept-CH',
                'SetHeaderPlatformAccept-CH'
            ]
        ];
        $testPipeline = new TestPipeline();
        $setHeaderElement = new SetHeaderElement();
        // A processed FlowData is read only, so the data is set on one that
        // has not been processed.
        $flowData = $testPipeline->pipeline->createFlowData();
        $flowData->data = $device;
        $actualValue = $setHeaderElement->getResponseHeaderValue($flowData, $setHeaderPropertiesDict);
        $this->assertSame($expectedValue, $actualValue['Accept-CH']);
    }

    /**
     * A cloud engine asked for one of its SetHeader properties, by a
     * license key with the property named, answers only that one, and its
     * data throws for the others, as engine data does for a property it
     * has no value for. The one it answered still sets the header.
     */
    public function testAPropertyTheDataThrowsForSetsNoHeader()
    {
        $device = new class(null, [
            'setheaderbrowseraccept-ch' => new AspectPropertyValue(null, 'Sec-CH-UA')
        ]) extends TestDataDictionary {
            public function getInternal(string $key)
            {
                if (!array_key_exists($key, $this->contents)) {
                    throw new \Exception("The data holds no value for {$key}.");
                }

                return $this->contents[$key];
            }
        };
        $setHeaderPropertiesDict = [
            'device' => [
                'SetHeaderBrowserAccept-CH',
                'SetHeaderHardwareAccept-CH',
                'SetHeaderPlatformAccept-CH'
            ]
        ];
        $testPipeline = new TestPipeline();
        $testPipeline->flowData->data = ['device' => $device];
        $actualValue = (new SetHeaderElement())->getResponseHeaderValue(
            $testPipeline->flowData,
            $setHeaderPropertiesDict
        );
        $this->assertSame(['Accept-CH' => 'Sec-CH-UA'], $actualValue);
    }

    /**
     * Test response header not being sent for empty value
     */
    public function testSetResponseHeaderEmptyHeader()
    {
        $this->markTestSkipped('Nothing being tested because Utils::setResponseHeader() returns void');
        
        $data = [
            'set-headers' => (object) [
                'responseheaderdictionary' => [
                    'Accept-CH' => ''
                ]
            ]
        ];
        $setHeaderPropertiesDict = [
            'device' => [
                'SetHeaderBrowserAccept-CH',
                'SetHeaderHardwareAccept-CH',
                'SetHeaderPlatformAccept-CH'
            ]
        ];
        $testPipeline = new TestPipeline();
        $flowData = $testPipeline->pipeline->createFlowData();
        $flowData->data = $data;
        $actualValue = Utils::setResponseHeader($flowData);
        $this->assertEquals(false, isset($actualValue['Accept-CH']));
    }

    // Data Provider for testGetResponseHeaderValue
    public static function provider_testGetResponseHeaderName_Valid()
    {
        return [
            ['SetHeaderBrowserAccept-CH', 'Accept-CH'],
            ['SetHeaderBrowserCritical-CH', 'Critical-CH'],
            ['SetHeaderUnknownAccept-CH', 'Accept-CH'],
            // The identifier may start with any character, and the header
            // name starts at the next upper-case letter after it.
            ['SetHeaderbrowserAccept-CH', 'Accept-CH'],
            ['SetHeaderbrowserAccept-ch', 'Accept-ch'],
            ['SetHeader9Accept-CH', 'Accept-CH'],
            // Only the identifier is taken off, not every copy of it.
            ['SetHeaderAcceptAccept-CH', 'Accept-CH'],
            ['SetHeaderAAccept-CH', 'Accept-CH']
        ];
    }

    /**
     * Test get response header function for valid formats.
     * @dataProvider provider_testGetResponseHeaderName_Valid
     */
    #[DataProvider("provider_testGetResponseHeaderName_Valid")]
    public function testGetResponseHeaderNameValid($data, $expectedValue)
    {
        $setHeaderElement = new SetHeaderElement();
        $actualValue = $setHeaderElement->getResponseHeaderName($data);
        $this->assertSame($expectedValue, $actualValue);
    }

    // Data Provider for testGetResponseHeaderValue
    public static function provider_testGetResponseHeaderName_InValid()
    {
        return [
            ['TestBrowserAccept-CH', Messages::PROPERTY_NOT_SET_HEADER],
            ['SetHeaderBrowseraccept-ch', Messages::WRONG_PROPERTY_FORMAT],
            ['SetHeaderbrowseraccept-ch', Messages::WRONG_PROPERTY_FORMAT],
            ['SetHeader', Messages::WRONG_PROPERTY_FORMAT]
        ];
    }

    /**
     * Test get response header function for valid formats.
     * @dataProvider provider_testGetResponseHeaderName_InValid
     */
    #[DataProvider("provider_testGetResponseHeaderName_InValid")]
    public function testGetResponseHeaderNameInValid($data, $expectedValue)
    {
        $setHeaderElement = new SetHeaderElement();

        $this->expectExceptionMessage(sprintf($expectedValue, $data));
        $setHeaderElement->getResponseHeaderName($data);
    }

    /**
     * Only a property whose name starts with SetHeader sets a response
     * header. A name with SetHeader later in it is an ordinary property.
     */
    public function testOnlyANameStartingWithSetHeaderSetsAHeader()
    {
        $pipeline = (new PipelineBuilder(['addJavaScriptBuilder' => false]))
            ->add(new SetHeaderNamesElement())
            ->build();

        $this->assertSame(
            ['headernames' => ['SetHeaderBrowserAccept-CH']],
            (new SetHeaderElement())->getSetHeaderPropertiesPipeline($pipeline)
        );

        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame([], $flowData->errors);
        $this->assertSame(
            ['Accept-CH' => 'Sec-CH-UA'],
            $flowData->get('set-headers')->get('responseheaderdictionary')
        );
    }
}
