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

use fiftyone\pipeline\devicedetection\tests\classes\FailingElement;
use PHPUnit\Framework\TestCase;

/**
 * The examples demonstrate the settings that are commonly used but are not
 * the default. The Getting Started web example sets suppressProcessExceptions
 * so that a failure while processing a request still serves the page. The
 * case runs the example in its own PHP with the free Lite data file, with
 * its own configuration plus an element that always fails.
 */
class ExampleSettingsTests extends TestCase
{
    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    private const CONFIG = __DIR__ .
        '/../examples/onpremise/gettingStartedWeb.json';

    // The variables a web server gives a script for a request for the home
    // page. PHP from the command line puts environment variables in
    // $_SERVER, so the example sees the request.
    private const WEB_REQUEST = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_HOST' => 'localhost',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
            'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];

    // Serves the page as gettingStartedWeb.php does, from the configuration
    // file the second argument names. The first is the autoloader.
    private const SERVE = <<<'PHP'
        require $argv[1];
        (new fiftyone\pipeline\devicedetection\examples\onpremise\classes\GettingStartedWeb())->run(
            $argv[2],
            new fiftyone\pipeline\core\Logger('info'),
            function ($text) { echo $text; }
        );
        PHP;

    public function testGettingStartedWebServesThePageWhenAnElementFails(): void
    {
        $config = json_decode((string) file_get_contents(self::CONFIG), true);
        $config['PipelineOptions']['Elements'][] = ['BuilderName' => FailingElement::class];
        $file = (string) tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($file, json_encode($config));
        try {
            [$status, $output] = $this->serve($file);
        } finally {
            unlink($file);
        }

        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('<div id="find-out-more">', $output, $output);
        $this->assertStringNotContainsString(FailingElement::MESSAGE, $output, $output);
    }

    /**
     * The exit status and what the example printed, in a PHP started with
     * the Lite data file.
     *
     * @return array{0: int, 1: string}
     */
    private function serve(string $config): array
    {
        $dataFile = realpath(self::LITE_DATA_FILE);
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $environment = getenv();
        unset($environment['_51DEGREES_DD_PATH']);
        $process = proc_open(
            [
                PHP_BINARY,
                '-d',
                "FiftyOneDegreesHashEngine.data_file={$dataFile}",
                '-r',
                self::SERVE,
                '--',
                $this->autoload(),
                $config
            ],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            array_merge($environment, self::WEB_REQUEST)
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        return [$status, $output . $errors];
    }

    private function autoload(): string
    {
        return dirname(
            (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))
                ->getFileName(),
            2
        ) . '/autoload.php';
    }
}
