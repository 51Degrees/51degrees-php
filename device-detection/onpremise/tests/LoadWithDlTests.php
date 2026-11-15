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

namespace fiftyone\pipeline\devicedetection\tests;

use PHPUnit\Framework\TestCase;

/**
 * The extension loads with dl() and PHP then stops cleanly. PHP unloads an
 * extension loaded with dl() when the request ends and frees what it
 * registered, and the C library stops PHP for a damaged heap only some of
 * the time, so the command runs several times. No ini file is read, so
 * nothing else loads the extension.
 */
class LoadWithDlTests extends TestCase
{
    private const EXTENSION = __DIR__ .
        '/../on-premise/modules/FiftyOneDegreesHashEngine.so';

    private const RUNS = 10;

    // Loads the extension and prints whether PHP has it.
    private const LOAD = <<<'PHP'
        dl('FiftyOneDegreesHashEngine.so');
        echo extension_loaded('FiftyOneDegreesHashEngine')
            ? 'loaded' : 'missing';
        PHP;

    public function testLoadsWithDlAndPhpStopsCleanly()
    {
        $extension = realpath(self::EXTENSION);
        if ($extension === false) {
            $this->markTestSkipped('The built extension was not found.');
        }
        for ($run = 1; $run <= self::RUNS; $run++) {
            [$status, $printed] = $this->runPhp([
                '-n',
                '-d', 'enable_dl=1',
                '-d', 'extension_dir=' . dirname($extension),
                '-r', self::LOAD,
            ]);
            $this->assertSame(0, $status, "Run {$run}: {$printed}");
            $this->assertSame('loaded', $printed, "Run {$run}");
        }
    }

    /**
     * Runs PHP with the arguments, and returns its exit status and what it
     * wrote.
     *
     * @param array<string> $arguments
     * @return array{0: int, 1: string}
     */
    private function runPhp(array $arguments): array
    {
        $process = proc_open(
            array_merge([PHP_BINARY], $arguments),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],
            ],
            $pipes
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $printed = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return [proc_close($process), $printed];
    }
}
