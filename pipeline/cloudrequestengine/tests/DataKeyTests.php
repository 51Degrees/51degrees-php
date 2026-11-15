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
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;

/**
 * Stands in for a Cloud Request Engine still registered under cloud, the
 * Data Key the engine had before, as a replacement written for tests can be.
 */
class OldKeyCloudRequestElement extends FlowElement
{
    public string $dataKey = 'cloud';

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    public array $flowElementProperties = [];

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary(
            $this,
            ['cloud' => CloudRequestEngineTestsBase::jsonResponse]
        ));
    }
}

/**
 * The Cloud Request Engine's Element Data key and its one Property, which
 * the Cloud Request Engine specification names cloud-response and
 * json-response, and cloud, the name each had before, which still works for
 * at least one release.
 */
class DataKeyTests extends CloudRequestEngineTestsBase
{
    public function testTheElementDataKeyIsCloudResponse(): void
    {
        $engine = $this->engine();
        $this->assertSame('cloud-response', $engine->dataKey);

        $flowData = self::process([$engine], self::logger());

        $this->assertSame(
            self::jsonResponse,
            $flowData->get('cloud-response')->get('json-response')
        );
    }

    /**
     * The Element Data holds the raw response as one Property,
     * json-response.
     */
    public function testTheElementDatasPropertyIsJsonResponse(): void
    {
        $flowData = self::process([$this->engine()], self::logger());
        $data = $flowData->get('cloud-response');

        $this->assertSame(self::jsonResponse, $data->get('json-response'));
        $this->assertSame(self::jsonResponse, $data->{'json-response'});
        $this->assertSame(['json-response'], array_keys($data->asDictionary()));
    }

    /**
     * The engine's Property metadata lists the one Property it populates.
     */
    public function testTheEngineListsItsProperty(): void
    {
        $properties = $this->engine()->getProperties();

        $this->assertSame(['json-response'], array_keys($properties));
        $this->assertSame('json-response', $properties['json-response']['name']);
        $this->assertSame('string', $properties['json-response']['type']);
    }

    /**
     * The Property's old name gives the same response, and is logged once
     * as deprecated, naming the Property to use.
     */
    public function testTheOldPropertyNameStillWorksAndIsLoggedOnce(): void
    {
        $logger = self::logger();
        $flowData = self::process([$this->engine()], $logger);
        $data = $flowData->get('cloud-response');

        $this->assertSame(self::jsonResponse, $data->get('cloud'));
        $this->assertSame(self::jsonResponse, $data->cloud);

        $deprecations = self::warningsNaming($logger, "'json-response'");
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString("'cloud'", $deprecations[0]);
    }

    /**
     * The key the engine had before gives the same element and Element
     * Data, and is logged once as deprecated, naming the key to use.
     */
    public function testTheOldKeyStillWorksAndIsLoggedOnce(): void
    {
        $logger = self::logger();
        $engine = $this->engine();
        $flowData = self::process([$engine], $logger);

        $data = $flowData->get('cloud-response');
        $this->assertSame($data, $flowData->get('cloud'));
        $this->assertSame($data, $flowData->cloud);
        $this->assertSame($engine, $flowData->pipeline->getElement('cloud'));
        $this->assertSame($engine, $flowData->pipeline->flowElementsList['cloud']);

        $deprecations = self::oldKeyWarnings($logger);
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString("'cloud-response'", $deprecations[0]);
    }

    /**
     * A cloud engine reads the response under the new key and by the
     * Property's new name, so a Pipeline that uses only the elements here
     * logs no deprecation.
     */
    public function testACloudEngineReadsTheResponseWithoutTheOldKey(): void
    {
        $logger = self::logger();
        $flowData = self::process([$this->engine(), self::deviceEngine()], $logger);

        $this->assertSame('1', $flowData->get('device')->get('value')->value);
        $this->assertSame([], self::oldKeyWarnings($logger));
    }

    /**
     * A cloud engine still reads the response of an element registered
     * under the old key that gives it by the Property's old name.
     */
    public function testACloudEngineStillReadsAnElementUnderTheOldKey(): void
    {
        $flowData = self::process(
            [new OldKeyCloudRequestElement(), self::deviceEngine()],
            self::logger()
        );

        $this->assertSame('1', $flowData->get('device')->get('value')->value);
    }

    /**
     * The JSON the page receives leaves out the raw response.
     */
    public function testTheJsonLeavesOutTheResponse(): void
    {
        $flowData = self::process(
            [$this->engine(), self::deviceEngine()],
            self::logger()
        );
        $json = $flowData->jsonbundler->json;

        $this->assertArrayNotHasKey('cloud-response', $json);
        $this->assertArrayNotHasKey('cloud', $json);
        $this->assertArrayHasKey('device', $json);
    }

    private function engine(): CloudRequestEngine
    {
        return new CloudRequestEngine([
            'resourceKey' => self::resourceKey,
            'httpClient' => $this->mockHttp()
        ]);
    }

    private static function deviceEngine(): CloudEngine
    {
        $engine = new CloudEngine();
        $engine->dataKey = 'device';

        return $engine;
    }

    /**
     * Processes the stubbed Evidence through a Pipeline of the elements.
     *
     * @param array<\fiftyone\pipeline\core\FlowElement> $elements
     */
    private static function process(array $elements, Logger $logger): FlowData
    {
        $builder = (new PipelineBuilder())->addLogger($logger);
        foreach ($elements as $element) {
            $builder->add($element);
        }
        $flowData = $builder->build()->createFlowData();
        $flowData->evidence->set('query.User-Agent', self::userAgent);

        return $flowData->process();
    }

    /**
     * A logger that keeps every entry.
     */
    private static function logger(): Logger
    {
        return new class ('trace') extends Logger {
            /** @var array<array<string, string>> */
            public array $entries = [];

            public function logInternal(array $log): void
            {
                $this->entries[] = $log;
            }
        };
    }

    /**
     * The warnings that name cloud, the old Data Key and the Property's old
     * name.
     *
     * @return array<string>
     */
    private static function oldKeyWarnings(Logger $logger): array
    {
        return self::warningsNaming($logger, "'cloud'");
    }

    /**
     * The warnings whose message holds the text.
     *
     * @return array<string>
     */
    private static function warningsNaming(Logger $logger, string $text): array
    {
        $warnings = [];
        foreach ($logger->entries as $entry) {
            if (
                $entry['level'] === 'warning' &&
                strpos($entry['message'], $text) !== false
            ) {
                $warnings[] = $entry['message'];
            }
        }

        return $warnings;
    }
}
