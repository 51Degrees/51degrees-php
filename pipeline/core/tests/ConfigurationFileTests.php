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

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\ConfiguredElement;
use fiftyone\pipeline\core\tests\classes\ExampleFlowElement1;
use PHPUnit\Framework\TestCase;

/**
 * A configuration file laid out as the other languages lay theirs out, as
 * pipeline-configuration.md in the Pipeline specification requires.
 * PipelineOptions holds Elements, and each entry names the element in
 * BuilderName and gives its settings in BuildParameters, as in the .NET and
 * Java sample configuration files that the specification links to.
 */
class ConfigurationFileTests extends TestCase
{
    /**
     * The file's text, written out so that the names it uses are the ones
     * the test checks. The class names fill the two placeholders.
     */
    private const FILE_TEXT = <<<'JSON'
{
  "PipelineOptions": {
    "Elements": [
      {
        "BuilderName": %1$s,
        "BuildParameters": {
          "dataKey": "first",
          "answer": 1
        }
      },
      {
        "BuilderName": %1$s,
        "BuildParameters": {
          "dataKey": "second",
          "answer": 2
        }
      },
      {
        "BuilderName": %2$s
      }
    ]
  }
}
JSON;

    public function testTheSharedLayoutBuildsEachElementInOrder(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'pipeline');
        file_put_contents($file, sprintf(
            self::FILE_TEXT,
            json_encode(ConfiguredElement::class),
            json_encode(ExampleFlowElement1::class)
        ));

        try {
            $pipeline = (new PipelineBuilder())->buildFromConfig($file);
            $this->assertSame(
                ['first', 'second', 'example1'],
                array_keys($pipeline->flowElementsList)
            );

            $flowData = $pipeline->createFlowData();
            $flowData->process();

            // Each entry's BuildParameters reached its own element, and an
            // entry without them built its element with none.
            $this->assertSame(1, $flowData->get('first')->get('answer'));
            $this->assertSame(2, $flowData->get('second')->get('answer'));
            $this->assertSame(5, $flowData->get('example1')->get('integer'));
        } finally {
            unlink($file);
        }
    }
}
