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

use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\HumanConfidenceWeb;
use fiftyone\pipeline\devicedetection\tests\classes\Constants;
use fiftyone\pipeline\devicedetection\tests\classes\ExampleRun;
use fiftyone\pipeline\devicedetection\tests\classes\ResourceKeys;
use PHPUnit\Framework\TestCase;

/**
 * Serves the Human Confidence web example with PHP's built-in server and
 * asks it for what its page asks it for, with the resource key of the
 * tests. The browser tests need a browser, so the steps that follow them
 * are checked only as far as a program can take them.
 *
 * Every test is skipped with no resource key. The tests of the flow are
 * skipped where the resource key does not include the HumanConfidence
 * property, and the page is then checked for the link to contact us.
 */
class HumanConfidenceWebTests extends TestCase
{
    // The timeout of the requests the tests make to the cloud service
    // themselves. The example is served with the same timeout by
    // humanConfidenceWebRouter.php.
    private const CLOUD_TIMEOUT_SECONDS = 30;

    // How long the example has to answer one request. The example makes up
    // to three requests to the cloud service for one of its own.
    private const ANSWER_SECONDS = 120;

    // How long the server has to start listening.
    private const START_SECONDS = 60;

    // How long to wait before trying a server that is not yet listening
    // again.
    private const RETRY_MICROSECONDS = 100000;

    // The cloud service the example calls unless the environment names
    // another.
    private const DEFAULT_ENDPOINT = 'https://cloud.51degrees.com/api/v4/';

    // The properties the example reads, as the cloud service names them in
    // its results.
    private const PROPERTY = 'humanconfidence';
    private const SOURCE_PROPERTY = 'humanconfidencesource';

    // The levels a passive result can be.
    private const LEVELS = ['High', 'Medium', 'Low'];

    // Text that shows the example printed a programming fault rather than
    // an answer.
    private const FAULT_MARKERS = [
        'Fatal error',
        'Parse error',
        'Warning:',
        'Notice:',
        'Deprecated:',
        'Uncaught'
    ];

    /** @var null|string The resource key of the tests */
    private static $key;

    /**
     * @var null|array<string, bool> The names, in lower case, of the
     * properties the resource key includes, or null where the cloud service
     * did not list them
     */
    private static $properties;

    /** @var null|resource The server */
    private static $process;

    /** @var string The address the server listens on */
    private static $address = '';

    /** @var string The file the server prints to */
    private static $log = '';

    public static function setUpBeforeClass(): void
    {
        self::$key = ResourceKeys::find(Constants::RESOURCE_ENV_VAR);
        if (self::$key === null) {
            return;
        }

        self::$properties = self::accessibleProperties(self::$key);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('No free port was found.');
        }
        self::$address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        self::$log = (string) tempnam(sys_get_temp_dir(), 'example');
        // The example reads the resource key and the license key under the
        // names a person sets, so the server is given the keys of the tests
        // under those names, and no license key when the tests have none.
        $environment = getenv();
        $environment[ExampleUtils::RESOURCE_KEY_ENV_VAR] = self::$key;
        unset($environment[HumanConfidenceWeb::LICENSE_KEY_VARIABLE]);
        $license = ResourceKeys::find(Constants::LICENSE_ENV_VAR);
        if ($license !== null) {
            $environment[HumanConfidenceWeb::LICENSE_KEY_VARIABLE] = $license;
        }
        $process = proc_open(
            [PHP_BINARY, '-S', self::$address, __DIR__ . '/humanConfidenceWebRouter.php'],
            [0 => ['pipe', 'r'], 1 => ['file', self::$log, 'w'], 2 => ['file', self::$log, 'a']],
            $pipes,
            __DIR__ . '/../examples',
            $environment
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('The Human Confidence web example could not be served.');
        }
        fclose($pipes[0]);
        self::$process = $process;

        $deadline = microtime(true) + self::START_SECONDS;
        do {
            $connection = @stream_socket_client('tcp://' . self::$address, $code, $reason, 1);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            usleep(self::RETRY_MICROSECONDS);
        } while (microtime(true) < $deadline && proc_get_status($process)['running']);

        throw new \RuntimeException(
            "The Human Confidence web example did not start listening.\n" . self::printed()
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
        self::$process = null;
        if (self::$log !== '' && is_file(self::$log)) {
            unlink(self::$log);
        }
    }

    protected function setUp(): void
    {
        if (self::$key === null) {
            $this->markTestSkipped(ResourceKeys::missingMessage(Constants::RESOURCE_ENV_VAR));
        }
    }

