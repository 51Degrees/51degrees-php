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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;

/**
 * Stands in for a Cloud Request Engine whose discovery has not completed
 * when the Pipeline is built, as after a transient failure. It finds the
 * products when it first processes.
 */
class LateDiscoveryElement extends FlowElement
{
    public string $dataKey = 'cloud';

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    public array $flowElementProperties = [];

    public function processInternal(FlowData $flowData): void
    {
        $this->flowElementProperties = [
            'device' => ['value' => ['name' => 'Value', 'type' => 'String']],
        ];
        $flowData->setElementData(new ElementDataDictionary(
            $this,
            ['cloud' => CloudRequestEngineTestsBase::jsonResponse]
        ));
    }
}

/**
 * When a Cloud Aspect Engine has its Property metadata, as the Cloud Aspect
 * Engine specification's Start-up activity section describes.
 */
class AspectEnginePropertiesTests extends CloudRequestEngineTestsBase
{
    /**
     * A Cloud Request Engine that has its accessible Properties, as it has
     * once it is built, gives a Cloud Aspect Engine added after it its
     * Properties when the Pipeline is built.
     */
    public function testACloudEngineHasItsPropertiesWhenItJoinsThePipeline(): void
    {
        $cloud = new CloudRequestEngine([
            'resourceKey' => self::resourceKey,
            'httpClient' => $this->mockHttp(),
        ]);
        $cloud->getEngineProperties();
        $device = self::deviceEngine();

        (new PipelineBuilder())->add($cloud)->add($device)->build();

        $this->assertArrayHasKey('value', $device->getProperties());
        $this->assertSame('value', $device->getProperties()['value']['name']);
    }

    /**
     * Where the Cloud Request Engine has no Properties yet, the Cloud
     * Aspect Engine builds without them and takes them on first use, so an
     * empty result is not kept.
     */
    public function testACloudEngineTakesItsPropertiesLaterWhenThereAreNoneYet(): void
    {
        $device = self::deviceEngine();
        $pipeline = (new PipelineBuilder())
            ->add(new LateDiscoveryElement())
            ->add($device)
            ->build();

        $this->assertSame([], $device->getProperties());

        $pipeline->createFlowData()->process();

        $this->assertArrayHasKey('value', $device->getProperties());
    }

    /**
     * A Resource Key with no product for the Cloud Aspect Engine does not
     * fail the build, and the engine builds with no Properties.
     */
    public function testAMissingProductDoesNotFailTheBuild(): void
    {
        $cloud = new CloudRequestEngine([
            'resourceKey' => self::resourceKey,
            'httpClient' => $this->mockHttp(),
        ]);
        $cloud->getEngineProperties();
        $location = new CloudEngine();
        $location->dataKey = 'location';

        (new PipelineBuilder())->add($cloud)->add($location)->build();

        $this->assertSame([], $location->getProperties());
    }

    private static function deviceEngine(): CloudEngine
    {
        $engine = new CloudEngine();
        $engine->dataKey = 'device';

        return $engine;
    }
}
