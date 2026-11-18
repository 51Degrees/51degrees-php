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

namespace fiftyone\pipeline\ipintelligence\tests;

use PHPUnit\Framework\TestCase;

/**
 * PHP starts with the IP Intelligence extension whatever the data file
 * setting names. A file that is not an IP Intelligence data file leaves the
 * extension with no engine and a warning naming the file and the setting,
 * where it once stopped PHP before any script ran. Each case starts its own
 * PHP with no php.ini at all and the extension given on the command line, so
 * that nothing the package or the machine sets takes part.
 */
class StartWithUnusableDataFileTests extends TestCase
{
    private const EXTENSION = 'FiftyOneDegreesIpIntelligenceEngine';

    private const SETTING = self::EXTENSION . '.data_file';

    /**
     * Prints what the extension's engine accessor gives, which is NULL when
     * the extension built no engine.
     */
    private const ENGINE = 'var_export(ipi_engine_get());';

    /** @var null|string The extension's file, found once */
    private static $extension;

    /** @var null|string A file holding something that is not a data file */
    private static $unusable;

    public static function tearDownAfterClass(): void
    {
        if (self::$unusable !== null) {
            @unlink(self::$unusable);
            self::$unusable = null;
        }
    }

    protected function setUp(): void
    {
        if (self::$extension === null) {
            self::$extension = $this->extensionFile();
        }
        if (self::$unusable === null) {
            self::$unusable = tempnam(sys_get_temp_dir(), 'ipi-not-a-data-file');
            if (self::$unusable === false) {
                self::$unusable = null;
                $this->markTestSkipped('No temporary file could be made.');
            }
            file_put_contents(
                (string) self::$unusable,
                "This is not an IP Intelligence data file.\n"
            );
        }
    }

    public function testPhpStartsWhenTheDataFileCannotBeUsed(): void
    {
        $file = (string) self::$unusable;
        [$status, $printed] = $this->runPhp([
            '-d', self::SETTING . '=' . $file,
            '-r', self::ENGINE
        ]);

        $this->assertSame(0, $status, $printed);
        $this->assertStringEndsWith('NULL', $printed);
        $this->assertStringContainsString(
            "No IP intelligence engine was built from '" . $file .
            "', which " . self::SETTING . ' names.',
            $printed
        );
    }

    public function testPhpStartsWhenTheDataFileIsMissing(): void
    {
        [$status, $printed] = $this->runPhp([
            '-d', self::SETTING . '=' . __DIR__ . '/missing/51Degrees.ipi',
            '-r', self::ENGINE
        ]);

        $this->assertSame(0, $status, $printed);
        $this->assertSame('NULL', $printed);
    }

    public function testPhpStartsWithNoDataFileSetting(): void
    {
        [$status, $printed] = $this->runPhp(['-r', self::ENGINE]);

        $this->assertSame(0, $status, $printed);
        $this->assertSame('NULL', $printed);
    }

    /**
     * The extension's file, as the command line loads it, or a skipped test
     * where it was not built.
     */
    private function extensionFile(): string
    {
        $directory = (string) ini_get('extension_dir');
        foreach ([
            __DIR__ . '/../on-premise/modules/' . self::EXTENSION . '.so',
            $directory . '/' . self::EXTENSION . '.so',
            $directory . '/php_' . self::EXTENSION . '.dll'
        ] as $candidate) {
            $found = realpath($candidate);
            if ($found !== false) {
                return $found;
            }
        }

        $this->markTestSkipped('The built extension was not found.');
    }

    /**
     * Runs PHP with no php.ini of its own, the extension loaded from the
     * file found above, and the options given, and gives its exit status and
     * everything it wrote.
     *
     * @param array<string> $options
     *
     * @return array{0: int, 1: string}
     */
    private function runPhp(array $options): array
    {
        $command = implode(' ', array_map('escapeshellarg', array_merge(
            [
                PHP_BINARY,
                '-n',
                '-d', 'extension=' . (string) self::$extension,
                '-d', 'display_errors=1',
                '-d', 'error_reporting=' . (string) E_ALL
            ],
            $options
        ))) . ' 2>&1';
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $printed = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($process);

        return [$status, trim($printed)];
    }
}
