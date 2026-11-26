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

namespace fiftyone\pipeline\devicedetection\tests\classes;

use fiftyone\pipeline\devicedetection\examples\cloud\classes\EnvironmentVariable;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\HumanConfidenceWeb;
use fiftyone\pipeline\tests\PhpMessage;
use PHPUnit\Framework\TestCase;

/**
 * Serves the Human Confidence web example with PHP's built-in server and
 * asks it, and the cloud service it calls, for what its page asks for. Each
 * class of tests names the variables its cloud service and resource key are
 * read from, and the example is served with those.
 *
 * Every test is skipped, naming the variable, when the resource key or the
 * cloud service is not set. The tests that need the HumanConfidence
 * property fail where the resource key does not include it, as a test that
 * cannot see a real level has proved nothing.
 */
abstract class HumanConfidenceWebTestCase extends TestCase
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

    // The properties the example reads, as the cloud service names them in
    // its results.
    protected const PROPERTY = 'humanconfidence';
    protected const SOURCE_PROPERTY = 'humanconfidencesource';

    // The properties the tests name when they ask the cloud service
    // themselves, so the answer carries both whatever the key's own list
    // of properties holds.
    private const PROPERTIES = 'derived.humanconfidence,derived.humanconfidencesource';

    // The levels a passive result can be. Unknown is not one, as an answer
    // of Unknown gives no level.
    protected const LEVELS = ['High', 'Medium', 'Low'];

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

    /** @var null|string Why every test is skipped, or null where they run */
    private static $skipped;

    /** @var string The resource key of the tests */
    protected static $key = '';

    /** @var string The cloud service the tests and the example call */
    protected static $endpoint = '';

    /** @var string The cloud service, as failure messages name it */
    private static $serviceName = '';

    /**
     * @var null|array<string, bool> The names, in lower case, of the
     * properties the resource key includes, or null where the cloud service
     * did not list them
     */
    protected static $properties;

    /** @var null|resource The server */
    private static $process;

    /** @var string The address the server listens on */
    private static $address = '';

    /** @var string The file the server prints to */
    private static $log = '';

    /**
     * The name of the variable the resource key of the tests is read from.
     */
    abstract protected static function resourceKeyVariable(): string;

    /**
     * The name of the variable the cloud service is read from, as its API
     * base including the /api/v4/ segment.
     */
    abstract protected static function endpointVariable(): string;

    /**
     * The cloud service called when the endpoint variable is not set, or
     * null where every test is skipped without it.
     */
    abstract protected static function defaultEndpoint(): ?string;

    /**
     * The license key the example is given, or null to give it none.
     */
    abstract protected static function licenseKey(): ?string;

    public static function setUpBeforeClass(): void
    {
        // The properties are shared by every class of tests, so each class
        // starts from nothing.
        self::$skipped = null;
        self::$key = '';
        self::$endpoint = '';
        self::$serviceName = '';
        self::$properties = null;
        self::$process = null;
        self::$address = '';
        self::$log = '';

        $key = ResourceKeys::find(static::resourceKeyVariable());
        $configured = trim(EnvironmentVariable::read(static::endpointVariable()));
        $endpoint = $configured === '' ? (string) static::defaultEndpoint() : $configured;
        $missing = [];
        if ($key === null) {
            $missing[] = ResourceKeys::missingMessage(static::resourceKeyVariable());
        }
        if ($endpoint === '') {
            $missing[] = "No cloud service found. Set the environment variable '" .
                static::endpointVariable() . "' to its API base.";
        }
        if ($missing !== []) {
            self::$skipped = implode(' ', $missing);

            return;
        }

        self::$key = (string) $key;
        self::$endpoint = rtrim($endpoint, '/') . '/';
        // A service set in its variable is named by the variable rather
        // than its address, and the public service by its address.
        self::$serviceName = $configured === ''
            ? 'The cloud service at ' . self::$endpoint
            : 'The cloud service named by ' . static::endpointVariable();
        self::$properties = self::accessibleProperties(self::$key);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('No free port was found.');
        }
        self::$address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        self::$log = (string) tempnam(sys_get_temp_dir(), 'example');
        // The example reads the resource key, the license key and the cloud
        // service under the names a person sets, so the server is given the
        // ones of the tests under those names. Where the cloud service is
        // not set the example calls its own default.
        $environment = getenv();
        $environment[ExampleUtils::RESOURCE_KEY_ENV_VAR] = self::$key;
        unset($environment[ExampleUtils::CLOUD_ENDPOINT_VARIABLE]);
        if ($configured !== '') {
            $environment[ExampleUtils::CLOUD_ENDPOINT_VARIABLE] = $configured;
        }
        unset($environment[HumanConfidenceWeb::LICENSE_KEY_VARIABLE]);
        $license = static::licenseKey();
        if ($license !== null) {
            $environment[HumanConfidenceWeb::LICENSE_KEY_VARIABLE] = $license;
        }
        $process = proc_open(
            [PHP_BINARY, '-S', self::$address, __DIR__ . '/../humanConfidenceWebRouter.php'],
            [0 => ['pipe', 'r'], 1 => ['file', self::$log, 'w'], 2 => ['file', self::$log, 'a']],
            $pipes,
            __DIR__ . '/../../examples',
            $environment
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('The Human Confidence web example could not be served.');
        }
        fclose($pipes[0]);
        self::$process = $process;

        $deadline = microtime(true) + self::START_SECONDS;
        do {
            $connection = PhpMessage::take(function () use (&$address, &$code, &$reason) {
                return stream_socket_client('tcp://' . self::$address, $code, $reason, 1);
            });
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
        if (self::$skipped !== null) {
            $this->markTestSkipped(self::$skipped);
        }
    }

    /**
     * Fails where the resource key does not include the HumanConfidence
     * property, or where the cloud service did not say which properties it
     * includes.
     *
     * @param null|array<string, bool> $properties The names, in lower case,
     * of the properties the resource key includes, or null where the cloud
     * service did not list them
     */
    public static function assertIncludesTheProperty(?array $properties): void
    {
        self::assertNotNull($properties, static::notListedMessage());
        self::assertArrayHasKey(
            self::PROPERTY,
            $properties,
            'The resource key in ' . static::resourceKeyVariable() .
            ' does not include the HumanConfidence property, so the ' .
            'example would show its contact us link rather than a level. ' .
            'Set the variable to a key that includes it.'
        );
    }

    /**
     * Checks that nothing in the answer reads as a programming fault.
     *
     * @param array{status: int, headers: array<string, string>, body: string} $answer
     */
    protected function assertNoFault(array $answer): void
    {
        foreach (self::FAULT_MARKERS as $marker) {
            $this->assertStringNotContainsString($marker, $answer['body'], self::printed($answer));
        }
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
        $body = PhpMessage::take(function () use (&$endpoint, &$key, &$context) {
            return file_get_contents(
                self::$endpoint . 'accessibleproperties?resource=' . rawurlencode($key),
                false,
                $context
            );
        });
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

    /**
     * The derived part of the cloud service's answer to a passive request
     * with a browser's User-Agent that names the two properties, as the
     * example's own passive request does.
     *
     * @return array<string, mixed>
     */
    protected static function passiveAnswer(string $key): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'user-agent' => Constants::CHROME_UA,
                'values' => self::PROPERTIES
            ]),
            'ignore_errors' => true,
            'timeout' => self::CLOUD_TIMEOUT_SECONDS
        ]]);
        $body = PhpMessage::take(function () use (&$endpoint, &$key, &$context) {
            return file_get_contents(self::$endpoint . rawurlencode($key) . '.json', false, $context);
        });
        self::assertNotFalse($body, self::$serviceName . ' did not answer.');
        $json = json_decode($body, true);
        self::assertIsArray($json, 'The cloud service did not answer with JSON.');
        self::assertArrayNotHasKey('errors', $json, str_replace($key, '<key>', $body));

        return is_array($json['derived'] ?? null) ? $json['derived'] : [];
    }

    /**
     * The cloud service's answer to a form posted to a path below its API
     * base, whatever its status.
     *
     * @param array<string, string> $fields
     * @return array{status: int, body: string}
     */
    protected static function postToCloud(string $path, array $fields): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields),
            'ignore_errors' => true,
            'timeout' => self::CLOUD_TIMEOUT_SECONDS
        ]]);
        $stream = PhpMessage::take(function () use (&$endpoint, &$path, &$context) {
            return fopen(self::$endpoint . $path, 'r', false, $context);
        });
        self::assertNotFalse($stream, self::$serviceName . ' did not answer ' . $path . '.');
        $lines = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = (string) stream_get_contents($stream);
        fclose($stream);

        // The last status line is the answer's own, after any redirect.
        $status = -1;
        foreach ((array) $lines as $line) {
            if (preg_match('~^HTTP/\S+ (\d{3})~', (string) $line, $code) === 1) {
                $status = (int) $code[1];
            }
        }

        return ['status' => $status, 'body' => $body];
    }

    protected static function notListedMessage(): string
    {
        return self::$serviceName . ' did not list the properties of the ' .
            'resource key in ' . static::resourceKeyVariable() . '.';
    }

    /**
     * The example's answer to a GET.
     *
     * @param string $path The path asked for
     * @param string $header Request headers, each ended with a line break
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    protected static function get(string $path, string $header = ''): array
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
    protected static function request(string $path, array $http): array
    {
        $context = stream_context_create(
            ['http' => $http + ['ignore_errors' => true, 'timeout' => self::ANSWER_SECONDS]]
        );
        $stream = PhpMessage::take(function () use (&$address, &$path, &$context) {
            return fopen('http://' . self::$address . $path, 'r', false, $context);
        });
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
    protected static function printed(?array $answer = null): string
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
