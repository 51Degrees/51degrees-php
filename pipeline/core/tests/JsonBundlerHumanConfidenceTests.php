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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * An element whose Element Data copies the sealed Human Confidence result
 * and its page id from Evidence, as an element echoing a remote server's
 * answer could, beside a value of its own.
 */
class HumanConfidenceEchoElement extends FlowElement
{
    public string $dataKey = 'echo';

    public array $properties = [
        'Sealed' => ['type' => 'string'],
        'Page' => ['type' => 'string'],
        'Quoted' => ['type' => 'string'],
        'Listed' => ['type' => 'array'],
        'Own' => ['type' => 'string'],
    ];

    public function processInternal($flowData): void
    {
        $sealed = (string) $flowData->evidence->get('query.humanconfidence');
        $page = (string) $flowData->evidence->get('query.humanconfidencepage');
        $flowData->setElementData(new ElementDataDictionary($this, [
            'Sealed' => new AspectPropertyValue(null, $sealed),
            'Page' => $page,
            'Quoted' => new AspectPropertyValue(null, 'It sent "' . $sealed . '".'),
            'Listed' => new AspectPropertyValue(null, ['a', $sealed]),
            'Own' => new AspectPropertyValue(null, 'kept'),
        ]));
    }
}

/**
 * An element whose Data Key is query, so that its Properties are named as
 * the two Evidence values are.
 */
class HumanConfidenceQueryElement extends FlowElement
{
    public string $dataKey = 'query';

    public array $properties = [
        'HumanConfidence' => ['type' => 'string'],
        'HumanConfidencePage' => ['type' => 'string'],
        'Other' => ['type' => 'string'],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'HumanConfidence' => new AspectPropertyValue(null, 'named as the sealed result'),
            'HumanConfidencePage' => new AspectPropertyValue(null, 'named as the page id'),
            'Other' => new AspectPropertyValue(null, 'kept too'),
        ]));
    }
}

/**
 * The JSON Builder never writes query.humanconfidence or
 * query.humanconfidencepage, by name or by value, so neither reaches its
 * output or the JavaScript Builder's script, which carries that output.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#what-a-package-never-exposes
 */
class JsonBundlerHumanConfidenceTests extends TestCase
{
    private const SEALED = 'SEALED-HUMAN-CONFIDENCE-0123456789';

    private const PAGE = 'PAGE-ID-abcdefghijklmnop';

    public function testTheJsonLeavesOutTheSealedResultAndThePageId(): void
    {
        foreach (['query.humanconfidence', 'query.HumanConfidence', 'QUERY.HUMANCONFIDENCE'] as $key) {
            $flowData = (new PipelineBuilder([]))
                ->add(new HumanConfidenceEchoElement())
                ->add(new HumanConfidenceQueryElement())
                ->build()
                ->createFlowData();
            $flowData->evidence->set($key, self::SEALED);
            $flowData->evidence->set(str_ireplace('humanconfidence', 'humanconfidencepage', $key), self::PAGE);
            $flowData->process();

            $json = $flowData->jsonbundler->json;
            $written = (string) json_encode($json, JSON_UNESCAPED_SLASHES);
            $this->assertStringNotContainsString(self::SEALED, $written, $key);
            $this->assertStringNotContainsString(self::PAGE, $written, $key);
            foreach (['sealed', 'page', 'quoted', 'listed'] as $property) {
                $this->assertArrayNotHasKey($property, $json['echo'], $key);
            }
            $this->assertSame('kept', $json['echo']['own'], $key);
            $this->assertSame(['other' => 'kept too'], $json['query'], $key);

            $script = (string) $flowData->javascriptbuilder->javascript;
            $this->assertStringContainsString('kept too', $script, $key);
            $this->assertStringNotContainsString(self::SEALED, $script, $key);
            $this->assertStringNotContainsString(self::PAGE, $script, $key);
        }
    }

    /**
     * An empty sealed result or page id withholds nothing.
     */
    public function testAnEmptySealedResultWithholdsNothing(): void
    {
        $flowData = (new PipelineBuilder([]))
            ->add(new HumanConfidenceEchoElement())
            ->build()
            ->createFlowData();
        $flowData->evidence->set('query.humanconfidence', '');
        $flowData->process();

        $json = $flowData->jsonbundler->json;
        $this->assertSame('', $json['echo']['sealed']);
        $this->assertSame('kept', $json['echo']['own']);
    }
}
