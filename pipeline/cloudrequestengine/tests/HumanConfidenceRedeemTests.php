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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\Constants;
use fiftyone\pipeline\cloudrequestengine\DerivedCloudEngine;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\cloudrequestengine\HumanConfidencePage;
use fiftyone\pipeline\cloudrequestengine\HumanConfidenceTransactions;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\LicenseKey;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\DataKeyedCache;
use fiftyone\pipeline\tests\PhpMessage;
use PHPUnit\Framework\TestCase;

/**
 * The package redeems a sealed Human Confidence result through its JSON
 * call, with the transaction it kept for the request's page id, and gives
 * HumanConfidence and HumanConfidenceSource as the specification's table
 * says, so a consumer only reads the two Properties.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#required-tests
 */
class HumanConfidenceRedeemTests extends TestCase
{
    private const SEALED = 'SEALED-RESULT-abcdefghijklmnopqrstuvwxyz0123456789';

    private const LICENSE = 'LICENSE-KEY-0123456789ABCDEF';

    /** @var array<array<string, string>> The JSON calls made, as form fields */
    private array $calls = [];

    /** @var array<array<string, string>> The entries the pipeline logged */
    private array $logged = [];

    private string $folder = '';

    private HumanConfidenceTransactions $transactions;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'hc-redeem-tests-' . bin2hex(random_bytes(8));
        $this->transactions = new HumanConfidenceTransactions($this->folder);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->folder)) {
            rmdir($this->folder);
        }
    }

    /**
     * A pipeline of a Cloud Request Engine and the derived cloud engine,
     * whose JSON call is answered by the function given.
     *
     * @param callable(array<string, string>): string $answer
     * @param array<string, mixed> $settings
     * @param null|callable(): string $evidenceKeys Answers the request for
     *   the accepted Evidence keys
     */
    private function pipeline(
        callable $answer,
        array $settings = [],
        ?DataKeyedCache $cache = null,
        ?callable $evidenceKeys = null,
        bool $suppress = true
    ): Pipeline {
        $this->calls = [];
        $this->logged = [];
        $client = $this->createStub(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function ($type, $url, $content, $origin) use ($answer, $evidenceKeys) {
                if (stripos($url, 'accessibleProperties') !== false) {
                    return '{"Products": {"derived": {"DataTier": "tier", "Properties": [' .
                        '{"Name": "HumanConfidence", "Type": "String", "Category": "Bot"},' .
                        '{"Name": "HumanConfidenceSource", "Type": "String", "Category": "Bot"},' .
                        '{"Name": "Other", "Type": "String", "Category": "Bot"}]}}}';
                }
                if (stripos($url, 'evidencekeys') !== false) {
                    return $evidenceKeys === null
                        ? '["header.user-agent", "query.humanconfidence"]'
                        : $evidenceKeys();
                }
                parse_str((string) $content, $fields);
                $this->calls[] = $fields;

                return $answer($fields);
            }
        );

        $engine = new CloudRequestEngine($settings + [
            'resourceKey' => 'resource_key',
            'licenseKey' => self::LICENSE,
            'httpClient' => $client,
            'humanConfidenceTransactions' => $this->transactions,
        ]);
        if ($cache !== null) {
            $engine->setCache($cache);
        }
        $logged = &$this->logged;
        $logger = new class ('trace', $logged) extends Logger {
            /** @var array<array<string, string>> */
            private array $entries;

            /**
             * @param array<array<string, string>> $entries
             */
            public function __construct(string $level, array &$entries)
            {
                parent::__construct($level);
                $this->entries = &$entries;
            }

            public function logInternal(array $log): void
            {
                $this->entries[] = $log;
            }
        };

        return (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => $suppress,
        ]))->addLogger($logger)->add($engine)->add(new DerivedCloudEngine())->build();
    }

    /**
     * A page the package issued a page id and a transaction for.
     */
    private function newPage(): HumanConfidencePage
    {
        return $this->transactions->newPage();
    }

    /**
     * Processes a request whose Evidence holds the sealed result, the page
     * id given, or that of a new page the package issued, and any other
     * Evidence given. Evidence given as null is left out.
     *
     * @param array<string, null|string> $evidence
     */
    private function process(Pipeline $pipeline, array $evidence = [], ?string $pageId = null): FlowData
    {
        $evidence += [
            'header.user-agent' => 'agent',
            'query.humanconfidence' => self::SEALED,
            'query.humanconfidencepage' => $pageId ?? $this->newPage()->pageId(),
        ];
        $flowData = $pipeline->createFlowData();
        foreach ($evidence as $key => $value) {
            if ($value !== null) {
                $flowData->evidence->set($key, $value);
            }
        }
        $flowData->process();

        return $flowData;
    }

    /**
     * The transaction a page carries to the remote server, read from its
     * script's address as the remote server would read it.
     */
    private static function transactionOf(HumanConfidencePage $page): string
    {
        parse_str((string) parse_url($page->scriptUrl('https://cloud.example/api/v4', 'key'), PHP_URL_QUERY), $query);

        return (string) $query['transaction'];
    }

    /**
     * @param array<string, mixed> $derived
     * @param array<string, mixed> $more
     */
    private static function answer(array $derived, array $more = []): callable
    {
        return static fn (array $fields): string =>
            (string) json_encode(['derived' => $derived] + $more);
    }

    /**
     * @return array{0: mixed, 1: mixed} HumanConfidence and
     *   HumanConfidenceSource as read
     */
    private static function read(FlowData $flowData): array
    {
        return [
            $flowData->derived->humanconfidence->value,
            $flowData->derived->humanconfidencesource->value,
        ];
    }

    public function testASealedResultThatRedeemsGivesTheValueAndActive(): void
    {
        foreach (['High', 'Medium', 'Low'] as $level) {
            $pipeline = $this->pipeline(self::answer([
                'humanconfidence' => $level,
                // The package sets Active itself, whatever the response says.
                'humanconfidencesource' => 'Passive',
            ]));
            $page = $this->newPage();
            $flowData = $this->process($pipeline, [], $page->pageId());
            $this->assertSame([$level, 'Active'], self::read($flowData), $level);
            $this->assertCount(1, $this->calls, $level);
            $this->assertSame(self::SEALED, $this->calls[0]['humanconfidence'], $level);
            $this->assertSame(self::transactionOf($page), $this->calls[0]['transaction'], $level);
            $this->assertSame(self::LICENSE, $this->calls[0]['license'], $level);
            // The page id is the package's own, so the call leaves it out.
            $this->assertArrayNotHasKey('humanconfidencepage', $this->calls[0], $level);
            $this->assertSame([], $flowData->errors, $level);
        }
    }

    public function testEachCaseWhereTheSealedResultIsNotRedeemedGivesMediumAndActive(): void
    {
        $high = self::answer(['humanconfidence' => 'High']);
        $cases = [
            'no License Key' => [$high, ['licenseKey' => '', 'requestedProperties' => 'derived.Other'], []],
            'no request made' => [$high, ['evidenceKeys' => static function (): string {
                throw new CloudRequestException('The evidence keys were not given.', 503);
            }], []],
            'the request failed' => [static function (): string {
                throw new CloudRequestException('The cloud service failed.', 500);
            }, [], []],
            'the request timed out' => [static function (): string {
                throw new \Exception('Operation timed out after 2000 milliseconds');
            }, [], []],
            'errors say not covered' => [self::answer(
                ['humanconfidence' => 'High'],
                ['errors' => ['HumanConfidence is not covered by the credential.']]
            ), [], []],
            'no derived member' => [static fn (): string => '{"device":{}}', [], []],
            'no humanconfidence' => [self::answer(['other' => 'x']), [], []],
            'null humanconfidence' => [self::answer([
                'humanconfidence' => null,
                'humanconfidencenullreason' => 'Not in the credential.',
            ]), [], []],
            'Unknown' => [self::answer(['humanconfidence' => 'Unknown']), [], []],
            'a word not known' => [self::answer(['humanconfidence' => 'VeryHigh']), [], []],
            'not a string' => [self::answer(['humanconfidence' => 3]), [], []],
            'not JSON' => [static fn (): string => '<html>', [], []],
        ];
        foreach ($cases as $name => [$answer, $settings, $evidence]) {
            $evidenceKeys = $settings['evidenceKeys'] ?? null;
            unset($settings['evidenceKeys']);
            $pipeline = $this->pipeline($answer, $settings, null, $evidenceKeys);
            $flowData = $this->process($pipeline, $evidence);
            $this->assertSame(['Medium', 'Active'], self::read($flowData), $name);
            // Only the Cloud Request Engine's own failure is an error.
            $failed = in_array($name, ['no request made', 'the request failed', 'the request timed out'], true);
            $this->assertSame($failed ? ['cloud-response'] : [], array_keys($flowData->errors), $name);
        }
    }

    /**
     * Where the Cloud Request Engine makes no request for the Flow Data, as
     * when the accepted Evidence keys can't be had at build time or again
     * while processing, the reads give Medium and Active, and the only
     * error is the engine's own.
     */
    public function testNoRequestMadeForTheFlowDataGivesMediumAndActive(): void
    {
        $asked = 0;
        $pipeline = $this->pipeline(
            self::answer(['humanconfidence' => 'High']),
            [],
            null,
            static function () use (&$asked): string {
                ++$asked;

                throw new CloudRequestException('The evidence keys were not given.', 503);
            }
        );
        $flowData = $this->process($pipeline);
        $this->assertSame(2, $asked);
        $this->assertSame([], $this->calls);
        $this->assertSame(['Medium', 'Active'], self::read($flowData));
        $this->assertSame(['cloud-response'], array_keys($flowData->errors));
    }

    /**
     * A Pipeline of the derived cloud engine alone, with no Cloud Request
     * Engine. Exceptions aren't suppressed, so an error would throw.
     */
    private static function pipelineWithoutCloudRequestEngine(DerivedCloudEngine $engine): Pipeline
    {
        return (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => false,
        ]))->add($engine)->build();
    }

    /**
     * Without a Cloud Request Engine nothing is redeemed. The derived cloud
     * engine can still be added, and a request that carries a sealed
     * result reads Medium and Active through every way of reading, with no
     * error and no cloud request.
     */
    public function testThePipelineHasNoCloudRequestEngine(): void
    {
        $engine = new DerivedCloudEngine();
        $pipeline = self::pipelineWithoutCloudRequestEngine($engine);
        $this->assertSame(
            ['humanconfidence', 'humanconfidencesource'],
            array_keys($engine->getProperties())
        );
        foreach (['query.humanconfidence', 'Query.HumanConfidence'] as $key) {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('header.user-agent', 'agent');
            $flowData->evidence->set($key, self::SEALED);
            $flowData->evidence->set('query.humanconfidencepage', $this->newPage()->pageId());
            $flowData->process();

            $this->assertSame([], $flowData->errors, $key);
            $this->assertNull($flowData->tryGet(Constants::DATA_KEY), $key);
            $this->assertSame(['Medium', 'Active'], self::read($flowData), $key);
            $data = $flowData->get('derived');
            $this->assertSame('Medium', $data->get('HumanConfidence')->value, $key);
            $this->assertSame('Active', $data->get('HumanConfidenceSource')->value, $key);
            $this->assertSame('Medium', $data->getAsString('humanconfidence'), $key);
            $this->assertSame('Active', $data->getAsString('humanconfidencesource'), $key);
            $this->assertSame('Medium', $flowData->getFromElement($engine)->humanconfidence->value, $key);
            $this->assertSame(
                ['humanconfidence' => 'Medium', 'humanconfidencesource' => 'Active'],
                array_map(static fn ($value) => $value->value, $data->asDictionary()),
                $key
            );
            $this->assertSame('Medium', $flowData->getWhere('name', 'HumanConfidence')['humanconfidence']->value, $key);
            $this->assertSame(
                'Active',
                $flowData->getWhere('name', 'HumanConfidenceSource')['humanconfidencesource']->value,
                $key
            );
        }
    }

    /**
     * Without a Cloud Request Engine, a request that carries no sealed
     * result, or an empty one, gives the two Properties with no value and
     * the reason, never a level, and adds no error.
     */
    public function testWithNoCloudRequestEngineARequestWithoutASealedResultGivesNoValue(): void
    {
        $pipeline = self::pipelineWithoutCloudRequestEngine(new DerivedCloudEngine());
        $requests = [
            'no humanconfidence' => [],
            'an empty one' => ['query.humanconfidence' => ''],
            'one under another key' => ['header.humanconfidence' => self::SEALED],
        ];
        foreach ($requests as $name => $evidence) {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('header.user-agent', 'agent');
            foreach ($evidence as $key => $value) {
                $flowData->evidence->set($key, $value);
            }
            $flowData->process();

            $this->assertSame([], $flowData->errors, $name);
            foreach (['HumanConfidence', 'HumanConfidenceSource'] as $property) {
                $value = $flowData->derived->{strtolower($property)};
                $this->assertFalse($value->hasValue, "$name $property");
                $this->assertSame(
                    sprintf(DerivedCloudEngine::NO_CLOUD_REQUEST_ENGINE_MESSAGE, $property),
                    $value->noValueMessage,
                    "$name $property"
                );
            }
        }
    }

    public function testAReadAfterAFailedRequestDoesNotThrowWhereExceptionsAreNotSuppressed(): void
    {
        $pipeline = $this->pipeline(static function (): string {
            throw new CloudRequestException('The cloud service failed.', 500);
        }, [], null, null, false);
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.humanconfidence', self::SEALED);
        $flowData->evidence->set('query.humanconfidencepage', $this->newPage()->pageId());
        try {
            $flowData->process();
            $this->fail('The failed request was not raised.');
        } catch (CloudRequestException $e) {
            // The Cloud Request Engine's error, raised as for any request.
        }
        $this->assertSame(['Medium', 'Active'], self::read($flowData));
        $this->assertSame(['cloud-response'], array_keys($flowData->errors));
    }

    public function testTheEvidenceKeysAreMatchedInAnyCase(): void
    {
        $pipeline = $this->pipeline(self::answer(['humanconfidence' => 'Low']));
        $flowData = $this->process($pipeline, [
            'query.humanconfidence' => null,
            'query.HumanConfidence' => self::SEALED,
        ]);
        $this->assertSame(['Low', 'Active'], self::read($flowData));
        $this->assertCount(1, $this->calls);
    }

    public function testWithoutALicenseKeyTheSealedResultIsNotSent(): void
    {
        $pipeline = $this->pipeline(
            self::answer(['humanconfidence' => 'High']),
            ['licenseKey' => '']
        );
        $this->process($pipeline);
        $this->assertArrayNotHasKey('humanconfidence', $this->calls[0]);
        $this->assertArrayNotHasKey('transaction', $this->calls[0]);
    }

    public function testTheLicenseKeyIsNeverTakenFromEvidence(): void
    {
        $pipeline = $this->pipeline(
            self::answer(['humanconfidence' => 'High']),
            ['licenseKey' => '']
        );
        $flowData = $this->process($pipeline, [
            'query.license' => 'FROM-THE-QUERY',
            'header.licence' => 'FROM-A-HEADER',
        ]);
        $this->assertSame(['Medium', 'Active'], self::read($flowData));
        $this->assertArrayNotHasKey('humanconfidence', $this->calls[0]);
        $this->assertArrayNotHasKey('license', $this->calls[0]);
        $this->assertArrayNotHasKey('licence', $this->calls[0]);

        $configured = $this->pipeline(self::answer(['humanconfidence' => 'High']));
        $this->process($configured, ['query.license' => 'FROM-THE-QUERY']);
        $this->assertSame(self::LICENSE, $this->calls[0]['license']);
    }

    public function testWithoutAKeptTransactionForThePageTheSealedResultIsNotSentAndReadsMedium(): void
    {
        $taken = $this->newPage();
        HumanConfidenceTransactionsTests::take($this->transactions, $taken->pageId());
        $expired = new HumanConfidenceTransactions($this->folder, 1);
        $old = $expired->newPage();
        foreach (glob($this->folder . DIRECTORY_SEPARATOR . '*.transaction') ?: [] as $file) {
            touch($file, time() - 5);
        }
        // Each would redeem as High if the sealed result were sent.
        $cases = [
            'no page id' => ['', $this->transactions],
            'an unknown page id' => ['unknown-page', $this->transactions],
            'a page id whose transaction was taken' => [$taken->pageId(), $this->transactions],
            'a page id whose transaction expired' => [$old->pageId(), $expired],
        ];
        foreach ($cases as $name => [$pageId, $transactions]) {
            $pipeline = $this->pipeline(
                self::answer(['humanconfidence' => 'High']),
                ['humanConfidenceTransactions' => $transactions]
            );
            $flowData = $this->process($pipeline, [
                'query.humanconfidencepage' => $pageId === '' ? null : $pageId,
                'query.transaction' => 'FROM-THE-PAGE',
            ], $pageId);
            $this->assertSame(['Medium', 'Active'], self::read($flowData), $name);
            $this->assertCount(1, $this->calls, $name);
            foreach (array_keys($this->calls[0]) as $field) {
                $this->assertNotContains(
                    strtolower((string) $field),
                    ['humanconfidence', 'humanconfidencepage', 'transaction', 'challenge'],
                    $name
                );
            }
            $this->assertStringNotContainsString(self::SEALED, http_build_query($this->calls[0]), $name);
            $this->assertStringNotContainsStringIgnoringCase(
                'HumanConfidence',
                (string) ($this->calls[0]['values'] ?? ''),
                $name
            );
            $this->assertSame([], $flowData->errors, $name);
        }
    }

    public function testAKeptTransactionRedeemsOnceAndThePageIdThenReadsMedium(): void
    {
        $pipeline = $this->pipeline(self::answer(['humanconfidence' => 'High']));
        $page = $this->newPage();
        $first = $this->process($pipeline, [], $page->pageId());
        $this->assertSame(self::transactionOf($page), $this->calls[0]['transaction']);
        $this->assertSame(['High', 'Active'], self::read($first));

        $second = $this->process($pipeline, [], $page->pageId());
        $this->assertArrayNotHasKey('humanconfidence', $this->calls[1]);
        $this->assertArrayNotHasKey('transaction', $this->calls[1]);
        $this->assertSame(['Medium', 'Active'], self::read($second));
    }

    public function testTwoPagesInOneSessionEachRedeemTheirOwnSealedResult(): void
    {
        // The remote server redeems a sealed result only with the
        // transaction it was made for.
        $pipeline = $this->pipeline(static fn (array $fields): string => (string) json_encode([
            'derived' => [
                'humanconfidence' => ($fields['humanconfidence'] ?? '') === 'SEALED-FOR-' . ($fields['transaction'] ?? '')
                    ? 'High'
                    : 'Medium',
            ],
        ]));
        $older = $this->newPage();
        $newer = $this->newPage();
        $this->assertNotSame($older->pageId(), $newer->pageId());
        $session = ['query.session-id' => 'one-session-for-both-tabs'];

        // The newer page redeems first, and the older one can still redeem.
        foreach (['newer' => $newer, 'older' => $older] as $name => $page) {
            $flowData = $this->process($pipeline, $session + [
                'query.humanconfidence' => 'SEALED-FOR-' . self::transactionOf($page),
            ], $page->pageId());
            $this->assertSame(['High', 'Active'], self::read($flowData), $name);
            $this->assertSame(self::transactionOf($page), end($this->calls)['transaction'], $name);
        }
        $this->assertCount(2, $this->calls);
    }

    public function testTwoReadsMakeOneRequest(): void
    {
        $pipeline = $this->pipeline(self::answer(['humanconfidence' => 'High']));
        $flowData = $this->process($pipeline);
        $this->assertSame(['High', 'Active'], self::read($flowData));
        $this->assertSame(['High', 'Active'], self::read($flowData));
        $this->assertCount(1, $this->calls);
    }

    public function testARequestWithASealedResultIsNeitherStoredNorServedFromACache(): void
    {
        $cache = new class extends DataKeyedCache {
            /** @var array<string, mixed> */
            public array $entries = [];

            public function get($cacheKey)
            {
                return $this->entries[json_encode($cacheKey)] ?? null;
            }

            public function set($cacheKey, $value): void
            {
                $this->entries[json_encode($cacheKey)] = $value;
            }
        };
        $pipeline = $this->pipeline(self::answer(['humanconfidence' => 'High']), [], $cache);
        // The Evidence the engine accepts is the same both times, so a cache
        // would serve the second.
        $first = $this->process($pipeline);
        $second = $this->process($pipeline);
        $this->assertCount(2, $this->calls);
        $this->assertSame(['High', 'Active'], self::read($first));
        $this->assertSame(['High', 'Active'], self::read($second));
        $this->assertSame([], $cache->entries);

        // A request without a sealed result is cached as before.
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', 'agent');
        $flowData->process();
        $this->assertCount(1, $cache->entries);
    }

    /**
     * A cache set on the derived cloud engine never serves a Value redeemed
     * for one request to another, nor serves a sealed request a Value
     * cached for another. Its cache key holds no Evidence, so every request
     * would share one entry.
     */
    public function testACacheOnTheDerivedCloudEngineNeverServesARedeemedValue(): void
    {
        $cache = new class extends DataKeyedCache {
            /** @var array<string, mixed> */
            public array $entries = [];

            public function get($cacheKey)
            {
                return $this->entries[json_encode($cacheKey)] ?? null;
            }

            public function set($cacheKey, $value): void
            {
                $this->entries[json_encode($cacheKey)] = $value;
            }
        };
        $pipeline = $this->pipeline(static fn (array $fields): string => (string) json_encode([
            'derived' => isset($fields['humanconfidence'])
                ? ['humanconfidence' => 'High']
                : ['humanconfidence' => 'Low', 'humanconfidencesource' => 'Passive'],
        ]));
        foreach ($pipeline->flowElements as $element) {
            if ($element instanceof DerivedCloudEngine) {
                $element->setCache($cache);
            }
        }

        $redeemed = $this->process($pipeline);
        $this->assertSame(['High', 'Active'], self::read($redeemed));
        $this->assertSame([], $cache->entries);

        // No transaction is kept for this page, so nothing is redeemed.
        $notKept = $this->process($pipeline, [], 'a page with nothing kept');
        $this->assertSame(['Medium', 'Active'], self::read($notKept));

        // A request without a sealed result is cached as before.
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', 'agent');
        $flowData->process();
        $this->assertSame(['Low', 'Passive'], self::read($flowData));
        $this->assertCount(1, $cache->entries);

        // A sealed request isn't served what that request cached.
        $again = $this->process($pipeline);
        $this->assertSame(['High', 'Active'], self::read($again));
    }

    public function testTheCallHoldsOnlyTheKeptTransaction(): void
    {
        $pipeline = $this->pipeline(self::answer(['humanconfidence' => 'High']));
        $page = $this->newPage();
        // One prefix other than query for each name, as two would raise
        // the engine's warning about conflicting Evidence.
        $flowData = $this->process($pipeline, [
            'server.transaction' => 'FROM-THE-SERVER',
            'query.Transaction' => 'FROM-THE-QUERY',
            'cookie.challenge' => 'FROM-A-COOKIE',
            'query.CHALLENGE' => 'FROM-THE-QUERY-TOO',
        ], $page->pageId());
        $this->assertSame(['High', 'Active'], self::read($flowData));
        $transactions = [];
        foreach ($this->calls[0] as $name => $value) {
            if (in_array(strtolower((string) $name), ['transaction', 'challenge'], true)) {
                $transactions[$name] = $value;
            }
        }
        $this->assertSame(['transaction' => self::transactionOf($page)], $transactions);
    }

    public function testTheCallNamesHumanConfidenceAndTheReportIsForTheConfiguredList(): void
    {
        $pipeline = $this->pipeline(
            self::answer(['humanconfidence' => 'High']),
            ['requestedProperties' => ['derived.Other']]
        );
        $this->process($pipeline);
        $this->assertSame('derived.Other,derived.HumanConfidence', $this->calls[0]['values']);
        $reported = array_filter(
            $this->logged,
            static fn (array $entry): bool => stripos($entry['message'], 'derived.') !== false
        );
        // derived.Other is not in the answer, so it alone is reported.
        $this->assertCount(1, $reported);
        $this->assertStringContainsString('derived.Other', reset($reported)['message']);
        $this->assertStringNotContainsStringIgnoringCase('HumanConfidence', reset($reported)['message']);

        $named = $this->pipeline(
            self::answer(['humanconfidence' => 'High']),
            ['requestedProperties' => 'derived.humanconfidence']
        );
        $this->process($named);
        $this->assertSame('derived.humanconfidence', $this->calls[0]['values']);
    }

    /**
     * The required test that no log entry or error message holds the sealed
     * result, the License Key, or the transaction. The engine uses the real
     * HTTP client, and a local server stands in for a cloud service that
     * echoes all three back, as they are, URL-encoded, as JSON writes them,
     * and cut short, in a refusal and in its warnings. The Pipeline logs to
     * a file through the core logger at trace level, and the test reads
     * that file, every PHP warning raised, and every error the Flow Data
     * holds.
     */
    public function testNoLogOrErrorHoldsTheSealedResultTheLicenseKeyOrTheTransaction(): void
    {
        $sealed = 'SEALED+Result/' . self::SEALED . '==';
        $server = self::startEchoingCloud($this->folder . '-cloud');
        $logFile = $this->folder . '-cloud' . DIRECTORY_SEPARATOR . 'pipeline.log';
        try {
            $engine = new CloudRequestEngine([
                'resourceKey' => 'resource_key',
                'licenseKey' => self::LICENSE,
                'cloudEndPoint' => $server['url'],
                'humanConfidenceTransactions' => $this->transactions,
            ]);
            $pipeline = (new PipelineBuilder([
                'addJavaScriptBuilder' => false,
                'useSetHeaderProperties' => false,
                'suppressProcessExceptions' => true,
            ]))->addLogger(new Logger('trace', [Logger::FILE_SETTING => $logFile]))
                ->add($engine)->add(new DerivedCloudEngine())->build();
            $page = $this->newPage();
            $kept = self::transactionOf($page);
            // The engine raises a warning where two Evidence values conflict.
            $texts = [];
            set_error_handler(static function (int $level, string $message) use (&$texts): bool {
                $texts[] = $message;

                return true;
            });
            try {
                $flowData = $this->process($pipeline, [
                    'query.humanconfidence' => $sealed,
                    'header.transaction' => 'TRANSACTION-ONE',
                    'cookie.transaction' => 'TRANSACTION-TWO',
                    'header.humanconfidence' => $sealed,
                    'cookie.humanconfidence' => $sealed,
                ], $page->pageId());
            } finally {
                restore_error_handler();
            }
            $log = is_file($logFile) ? (string) file_get_contents($logFile) : '';
        } finally {
            self::stopEchoingCloud($server);
        }

        $this->assertNotEmpty($texts);
        $this->assertSame(['Medium', 'Active'], self::read($flowData));
        // The refusal and the echoed warnings were logged, so the file holds
        // what the cloud service sent back.
        $this->assertStringContainsString('[error]', $log);
        $this->assertStringContainsString('[warning]', $log);
        $this->assertStringContainsString('Refused', $log);
        $texts[] = $log;
        foreach ($flowData->errors as $error) {
            $texts[] = $error->getMessage();
            if ($error instanceof CloudRequestException) {
                $texts = array_merge($texts, $error->errors, $error->warnings);
            }
        }
        $forbidden = [];
        foreach ([$sealed, self::LICENSE, $kept] as $secret) {
            $forbidden[] = $secret;
            $forbidden[] = rawurlencode($secret);
            $forbidden[] = urlencode($secret);
            $forbidden[] = substr((string) json_encode($secret), 1, -1);
            $forbidden[] = substr($secret, 3, 16);
        }
        // The last four characters a License Key used to be masked to.
        $forbidden[] = substr(self::LICENSE, -4);
        foreach ($texts as $text) {
            foreach ($forbidden as $part) {
                $this->assertStringNotContainsString($part, $text);
            }
            $this->assertStringNotContainsString('TRANSACTION-', $text);
        }
    }

    /**
     * Starts a local server, in a new folder, that answers the discovery
     * requests as a cloud service does, and refuses each JSON call with
     * status 400, echoing the humanconfidence, transaction, and license
     * fields back as they are, URL-encoded, and cut short, both in its body,
     * which JSON writes with escaped slashes, and in its warnings.
     *
     * @return array{process: resource, url: string, folder: string}
     */
    private static function startEchoingCloud(string $folder): array
    {
        mkdir($folder);
        $router = $folder . DIRECTORY_SEPARATOR . 'router.php';
        file_put_contents($router, <<<'PHP'
<?php
header('Content-Type: application/json');
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (stripos($path, 'accessibleProperties') !== false) {
    echo '{"Products": {"derived": {"DataTier": "tier", "Properties": [' .
        '{"Name": "HumanConfidence", "Type": "String", "Category": "Bot"},' .
        '{"Name": "HumanConfidenceSource", "Type": "String", "Category": "Bot"}]}}}';
    return;
}
if (stripos($path, 'evidencekeys') !== false) {
    echo '["header.user-agent", "query.humanconfidence", "query.humanconfidencepage"]';
    return;
}
parse_str((string) file_get_contents('php://input'), $fields);
$echo = [];
foreach (['humanconfidence', 'transaction', 'license'] as $name) {
    $value = (string) ($fields[$name] ?? '');
    $echo[] = $name . '=' . $value . ' ' . rawurlencode($value) . ' ' .
        urlencode($value) . ' ' . substr($value, 3, 16);
}
http_response_code(400);
echo json_encode([
    'detail' => 'Refused ' . implode('; ', $echo),
    'warnings' => ['Saw ' . implode('; ', $echo)],
]);
PHP
        );

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            self::fail('No free port: ' . $errstr);
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);

        $serverLog = $folder . DIRECTORY_SEPARATOR . 'server.log';
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
            [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']],
            $pipes
        );
        if (!is_resource($process)) {
            self::fail('The local server could not be started.');
        }
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $connection = PhpMessage::take(static function () use ($port) {
                return fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            });
            if ($connection !== false) {
                fclose($connection);

                return ['process' => $process, 'url' => 'http://127.0.0.1:' . $port . '/', 'folder' => $folder];
            }
            usleep(100000);
        }
        self::fail('The local server did not start. ' .
            (is_readable($serverLog) ? (string) file_get_contents($serverLog) : ''));
    }

    /**
     * Stops the local server and removes its folder.
     *
     * @param array{process: resource, url: string, folder: string} $server
     */
    private static function stopEchoingCloud(array $server): void
    {
        proc_terminate($server['process']);
        proc_close($server['process']);
        foreach (glob($server['folder'] . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            PhpMessage::take(static function () use ($file) {
                return unlink($file);
            });
        }
        PhpMessage::take(static function () use ($server) {
            return rmdir($server['folder']);
        });
    }

    /**
     * Only query.humanconfidence carries a sealed result. A humanconfidence
     * under any other key is never redeemed, so the call leaves it out, and
     * any transaction or challenge from the request with it, as the page
     * chose those. The kept transaction stays kept.
     */
    public function testAHumanConfidenceUnderAnotherKeyIsNotSent(): void
    {
        $keys = [
            'header.humanconfidence',
            'Header.HumanConfidence',
            'cookie.humanconfidence',
            'server.humanconfidence',
            'humanconfidence',
        ];
        foreach ($keys as $key) {
            $pipeline = $this->pipeline(self::answer(['humanconfidence' => 'High']));
            $page = $this->newPage();
            $flowData = $this->process($pipeline, [
                'query.humanconfidence' => null,
                $key => 'sealed-' . self::transactionOf($page),
                'query.transaction' => 'FROM-THE-QUERY',
                'header.challenge' => 'FROM-A-HEADER',
            ], $page->pageId());
            $this->assertCount(1, $this->calls, $key);
            $left = array_values(array_filter(
                array_map('strtolower', array_map('strval', array_keys($this->calls[0]))),
                static fn (string $name): bool => in_array($name, ['humanconfidence', 'transaction', 'challenge'], true)
            ));
            $this->assertSame([], $left, $key);
            $this->assertStringNotContainsString('sealed-', http_build_query($this->calls[0]), $key);
            $this->assertSame([], $flowData->errors, $key);
        }
    }

    /**
     * A humanconfidence under another key is neither sent nor cached, so it
     * is never kept in a cache key either, by the Cloud Request Engine or
     * by the derived cloud engine.
     */
    public function testAHumanConfidenceUnderAnotherKeyIsNeverCached(): void
    {
        $newCache = static fn (): DataKeyedCache => new class extends DataKeyedCache {
            /** @var array<string, mixed> */
            public array $entries = [];

            public function get($cacheKey)
            {
                return $this->entries[json_encode($cacheKey)] ?? null;
            }

            public function set($cacheKey, $value): void
            {
                $this->entries[json_encode($cacheKey)] = $value;
            }
        };
        $requestCache = $newCache();
        $derivedCache = $newCache();
        $pipeline = $this->pipeline(
            self::answer(['humanconfidence' => 'Low', 'humanconfidencesource' => 'Passive']),
            [],
            $requestCache,
            static fn (): string => '["header.user-agent", "header.humanconfidence", "query.humanconfidence"]'
        );
        foreach ($pipeline->flowElements as $element) {
            if ($element instanceof DerivedCloudEngine) {
                $element->setCache($derivedCache);
            }
        }
        foreach (['first', 'second'] as $request) {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('header.user-agent', 'agent');
            $flowData->evidence->set('header.humanconfidence', 'SEALED-IN-A-HEADER');
            $flowData->process();
            $this->assertSame(['Low', 'Passive'], self::read($flowData), $request);
        }
        $this->assertCount(2, $this->calls);
        $this->assertSame([], $requestCache->entries);
        $this->assertSame([], $derivedCache->entries);
    }

    /**
     * An empty humanconfidence under another key is no sealed result, so
     * the call is made as before.
     */
    public function testAnEmptyHumanConfidenceUnderAnotherKeyChangesNothing(): void
    {
        $pipeline = $this->pipeline(self::answer(['humanconfidence' => 'Low']));
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', 'agent');
        $flowData->evidence->set('cookie.humanconfidence', '');
        $flowData->evidence->set('query.transaction', 'T');
        $flowData->evidence->set('header.challenge', 'C');
        $flowData->process();
        $this->assertSame('T', $this->calls[0]['transaction']);
        $this->assertSame('C', $this->calls[0]['challenge']);
        $this->assertSame('', $this->calls[0]['humanconfidence']);
    }

    /**
     * The texts of the errors the Flow Data holds, with their errors and
     * warnings lists, and of the entries the pipeline logged.
     *
     * @return array<string>
     */
    private function messages(FlowData $flowData): array
    {
        $texts = [];
        foreach ($flowData->errors as $error) {
            $texts[] = $error->getMessage();
            if ($error instanceof CloudRequestException) {
                $texts = array_merge($texts, $error->errors, $error->warnings);
            }
        }
        foreach ($this->logged as $entry) {
            $texts[] = $entry['message'];
        }

        return $texts;
    }

    /**
     * No message the engine builds holds any part of the License Key, on a
     * request with no sealed result too. The key is hidden whole, also
     * where the HTTP client already masked a license= value to its last
     * four characters.
     */
    public function testNoMessageHoldsAnyPartOfTheLicenseKeyWithoutASealedResult(): void
    {
        $pipeline = $this->pipeline(static function (array $fields): string {
            throw new CloudRequestException(
                LicenseKey::maskQuery('Not valid: ?license=' . $fields['license']) .
                    ' or ' . $fields['license'],
                400,
                [],
                ['Bad key ' . $fields['license']],
                ['Saw ' . $fields['license']]
            );
        });
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', 'agent');
        $flowData->process();

        $texts = $this->messages($flowData);
        $this->assertNotEmpty($flowData->errors);
        foreach ($texts as $text) {
            $this->assertStringNotContainsString(substr(self::LICENSE, -4), $text);
        }
        $this->assertStringContainsString('(hidden)', implode("\n", $texts));
    }

    /**
     * No message holds the sealed result, the transaction, or the License
     * Key as a form body or address would carry it, as JSON would write
     * it, or cut short, as a cloud service message might echo it.
     */
    public function testNoMessageHoldsTheSealedResultTheTransactionOrTheKeyEncodedOrInPart(): void
    {
        $sealed = 'SeAlEd+Result/With=Signs-0123456789abcdefghij';
        $pipeline = $this->pipeline(static function (array $fields): string {
            $sealed = $fields['humanconfidence'];
            $echo = 'Refused ' . rawurlencode($sealed) . ' and ' . urlencode($sealed) .
                ' and ' . substr((string) json_encode($sealed), 1, -1) .
                ' and ' . substr($sealed, 5, 20) .
                ' with ' . substr($fields['transaction'], 3, 20) .
                ' for ' . substr($fields['license'], 2, 14) . '.';
            throw new CloudRequestException($echo, 400, [], [$echo], [$echo]);
        });
        $page = $this->newPage();
        $kept = self::transactionOf($page);
        $flowData = $this->process($pipeline, ['query.humanconfidence' => $sealed], $page->pageId());

        $texts = $this->messages($flowData);
        $this->assertNotEmpty($flowData->errors);
        $this->assertStringContainsString('Refused [withheld]', implode("\n", $texts));
        $forbidden = [
            $sealed,
            rawurlencode($sealed),
            urlencode($sealed),
            substr((string) json_encode($sealed), 1, -1),
            substr($sealed, 5, 20),
            substr($kept, 3, 20),
            substr(self::LICENSE, 2, 14),
        ];
        foreach ($texts as $text) {
            foreach ($forbidden as $part) {
                $this->assertStringNotContainsString($part, $text);
            }
        }
    }

    /**
     * A humanconfidence under a key other than query.humanconfidence is
     * never sent, and no message holds it either, as in the warning about
     * conflicting Evidence where another key holds the same value.
     */
    public function testNoMessageHoldsAHumanConfidenceUnderAnotherKey(): void
    {
        $sealed = 'SEALED-UNDER-A-HEADER-0123456789';
        $pipeline = $this->pipeline(self::answer(['humanconfidence' => 'Low']));
        $texts = [];
        set_error_handler(static function (int $level, string $message) use (&$texts): bool {
            $texts[] = $message;

            return true;
        });
        try {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('header.user-agent', 'agent');
            $flowData->evidence->set('header.humanconfidence', $sealed);
            $flowData->evidence->set('cookie.x-copy', 'from a cookie');
            $flowData->evidence->set('header.x-copy', $sealed);
            $flowData->process();
        } finally {
            restore_error_handler();
        }
        $texts = array_merge($texts, $this->messages($flowData));
        $this->assertNotEmpty($texts);
        foreach ($texts as $text) {
            $this->assertStringNotContainsString($sealed, $text);
            $this->assertStringNotContainsString(substr($sealed, 4, 14), $text);
        }
    }

    /**
     * A sealed result that is part of the License Key doesn't stop the key
     * being hidden, as the longest value is hidden first.
     */
    public function testASealedResultThatIsPartOfTheLicenseKeyDoesNotUncoverIt(): void
    {
        $pipeline = $this->pipeline(static function (array $fields): string {
            throw new CloudRequestException('Refused ' . $fields['license'], 400);
        });
        $flowData = $this->process($pipeline, ['query.humanconfidence' => 'KEY-0123']);

        $texts = $this->messages($flowData);
        $this->assertNotEmpty($texts);
        foreach ($texts as $text) {
            $this->assertStringNotContainsString('456789ABCDEF', $text);
            $this->assertStringNotContainsString(substr(self::LICENSE, -4), $text);
        }
    }

    /**
     * The warning about conflicting Evidence hides the License Key on any
     * request, as an Evidence value can hold it.
     */
    public function testTheEvidenceConflictWarningHidesTheLicenseKey(): void
    {
        $pipeline = $this->pipeline(self::answer(['humanconfidence' => 'Low']));
        $texts = [];
        set_error_handler(static function (int $level, string $message) use (&$texts): bool {
            $texts[] = $message;

            return true;
        });
        try {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('header.user-agent', 'agent');
            $flowData->evidence->set('cookie.x-key', 'from a cookie ' . self::LICENSE);
            $flowData->evidence->set('header.x-key', self::LICENSE);
            $flowData->process();
        } finally {
            restore_error_handler();
        }
        $this->assertNotEmpty($texts);
        foreach ($texts as $text) {
            $this->assertStringNotContainsString(substr(self::LICENSE, -4), $text);
        }
    }

    public function testARequestWithoutASealedResultIsUnchanged(): void
    {
        $pipeline = $this->pipeline(self::answer([
            'humanconfidence' => 'Low',
            'humanconfidencesource' => 'Passive',
        ]));
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', 'agent');
        $flowData->evidence->set('query.humanconfidence', '');
        $flowData->evidence->set('query.transaction', 'T');
        $flowData->process();
        $this->assertSame(['Low', 'Passive'], self::read($flowData));
        $this->assertSame('T', $this->calls[0]['transaction']);
        $this->assertArrayNotHasKey('values', $this->calls[0]);
    }

    public function testACredentialWithoutThePropertiesGivesNoValueAndTheReason(): void
    {
        // The response holds each Property as null with the remote server's
        // reason.
        $pipeline = $this->pipeline(self::answer([
            'humanconfidence' => null,
            'humanconfidencenullreason' => 'HumanConfidence is not in the credential.',
            'humanconfidencesource' => null,
            'humanconfidencesourcenullreason' => 'HumanConfidenceSource is not in the credential.',
        ]));
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', 'agent');
        $flowData->process();
        foreach (['humanconfidence' => 'HumanConfidence', 'humanconfidencesource' => 'HumanConfidenceSource'] as $key => $name) {
            $value = $flowData->derived->{$key};
            $this->assertFalse($value->hasValue, $key);
            $this->assertSame($name . ' is not in the credential.', $value->noValueMessage, $key);
        }

        // The response leaves each Property out, so the reason is the one
        // for a missing property.
        $absent = $this->pipeline(self::answer(['other' => 'x']));
        $flowData = $absent->createFlowData();
        $flowData->evidence->set('header.user-agent', 'agent');
        $flowData->process();
        foreach (['humanconfidence', 'humanconfidencesource'] as $key) {
            try {
                $flowData->derived->{$key};
                $this->fail("A read of $key gave a value.");
            } catch (\Exception $e) {
                $this->assertStringContainsStringIgnoringCase($key, $e->getMessage(), $key);
            }
        }
    }
}
