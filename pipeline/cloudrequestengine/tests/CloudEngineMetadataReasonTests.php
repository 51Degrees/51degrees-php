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
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\Engine;
use fiftyone\pipeline\engines\MissingPropertyService;
use PHPUnit\Framework\TestCase;

/**
 * Step 3.2 of "Deciding the reason", pipeline-specification/features/properties.md
 * line 143 at 51Degrees/specifications dbaf099c: "An implementation SHOULD
 * decide the reason in this order, using the first that applies." A Cloud
 * Engine "Where it has loaded its Property metadata, ... gives
 * ProductNotAccessibleWithResourceKey when the metadata lists no
 * Properties, and PropertyNotAccessibleWithResourceKey otherwise. ... Where
 * it has not loaded its metadata, it gives CloudRequestFailed when its
 * request for the property list failed, and Unknown otherwise."
 */
class CloudEngineMetadataReasonTests extends TestCase
{
    private const PROPERTIES = '{"Products": {"device": {"Properties": [' .
        '{"Name": "IsMobile", "Type": "Boolean"}]}}}';
    private const EVIDENCE_KEYS = '["query.User-Agent"]';
    private const DATA = '{"device":{"ismobile":true}}';

    private const UNKNOWN = 'The reason for this is unknown.';
    private const CLOUD_REQUEST_FAILED = 'This is because an upstream cloud request failed';
    private const PRODUCT_NOT_ACCESSIBLE = 'your resource key does not include access to any properties';
    private const PROPERTY_NOT_ACCESSIBLE = 'your resource key does not include access to this property';

    private bool $propertiesFail = false;

    /**
     * A cloud engine in no pipeline has asked for no property list, so it
     * has not loaded its metadata, and nothing failed.
     */
    public function testAnEngineThatHasNotAskedForItsMetadataGivesUnknown(): void
    {
        $device = new CloudEngine();
        $device->dataKey = 'device';

        $this->assertStringContainsString(
            self::UNKNOWN,
            self::messageFor($device, 'ismobile')
        );
    }

    /**
     * The Cloud Request Engine's request for the property list failed when
     * the pipeline was built, so the engine has not loaded its metadata
     * because the request failed.
     */
    public function testAFailedPropertyListRequestGivesCloudRequestFailed(): void
    {
        $this->propertiesFail = true;
        [$device] = $this->engines();

        $this->assertStringContainsString(
            self::CLOUD_REQUEST_FAILED,
            self::messageFor($device, 'ismobile')
        );
    }

    /**
     * Once the property list has been read, a product the Resource Key does
     * not include is metadata that lists no properties.
     */
    public function testAProductTheKeyDoesNotIncludeGivesProductNotAccessible(): void
    {
        [, $location] = $this->engines();

        $this->assertStringContainsString(
            self::PRODUCT_NOT_ACCESSIBLE,
            self::messageFor($location, 'town')
        );
    }

    /**
     * After a failed property list request at build, the request made on
     * first use loads the metadata, so a property it does not list is one
     * the Resource Key does not include.
     */
    public function testMetadataLoadedOnFirstUseGivesPropertyNotAccessible(): void
    {
        $this->propertiesFail = true;
        [$device, , $pipeline] = $this->engines();
        $this->propertiesFail = false;

        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.User-Agent', 'iPhone');
        $flowData->process();

        $this->assertStringContainsString(
            self::PROPERTY_NOT_ACCESSIBLE,
            self::messageFor($device, 'browsername')
        );
    }

    /**
     * A cloud engine for device and one for location, in a Pipeline with a
     * Cloud Request Engine whose Resource Key includes the device product
     * only.
     *
     * @return array{CloudEngine, CloudEngine, Pipeline}
     */
    private function engines(): array
    {
        $client = $this->createStub(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function (string $type, string $url): string {
                $lower = strtolower($url);
                if (strpos($lower, 'accessibleproperties') !== false) {
                    if ($this->propertiesFail) {
                        throw new CloudRequestException('down', 503);
                    }

                    return self::PROPERTIES;
                }
                if (strpos($lower, 'evidencekeys') !== false) {
                    return self::EVIDENCE_KEYS;
                }

                return self::DATA;
            }
        );
        $device = new CloudEngine();
        $device->dataKey = 'device';
        $location = new CloudEngine();
        $location->dataKey = 'location';

        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->add(new CloudRequestEngine([
                'resourceKey' => 'resourcekey',
                'httpClient' => $client,
            ]))
            ->add($device)
            ->add($location)
            ->build();

        return [$device, $location, $pipeline];
    }

    private static function messageFor(Engine $engine, string $name): string
    {
        try {
            (new MissingPropertyService())->check($name, $engine);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        throw new \LogicException('No exception for ' . $name);
    }
}
