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
use PHPUnit\Framework\TestCase;

/**
 * The JUnit file of a run that the end of run check fails holds a failing
 * test case with what PHP logged, and the file of a clean run is as
 * PHPUnit wrote it. Each test runs PHPUnit, as this run was started, on
 * one test that starts a PHP of its own.
 */
class ChildPhpJunitTests extends TestCase
{
    /** @var string */
    private $folder = '';

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/child-php-junit-' .
            bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($this->folder));
    }

    protected function tearDown(): void
    {
        // PHPUnit also leaves its result cache, a dot file, here.
        foreach (array_diff((array) scandir($this->folder), ['.', '..']) as $file) {
            unlink($this->folder . '/' . $file);
        }
        rmdir($this->folder);
    }

    public function testAFailedCheckAddsAFailingTestCase(): void
    {
        [$status, $suite] = $this->runPhpUnit(
            'trigger_error("canary", E_USER_WARNING);'
        );

        $this->assertSame(1, $status);
        $this->assertSame('2', $suite->getAttribute('tests'));
        $this->assertSame('1', $suite->getAttribute('failures'));
        $failures = $suite->getElementsByTagName('failure');
        $this->assertSame(1, $failures->length);
        $this->assertSame(ChildPhp::class, $failures->item(0)->parentNode->getAttribute('class'));
        $this->assertStringContainsString('PHP Warning:  canary', $failures->item(0)->textContent);
    }

    public function testACleanRunLeavesTheFileAsPhpUnitWroteIt(): void
    {
        [$status, $suite] = $this->runPhpUnit('echo 1;');

        $this->assertSame(0, $status);
        $this->assertSame('1', $suite->getAttribute('tests'));
        $this->assertSame('0', $suite->getAttribute('failures'));
        $this->assertSame(1, $suite->getElementsByTagName('testcase')->length);
        $this->assertSame(0, $suite->getElementsByTagName('failure')->length);
    }

    public function testLicenseKeysAreMaskedInWhatIsShownAndWritten(): void
    {
        // Made up keys, never real ones.
        $fromEnvironment = 'MADEUPKEY0000TEST1234';
        $inQuery = 'MADEUPQUERYKEY99998888';
        [$status, $suite, $printed, $junit] = $this->runPhpUnit(
            'trigger_error("key ' . $fromEnvironment . ' at ' .
                'https://cloud.example/x?LicenseKeys=' . $inQuery . '", E_USER_WARNING);',
            ['_51DEGREES_LICENSE_KEY' => $fromEnvironment]
        );

        $this->assertSame(1, $status);
        $this->assertSame('1', $suite->getAttribute('failures'));
        foreach ([$printed, $junit] as $text) {
            $this->assertStringNotContainsString($fromEnvironment, $text);
            $this->assertStringNotContainsString($inQuery, $text);
            $this->assertStringContainsString('*****************1234', $text);
            $this->assertStringContainsString('LicenseKeys=******************8888', $text);
        }
    }

    /**
     * Writes a configuration and one test that runs the code in a PHP of
     * its own, from a file, as a shell can change code given on the
     * command line, runs PHPUnit on it with --log-junit and the environment
     * variables given, and gives the exit status, the outer test suite of
     * the JUnit file, what PHPUnit printed and the JUnit file's text.
     *
     * @param array<string, string> $set
     * @return array{int, \DOMElement, string, string}
     */
    private function runPhpUnit(string $code, array $set = []): array
    {
        $class = 'ChildPhpJunitFixture' . bin2hex(random_bytes(4));
        $child = $this->folder . '/child.php';
        file_put_contents($child, "<?php\n" . $code . "\n");
        file_put_contents($this->folder . '/' . $class . '.php', "<?php\n" .
            "class {$class} extends \\PHPUnit\\Framework\\TestCase {\n" .
            "    public function testChild(): void {\n" .
            "        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(" .
                var_export($child, true) . "));\n" .
            "        \$this->assertTrue(true);\n" .
            "    }\n" .
            "}\n");
        file_put_contents($this->folder . '/phpunit.xml', '<phpunit bootstrap="' .
            __DIR__ . '/bootstrap.php"><testsuites><testsuite name="Fixture"><file>' .
            $this->folder . '/' . $class . '.php</file></testsuite></testsuites></phpunit>');

        // The run gets its own settings and check, as this run's are
        // left out of PHP_INI_SCAN_DIR.
        $environment = getenv();
        $environment['PHP_INI_SCAN_DIR'] = implode(PATH_SEPARATOR, array_filter(
            explode(PATH_SEPARATOR, $environment['PHP_INI_SCAN_DIR']),
            function (string $folder): bool {
                return $folder !== ChildPhp::folder();
            }
        ));
        $environment = array_merge($environment, $set);
        $junit = $this->folder . '/junit.xml';
        $process = proc_open(
            [
                PHP_BINARY,
                (string) realpath($GLOBALS['argv'][0]),
                '--configuration', $this->folder . '/phpunit.xml',
                '--log-junit', $junit,
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->folder,
            $environment
        );
        $this->assertIsResource($process);
        $printed = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        $document = new \DOMDocument();
        $this->assertTrue($document->load($junit), $printed);
        $suite = $document->documentElement->getElementsByTagName('testsuite')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $suite, $printed);

        return [$status, $suite, $printed, (string) file_get_contents($junit)];
    }
}
