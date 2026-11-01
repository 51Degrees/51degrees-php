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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\core\Evidence;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;

class CloudResponse extends CloudRequestEngineTestsBase
{
    /**
     * Test cloud request engine adds correct information to post request
     * and returns the response in the ElementData.
     */
    public function testProcess()
    {
        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudResponse::resourceKey,
            'httpClient' => $httpClient]);

        $builder = new PipelineBuilder();
        $pipeline = $builder
            ->add($engine)
            ->build();
        $data = $pipeline->createFlowData();
        $data->evidence->set('query.User-Agent', CloudResponse::userAgent);

        $data->process();

        $result = $data->getFromElement($engine)->cloud;
        $this->assertEquals(CloudResponse::jsonResponse, $result);

        $jsonObj = \json_decode($result, true);
        $this->assertEquals(1, $jsonObj['device']['value']);
    }

    /**
     * Verify that the CloudRequestEngine can correctly parse a
     * response from the accessible properties endpoint that contains
     * meta-data for sub-properties.
     */
    public function testSubProperties()
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => 'subpropertieskey',
            'httpClient' => $this->mockHttp()
        ]);

        $flowData = $this->createMock(FlowData::class);
        $evidence = $this->getMockBuilder(Evidence::class)->setConstructorArgs(['flowData' => $flowData])->getMock();
        $evidence->method('getAll')->willReturn([]);
        $flowData->evidence = $evidence;

        try {
            $engine->process($flowData);
        } catch (CloudRequestException $exception) {
        }

        $this->assertEquals(2, count($engine->flowElementProperties));

        $deviceProperties = $engine->flowElementProperties['device'];
        $this->assertEquals(2, count($deviceProperties));
        $this->assertTrue($this->propertiesContainName($deviceProperties, 'IsMobile'));
        $this->assertTrue($this->propertiesContainName($deviceProperties, 'IsTablet'));
        $devicesProperties = $engine->flowElementProperties['devices'];
        $this->assertEquals(1, count($devicesProperties));
        $this->assertTrue(isset($devicesProperties['devices']));
        $this->assertTrue($this->propertiesContainName($devicesProperties['devices']['itemproperties'], 'IsMobile'));
        $this->assertTrue($this->propertiesContainName($devicesProperties['devices']['itemproperties'], 'IsTablet'));
    }

    /**
     * Verify that getEngineProperties() can be called directly
     * (without processInternal()) and that it both returns the
     * expected engines and populates $engine->flowElementProperties.
     */
    public function testGetEnginePropertiesEager()
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => 'subpropertieskey',
            'httpClient' => $this->mockHttp()
        ]);

        // flowElementProperties should be empty before any call
        $this->assertEmpty($engine->flowElementProperties);

        $result = $engine->getEngineProperties();

        // Return value should contain expected engines
        $this->assertArrayHasKey('device', $result);
        $this->assertArrayHasKey('devices', $result);

        // flowElementProperties should now also be populated
        $this->assertEquals($result, $engine->flowElementProperties);
        $this->assertEquals(2, count($engine->flowElementProperties));
    }

    /**
     * Test cloud request engine handles errors from the cloud service
     * as expected.
     * An exception should be thrown by the cloud request engine
     * containing the errors from the cloud service
     * and the pipeline is configured to throw any exceptions up
     * the stack.
     * We also check that the exception message includes the content
     * from the JSON response.
     */
    public function testValidateErrorHandlingInvalidResourceKey()
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => CloudResponse::invalidKey,
            'httpClient' => $this->mockHttp()
        ]);

        try {
            $engine->process($this->createMock(FlowData::class));
        } catch (CloudRequestException $exception) {
            $this->assertStringContainsString(CloudResponse::invalidKey, $exception->getMessage());
        }
    }

    /**
     * Test cloud request engine handles a lack of data from the
     * cloud service as expected.
     * An exception should be thrown by the cloud request engine
     * containing the errors from the cloud service
     * and the pipeline is configured to throw any exceptions up
     * the stack.
     * We also check that the exception message includes the content
     * from the JSON response.
     */
    public function testValidateErrorHandlingNoData()
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => CloudResponse::noDataKey,
            'httpClient' => $this->mockHttp()
        ]);

        try {
            $engine->process($this->createMock(FlowData::class));
        } catch (CloudRequestException $exception) {
            $this->assertStringContainsString(CloudResponse::noDataKeyMessageComplete, $exception->getMessage());
        }
    }
}
