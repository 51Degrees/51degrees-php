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

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\Constants;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The Start-up activity section of the cloud request engine specification.
 * The engine asks the cloud service for its accessible properties and its
 * accepted Evidence keys when it is built. A 4xx answer fails the build,
 * and any other failure logs a warning, completes the build and is tried
 * again on first use. An engine can instead be built from a copy of the
 * discovery results an earlier build gave, and then asks nothing.
 */
class DiscoveryTests extends TestCase
{
    private const RESOURCE_KEY = 'resource_key';
    private const OTHER_RESOURCE_KEY = 'other_resource_key';
    private const PROPERTIES = '{"Products": {"device": {"DataTier": "tier", ' .
        '"Properties": [{"Name": "IsMobile", "Type": "Boolean", ' .
        '"Category": "Device"}]}}}';
    private const EVIDENCE_KEYS = '["query.User-Agent","header.User-Agent"]';
    private const DATA = '{"device":{"ismobile":true}}';

    /**
     * Every request made, as [method, url].
     *
     * @var array<array{string, string}>
     */
    private array $requests = [];

    /**
     * The failure each kind of request gives, by 'properties', 'keys' or
     * 'data', or none where it answers.
     *
     * @var array<string, null|\Throwable>
     */
    private array $failures = [];

    /**
     * Both discovery requests are made when the engine is built, so the
     * built engine already gives its properties and accepted Evidence, and
     * processing makes only the data request.
     */
    public function testBothDiscoveryRequestsAreMadeWhenTheEngineIsBuilt(): void
    {
        $engine = $this->engine();

        $this->assertSame(['properties', 'keys'], $this->kinds());
        $this->assertArrayHasKey('ismobile', $engine->flowElementProperties['device'] ?? []);
        $this->assertSame(
            ['query.User-Agent', 'header.User-Agent'],
            $engine->getEvidenceKeyFilter()->getList()
        );

        $this->requests = [];
        $this->process($engine);
        $this->assertSame(['data'], $this->kinds());
    }

    /**
     * A 4xx answer to either discovery request fails the build, as the
     * same keys can never succeed.
     */
    public function testA4xxAnswerToADiscoveryRequestFailsTheBuild(): void
    {
        foreach (['properties', 'keys'] as $kind) {
            foreach ([400, 401, 403] as $status) {
                $this->failures = [$kind => new CloudRequestException("refused {$status}", $status)];
                try {
                    $this->engine();
                    $this->fail("A {$status} answer to the {$kind} request did not fail the build.");
                } catch (CloudRequestException $e) {
                    $this->assertSame($status, $e->httpStatusCode);
                    $this->assertSame("refused {$status}", $e->getMessage());
                }
            }
        }
    }

    /**
     * A request that could not be made, or a 5xx answer, completes the
     * build with a warning, and the engine asks again on first use.
     */
    public function testATransientFailureLogsAWarningAndIsTriedAgainOnFirstUse(): void
    {
        foreach ([0, 500, 503] as $status) {
            $this->failures = [
                'properties' => new CloudRequestException("down {$status}", $status),
                'keys' => new CloudRequestException("down {$status}", $status),
            ];
            $engine = $this->engine();

            $this->assertSame([], $engine->flowElementProperties);
            $this->assertNull($engine->getDiscoveryResults());

            $logger = self::memoryLogger();
            $pipeline = (new PipelineBuilder([
                'addJavaScriptBuilder' => false,
                'useSetHeaderProperties' => false,
            ]))->addLogger($logger)->add($engine)->build();
            $warnings = array_values(array_filter(
                $logger->entries,
                static fn (array $entry): bool => $entry['level'] === 'warning'
            ));
            $this->assertCount(2, $warnings, "status {$status}");
            foreach ($warnings as $warning) {
                $this->assertStringContainsString("down {$status}", $warning['message']);
            }

            // The cloud service is back, so first use asks again.
            $this->failures = [];
            $this->requests = [];
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('query.User-Agent', 'iPhone');
            $flowData->process();

            $this->assertSame(['keys', 'properties', 'data'], $this->kinds());
            $this->assertArrayHasKey('ismobile', $engine->flowElementProperties['device'] ?? []);
            $this->assertNotNull($engine->getDiscoveryResults());
        }
    }

    /**
     * After a transient failure the Cloud Aspect Engine's Property metadata
     * and the accepted Evidence keys are empty, and neither keeps that
     * empty result. Once first use has asked again, the aspect engine has
     * the metadata and the engine accepts the cloud service's keys.
     */
    public function testAnEmptyResultFromATransientFailureIsNotKept(): void
    {
        $this->failures = [
            'properties' => new CloudRequestException('down', 503),
            'keys' => new CloudRequestException('down', 503),
        ];
        $engine = $this->engine();
        $device = new CloudEngine();
        $device->dataKey = 'device';
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->add($device)->build();

        $this->assertSame([], $device->getProperties());

        $this->failures = [];
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.User-Agent', 'iPhone');
        $flowData->process();

        $this->assertSame(['ismobile'], array_keys($device->getProperties()));
        $this->assertSame('Boolean', $device->getProperties()['ismobile']['type']);
        $this->assertSame(true, $flowData->get('device')->get('ismobile')->value);
        $filter = $engine->getEvidenceKeyFilter();
        $this->assertTrue($filter->filterEvidenceKey('query.user-agent'));
        $this->assertTrue($filter->filterEvidenceKey('header.user-agent'));
    }

