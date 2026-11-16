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
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use JShrink\Minifier;
use PHPUnit\Framework\TestCase;

/**
 * Device data whose promise and fetch support, and a text value, are taken
 * from the query evidence, so that one Pipeline renders the script with each
 * choice the template makes.
 */
class MinifyChoicesDeviceElement extends FlowElement
{
    public string $dataKey = 'device';

    public array $properties = [
        'promise' => ['type' => 'string'],
        'fetch' => ['type' => 'bool'],
        'text' => ['type' => 'string'],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'promise' => new AspectPropertyValue(
                null,
                $flowData->evidence->get('query.t-promise') === 'yes'
                    ? 'Full' : 'None'
            ),
            'fetch' => new AspectPropertyValue(
                null,
                $flowData->evidence->get('query.t-fetch') === 'yes'
            ),
            'text' => new AspectPropertyValue(
                null,
                (string) $flowData->evidence->get('query.t-text')
            ),
        ]));
    }
}

/**
 * A JavaScript Property whose execution is delayed, so that the JSON names
 * delayexecution.
 */
class MinifyChoicesDelayedElement extends FlowElement
{
    public string $dataKey = 'delayed';

    public array $properties = [
        'script' => ['type' => 'javascript', 'delayexecution' => true],
        'needs' => ['evidenceproperties' => ['script']],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'script' => 'document.cookie = "51D_Width=" + screen.width;',
            'needs' => 1,
        ]));
    }
}

/**
 * Minification uses the third-party library JShrink, so the minified script
 * is always what JShrink gives for the script that is not minified, whatever
 * the template chooses to include and whatever values are written into it.
 * The same Pipeline renders each choice in turn, so that nothing kept from
 * one request changes the script of another.
 */
class JavaScriptBuilderMinifyChoicesTests extends TestCase
{
    /**
     * Text with quotes, comment and regular expression marks, a closing
     * script tag, escapes, a new line and characters outside ASCII.
     */
    private const AWKWARD_TEXT =
        "a \"double\" and 'single' // not a comment /* nor this */ " .
        "</script> /x+/g \\ back\nnew line \u{e9}\u{4e2d}\u{1f600} {{_objName}}";

    public function testEachChoiceIsJShrinksOutput()
    {
        $checked = 0;
        foreach ([false, true] as $delayed) {
            $minified = self::pipeline(true, $delayed);
            $plain = self::pipeline(false, $delayed);
            foreach (self::evidenceChoices() as $name => $evidence) {
                $expected = Minifier::minify(self::script($plain, $evidence));
                $actual = self::script($minified, $evidence);
                $this->assertSame(
                    $expected,
                    $actual,
                    "delayed " . ($delayed ? 'yes' : 'no') . ", $name"
                );
                $checked++;
            }
        }

        $this->assertSame(128, $checked);
    }

    public function testTheChoicesChangeTheScript()
    {
        // Each switch changes the minified script, or the test above would
        // not have covered the template's sections.
        $pipeline = self::pipeline(true, false);
        $base = self::evidence(false, false, false, false, false, false);
        $baseScript = self::script($pipeline, $base);
        foreach (range(0, 5) as $switch) {
            $flags = array_fill(0, 6, false);
            $flags[$switch] = true;
            $this->assertNotSame(
                $baseScript,
                self::script($pipeline, self::evidence(...$flags)),
                "switch $switch"
            );
        }

        $this->assertNotSame(
            $baseScript,
            self::script(self::pipeline(true, true), $base)
        );
    }

    /**
     * @return iterable<string, array<string, string>>
     */
    private static function evidenceChoices(): iterable
    {
        for ($bits = 0; $bits < 64; $bits++) {
            $flags = [];
            for ($bit = 0; $bit < 6; $bit++) {
                $flags[] = ($bits & (1 << $bit)) !== 0;
            }
            yield "choices $bits" => self::evidence(...$flags);
        }
    }

    /**
     * @return array<string, string>
     */
    private static function evidence(
        bool $host,
        bool $cookies,
        bool $promise,
        bool $fetch,
        bool $objectName,
        bool $awkward
    ): array {
        $evidence = [
            'query.session-id' => 'minify-choices',
            'query.sequence' => '2',
            'query.fod-js-enable-cookies' => $cookies ? 'true' : 'false',
            'query.t-promise' => $promise ? 'yes' : 'no',
            'query.t-fetch' => $fetch ? 'yes' : 'no',
            'query.t-text' => $awkward ? self::AWKWARD_TEXT : 'plain',
        ];
        if ($host) {
            $evidence['header.host'] = 'example.com';
        }
        if ($objectName) {
            $evidence['query.fod-js-object-name'] = 'my$Object_2';
        }

        return $evidence;
    }

    private static function pipeline(bool $minify, bool $delayed): Pipeline
    {
        $builder = (new PipelineBuilder([
            'javascriptBuilderSettings' => [
                'minify' => $minify,
                'endpoint' => '/51dpipeline/json'
            ]
        ]))->add(new MinifyChoicesDeviceElement());
        if ($delayed) {
            $builder->add(new MinifyChoicesDelayedElement());
        }

        return $builder->build();
    }

    /**
     * @param array<string, string> $evidence
     */
    private static function script(Pipeline $pipeline, array $evidence): string
    {
        $flowData = $pipeline->createFlowData();
        foreach ($evidence as $key => $value) {
            $flowData->evidence->set($key, $value);
        }
        $flowData->process();

        return $flowData->javascriptbuilder->javascript;
    }
}
