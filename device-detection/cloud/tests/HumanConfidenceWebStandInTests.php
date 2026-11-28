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
use fiftyone\pipeline\tests\PhpMessage;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

/**
 * Serves the Human Confidence web example with PHP's built-in server,
 * pointed at a stand-in for the cloud service in
 * humanConfidenceStandInCloud.php, and asks it for what its page asks for.
 * The stand-in answers three resource keys with the members the cloud
 * service gives such keys, and redeems a sealed result made for the
 * transaction of the call, so the example is checked with no key and no
 * network.
 *
 * The example reads HumanConfidence and HumanConfidenceSource and nothing
 * more. The sealed result reaches the cloud service only through the
 * Pipeline's JSON call, and the example never redeems one itself.
 */
class HumanConfidenceWebStandInTests extends TestCase
{
    // How long a server has to start listening.
    private const START_SECONDS = 30;

    // How long the example has to answer one request.
    private const ANSWER_SECONDS = 30;

    // How long to wait before trying a server that is not yet listening
    // again.
    private const RETRY_MICROSECONDS = 100000;

    // The License Key the stand-in redeems with.
    private const LICENSE = 'standin-license';

    // A License Key the stand-in refuses, naming it in its error.
    private const REFUSED_LICENSE = 'refused-license-WXYZ';

    // The example's source, which must not redeem.
    private const EXAMPLE_SOURCES = [
        __DIR__ . '/../examples/cloud/classes/HumanConfidenceWeb.php',
        __DIR__ . '/../examples/cloud/humanConfidenceWeb.php',
        __DIR__ . '/../examples/cloud/static/humanConfidence.html'
    ];

    /** @var null|resource The stand-in for the cloud service */
    private static $cloud;

    /** @var string The address the stand-in listens on */
    private static $cloudAddress = '';

    /** @var null|resource The example, where a test started it */
    private $example;

    /** @var string The address the example listens on */
    private $exampleAddress = '';

