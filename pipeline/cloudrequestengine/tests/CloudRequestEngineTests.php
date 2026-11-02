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
use fiftyone\pipeline\cloudrequestengine\Constants;
use fiftyone\pipeline\core\PipelineBuilder;

class CloudRequestEngineTests extends CloudRequestEngineTestsBase
{
    public const testEndPoint = 'http://testEndPoint/';
    public const testEnvVarEndPoint = 'http://testEnvVarEndPoint/';

    /**
     * Test the explicit setting of cloudEndPoint via constructor take
     * precedence over environment variable settings.
     */
    public function testConfigPrecedenceExplicitSettings()
    {
        $httpClient = $this->mockHttp();

        $this->assertTrue(putenv(Constants::FOD_CLOUD_API_URL . '=' . CloudRequestEngineTests::testEnvVarEndPoint));

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $httpClient,
            'cloudEndPoint' => CloudRequestEngineTests::testEndPoint
        ]);

        $this->assertSame(self::testEndPoint, $engine->baseURL);
    }

    /**
     * Test the environment variable settings of cloud endpoint take
     * precedence over the default value.
     */
    public function testConfigPrecedenceEnvironmentVariableSettings()
    {
        $httpClient = $this->mockHttp();

        $this->assertTrue(putenv(Constants::FOD_CLOUD_API_URL . '=' . CloudRequestEngineTests::testEnvVarEndPoint));

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $httpClient
        ]);

        $this->assertSame(self::testEnvVarEndPoint, $engine->baseURL);
    }

    /**
     * Test that the default end point is used if no other methods is used.
     */
    public function testConfigPrecedenceDefaultSettings()
    {
        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $httpClient
        ]);

        $this->assertSame(Constants::BASE_URL_DEFAULT, $engine->baseURL);
    }

    /**
     * Test that base URL is appended with slash if does not end with one.
     */
    public function testBaseUrlNoSlash()
    {
        $httpClient = $this->mockHttp();

        $testNoSlashUrl = 'http://localhost';

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $httpClient,
            'cloudEndPoint' => $testNoSlashUrl]);

        $this->assertSame($testNoSlashUrl . '/', $engine->baseURL);
    }

    // Data Provider for testGetSelectedEvidence
    public static function provider_testGetSelectedEvidence()
    {
        return [
            [
                [
                    'query.User-Agent' => 'iPhone',
                    'header.User-Agent' => 'iPhone'
                ],
                'query',
                [
                    'query.User-Agent' => 'iPhone'
                ]
            ],
            [
                [
                    'header.User-Agent' => 'iPhone',
                    'a.User-Agent' => 'iPhone',
                    'z.User-Agent' => 'iPhone'
                ],
                'other',
                [
                    'z.User-Agent' => 'iPhone',
                    'a.User-Agent' => 'iPhone'
                ]
            ]
        ];
    }

    /**
     * Test evidence of specific type is returned from all
     * the evidence passed, if type is not from query, header
     * or cookie then evidences are returned sorted in descending order.
     *
     * @dataProvider provider_testGetSelectedEvidence
     * @param mixed $evidence
     * @param mixed $type
     * @param mixed $expectedValue
     */
    public function testGetSelectedEvidence($evidence, $type, $expectedValue)
    {
        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $httpClient
        ]);

        $result = $engine->getSelectedEvidence($evidence, $type);
        $this->assertSame($expectedValue, $result);
    }

    // Data Provider for testGetContent_nowarning
    public static function provider_testGetContent_nowarning()
    {
        return [
            [
                [
                    'query.User-Agent' => 'query-iPhone',
                    'header.user-agent' => 'header-iPhone'
                ],
                'query-iPhone'
            ],
            [
                [
                    'query.User-Agent' => 'query-iPhone',
                    'cookie.User-Agent' => 'cookie-iPhone'
                ],
                'query-iPhone'
            ],
            [
                [
                    'query.User-Agent' => 'query-iPhone',
                    'a.User-Agent' => 'a-iPhone'
                ],
                'query-iPhone'
            ]
        ];
    }

    /**
     * Test Content to send in the POST request is generated as
     * per the precedence rule of The evidence keys. Verify that query
     * evidence overwrite other evidences without any warning logged.
     *
     * @dataProvider provider_testGetContent_nowarning
     * @param mixed $evidence
     * @param mixed $expectedValue
     */
    public function testGetContent_nowarning($evidence, $expectedValue)
    {
        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $httpClient
        ]);

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($engine)->build();

        $data = $pipeline->createFlowData();

        foreach ($evidence as $key => $value) {
            $data->evidence->set($key, $value);
        }

        $result = $engine->getContent($data);
        $this->assertSame($expectedValue, $result['user-agent']);
    }

    // Data Provider for testGetContent_warnings
    public static function provider_testGetContent_warnings()
    {
        return [
            [
                [
                    'header.User-Agent' => 'header-iPhone',
                    'cookie.User-Agent' => 'cookie-iPhone'
                ],
                'header-iPhone'
            ],
            [
                [
                    'a.User-Agent' => 'a-iPhone',
                    'b.User-Agent' => 'b-iPhone',
                    'z.User-Agent' => 'z-iPhone'
                ],
                'a-iPhone'
            ],
            [
                [
                    'query.User-Agent' => 'query-iPhone',
                    'header.User-Agent' => 'header-iPhone',
                    'cookie.User-Agent' => 'cookie-iPhone',
                    'a.User-Agent' => 'a-iPhone'
                ],
                'query-iPhone'
            ]
        ];
    }

    /**
     * Test Content to send in the POST request is generated as
     * per the precedence rule of The evidence keys. These are
     * added to the evidence in reverse order, if there is conflict then
     * the queryData value is overwritten and warnings are logged.
     *
     * @dataProvider provider_testGetContent_warnings
     * @param mixed $evidence
     * @param mixed $expectedValue
     */
    public function testGetContent_warnings($evidence, $expectedValue)
    {
        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $httpClient
        ]);

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($engine)->build();

        $data = $pipeline->createFlowData();

        foreach ($evidence as $key => $value) {
            $data->evidence->set($key, $value);
        }

        $this->assertSame($expectedValue, @$engine->getContent($data)['user-agent']);

        // register handler for user-level errors generated by trigger_error()
        set_error_handler(function ($errno, $errstr) {
            $this->assertEquals(E_USER_WARNING, $errno);
            $this->assertStringContainsString('evidence conflicts with', $errstr);
        }, E_USER_WARNING);

        $engine->getContent($data);

        restore_error_handler();
    }

    // Data Provider for testGetContent_case_insensitive
    public static function provider_testGetContent_case_insensitive()
    {
        return [
            [
                [
                    'query.User-Agent' => 'iPhone1',
                    'Query.user-agent' => 'iPhone2'
                ],
                'iPhone2'
            ],
            [
                [
                    'a.User-Agent' => 'iPhone1',
                    'A.user-agent' => 'iPhone2'
                ],
                'iPhone2'
            ]
        ];
    }

    /**
     * Test Content to send in the POST request is generated as
     * per the precedence rule of The evidence keys. Verify that
     * comparison is case-insensitive and evidence values will be
     * overwritten without any warning logged.
     *
     * @dataProvider provider_testGetContent_case_insensitive
     * @param mixed $evidence
     * @param mixed $expectedValue
     */
    public function testGetContent_case_insensitive($evidence, $expectedValue)
    {
        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $httpClient
        ]);

        $pipeline = new PipelineBuilder();

        $pipeline = $pipeline->add($engine)->build();

        $data = $pipeline->createFlowData();

        foreach ($evidence as $key => $value) {
            $data->evidence->set($key, $value);
        }

        $result = $engine->getContent($data);
        $this->assertSame($expectedValue, $result['user-agent']);
    }

    /**
     * The name sent to the cloud service is everything after the first dot,
     * as the .NET engine sends it. Several evidence names have a dot of
     * their own, among them 'query.id.usage', which a 51Did depends on, and
     * cutting at the last dot sent it as 'usage', which the cloud service
     * does not read.
     */
    public function testGetContent_keyKeepsEverythingAfterThePrefix()
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $this->mockHttp()
        ]);

        $data = (new PipelineBuilder())->add($engine)->build()->createFlowData();
        $data->evidence->set('query.id.usage', 'personalized');
        $data->evidence->set('query.robotstxt.train', 'no');
        $data->evidence->set('header.user-agent', 'iPhone');

        $result = $engine->getContent($data);

        $this->assertSame(
            [
                'user-agent' => 'iPhone',
                'id.usage' => 'personalized',
                'robotstxt.train' => 'no'
            ],
            $result
        );
    }

    /**
     * The header route to a 51Did is cut in the same place. The cloud
     * service lists 'header.id.usage', 'header.id.email' and
     * 'header.id.salt' alongside the query forms, and each was sent under
     * the name after its last dot before this change.
     */
    public function testGetContent_headerKeyKeepsEverythingAfterThePrefix()
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $this->mockHttp()
        ]);

        $data = (new PipelineBuilder())->add($engine)->build()->createFlowData();
        $data->evidence->set('header.id.usage', 'personalized');

        $result = $engine->getContent($data);

        $this->assertSame(['id.usage' => 'personalized'], $result);
    }

    /**
     * A prefix the engine does not group, such as the 'derived.id.usage'
     * the cloud service lists, reaches addQueryData through the 'other'
     * branch of getSelectedEvidence rather than one of the three prefix
     * branches, and is cut in the same place.
     */
    public function testGetContent_otherPrefixKeyKeepsEverythingAfterThePrefix()
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $this->mockHttp()
        ]);

        $data = (new PipelineBuilder())->add($engine)->build()->createFlowData();
        $data->evidence->set('derived.id.usage', 'personalized');

        $result = $engine->getContent($data);

        $this->assertSame(['id.usage' => 'personalized'], $result);
    }

    /**
     * A key with no dot at all has no prefix to remove, so the whole key is
     * the name sent. Limiting the split to two parts leaves one part for
     * such a key, and end() returns it; reading part one instead would send
     * every unprefixed evidence name empty.
     */
    public function testGetContent_keyWithNoSeparatorIsSentWhole()
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => CloudRequestEngineTests::resourceKey,
            'httpClient' => $this->mockHttp()
        ]);

        $data = (new PipelineBuilder())->add($engine)->build()->createFlowData();
        $data->evidence->set('51D_ProfileIds', '12345');

        $result = $engine->getContent($data);

        $this->assertSame(['51d_profileids' => '12345'], $result);
    }

    /**
     * @after
     */
    protected function tearDowniCloudEndPoint()
    {
        $this->assertTrue(putenv(Constants::FOD_CLOUD_API_URL));
    }
}