    /**
     * The page has a row for each of the three steps, the resource key and
     * the cloud service its script calls, and the links to find out more,
     * and is never kept by a cache, as each load runs the browser tests.
     */
    public function testPageShowsTheThreeSteps()
    {
        $this->skipWithoutTheProperty();

        $answer = self::get('/');

        $this->assertSame(200, $answer['status'], self::printed($answer));
        $this->assertNoFault($answer);
        $this->assertSame('no-store', $answer['headers']['cache-control'] ?? null);
        foreach (['passive-source', 'passive-level', 'active-source', 'active-value',
            'redeem-detail', 'redeem-level', 'again'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $answer['body']);
        }
        $this->assertStringContainsString('var RESOURCE = "' . self::$key . '";', $answer['body']);
        $this->assertStringContainsString('var API = "' . self::endpoint() . '";', $answer['body']);
        $this->assertDoesNotMatchRegularExpression('/__[A-Z_]+__/', $answer['body']);
        $this->assertEndsWithFindOutMore($answer);
    }

    /**
     * With a resource key that does not include the property, the page
     * gives a tagged link to contact us in place of the three steps.
     */
    public function testPageGivesAContactLinkWithoutTheProperty()
    {
        $this->assertNotNull(self::$properties, self::notListedMessage());
        if (isset(self::$properties[self::PROPERTY])) {
            $this->markTestSkipped('The resource key includes the HumanConfidence property.');
        }

        $answer = self::get('/');

        $this->assertSame(200, $answer['status'], self::printed($answer));
        $this->assertNoFault($answer);
        $this->assertStringContainsString(
            'https://51degrees.com/contact-us?',
            html_entity_decode($answer['body'])
        );
        $this->assertStringNotContainsString('id="passive-level"', $answer['body']);
        $this->assertEndsWithFindOutMore($answer);
    }

    /**
     * The page links its style sheet, so the example serves it.
     */
    public function testStyleSheet()
    {
        $answer = self::get('/examples-main.min.css');

        $this->assertSame(200, $answer['status'], self::printed($answer));
        $this->assertStringStartsWith('text/css', $answer['headers']['content-type'] ?? '');
        $this->assertStringContainsString('c-eg-status', $answer['body']);
    }

    /**
     * The first step. The example asks the cloud service with the headers
     * of the request it was sent, and answers with the source and the level
     * the cloud service gave.
     */
    public function testPassive()
    {
        $this->skipWithoutTheProperty();

        $answer = self::get('/passive', Constants::UA_HEADER . Constants::CHROME_UA . "\r\n");

        $this->assertSame(200, $answer['status'], self::printed($answer));
        $this->assertNoFault($answer);
        $json = json_decode($answer['body'], true);
        $this->assertIsArray($json, self::printed($answer));
        $this->assertArrayNotHasKey('error', $json, self::printed($answer));
        $this->assertContains($json['humanConfidence'] ?? null, self::LEVELS, self::printed($answer));
        if (isset(self::$properties[self::SOURCE_PROPERTY])) {
            $this->assertSame('Passive', $json['source'] ?? null, self::printed($answer));
        }
    }

    /**
     * The third step, with a value the cloud service did not seal. The
     * example answers with what the cloud service said, or with why it
     * could not ask, and never with a level.
     */
    public function testRedeemOfAValueThatWasNotSealedGivesNoLevel()
    {
        $this->skipWithoutTheProperty();

        $answer = self::request('/redeem', [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['value' => 'not-a-sealed-value'])
        ]);