    public static function setUpBeforeClass(): void
    {
        self::$cloudAddress = self::freeAddress();
        self::$cloud = self::serve(
            self::$cloudAddress,
            __DIR__ . '/humanConfidenceStandInCloud.php',
            __DIR__,
            getenv()
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::stop(self::$cloud);
        self::$cloud = null;
        if (is_file(self::cloudLog())) {
            unlink(self::cloudLog());
        }
    }

    protected function tearDown(): void
    {
        self::stop($this->example);
        $this->example = null;
    }

    /**
     * A key whose products offer HumanConfidenceSource gets the source
     * Passive, as no browser tests have run, and the level the cloud
     * service gave.
     */
    public function testPassiveGivesTheSourceTheCloudServiceGave()
    {
        $this->assertSame(
            [
                'source' => 'Passive',
                'sourceReason' => null,
                'humanConfidence' => 'Medium',
                'humanConfidenceReason' => null
            ],
            $this->passive('source-key')
        );
    }

    /**
     * A Basic key is answered with neither property, as the Basic product
     * offers neither. The example makes up neither, and shows the message
     * that says why each has no value.
     */
    public function testPassiveForABasicKeyGivesNoSourceAndTheReason()
    {
        $this->assertSame(
            [
                'source' => null,
                'sourceReason' => 'HumanConfidenceSource is not in the Basic product.',
                'humanConfidence' => null,
                'humanConfidenceReason' => 'HumanConfidence is not in the Basic product.'
            ],
            $this->passive('basic-key')
        );
    }

    /**
     * A key whose own property list holds HumanConfidence alone, as keys
     * made before the source existed do, gets the source only when the
     * request names it. The example's Pipeline names both properties, so
     * it gives the source Passive.
     */
    public function testPassiveNamesTheSourceSoAKeyWithOnlyTheLevelGetsIt()
    {
        $answer = $this->passive('level-only-key');

        $this->assertSame('Passive', $answer['source'] ?? null);
        $this->assertSame('Medium', $answer['humanConfidence'] ?? null);
    }

    /**
     * The page loads the client script from an address the package gave
     * it, which carries a transaction of the page's own, and holds the
     * page id it sends back. Two loads get two transactions and two page
     * ids.
     */
    public function testEachPageHasAScriptAddressWithItsOwnTransaction()
    {
        $this->startExample('source-key', self::LICENSE);

        $first = $this->page();
        $second = $this->page();

        $prefix = 'http://' . self::$cloudAddress . '/api/v4/source-key.js?transaction=';
        $this->assertStringStartsWith($prefix, $first['script']);
        $this->assertStringStartsWith($prefix, $second['script']);
        $this->assertNotSame($first['transaction'], $second['transaction']);
        $this->assertNotSame($first['page'], $second['page']);
        $this->assertStringNotContainsString($first['transaction'], $first['page']);
    }

    /**
     * The page sends its page id back with the sealed result, as the
     * parameter humanconfidencepage, and sends no transaction.
     */
    public function testThePageSendsItsPageIdBackWithTheSealedResult()
    {
        $this->startExample('source-key', self::LICENSE);

        $body = $this->request('GET', '/');

        $this->assertStringContainsString('body.set("humanconfidence", sealed);', $body);
        $this->assertStringContainsString('body.set("humanconfidencepage", PAGE);', $body);
        $this->assertSame(2, substr_count($body, 'body.set('), $body);
    }

    /**
     * A sealed result the page sends with its page id is redeemed by the
     * Pipeline with the page's transaction, so the example reads the
     * Value with the source Active. Sent again, it reads Medium and the
     * Pipeline doesn't send it, as the page's transaction was taken.
     */
    public function testTheSealedResultIsRedeemedByThePipelineOnce()
    {
        $this->startExample('source-key', self::LICENSE);
        $page = $this->page();
        $sealed = 'sealed-' . $page['transaction'];

        $this->assertSame(
            [
                'source' => 'Active',
                'sourceReason' => null,
                'humanConfidence' => 'High',
                'humanConfidenceReason' => null
            ],
            $this->sendSealedResult($sealed, $page['page'])
        );

        self::clearCloudLog();
        $again = $this->sendSealedResult($sealed, $page['page']);
        $this->assertSame('Active', $again['source'] ?? null);
        $this->assertSame('Medium', $again['humanConfidence'] ?? null);
        $this->assertSame(0, self::callsHoldingTheSealedResult());
    }

    /**
     * Two pages open in one session each redeem their own sealed result,
     * whichever redeems first.
     */
    public function testTwoPagesInOneSessionEachRedeemTheirOwnSealedResult()
    {
        $this->startExample('source-key', self::LICENSE);
        $session = ['session-id' => 'one-session-for-both-tabs'];
        $older = $this->page($session);
        $newer = $this->page($session);

        foreach (['newer' => $newer, 'older' => $older] as $name => $page) {
            $answer = $this->sendSealedResult('sealed-' . $page['transaction'], $page['page'], $session);
            $this->assertSame('Active', $answer['source'] ?? null, $name);
            $this->assertSame('High', $answer['humanConfidence'] ?? null, $name);
        }
    }

    /**
     * Without a License Key the Pipeline can't redeem, so the example
     * reads Medium with the source Active.
     */
    public function testWithoutALicenseKeyTheSealedResultReadsMedium()
    {
        $this->startExample('source-key', null);
        $page = $this->page();

        $answer = $this->sendSealedResult('sealed-' . $page['transaction'], $page['page']);

        $this->assertSame('Active', $answer['source'] ?? null);
        $this->assertSame('Medium', $answer['humanConfidence'] ?? null);
    }

    /**
     * A sealed result sent with the page id of another page reads Medium,
     * as the Pipeline sends that page's transaction.
     */
    public function testASealedResultWithAnotherPagesIdReadsMedium()
    {
        $this->startExample('source-key', self::LICENSE);
        $page = $this->page();
        $other = $this->page();

        $answer = $this->sendSealedResult('sealed-' . $page['transaction'], $other['page']);

        $this->assertSame('Active', $answer['source'] ?? null);
        $this->assertSame('Medium', $answer['humanConfidence'] ?? null);
    }

    /**
     * Where the example's package keeps no transaction for the page, as
     * for no page id, an unknown one, or one already taken, the Pipeline
     * doesn't send the sealed result, and the example reads Medium with
     * the source Active. Sent, each of these would redeem as High.
     */
    public function testWithoutAKeptTransactionForThePageTheSealedResultIsNotSent()
    {
        $this->startExample('source-key', self::LICENSE);
        $taken = $this->page();
        $this->sendSealedResult('sealed-' . $taken['transaction'], $taken['page']);
        $unknown = $this->page();

        $cases = [
            'no page id' => ['sealed-', ''],
            'an unknown page id' => ['sealed-' . $unknown['transaction'], 'unknown-page'],
            'a page id already taken' => ['sealed-' . $taken['transaction'], $taken['page']]
        ];
        foreach ($cases as $name => [$sealed, $pageId]) {
            self::clearCloudLog();
            $answer = $this->sendSealedResult($sealed, $pageId);
            $this->assertSame('Active', $answer['source'] ?? null, $name);
            $this->assertSame('Medium', $answer['humanConfidence'] ?? null, $name);
            $this->assertSame(0, self::callsHoldingTheSealedResult(), $name);
        }
    }

    /**
     * The example never redeems a sealed result itself. It makes no
     * request to a redeem endpoint, and the sealed result reaches the cloud
     * service only in the Pipeline's JSON call. Its source names no redeem
     * endpoint and no redeem parameter.
     */
    public function testTheExampleNeverRedeemsASealedResultItself()
    {
        $this->startExample('source-key', self::LICENSE);
        $page = $this->page();
        self::clearCloudLog();

        $this->sendSealedResult('sealed-' . $page['transaction'], $page['page']);

        $calls = array_filter(explode("\n", (string) file_get_contents(self::cloudLog())));
        $this->assertNotEmpty($calls);
        foreach ($calls as $call) {
            $this->assertStringNotContainsStringIgnoringCase('redeem', $call);
            [$path, $fields] = explode(' ', $call . ' ', 2);
            if (stripos($fields, 'humanconfidence') !== false) {
                $this->assertSame('/api/v4/source-key.json', $path, $call);
            }
        }

        foreach (self::EXAMPLE_SOURCES as $file) {
            $source = (string) file_get_contents($file);
            foreach (['redeem(', 'hc/redeem', 'sealedResult', 'fopen(', 'curl_'] as $text) {
                $this->assertStringNotContainsString($text, $source, $file);
            }
        }
    }

    /**
     * Where the Pipeline can't be built, as for a License Key the cloud
     * service refuses and names in its error, the page gets a fixed
     * message and no part of the key.
     */
    public function testThePageGetsNoPartOfARefusedLicenseKey()
    {
        $this->startExample('source-key', self::REFUSED_LICENSE);
        $page = $this->page();

        foreach (['/passive' => null, '/humanconfidence' => ['humanconfidence' => 'sealed-x', 'humanconfidencepage' => $page['page']]] as $path => $fields) {
            $body = $this->request($fields === null ? 'GET' : 'POST', $path, $fields);
            $this->assertStringNotContainsString('WXYZ', $body, $path);
            $this->assertStringNotContainsString('refused-license', $body, $path);
            $this->assertSame(['error' => HumanConfidenceWeb::MESSAGE_NOT_BUILT], json_decode($body, true), $path);
        }
    }

    /**
     * A key without the HumanConfidence property cannot show a level, so
     * the live tests that need one fail, naming the variable, rather than
     * skip.
     */
    public function testLiveTestsFailForAKeyWithoutTheProperty()
    {
        $message = self::failure(function () {
            HumanConfidenceWebTests::assertIncludesTheProperty(['humanconfidencesource' => true]);
        });

        $this->assertStringContainsString(Constants::RESOURCE_ENV_VAR, $message);
        $this->assertStringContainsString('HumanConfidence property', $message);
    }

    /**
     * Where the cloud service did not list the key's properties, the live
     * tests fail, naming the variable, as nothing shows the key includes
     * the property.
     */
    public function testLiveTestsFailWhereThePropertiesWereNotListed()
    {
        $message = self::failure(function () {
            HumanConfidenceWebTests::assertIncludesTheProperty(null);
        });

        $this->assertStringContainsString(Constants::RESOURCE_ENV_VAR, $message);
    }

    /**
     * The staging tests fail the same way, naming their own variable.
     */
    public function testStagingTestsFailForAKeyWithoutTheProperty()
    {
        $message = self::failure(function () {
            HumanConfidenceWebStagingTests::assertIncludesTheProperty(['humanconfidencesource' => true]);
        });

        $this->assertStringContainsString(Constants::STAGING_RESOURCE_ENV_VAR, $message);
        $this->assertStringContainsString('HumanConfidence property', $message);
    }

    /**
     * The message of the assertion the function fails.
     */
    private static function failure(callable $assertion): string
    {
        try {
            $assertion();
        } catch (AssertionFailedError $failure) {
            return $failure->getMessage();
        }
        self::fail('The assertion passed.');
    }

    /**
     * The example's answer to the passive step, with the resource key given
     * and no License Key.
     *
     * @return mixed
     */
    private function passive(string $key)
    {
        $this->startExample($key, null);

        return json_decode($this->request('GET', '/passive'), true);
    }

    /**
     * The example's answer to the page sending a sealed result and its
     * page id, as its script does, with any other fields given. An empty
     * page id is not sent.
     *
     * @param array<string, string> $more
     * @return mixed
     */
    private function sendSealedResult(string $sealed, string $pageId, array $more = [])
    {
        $fields = ['humanconfidence' => $sealed];
        if ($pageId !== '') {
            $fields['humanconfidencepage'] = $pageId;
        }

        return json_decode($this->request('POST', '/humanconfidence', $fields + $more), true);
    }

    /**
     * The address of the client script the example's page loads, the
     * transaction it carries, and the page's page id.
     *
     * @param array<string, string> $query Parameters of the page's address
     * @return array{script: string, transaction: string, page: string}
     */
    private function page(array $query = []): array
    {
        $body = $this->request('GET', '/' . ($query === [] ? '' : '?' . http_build_query($query)));
        $this->assertDoesNotMatchRegularExpression('/__[A-Z_]+__/', $body);
        $this->assertSame(1, preg_match('/var SCRIPT = ("[^"]*");/', $body, $script), $body);
        $this->assertSame(1, preg_match('/var PAGE = ("[^"]*");/', $body, $page), $body);
        $address = (string) json_decode($script[1]);
        parse_str((string) parse_url($address, PHP_URL_QUERY), $parameters);

        return [
            'script' => $address,
            'transaction' => (string) ($parameters['transaction'] ?? ''),
            'page' => (string) json_decode($page[1])
        ];
    }

    /**
     * Empties the record of the stand-in's requests.
     */
    private static function clearCloudLog(): void
    {
        if (is_file(self::cloudLog())) {
            unlink(self::cloudLog());
        }
    }

    /**
     * How many requests the stand-in answered since its record was emptied
     * held a humanconfidence field. The Pipeline's JSON call must be among
     * them, so a request that made no call can't pass for one that left
     * the sealed result out.
     */
    private static function callsHoldingTheSealedResult(): int
    {
        $calls = array_filter(explode("\n", is_file(self::cloudLog())
            ? (string) file_get_contents(self::cloudLog())
            : ''));
        self::assertNotEmpty(
            array_filter($calls, static function ($call) {
                return strpos($call, '/api/v4/source-key.json ') === 0;
            }),
            'The Pipeline made no JSON call.'
        );
        $holding = 0;
        foreach ($calls as $call) {
            [, $fields] = explode(' ', $call . ' ', 2);
            $names = array_map('strtolower', explode(',', trim($fields)));
            if (in_array('humanconfidence', $names, true)) {
                ++$holding;
            }
        }

        return $holding;
    }

    /**
     * Serves the example with the resource key and License Key given and
     * the stand-in as its cloud service.
     */
    private function startExample(string $key, ?string $license): void
    {
        $environment = getenv();
        $environment[ExampleUtils::RESOURCE_KEY_ENV_VAR] = $key;
        $environment[ExampleUtils::CLOUD_ENDPOINT_VARIABLE] =
            'http://' . self::$cloudAddress . '/api/v4/';
        unset($environment[HumanConfidenceWeb::LICENSE_KEY_VARIABLE]);
        if ($license !== null) {
            $environment[HumanConfidenceWeb::LICENSE_KEY_VARIABLE] = $license;
        }
        $this->exampleAddress = self::freeAddress();
        $this->example = self::serve(
            $this->exampleAddress,
            __DIR__ . '/humanConfidenceWebRouter.php',
            __DIR__ . '/../examples',
            $environment
        );
    }

    /**
     * The body of the example's answer to a request.
     *
     * @param null|array<string, string> $fields Form fields to post
     */
    private function request(string $method, string $path, ?array $fields = null): string
    {
        $http = [
            'method' => $method,
            'header' => Constants::UA_HEADER . Constants::CHROME_UA . "\r\n",
            'ignore_errors' => true,
            'timeout' => self::ANSWER_SECONDS
        ];
        if ($fields !== null) {
            $http['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $http['content'] = http_build_query($fields);
        }
        $context = stream_context_create(['http' => $http]);
        $url = 'http://' . $this->exampleAddress . $path;
        $body = PhpMessage::take(function () use (&$url, &$context) {
            return file_get_contents($url, false, $context);
        });
        self::assertNotFalse($body, "The example did not answer {$path}.");

        return $body;
    }

    /**
     * The file the stand-in adds each request it answers to.
     */
    private static function cloudLog(): string
    {
        $port = (int) substr(self::$cloudAddress, (int) strrpos(self::$cloudAddress, ':') + 1);

        return sys_get_temp_dir() . '/hc-stand-in-' . $port . '.log';
    }

    /**
     * An address on this machine with a port nothing listens on.
     */
    private static function freeAddress(): string
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('No free port was found.');
        }
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return $address;
    }

    /**
     * Serves a script with PHP's built-in server and waits until it
     * listens.
     *
     * @param array<string, string> $environment
     * @return resource
     */
    private static function serve(string $address, string $script, string $folder, array $environment)
    {
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open(
            [PHP_BINARY, '-S', $address, $script],
            [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes,
            $folder,
            $environment
        );
        if (!is_resource($process)) {
            throw new \RuntimeException("{$script} could not be served.");
        }
        fclose($pipes[0]);

        $deadline = microtime(true) + self::START_SECONDS;
        do {
            $connection = PhpMessage::take(function () use (&$address, &$code, &$reason) {
                return stream_socket_client('tcp://' . $address, $code, $reason, 1);
            });
            if ($connection !== false) {
                fclose($connection);

                return $process;
            }
            usleep(self::RETRY_MICROSECONDS);
        } while (microtime(true) < $deadline && proc_get_status($process)['running']);

        self::stop($process);

        throw new \RuntimeException("{$script} did not start listening on {$address}.");
    }

    /**
     * Stops a server started by serve.
     *
     * @param null|resource $process
     */
    private static function stop($process): void
    {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
}
