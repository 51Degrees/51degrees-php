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
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The HTTP requests section of the cloud request engine specification.
 * Each entry of a response's errors array is kept apart in the exception
 * thrown, and each entry of its warnings array is logged as a warning.
 */
class CloudWarningsAndErrorsTests extends TestCase
{
    private const URL = 'https://cloud.51degrees.com/api/v4/resource_key.json';
    private const LICENSE_KEY = 'LICENSEKEY1234567890';
    private const MASKED_LICENSE_KEY = '****************7890';

    /**
     * Each entry of the errors array is kept apart in the exception, which
     * also holds the warnings, and the message still names every error.
     */
    public function testEachErrorIsKeptApartInTheException(): void
    {
        $body = '{"errors":["first error","second error"],' .
            '"warnings":["a warning"]}';

        $e = $this->validate($body, 400);

        $this->assertSame(['first error', 'second error'], $e->errors);
        $this->assertSame(['a warning'], $e->warnings);
        $this->assertSame(400, $e->httpStatusCode);
        $this->assertStringContainsString('first error', $e->getMessage());
        $this->assertStringContainsString('second error', $e->getMessage());
    }

    /**
     * A failure the errors array does not explain holds no errors.
     */
    public function testAFailureWithNoErrorsArrayHoldsNoErrors(): void
    {
        $e = $this->validate('{"warnings":["a warning"]}', 500);

        $this->assertSame([], $e->errors);
        $this->assertSame(['a warning'], $e->warnings);
    }

    /**
     * Each entry of the warnings array in an answer is logged as a
     * warning, whether it answers a discovery request when the engine is
     * built or the data request when it processes.
     */
    public function testWarningsInAnAnswerAreLoggedAsWarnings(): void
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => 'resource_key',
            'httpClient' => $this->client(function (string $kind): string {
                switch ($kind) {
                    case 'properties':
                        return '{"Products": {"device": {"Properties": [' .
                            '{"Name": "IsMobile", "Type": "Boolean"}]}}, ' .
                            '"warnings": ["properties warning"]}';
                    case 'keys':
                        return '["query.User-Agent"]';
                    default:
                        return '{"device":{"ismobile":true},' .
                            '"warnings":["first data warning","second data warning"]}';
                }
            }),
        ]);
        $logger = self::memoryLogger();
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->addLogger($logger)->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.User-Agent', 'iPhone');
        $flowData->process();

        $warnings = self::warnings($logger);
        foreach (['properties warning', 'first data warning', 'second data warning'] as $text) {
            $this->assertCount(
                1,
                array_filter($warnings, static fn (string $w): bool => strpos($w, $text) !== false),
                $text . ' in ' . implode(' | ', $warnings)
            );
        }
    }

    /**
     * The warnings of an answer that fails the request are logged too, and
     * the errors the engine throws keep the license key masked, as the
     * message does.
     */
    public function testAFailedRequestLogsItsWarningsAndKeepsItsErrorsMasked(): void
    {
        $failure = new CloudRequestException(
            'refused for ' . self::LICENSE_KEY,
            400,
            [],
            ['refused for ' . self::LICENSE_KEY, 'second error'],
            ['warning about ' . self::LICENSE_KEY]
        );
        $engine = new CloudRequestEngine([
            'licenseKey' => self::LICENSE_KEY,
            'requestedProperties' => ['device.ismobile'],
            'httpClient' => $this->client(function (string $kind) use ($failure): string {
                switch ($kind) {
                    case 'properties':
                        return '{"Products": {}}';
                    case 'keys':
                        return '["query.User-Agent"]';
                    default:
                        throw $failure;
                }
            }),
        ]);
        $logger = self::memoryLogger();
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->addLogger($logger)->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.User-Agent', 'iPhone');

        try {
            $flowData->process();
            $this->fail('The failed request was not thrown.');
        } catch (CloudRequestException $e) {
            $this->assertSame(
                ['refused for ' . self::MASKED_LICENSE_KEY, 'second error'],
                $e->errors
            );
            $this->assertSame(400, $e->httpStatusCode);
        }

        $warnings = implode("\n", self::warnings($logger));
        $this->assertStringContainsString('warning about ' . self::MASKED_LICENSE_KEY, $warnings);
        $this->assertStringNotContainsString(self::LICENSE_KEY, $warnings);
    }

    /**
     * Runs HttpClient's check of an answer, which every request makes, and
     * returns what it throws.
     */
    private function validate(string $body, int $status): CloudRequestException
    {
        $validate = new \ReflectionMethod(HttpClient::class, 'validateResponse');
        if (PHP_VERSION_ID < 80100) {
            $validate->setAccessible(true);
        }
        try {
            $validate->invoke(new HttpClient(), $body, $status, [], self::URL);
        } catch (CloudRequestException $e) {
            return $e;
        }
        $this->fail('The answer was not refused.');
    }

    /**
     * A client that answers each kind of request, being 'properties',
     * 'keys' or 'data', with what $answer gives for it.
     */
    private function client(callable $answer): HttpClient
    {
        $client = $this->createStub(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            static function (string $type, string $url) use ($answer): string {
                $lower = strtolower($url);
                if (strpos($lower, 'accessibleproperties') !== false) {
                    return $answer('properties');
                }
                if (strpos($lower, 'evidencekeys') !== false) {
                    return $answer('keys');
                }

                return $answer('data');
            }
        );

        return $client;
    }

    /**
     * @return array<string>
     */
    private static function warnings(Logger $logger): array
    {
        $warnings = [];
        foreach ($logger->entries as $entry) {
            if ($entry['level'] === 'warning') {
                $warnings[] = $entry['message'];
            }
        }

        return $warnings;
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
