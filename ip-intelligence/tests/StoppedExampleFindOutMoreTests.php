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
 * An example that cannot run still ends with the "Find out more" section,
 * because the person running it is the one who most needs somewhere to go.
 * Each case starts its own PHP with no php.ini, so the extension is not
 * loaded and the example stops on its first check.
 */
class StoppedExampleFindOutMoreTests extends TestCase
{
    /**
     * The console examples that need the native extension, so that each
     * stops on the same check.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function examples(): iterable
    {
        foreach ([
            'gettingstarted-console.php',
            'metadata-console.php'
        ] as $name) {
            yield $name => [__DIR__ . '/../examples/onpremise/' . $name];
        }
    }

    /**
     * @dataProvider examples
     */
    public function testAnExampleThatCannotStartGivesTheFindOutMoreLinks(
        string $example
    ): void {
        if (!is_file($example)) {
            $this->markTestSkipped($example . ' is not there.');
        }

        [$status, $printed] = $this->runPhp($example);

        $this->assertSame(1, $status, $printed);
        $this->assertStringContainsString('Find out more', $printed);
        $this->assertStringContainsString(
            'https://51degrees.com/',
            $printed
        );
        $this->assertStringContainsString('utm_source=code', $printed);
    }

    /**
     * Runs the example in a PHP with no php.ini of its own, so that no
     * extension is loaded, and gives its exit status and what it wrote.
     *
     * @return array{0: int, 1: string}
     */
    private function runPhp(string $example): array
    {
        $command = implode(' ', array_map('escapeshellarg', [
            PHP_BINARY,
            '-n',
            '-d', 'display_errors=1',
            $example
        ])) . ' 2>&1';
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
