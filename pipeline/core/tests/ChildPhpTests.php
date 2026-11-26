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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\tests\ChildPhp;
use fiftyone\pipeline\tests\PhpMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The check tests/bootstrap.php makes when a run ends. Each test starts a
 * PHP that loads the bootstrap as PHPUnit does and then runs the given
 * code, which may start a PHP of its own. That PHP gets an environment
 * without this run's settings, so the bootstrap it loads makes its own log
 * and checks it, and what it logs does not reach this run's log.
 */
class ChildPhpTests extends TestCase
{
    /**
     * Code that raises each level the check fails on, in a PHP the code
     * starts and in the process that loaded the bootstrap.
     *
     * @return array<string, array{string, string}>
     */
    public static function provider_fails(): array
    {
        $child = function (string $code): string {
            return 'passthru(escapeshellarg(PHP_BINARY) . " -r " . ' .
                'escapeshellarg(' . var_export($code, true) . '));';
        };
        return [
            'warning in a child' => [
                $child('trigger_error("canary", E_USER_WARNING);'),
                'PHP Warning:  canary',
            ],
            'notice in a child' => [
                $child('trigger_error("canary", E_USER_NOTICE);'),
                'PHP Notice:  canary',
            ],
            'deprecation in a child' => [
                $child('trigger_error("canary", E_USER_DEPRECATED);'),
                'PHP Deprecated:  canary',
            ],
            'fatal error in a child' => [
                $child('canary();'),
                'PHP Fatal error:  Uncaught Error: Call to undefined function canary()',
            ],
            'parse error in a child' => [
                $child('echo canary canary;'),
                'PHP Parse error:  ',
            ],
            'deprecation inside a call whose warning is taken' => [
                '\\fiftyone\\pipeline\\tests\\PhpMessage::take(' .
                    'function () { trigger_error("canary", E_USER_DEPRECATED); });',
                'PHP Deprecated:  canary',
            ],
            'deprecation outside a test' => [
                'trigger_error("canary", E_USER_DEPRECATED);',
                'PHP Deprecated:  canary',
            ],
        ];
    }

    /**
     * @dataProvider provider_fails
     */
    #[DataProvider('provider_fails')]
    public function testCheckFailsTheRun(string $code, string $logged): void
    {
        [$status, $error] = $this->run_($code);
        $this->assertStringContainsString($logged, $error);
        $this->assertStringContainsString('The run fails', $error);
        $this->assertSame(1, $status);
    }

    public function testCheckPassesARunWhoseFatalErrorWasTaken(): void
    {
        [$status, $error] = $this->run_(
            'passthru(escapeshellarg(PHP_BINARY) . " -r \"canary();\"");' .
            'exit(\\fiftyone\\pipeline\\tests\\ChildPhp::take(' .
            '"~PHP Fatal error:  Uncaught Error: Call to undefined function ' .
            'canary\\(\\)~") === 1 ? 0 : 2);'
        );
        $this->assertStringNotContainsString('The run fails', $error);
        $this->assertSame(0, $status);
    }

    public function testTakeHandsBackTheWarningOfTheCall(): void
    {
        $missing = sys_get_temp_dir() . '/no-such-folder-' .
            bin2hex(random_bytes(8)) . '/file';
        $handle = PhpMessage::take(
            function () use ($missing) {
                return fopen($missing, 'r');
            },
            $warning
        );
        $this->assertFalse($handle);
        $this->assertIsString($warning);
        $this->assertStringContainsString($missing, $warning);
    }

    public function testCheckPassesARunWithNoMessage(): void
    {
        [$status, $error] = $this->run_(
            'passthru(escapeshellarg(PHP_BINARY) . " -r \"echo 1;\"");'
        );
        $this->assertSame('', $error);
        $this->assertSame(0, $status);
    }

    /**
     * Runs the code in a PHP that loads the bootstrap first, and returns
     * its exit status and what it wrote to standard error.
     *
     * @return array{int, string}
     */
    private function run_(string $code): array
    {
        $script = tempnam(sys_get_temp_dir(), 'child-php-test');
        $this->assertNotFalse($script);
        try {
            file_put_contents(
                $script,
                '<?php require ' .
                    var_export(__DIR__ . '/bootstrap.php', true) . ";\n" .
                    $code . "\n"
            );
            $environment = getenv();
            $scan = array_filter(
                explode(PATH_SEPARATOR, $environment['PHP_INI_SCAN_DIR']),
                function (string $folder): bool {
                    return $folder !== ChildPhp::folder();
                }
            );
            $environment['PHP_INI_SCAN_DIR'] =
                implode(PATH_SEPARATOR, $scan);
            $process = proc_open(
                [PHP_BINARY, $script],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                $environment
            );
            $this->assertIsResource($process);
            stream_get_contents($pipes[1]);
            $error = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            return [proc_close($process), $error];
        } finally {
            unlink($script);
        }
    }
}