        $this->assertNotSame(500, $answer['status'], self::printed($answer));
        $this->assertNotSame('', trim($answer['body']), self::printed($answer));
        $this->assertNoFault($answer);
        $json = json_decode($answer['body'], true);
        if (is_array($json)) {
            $this->assertArrayNotHasKey('humanConfidence', $json, self::printed($answer));
        }
    }

    /**
     * The example answers only the addresses its page asks for.
     */
    public function testAnotherAddressIsNotFound()
    {
        $answer = self::get('/not-part-of-the-example');

        $this->assertSame(404, $answer['status'], self::printed($answer));
    }

    /**
     * Skips the test where the resource key does not include the
     * HumanConfidence property, and fails where the cloud service did not
     * say which properties it includes.
     */
    private function skipWithoutTheProperty(): void
    {
        $this->assertNotNull(self::$properties, self::notListedMessage());
        if (!isset(self::$properties[self::PROPERTY])) {
            $this->markTestSkipped(
                'The resource key in ' . Constants::RESOURCE_ENV_VAR .
                ' does not include the HumanConfidence property.'
            );
        }
    }

    /**
     * Checks that nothing in the answer reads as a programming fault.
     *
     * @param array{status: int, headers: array<string, string>, body: string} $answer
     */
    private function assertNoFault(array $answer): void
    {
        foreach (self::FAULT_MARKERS as $marker) {
            $this->assertStringNotContainsString($marker, $answer['body'], self::printed($answer));
        }
    }

    /**
     * Checks that the page ends with the Find out more section and that
     * every 51degrees.com link in it is tagged, as every example's page is
     * checked.
     *
     * @param array{status: int, headers: array<string, string>, body: string} $answer
     */
    private function assertEndsWithFindOutMore(array $answer): void
    {
        $run = new ExampleRun();
        $run->output = $answer['body'];
        $run->httpStatus = $answer['status'];
        $run->assertPageEndsWithFindOutMore();
    }

    /**
     * The cloud service the example calls, as the example reads it.
     */
    private static function endpoint(): string
    {
        $endpoint = trim((string) ExampleUtils::getCloudEndpoint());

        return rtrim($endpoint === '' ? self::DEFAULT_ENDPOINT : $endpoint, '/') . '/';
    }

    /**
     * The names, in lower case, of the properties the resource key
     * includes, as the cloud service lists them, or null where it did not.
     *
     * @return null|array<string, bool>
     */
    private static function accessibleProperties(string $key): ?array
    {
        $context = stream_context_create(
            ['http' => ['ignore_errors' => true, 'timeout' => self::CLOUD_TIMEOUT_SECONDS]]
        );
        $body = @file_get_contents(
            self::endpoint() . 'accessibleproperties?resource=' . rawurlencode($key),
            false,
            $context
        );
        $json = $body === false ? null : json_decode($body, true);
        if (!is_array($json)) {
            return null;
        }
        $json = array_change_key_case($json);
        if (!is_array($json['products'] ?? null)) {
            return null;
        }

        $names = [];
        foreach ($json['products'] as $product) {
            if (!is_array($product)) {
                continue;
            }
            $product = array_change_key_case($product);
            if (!is_array($product['properties'] ?? null)) {
                continue;
            }
            foreach ($product['properties'] as $property) {
                if (!is_array($property)) {
                    continue;
                }
                $property = array_change_key_case($property);
                if (is_string($property['name'] ?? null)) {
                    $names[strtolower($property['name'])] = true;
                }
            }
        }

        return $names;
    }

    private static function notListedMessage(): string
    {
        return 'The cloud service at ' . self::endpoint() . ' did not list the ' .
            'properties of the resource key in ' . Constants::RESOURCE_ENV_VAR . '.';
    }

    /**
     * The example's answer to a GET.
     *
     * @param string $path The path asked for
     * @param string $header Request headers, each ended with a line break
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private static function get(string $path, string $header = ''): array
    {
        return self::request($path, ['method' => 'GET', 'header' => $header]);
    }

    /**
     * The example's answer to a request, whatever its status, with the
     * names of its headers in lower case.
     *
     * @param string $path The path asked for
     * @param array<string, mixed> $http The HTTP context options
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private static function request(string $path, array $http): array
    {
        $context = stream_context_create(
            ['http' => $http + ['ignore_errors' => true, 'timeout' => self::ANSWER_SECONDS]]
        );
        $stream = @fopen('http://' . self::$address . $path, 'r', false, $context);
        self::assertNotFalse($stream, "The example did not answer {$path}.\n" . self::printed());
        $lines = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = (string) stream_get_contents($stream);
        fclose($stream);

        $answer = ['status' => -1, 'headers' => [], 'body' => $body];
        foreach ((array) $lines as $line) {
            if (preg_match('~^HTTP/\S+ (\d{3})~', (string) $line, $code) === 1) {
                $answer['status'] = (int) $code[1];
                $answer['headers'] = [];

                continue;
            }
            $parts = explode(':', (string) $line, 2);
            if (isset($parts[1])) {
                $answer['headers'][strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return $answer;
    }

    /**
     * An answer and what the server printed, for a failure message.
     *
     * @param null|array{status: int, headers: array<string, string>, body: string} $answer
     */
    private static function printed(?array $answer = null): string
    {
        $text = '';
        if ($answer !== null) {
            $text = "HTTP status {$answer['status']}.\nBody:\n{$answer['body']}\n";
        }
        $log = self::$log !== '' && is_file(self::$log)
            ? (string) file_get_contents(self::$log)
            : '';

        return $text . "The server printed:\n" . $log;
    }
}