    /**
     * A first-use attempt that fails again is a processing error, so a
     * Pipeline that suppresses processing errors keeps it in the Flow Data
     * rather than throwing it.
     */
    public function testAFailedFirstUseAttemptIsAProcessingError(): void
    {
        $this->failures = [
            'properties' => new CloudRequestException('down', 503),
            'keys' => new CloudRequestException('down', 503),
        ];
        $engine = $this->engine();
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => true,
        ]))->add($engine)->build();

        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.User-Agent', 'iPhone');
        $flowData->process();

        $this->assertArrayHasKey($engine->dataKey, $flowData->errors);
        $this->assertStringContainsString('down', $flowData->errors[$engine->dataKey]->getMessage());
    }

    /**
     * An engine built from the discovery results an earlier build gave
     * makes no discovery request, and gives the same properties and
     * accepted Evidence. The copy survives being stored as JSON.
     */
    public function testAnEngineBuiltFromPersistedResultsAsksNothing(): void
    {
        $first = $this->engine();
        $copy = json_decode((string) json_encode($first->getDiscoveryResults()), true);
        $this->assertIsArray($copy);

        $this->requests = [];
        $this->failures = [
            'properties' => new CloudRequestException('asked', 503),
            'keys' => new CloudRequestException('asked', 503),
        ];
        $second = $this->engine(['discoveryResults' => $copy]);

        $this->assertSame([], $this->kinds());
        $this->assertSame($first->flowElementProperties, $second->flowElementProperties);
        $this->assertSame(
            $first->getEvidenceKeyFilter()->getList(),
            $second->getEvidenceKeyFilter()->getList()
        );
        $this->assertSame($copy, $second->getDiscoveryResults());

        $this->process($second);
        $this->assertSame(['data'], $this->kinds());
    }

    /**
     * The results depend on the keys and the endpoint, so a copy made for
     * other keys or another endpoint is refused, and so is anything that
     * is not a copy.
     */
    public function testACopyForOtherKeysOrNotACopyIsRefused(): void
    {
        $copy = $this->engine()->getDiscoveryResults();

        $cases = [
            'other key' => [['resourceKey' => self::OTHER_RESOURCE_KEY, 'discoveryResults' => $copy],
                Constants::MESSAGE_DISCOVERY_RESULTS_OTHER_CREDENTIALS],
            'other endpoint' => [['cloudEndPoint' => 'https://example.com/api/v4/', 'discoveryResults' => $copy],
                Constants::MESSAGE_DISCOVERY_RESULTS_OTHER_CREDENTIALS],
            'not a copy' => [['discoveryResults' => ['evidenceKeys' => []]],
                Constants::MESSAGE_DISCOVERY_RESULTS_NOT_A_COPY],
            'not an array' => [['discoveryResults' => 'results'],
                Constants::MESSAGE_DISCOVERY_RESULTS_NOT_A_COPY],
        ];
        foreach ($cases as $name => [$settings, $message]) {
            $this->requests = [];
            try {
                $this->engine($settings);
                $this->fail("The {$name} copy was not refused.");
            } catch (\Exception $e) {
                $this->assertNotInstanceOf(CloudRequestException::class, $e, $name);
                $this->assertSame($message, $e->getMessage(), $name);
            }
            $this->assertSame([], $this->kinds(), $name);
        }
    }

    /**
     * Builds the engine with the recording client and the given settings.
     *
     * @param array<string, mixed> $settings
     */
    private function engine(array $settings = []): CloudRequestEngine
    {
        return new CloudRequestEngine(array_merge([
            'resourceKey' => self::RESOURCE_KEY,
            'httpClient' => $this->client(),
        ], $settings));
    }

    private function process(CloudRequestEngine $engine): void
    {
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.User-Agent', 'iPhone');
        $flowData->process();
    }

    /**
     * The kind of each request made, in order.
     *
     * @return array<string>
     */
    private function kinds(): array
    {
        return array_map(
            static fn (array $request): string => self::kind($request[1]),
            $this->requests
        );
    }

    private static function kind(string $url): string
    {
        $lower = strtolower($url);
        if (strpos($lower, 'accessibleproperties') !== false) {
            return 'properties';
        }
        if (strpos($lower, 'evidencekeys') !== false) {
            return 'keys';
        }

        return 'data';
    }

    /**
     * A client that records each request and answers it, or throws the
     * failure set for its kind.
     */
    private function client(): HttpClient
    {
        $client = $this->createStub(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function (string $type, string $url): string {
                $this->requests[] = [$type, $url];
                $kind = self::kind($url);
                if (isset($this->failures[$kind])) {
                    throw $this->failures[$kind];
                }
                switch ($kind) {
                    case 'properties':
                        return self::PROPERTIES;
                    case 'keys':
                        return self::EVIDENCE_KEYS;
                    default:
                        return self::DATA;
                }
            }
        );

        return $client;
    }

    /**
     * A logger that keeps every entry, at every level.
     */
    private static function memoryLogger(): Logger
    {
        return new class ('trace') extends Logger {
            /** @var array<array<string, string>> */
            public array $entries = [];

            public function logInternal(array $log): void
            {
                $this->entries[] = $log;
            }
        };
    }
}
