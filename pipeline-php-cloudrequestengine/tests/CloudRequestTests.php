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

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\PipelineBuilder;

class CloudRequestTests extends CloudRequestEngineTestsBase
{
    public function testCloudRequestEngine()
    {
        $params = ['resourceKey' => $this->getResourceKey()];

        $this->assertNotSame(
            '!!YOUR_RESOURCE_KEY!!',
            $params['resourceKey'],
            'You need to create a resource key at ' .
            'https://configure.51degrees.com and set the RESOURCEKEY' .
            'environment variable to its value before running tests'
        );

        $cloud = new CloudRequestEngine($params);

        $engine = new CloudEngine();

        $engine->dataKey = 'device';

        $cloud->setRestrictedProperties(['cloud']);

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($cloud)->add($engine)->build();

        $fd = $pipeline->createFlowData();

        $fd->evidence->set('header.user-agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:78.0) Gecko/20100101 Firefox/78.0');

        $result = $fd->process();

        $this->assertTrue($result->device->ismobile->hasValue);
    }

    /**
     *  Verify that making POST request with SequenceElement evidence
     *  will not return any errors from cloud.
     *  This is an integration test that uses the live cloud service
     *  so any problems with that service could affect the result
     *  of this test.
     */
    public function testCloudPostRequestWithSequenceEvidence()
    {
        $params = ['resourceKey' => $this->getResourceKey()];

        $this->assertNotSame(
            '!!YOUR_RESOURCE_KEY!!',
            $params['resourceKey'],
            'You need to create a resource key at ' .
            'https://configure.51degrees.com and paste it into the ' .
            'phpunit.xml config file, ' .
            'replacing !!YOUR_RESOURCE_KEY!!.'
        );

        $cloud = new CloudRequestEngine($params);

        $engine = new CloudEngine();

        $engine->dataKey = 'device';

        $cloud->setRestrictedProperties(['cloud']);

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($cloud)->add($engine)->build();

        $fd = $pipeline->createFlowData();

        $fd->evidence->set('query.session-id', '8b5461ac-68fc-4b18-a660-7bd463b2537a');
        $fd->evidence->set('query.sequence', 1);

        try {
            $result = $fd->process();
            $this->assertEmpty($result->errors);
        } catch (\Exception $e) {
            $this->fail('FlowData returns errors for POST request.');
        }
    }

    /**
     *  Verify that making GET request with SequenceElement evidence
     *  in query params will return an error from cloud
     *  This is an integration test that uses the live cloud service
     *  so any problems with that service could affect the result
     *  of this test.
     */
    public function testCloudGetRequestWithSequenceEvidence()
    {
        $httpClient = new HttpClient();

        $params = [
            'resourceKey' => $this->getResourceKey(),
            'httpClient' => $httpClient
        ];

        $this->assertNotSame(
            '!!YOUR_RESOURCE_KEY!!',
            $params['resourceKey'],
            'You need to create a resource key at ' .
            'https://configure.51degrees.com and paste it into the ' .
            'phpunit.xml config file, ' .
            'replacing !!YOUR_RESOURCE_KEY!!.'
        );

        $cloud = new CloudRequestEngine($params);

        $engine = new CloudEngine();

        $engine->dataKey = 'device';

        $cloud->setRestrictedProperties(['cloud']);

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($cloud)->add($engine)->build();

        $fd = $pipeline->createFlowData();

        $fd->evidence->set('query.session-id', '8b5461ac-68fc-4b18-a660-7bd463b2537a');
        $fd->evidence->set('query.sequence', 1);

        $fd->process();

        $url = $cloud->baseURL . $cloud->resourceKey . '.json?&';

        $evidence = $fd->evidence->getAll();

        // Remove prefix from evidence
        $evidenceWithoutPrefix = [];

        foreach ($evidence as $key => $value) {
            $keySplit = explode('.', $key);

            if (isset($keySplit[1])) {
                $evidenceWithoutPrefix[$keySplit[1]] = $value;
            }
        }

        $url .= http_build_query($evidenceWithoutPrefix);

        $result = $httpClient->makeCloudRequest('GET', $url, null, null);
        $result = json_decode($result, true);
        $this->assertArrayNotHasKey('errors', $result);
    }

    /**
     *  Check that errors from the cloud service will cause the
     *  appropriate data to be set in the CloudRequestException.
     */
    public function testHttpDataSetInException()
    {
        $params = ['resourceKey' => 'resource_key'];

        $pipeline = new PipelineBuilder();

        try {
            $cloud = new CloudRequestEngine($params);
            $cloud->setRestrictedProperties(['cloud']);
            $pipeline = $pipeline->add($cloud)->build();
            $flowData = $pipeline->createFlowData();
            $flowData->process();
            $this->fail('Expected exception did not occur');
        } catch (CloudRequestException $e) {
            $this->assertGreaterThan(0, $e->httpStatusCode, 'Status code should not be 0');
            $this->assertNotEmpty($e->responseHeaders, 'Response headers not populated');
            $this->assertGreaterThan(0, count($e->responseHeaders), 'Response headers not populated');
        }
    }

    public function testPipelineSuppressesExceptionsWhenCloudServiceIsDown()
    {
        $params = [
            'resourceKey' => $this->getResourceKey(),
            'httpClient' => $this->mockHttp()
        ];

        $this->assertNotSame(
            '!!YOUR_RESOURCE_KEY!!',
            $params['resourceKey'],
            'You need to create a resource key at ' .
            'https://configure.51degrees.com and set the RESOURCEKEY' .
            'environment variable to its value before running tests'
        );

        $cloud = new CloudRequestEngine($params);

        $engine = new CloudEngine();

        $engine->dataKey = 'device';

        $cloud->setRestrictedProperties(['cloud']);

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($cloud)->add($engine)->build();
        $pipeline->suppressProcessExceptions = true;

        $fd = $pipeline->createFlowData();

        $fd->evidence->set('header.user-agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:78.0) Gecko/20100101 Firefox/78.0');

        $result = $fd->process();

        try {
            $result->device;
        } catch (\Exception $exception) {
            // silently discard exceptions not being tested here
        }

        $this->assertArrayHasKey('cloud', $result->errors);
        $this->assertInstanceOf(CloudRequestException::class, $result->errors['cloud']);
    }

    public function testPipelineDoesNotSuppressExceptionsWhenCloudServiceIsDown()
    {
        $params = [
            'resourceKey' => $this->getResourceKey(),
            'httpClient' => $this->mockHttp()
        ];

        $this->assertNotSame(
            '!!YOUR_RESOURCE_KEY!!',
            $params['resourceKey'],
            'You need to create a resource key at ' .
            'https://configure.51degrees.com and set the RESOURCEKEY' .
            'environment variable to its value before running tests'
        );

        $cloud = new CloudRequestEngine($params);

        $engine = new CloudEngine();

        $engine->dataKey = 'device';

        $cloud->setRestrictedProperties(['cloud']);

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($cloud)->add($engine)->build();

        $fd = $pipeline->createFlowData();

        $fd->evidence->set('header.user-agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:78.0) Gecko/20100101 Firefox/78.0');

        $this->expectException(CloudRequestException::class);
        $fd->process();
    }

    private function getResourceKey()
    {
        return $_ENV[self::resourceKey] ?? null;
    }
}
