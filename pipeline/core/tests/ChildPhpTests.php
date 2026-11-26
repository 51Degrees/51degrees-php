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
        return [
            'warning in a child' => [
                self::inChild('trigger_error("canary", E_USER_WARNING);'),
                'PHP Warning:  canary',
            ],
            'notice in a child' => [
                self::inChild('trigger_error("canary", E_USER_NOTICE);'),
                'PHP Notice:  canary',
            ],
            'deprecation in a child' => [
                self::inChild('trigger_error("canary", E_USER_DEPRECATED);'),
                'PHP Deprecated:  canary',
            ],
            'fatal error in a child' => [
                self::inChild('canary();'),
                'PHP Fatal error:  Uncaught Error: Call to undefined function canary()',
            ],
            'parse error in a child' => [
                self::inChild('echo canary canary;'),
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
            self::inChild('canary();') .
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

    /**
     * The tests of the web examples read the status through read(), so it
     * must hand back the headers of the request it made, on every PHP.
     */
    public function testReadHandsBackTheResponseHeaders(): void
    {
        $folder = sys_get_temp_dir() . '/read-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($folder));
        $router = $folder . '/router.php';
        file_put_contents(
            $router,
            '<?php http_response_code(201); header("X-Canary: canary"); echo "page";'
        );
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr($name, strrpos($name, ':') + 1);
        $log = $folder . '/server.log';
        $server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", $router],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
            $pipes,
            $folder
        );
        $this->assertIsResource($server);
        fclose($pipes[0]);
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        try {
            $deadline = microtime(true) + 10;
            do {
                $page = PhpMessage::read("http://127.0.0.1:{$port}/", $context, $headers);
                if ($page !== false) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $deadline && proc_get_status($server)['running']);
        } finally {
            proc_terminate($server);
            proc_close($server);
            unlink($log);
            unlink($router);
            rmdir($folder);
        }

        $this->assertSame('page', $page);
        $this->assertMatchesRegularExpression('~^HTTP/\S+ 201 ~', $headers[0] ?? '');
        $this->assertContains('X-Canary: canary', $headers);
    }

    public function testCheckPassesARunWithNoMessage(): void
    {
        [$status, $error] = $this->run_(
            self::inChild('echo 1;')
        );
        $this->assertSame('', $error);
        $this->assertSame(0, $status);
    }

    /**
     * Code that runs the given code in a PHP of its own. The code goes in
     * a file, as a shell can change code given on the command line. On
     * Windows escapeshellarg() turns each double quote into a space.
     */
    private static function inChild(string $code): string
    {
        return '$file = tempnam(sys_get_temp_dir(), "child-php-test");' .
            'try {' .
            'file_put_contents($file, ' . var_export("<?php\n" . $code . "\n", true) . ');' .
            'passthru(escapeshellarg(PHP_BINARY) . " " . escapeshellarg($file));' .
            '} finally {' .
            'unlink($file);' .
            '}';
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
