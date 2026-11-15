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
use fiftyone\pipeline\core\Pipeline;
use PHPUnit\Framework\TestCase;

/**
 * The Logger writes to a log target at a location given in its settings,
 * with no subclass, so that where the log goes and how much is written are
 * settings rather than code.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/logging.md
 */
class LoggerTargetTests extends TestCase
{
    /**
     * @var array<string>
     */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function file(): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'pipeline-log');
        $this->files[] = $file;

        return $file;
    }

    /**
     * @return array<string>
     */
    private static function lines(string $file): array
    {
        return file($file, FILE_IGNORE_NEW_LINES) ?: [];
    }

    public function testEntriesAtOrAboveTheLevelAreWrittenToTheLocation(): void
    {
        $file = $this->file();
        $pipeline = new Pipeline(
            [],
            ['logger' => new Logger('info', ['file' => $file])]
        );

        $pipeline->log('debug', 'Below the level.');
        $pipeline->log('info', 'At the level.');
        $pipeline->log('error', 'Above the level.');

        $lines = self::lines($file);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('info', $lines[0]);
        $this->assertStringEndsWith('At the level.', $lines[0]);
        $this->assertStringContainsString('error', $lines[1]);
        $this->assertStringEndsWith('Above the level.', $lines[1]);
    }

    public function testEachLoggerWritesToItsOwnLocation(): void
    {
        $first = $this->file();
        $second = $this->file();

        (new Logger('trace', ['file' => $first]))->log('warning', 'First.');
        (new Logger('trace', ['file' => $second]))->log('warning', 'Second.');

        $this->assertCount(1, self::lines($first));
        $this->assertStringEndsWith('First.', self::lines($first)[0]);
        $this->assertCount(1, self::lines($second));
        $this->assertStringEndsWith('Second.', self::lines($second)[0]);
    }

    /**
     * Entries are added to what the location already holds, so restarting
     * the application keeps the log written before.
     */
    public function testEntriesAreAddedToTheLocation(): void
    {
        $file = $this->file();
        file_put_contents($file, "Written before.\n");

        (new Logger('error', ['file' => $file]))->log('error', 'Written now.');

        $lines = self::lines($file);
        $this->assertCount(2, $lines);
        $this->assertSame('Written before.', $lines[0]);
        $this->assertStringEndsWith('Written now.', $lines[1]);
    }

    /**
     * A location that cannot be written does not stop the caller, even
     * where the application's error handler turns every PHP warning into
     * an exception.
     */
    public function testALocationThatCannotBeWrittenIsIgnored(): void
    {
        $logger = new Logger('error', [
            'file' => sys_get_temp_dir() . '/no-such-folder-' .
                bin2hex(random_bytes(8)) . '/pipeline.log'
        ]);

        set_error_handler(function (int $level, string $message): bool {
            throw new \ErrorException($message, 0, $level);
        });
        try {
            $logger->log('error', 'Not written.');
        } finally {
            restore_error_handler();
        }

        $this->addToAssertionCount(1);
    }
}
