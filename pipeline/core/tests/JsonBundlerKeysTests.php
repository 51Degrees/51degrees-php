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

class MixedCaseKeyElement extends FlowElement
{
    public string $dataKey = 'MixedKey';

    public array $properties = [
        'DelayedScript' => [
            'type' => 'javascript',
            'delayexecution' => true
        ],
        // NoValue is not delayed, so only DelayedScript is listed, and it is
        // second in the metadata.
        'NeedsScript' => [
            'type' => 'string',
            'evidenceproperties' => ['NoValue', 'DelayedScript']
        ],
        'NoValue' => [
            'type' => 'string'
        ]
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'DelayedScript' => 'script',
            'NeedsScript' => new AspectPropertyValue(null, 'x'),
            'NoValue' => new AspectPropertyValue('none'),
        ]));
    }
}

/**
 * Stands in for the Cloud Request Engine, whose Data Key is cloud and whose
 * Element Data holds the cloud service's raw response.
 */
class CloudStandInElement extends FlowElement
{
    public string $dataKey = 'cloud';

    public array $properties = [
        'cloud' => ['type' => 'string']
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'cloud' => '{"device":{"ismobile":true}}',
        ]));
    }
}

/**
 * Every key the JSON Bundler writes is lower case, except
 * javascriptProperties.
 */
class JsonBundlerKeysTests extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        $flowData = (new PipelineBuilder([]))
            ->add(new MixedCaseKeyElement())
            ->build()
            ->createFlowData();
        $flowData->process();

        return $flowData->jsonbundler->json;
    }

    public function testTopLevelKeysAreLowerCase()
    {
        $json = $this->json();

        $this->assertArrayHasKey('mixedkey', $json);
        $this->assertArrayNotHasKey('MixedKey', $json);
        $this->assertArrayHasKey('javascriptProperties', $json);
    }

    public function testEveryPropertyAndSuffixKeyIsLowerCase()
    {
        $this->assertEqualsCanonicalizing(
            [
                'delayedscript',
                'delayedscriptdelayexecution',
                'needsscript',
                'needsscriptevidenceproperties',
                'novalue',
                'novaluenullreason',
            ],
            array_keys($this->json()['mixedkey'])
        );
    }

    public function testEvidencePropertiesAreLowerCaseNamesInAList()
    {
        $this->assertSame(
            ['mixedkey.delayedscript'],
            $this->json()['mixedkey']['needsscriptevidenceproperties']
        );
    }

    public function testTheCloudRequestEnginesDataIsLeftOut()
    {
        $flowData = (new PipelineBuilder([]))
            ->add(new CloudStandInElement())
            ->add(new MixedCaseKeyElement())
            ->build()
            ->createFlowData();
        $flowData->process();
        $json = $flowData->jsonbundler->json;

        $this->assertArrayNotHasKey('cloud', $json);
        $this->assertArrayHasKey('mixedkey', $json);
    }
}
