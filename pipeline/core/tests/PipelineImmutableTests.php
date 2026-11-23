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

use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\ErrorFlowData;
use PHPUnit\Framework\TestCase;

/**
 * An element that records that it ran, under the Data Key it is given.
 */
class RanElement extends FlowElement
{
    public array $properties = [
        'ran' => ['type' => 'bool'],
    ];

    public function __construct(string $dataKey)
    {
        parent::__construct();
        $this->dataKey = $dataKey;
    }

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'ran' => true,
        ]));
    }
}

/**
 * A Pipeline cannot be changed once it is built, as conceptual-overview.md
 * in the Pipeline specification asks, and its elements can still be read.
 */
class PipelineImmutableTests extends TestCase
{
    private static function pipeline(): Pipeline
    {
        return new Pipeline([new RanElement('first')], []);
    }

    /**
     * The Data Keys of the elements that ran.
     *
     * @return array<string>
     */
    private static function ran(Pipeline $pipeline): array
    {
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->process();

            return array_keys($flowData->data);
        } finally {
            $flowData->close();
        }
    }

    public function testTheElementsCanBeRead()
    {
        $pipeline = self::pipeline();

        $this->assertCount(1, $pipeline->flowElements);
        $this->assertTrue(isset($pipeline->flowElements));
        $this->assertFalse(empty($pipeline->flowElementsList));
        $this->assertSame('first', $pipeline->flowElements[0]->dataKey);
        $this->assertTrue(isset($pipeline->flowElementsList['first']));
        $this->assertFalse(isset($pipeline->flowElementsList['second']));
        $this->assertSame(
            $pipeline->flowElements[0],
            $pipeline->flowElementsList['first'] ?? null
        );
        $this->assertNull($pipeline->flowElementsList['second'] ?? null);
        $this->assertSame('first', $pipeline->getElement('first')->dataKey);
    }

    public function testAnElementAddedToTheListDoesNotRun()
    {
        $pipeline = self::pipeline();

        $pipeline->flowElements[] = new RanElement('second');

        $this->assertSame(['first'], self::ran($pipeline));
        $this->assertCount(1, $pipeline->flowElements);
    }

    public function testAnElementRemovedFromTheListStillRuns()
    {
        $pipeline = self::pipeline();

        unset($pipeline->flowElements[0]);

        $this->assertSame(['first'], self::ran($pipeline));
    }

    public function testAnElementAddedByDataKeyIsNotFound()
    {
        $pipeline = self::pipeline();

        $pipeline->flowElementsList['second'] = new RanElement('second');

        $this->assertFalse(isset($pipeline->flowElementsList['second']));
    }

    public function testTheListsCannotBeReplaced()
    {
        $pipeline = self::pipeline();

        foreach (['flowElements', 'flowElementsList'] as $name) {
            $refusal = null;
            try {
                $pipeline->{$name} = [];
            } catch (\Exception $e) {
                $refusal = $e->getMessage();
            }
            $this->assertNotNull($refusal, "{$name} was replaced.");
            $this->assertStringContainsString($name, $refusal);
        }
        $this->assertSame(['first'], self::ran($pipeline));
    }

    /**
     * Each setting is given when the Pipeline is built, to the
     * PipelineBuilder or to the Pipeline, and is read from the Pipeline.
     */
    public function testTheSettingsAreGivenWhenThePipelineIsBuilt()
    {
        $logger = new Logger('info');
        $store = new \stdClass();
        $settings = [
            'logger' => $logger,
            'metaDataStore' => $store,
            'suppressProcessExceptions' => true,
            'autoCloseElements' => false,
        ];
        $pipelines = [
            'builder' => (new PipelineBuilder($settings))
                ->add(new RanElement('first'))
                ->build(),
            'pipeline' => new Pipeline([new RanElement('first')], $settings),
        ];

        foreach ($pipelines as $how => $pipeline) {
            $this->assertSame($logger, $pipeline->logger, $how);
            $this->assertSame($store, $pipeline->metaDataStore, $how);
            $this->assertTrue($pipeline->suppressProcessExceptions, $how);
            $this->assertFalse($pipeline->autoCloseElements, $how);
            foreach (array_keys($settings) as $name) {
                $this->assertTrue(isset($pipeline->{$name}), "{$how} {$name}");
            }
        }

        $pipeline = self::pipeline();
        $this->assertInstanceOf(Logger::class, $pipeline->logger);
        $this->assertFalse(isset($pipeline->metaDataStore));
        $this->assertFalse($pipeline->suppressProcessExceptions);
        $this->assertTrue($pipeline->autoCloseElements);
    }

    public function testTheLoggerCannotBeSet()
    {
        $logger = self::keepingLogger();
        $pipeline = new Pipeline(
            [new RanElement('first')],
            ['logger' => $logger]
        );

        $this->assertSettingRefused($pipeline, 'logger', self::keepingLogger());

        $this->assertSame($logger, $pipeline->logger);
        $pipeline->log('error', 'logged');
        $this->assertSame(['logged'], $logger->messages);
    }

    public function testTheMetaDataStoreCannotBeSet()
    {
        $pipeline = new Pipeline(
            [new RanElement('first')],
            ['metaDataStore' => 'built']
        );

        $this->assertSettingRefused($pipeline, 'metaDataStore', 'other');

        $this->assertSame('built', $pipeline->metaDataStore);
    }

    /**
     * The Pipeline still throws the error of an element that fails.
     */
    public function testSuppressProcessExceptionsCannotBeSet()
    {
        $pipeline = new Pipeline([new ErrorFlowData()], []);

        $this->assertSettingRefused(
            $pipeline,
            'suppressProcessExceptions',
            true
        );

        $this->assertFalse($pipeline->suppressProcessExceptions);
        $flowData = $pipeline->createFlowData();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Something went wrong');
        $flowData->process();
    }

    /**
     * Closing the Pipeline still closes its elements.
     */
    public function testAutoCloseElementsCannotBeSet()
    {
        $element = new class() extends RanElement {
            public int $closes = 0;

            public function __construct()
            {
                parent::__construct('first');
            }

            public function close(): void
            {
                ++$this->closes;
            }
        };
        $pipeline = new Pipeline([$element], []);

        $this->assertSettingRefused($pipeline, 'autoCloseElements', false);

        $this->assertTrue($pipeline->autoCloseElements);
        $pipeline->close();
        $this->assertSame(1, $element->closes);
    }

    /**
     * The database the Pipeline builds from its elements' properties can be
     * read, but neither replaced nor changed.
     */
    public function testThePropertyDatabaseCannotBeSet()
    {
        $pipeline = self::pipeline();
        $built = $pipeline->propertyDatabase;

        $refusal = null;
        try {
            $pipeline->propertyDatabase = [];
        } catch (\Exception $e) {
            $refusal = $e->getMessage();
        }
        $this->assertNotNull($refusal, 'propertyDatabase was replaced.');
        $this->assertStringContainsString("'propertyDatabase'", $refusal);

        $pipeline->propertyDatabase['type'] = [];

        $this->assertArrayHasKey('bool', $built['type']);
        $this->assertSame($built, $pipeline->propertyDatabase);
    }

    /**
     * Nor can a property the Pipeline keeps to itself be set from outside.
     */
    public function testNoOtherPropertyItDeclaresCanBeSet()
    {
        $pipeline = self::pipeline();

        foreach (['deprecatedDataKeys', 'deprecatedDataKeysLogged'] as $name) {
            $refusal = null;
            try {
                $pipeline->{$name} = ['other' => 'first'];
            } catch (\Exception $e) {
                $refusal = $e->getMessage();
            }
            $this->assertNotNull($refusal, "{$name} was set.");
            $this->assertStringContainsString("'{$name}'", $refusal);
        }
        $this->assertSame(['first'], self::ran($pipeline));
    }

    /**
     * Sets the property, and checks that this is refused with a message
     * that names it and says to give it in the settings.
     *
     * @param mixed $value
     */
    private function assertSettingRefused(
        Pipeline $pipeline,
        string $name,
        $value
    ): void {
        $refusal = null;
        try {
            $pipeline->{$name} = $value;
        } catch (\Exception $e) {
            $refusal = $e->getMessage();
        }
        $this->assertNotNull($refusal, "{$name} was set.");
        $this->assertStringContainsString("'{$name}'", $refusal);
        $this->assertStringContainsString('settings', $refusal);
    }

    /**
     * A logger that keeps the message of every entry.
     */
    private static function keepingLogger(): Logger
    {
        return new class ('trace') extends Logger {
            /** @var array<string> */
            public array $messages = [];

            public function logInternal(array $log): void
            {
                $this->messages[] = $log['message'];
            }
        };
    }
}
