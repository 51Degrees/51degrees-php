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

use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * pipeline-specification/features/logging.md line 4 at
 * 51Degrees/specifications dbaf099c: "Whichever library is used, the system
 * SHOULD be configurable without rebuilding binaries and capable of
 * outputting to a log target at a configurable location."
 */
class LogTargetTests extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'pipeline-log-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /**
     * The Logger itself writes to a file at the location its settings give,
     * one line for each entry at or above its level, with no subclass.
     */
    public function testTheLoggerWritesToTheFileItsSettingsName(): void
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . 'pipeline.log';
        $logger = new Logger('info', ['file' => $file]);

        $logger->log('debug', 'below the level');
        $logger->log('info', 'first entry');
        $logger->log('error', 'second entry');

        $this->assertFileExists($file);
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $this->assertCount(2, $lines);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[info\] first entry$/',
            $lines[0]
        );
        $this->assertStringEndsWith('[error] second entry', $lines[1]);
    }

    /**
     * Entries are added to a file that is already there, so a log kept
     * across requests keeps every request's entries.
     */
    public function testEntriesAreAddedToTheFile(): void
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . 'pipeline.log';
        file_put_contents($file, "earlier\n");

        (new Logger('warning', ['file' => $file]))->log('warning', 'later');

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $this->assertSame('earlier', $lines[0]);
        $this->assertStringEndsWith('[warning] later', $lines[1]);
    }

    /**
     * A configuration file, which needs no code changed, gives the level
     * and the location of the log, and the Pipeline built from it logs
     * there.
     */
    public function testAConfigurationFileGivesTheLevelAndTheLocation(): void
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . 'configured.log';
        $configuration = $this->directory . DIRECTORY_SEPARATOR . 'pipeline.json';
        file_put_contents($configuration, json_encode([
            'PipelineOptions' => [
                'Logging' => ['Level' => 'Debug', 'File' => $file],
                'Elements' => [],
            ],
        ]));

        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->buildFromConfig($configuration);
        $pipeline->log('trace', 'below the level');
        $pipeline->log('debug', 'from the configured pipeline');

        $this->assertFileExists($file);
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $this->assertCount(1, $lines);
        $this->assertStringEndsWith('[debug] from the configured pipeline', $lines[0]);
    }

    /**
     * A logger the application adds in code is kept, so the configuration
     * does not replace a logger written for the application.
     */
    public function testALoggerAddedInCodeIsKept(): void
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . 'configured.log';
        $logger = new class ('trace') extends Logger {
            /** @var array<string> */
            public array $messages = [];

            public function logInternal(array $log): void
            {
                $this->messages[] = $log['message'];
            }
        };

        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->addLogger($logger)->buildFromConfig([
            'PipelineOptions' => [
                'Logging' => ['Level' => 'Trace', 'File' => $file],
                'Elements' => [],
            ],
        ]);
        $pipeline->log('info', 'to the application logger');

        $this->assertSame(['to the application logger'], $logger->messages);
        $this->assertFileDoesNotExist($file);
    }
}
