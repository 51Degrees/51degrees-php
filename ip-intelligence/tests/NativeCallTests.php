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

declare(strict_types=1);

namespace fiftyone\pipeline\ipintelligence\tests;

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\AspectData;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use fiftyone\pipeline\ipintelligence\tests\classes\StubNativeEngine;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigList;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigValue;
use fiftyone\pipeline\ipintelligence\tests\classes\StubWeightedValue;
use PHPUnit\Framework\TestCase;

/**
 * What the on-premise engine asks of the native engine, recorded by a
 * stand-in for it, so these tests need no extension. The accepted Evidence
 * keys are read when the engine is built and when its data is refreshed, and
 * not on each request. Processing wraps the native results and reads no
 * value from them, a value is read only when it is asked for, and a
 * property's metadata is looked up once.
 */
class NativeCallTests extends TestCase
{
    // The keys as the native engine gives them, in its order, and in mixed
    // case, as a data file may give them.
    private const KEYS = [
        'query.Client-IP-51D',
        'server.client-ip-51d',
        'query.client-ip',
        'server.client-ip',
    ];

    private const REFRESHED_KEYS = [
        'query.True-Client-IP-51D',
        'server.true-client-ip-51d',
    ];

    private const REQUESTS = 3;

    private static function native(?array $refreshedKeys = null): StubNativeEngine
    {
        return new StubNativeEngine(
            self::KEYS,
            [
                'RegisteredName' => 'WeightedString',
                'RegisteredCountry' => 'WeightedString',
            ],
            [
                'RegisteredName' => new StubSwigValue(true, new StubSwigList([
                    new StubWeightedValue('An ISP', 65535),
                ])),
                'RegisteredCountry' => new StubSwigValue(true, new StubSwigList([
                    new StubWeightedValue('GB', 65535),
                ])),
            ],
            $refreshedKeys
        );
    }

    /**
     * The engine class over the stand-in, set up as its constructor sets it
     * up once the native engine is built.
     */
    private static function engine(StubNativeEngine $native): IpIntelligenceOnPremise
    {
        $engine = (new \ReflectionClass(IpIntelligenceOnPremise::class))
            ->newInstanceWithoutConstructor();
        $engine->engine = $native;
        (function (): void {
            $this->initEngineMetaData();
        })->call($engine);

        return $engine;
    }

    private static function pipeline(IpIntelligenceOnPremise $engine): Pipeline
    {
        return (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
    }

    private static function process(Pipeline $pipeline): FlowData
    {
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip-51d', Constants::IPV4_ADDRESS);
        $flowData->process();

        return $flowData;
    }

    public function testTheAcceptedEvidenceIsTheEnginesKeysInItsOrder(): void
    {
        $native = self::native();
        $engine = self::engine($native);

        $this->assertSame(
            array_map('strtolower', self::KEYS),
            $engine->getEvidenceKeyFilter()->getList()
        );
        $this->assertSame(1, $native->keyReads);
    }

    public function testARefreshReadsTheKeysAgain(): void
    {
        $native = self::native(self::REFRESHED_KEYS);
        $engine = self::engine($native);

        $engine->refreshData();

        $this->assertSame(2, $native->keyReads);
        $this->assertSame(
            array_map('strtolower', self::REFRESHED_KEYS),
            $engine->getEvidenceKeyFilter()->getList()
        );
    }

    public function testRequestsReadNeitherKeysNorMetadata(): void
    {
        $native = self::native();
        $pipeline = self::pipeline(self::engine($native));

        for ($request = 0; $request < self::REQUESTS; ++$request) {
            self::process($pipeline)->close();
        }

        $this->assertCount(self::REQUESTS, $native->results);
        $this->assertSame(1, $native->keyReads);
        $this->assertSame(0, $native->metaDataReads);
    }

    public function testProcessingReadsNoValue(): void
    {
        $native = self::native();
        $flowData = self::process(self::pipeline(self::engine($native)));

        $this->assertInstanceOf(AspectData::class, $flowData->ip);
        $this->assertSame([], $native->results[0]->requestedKeys);
    }

    public function testAValueIsReadOnlyWhenItIsAskedFor(): void
    {
        $native = self::native();
        $flowData = self::process(self::pipeline(self::engine($native)));

        $value = $flowData->ip->get('RegisteredName');

        $this->assertSame(
            [['value' => 'An ISP', 'rawweighting' => 65535]],
            $value->value
        );
        $this->assertSame(['RegisteredName'], $native->results[0]->requestedKeys);
    }

    public function testAPropertysMetadataIsLookedUpOnce(): void
    {
        $native = self::native();
        $pipeline = self::pipeline(self::engine($native));

        for ($request = 0; $request < self::REQUESTS; ++$request) {
            $flowData = self::process($pipeline);
            $flowData->ip->get('RegisteredName');
            $flowData->close();
        }

        $this->assertSame(['RegisteredName'], $native->propertyLookups);
    }
}
