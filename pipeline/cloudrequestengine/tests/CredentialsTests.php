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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\Constants;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The combinations of resource key, license key and requested properties in
 * the Credentials section of the cloud request engine specification, and
 * where each credential travels in the requests the engine makes.
 */
class CredentialsTests extends TestCase
{
    public const resourceKey = 'AAAAAAAAAAAA';
    public const licenseKey = 'LICENSEKEY1234567890';
    public const requested = ['device.ismobile', 'device.istablet'];
    public const failure = 'The request was refused.';

    /**
     * The license key as a message may show it, the last 4 characters
     * after one * for each other character.
     */
    public const maskedLicenseKey = '****************7890';

    /** @var array<array{string, string, null|string}> */
    private array $requests = [];

    /**
     * A client that records every request as [method, url, body] and
     * answers each endpoint with canned data, the data request with
     * $dataAnswer where one is given. A request whose URL contains $failing
     * is refused instead, with an error that names the license key, as the
     * cloud service's refusal of a license key does.
     */
    private function recordingClient(?string $failing = null, ?string $dataAnswer = null): HttpClient
    {
        $this->requests = [];
        $client = $this->createStub(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function (string $type, string $url, ?string $content) use ($failing, $dataAnswer): string {
                $this->requests[] = [$type, $url, $content];
                $lower = strtolower($url);
                if ($failing !== null && strpos($lower, $failing) !== false) {
                    throw new CloudRequestException(
                        self::failure . " License key(s): '" . self::licenseKey . "'",
                        403,
                        []
                    );
                }
                if (strpos($lower, 'accessibleproperties') !== false) {
                    return '{"Products": {"device": {"Properties": [' .
                        '{"Name": "IsMobile", "Type": "Boolean"}]}}}';
                }
                if (strpos($lower, 'evidencekeys') !== false) {
                    return '["query.User-Agent"]';
                }

                return $dataAnswer ?? '{"device":{"ismobile":true}}';
            }
        );

        return $client;
    }

    /**
     * A logger that keeps the message of every entry, at every level.
     */
    private static function memoryLogger(): Logger
    {
        return new class ('trace') extends Logger {
            /** @var array<string> */
            public array $messages = [];

            public function logInternal(array $log): void
            {
                $this->messages[] = $log['message'];
            }
        };
    }

    /**
     * Builds the engine in a pipeline and processes one request through it,
     * which fetches the evidence keys and accessible properties first.
     *
     * @param array<string, mixed> $settings
     * @return array{requests: array<array{string, string, array<string, string>}>, logged: array<string>, thrown: array<\Throwable>, engine: CloudRequestEngine}
     */
    private function process(array $settings, ?string $failing = null): array
    {
        $engine = new CloudRequestEngine(array_merge(
            ['httpClient' => $this->recordingClient($failing)],
            $settings
        ));
        $logger = self::memoryLogger();
        $pipeline = (new PipelineBuilder())->add($engine)->addLogger($logger)->build();
        $thrown = [];
        try {
            $data = $pipeline->createFlowData();
            $data->evidence->set('query.User-Agent', 'iPhone');
            $data->process();
        } catch (\Throwable $e) {
            $thrown[] = $e;
        }

        $requests = [];
        foreach ($this->requests as [$type, $url, $content]) {
            $fields = [];
            parse_str((string) $content, $fields);
            // The Sequence Element the builder adds sets these on every
            // request.
            unset($fields['session-id'], $fields['sequence']);
            $requests[] = [$type, $url, $fields];
        }

        return [
            'requests' => $requests,
            'logged' => $logger->messages,
            'thrown' => $thrown,
            'engine' => $engine
        ];
    }

    /**
     * @param array<array{string, string, array<string, string>}> $requests
     * @return array{string, string, array<string, string>}
     */
    private static function propertiesRequest(array $requests): array
    {
        foreach ($requests as $request) {
            if (stripos($request[1], 'accessibleproperties') !== false) {
                return $request;
            }
        }
        self::fail('No accessible properties request was made');
    }

    /**
     * @param array<array{string, string, array<string, string>}> $requests
     * @return array{string, string, array<string, string>}
     */
    private static function dataRequest(array $requests): array
    {
        return $requests[count($requests) - 1];
    }

    public function testResourceKeyAloneKeepsItsRouteAndNamesTheKeyInTheBody(): void
    {
        $result = $this->process(['resourceKey' => self::resourceKey]);

        $this->assertSame([], $result['thrown']);
        [$type, $url, $fields] = self::dataRequest($result['requests']);
        $this->assertSame('POST', $type);
        $this->assertSame(Constants::BASE_URL_DEFAULT . self::resourceKey . '.json?', $url);
        $this->assertSame(
            ['User-Agent' => 'iPhone', 'resource' => self::resourceKey],
            $fields
        );

        [$type, $url, $fields] = self::propertiesRequest($result['requests']);
        $this->assertSame('GET', $type);
        $this->assertSame(
            Constants::BASE_URL_DEFAULT . 'accessibleProperties?resource=' . self::resourceKey,
            $url
        );
        $this->assertSame([], $fields);
    }

    public function testResourceKeyWithLicenseKeySendsTheLicenseKeyInTheBody(): void
    {
        $result = $this->process([
            'resourceKey' => self::resourceKey,
            'licenseKey' => self::licenseKey
        ]);

        $this->assertSame([], $result['thrown']);
        [$type, $url, $fields] = self::propertiesRequest($result['requests']);
        $this->assertSame('POST', $type);
        $this->assertSame(Constants::BASE_URL_DEFAULT . 'accessibleProperties', $url);
        $this->assertSame(
            ['resource' => self::resourceKey, 'license' => self::licenseKey],
            $fields
        );

        [, $url, $fields] = self::dataRequest($result['requests']);
        $this->assertSame(Constants::BASE_URL_DEFAULT . self::resourceKey . '.json?', $url);
        $this->assertSame([
            'User-Agent' => 'iPhone',
            'resource' => self::resourceKey,
            'license' => self::licenseKey
        ], $fields);
    }

    public function testResourceKeyLicenseKeyAndPropertiesSendsAllThree(): void
    {
        $result = $this->process([
            'resourceKey' => self::resourceKey,
            'licenseKey' => self::licenseKey,
            'requestedProperties' => self::requested
        ]);

        $this->assertSame([], $result['thrown']);
        [, $url, $fields] = self::dataRequest($result['requests']);
        $this->assertSame(Constants::BASE_URL_DEFAULT . self::resourceKey . '.json?', $url);
        $this->assertSame([
            'User-Agent' => 'iPhone',
            'resource' => self::resourceKey,
            'license' => self::licenseKey,
            'values' => 'device.ismobile,device.istablet'
        ], $fields);
    }

    public function testResourceKeyWithPropertiesAndNoLicenseKeySendsTheList(): void
    {
        $result = $this->process([
            'resourceKey' => self::resourceKey,
            'requestedProperties' => self::requested
        ]);

        $this->assertSame([], $result['thrown']);
        [, $url, $fields] = self::dataRequest($result['requests']);
        $this->assertSame(Constants::BASE_URL_DEFAULT . self::resourceKey . '.json?', $url);
        $this->assertSame([
            'User-Agent' => 'iPhone',
            'resource' => self::resourceKey,
            'values' => 'device.ismobile,device.istablet'
        ], $fields);

        [$type, $url] = self::propertiesRequest($result['requests']);
        $this->assertSame('GET', $type);
        $this->assertSame(
            Constants::BASE_URL_DEFAULT . 'accessibleProperties?resource=' . self::resourceKey,
            $url
        );
    }

    public function testLicenseKeyWithPropertiesSendsLicenseAndValuesOnly(): void
    {
        $result = $this->process([
            'licenseKey' => self::licenseKey,
            'requestedProperties' => self::requested
        ]);

        $this->assertSame([], $result['thrown']);
        [$type, $url, $fields] = self::dataRequest($result['requests']);
        $this->assertSame('POST', $type);
        $this->assertSame(Constants::BASE_URL_DEFAULT . 'json', $url);
        $this->assertSame([
            'User-Agent' => 'iPhone',
            'license' => self::licenseKey,
            'values' => 'device.ismobile,device.istablet'
        ], $fields);
    }

    public function testLicenseKeyAloneDiscoversPropertiesAndEvidenceKeys(): void
    {
        $result = $this->process([
            'licenseKey' => self::licenseKey,
            'requestedProperties' => self::requested
        ]);

        $this->assertSame([], $result['thrown']);
        [$type, $url, $fields] = self::propertiesRequest($result['requests']);
        $this->assertSame('POST', $type);
        $this->assertSame(Constants::BASE_URL_DEFAULT . 'accessibleProperties', $url);
        $this->assertSame(['license' => self::licenseKey], $fields);
        $this->assertArrayHasKey('ismobile', $result['engine']->flowElementProperties['device']);

        $this->assertContains(
            ['GET', Constants::BASE_URL_DEFAULT . 'evidencekeys', []],
            $result['requests']
        );
        $this->assertSame(
            ['query.User-Agent'],
            $result['engine']->getEvidenceKeyFilter()->getList()
        );
    }

    public function testAListMayBeOneCommaSeparatedString(): void
    {
        $engine = new CloudRequestEngine([
            'licenseKey' => self::licenseKey,
            'requestedProperties' => ' device.ismobile , device.istablet,',
            'httpClient' => $this->recordingClient()
        ]);

        $this->assertSame(self::requested, $engine->requestedProperties);
    }

    public function testABlankKeyIsAbsentAndAnyOtherKeyIsKeptAsGiven(): void
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => ' ' . self::resourceKey . "\n",
            'licenseKey' => '   ',
            'requestedProperties' => [' ', ''],
            'httpClient' => $this->recordingClient()
        ]);

        $this->assertSame(' ' . self::resourceKey . "\n", $engine->resourceKey);
        $this->assertSame('', $engine->licenseKey);
        $this->assertSame([], $engine->requestedProperties);
    }

    public function testLicenseKeyWithoutPropertiesIsRefused(): void
    {
        foreach ([[], ['requestedProperties' => []], ['requestedProperties' => ' , ']] as $extra) {
            try {
                new CloudRequestEngine(array_merge(['licenseKey' => self::licenseKey], $extra));
                $this->fail('The engine should not build');
            } catch (\Exception $e) {
                $this->assertSame(Constants::MESSAGE_LICENSE_KEY_NEEDS_PROPERTIES, $e->getMessage());
            }
        }
        $this->assertStringContainsString('400', Constants::MESSAGE_LICENSE_KEY_NEEDS_PROPERTIES);
    }

    public function testNoCredentialIsRefused(): void
    {
        foreach ([
            [],
            ['requestedProperties' => self::requested],
            ['resourceKey' => ' ', 'licenseKey' => "\t", 'requestedProperties' => self::requested]
        ] as $settings) {
            try {
                new CloudRequestEngine($settings);
                $this->fail('The engine should not build');
            } catch (\Exception $e) {
                $this->assertSame(Constants::MESSAGE_NO_CREDENTIAL, $e->getMessage());
            }
        }
        $this->assertStringContainsString('401', Constants::MESSAGE_NO_CREDENTIAL);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function withLicenseKey(): array
    {
        return [
            'resource and license keys' => [[
                'resourceKey' => self::resourceKey,
                'licenseKey' => self::licenseKey
            ]],
            'resource and license keys and properties' => [[
                'resourceKey' => self::resourceKey,
                'licenseKey' => self::licenseKey,
                'requestedProperties' => self::requested
            ]],
            'license key and properties' => [[
                'licenseKey' => self::licenseKey,
                'requestedProperties' => self::requested
            ]]
        ];
    }

    /**
     * @dataProvider withLicenseKey
     * @param array<string, mixed> $settings
     */
    #[DataProvider('withLicenseKey')]
    public function testNoRequestCarriesTheLicenseKeyInItsUrl(array $settings): void
    {
        $requests = $this->process($settings)['requests'];

        $this->assertGreaterThan(2, count($requests));
        foreach ($requests as [, $url]) {
            $this->assertStringNotContainsString(self::licenseKey, $url);
        }
    }

    /**
     * @dataProvider withLicenseKey
     * @param array<string, mixed> $settings
     */
    #[DataProvider('withLicenseKey')]
    public function testFailedRequestsLogAndThrowNothingHoldingTheLicenseKey(array $settings): void
    {
        // The discovery request, then the data request, which is named by
        // the resource key where there is one.
        $dataPath = isset($settings['resourceKey'])
            ? strtolower(self::resourceKey) . '.json'
            : 'v4/json';
        foreach (['accessibleproperties', $dataPath] as $failing) {
            $result = $this->process($settings, $failing);

            $this->assertCount(1, $result['thrown'], $failing);
            $text = implode("\n", $result['logged']);
            foreach ($result['thrown'] as $e) {
                $text .= "\n" . $e->getMessage();
            }
            $this->assertStringContainsString(self::failure, $text);
            $this->assertStringContainsString(
                "License key(s): '" . self::maskedLicenseKey . "'",
                $text
            );
            $this->assertStringNotContainsString(self::licenseKey, $text);
        }
    }

    public function testPropertiesTheAnswerLeavesOutAreLoggedOnce(): void
    {
        // Names match in any case, and a null with its reason counts as
        // answered.
        $engine = new CloudRequestEngine([
            'licenseKey' => self::licenseKey,
            'requestedProperties' => ['Device.IsMobile', 'device.istablet', 'device.iscrawler'],
            'httpClient' => $this->recordingClient(
                null,
                '{"device":{"ismobile":true,"istablet":null,' .
                    '"istabletnullreason":"No value on this request"}}'
            )
        ]);
        $logger = self::memoryLogger();
        $pipeline = (new PipelineBuilder())->add($engine)->addLogger($logger)->build();
        for ($i = 0; $i < 2; $i++) {
            $data = $pipeline->createFlowData();
            $data->evidence->set('query.User-Agent', 'iPhone');
            $data->process();
        }

        $this->assertSame(
            [sprintf(Constants::MESSAGE_PROPERTIES_NOT_COVERED, 'device.iscrawler')],
            $logger->messages
        );
    }

    public function testANameAResourceKeyAnswerLeavesOutIsReportedAsNotAProperty(): void
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => self::resourceKey,
            'requestedProperties' => ['device.ismobile', 'device.notaproperty'],
            'httpClient' => $this->recordingClient()
        ]);
        $logger = self::memoryLogger();
        $pipeline = (new PipelineBuilder())->add($engine)->addLogger($logger)->build();
        $data = $pipeline->createFlowData();
        $data->evidence->set('query.User-Agent', 'iPhone');
        $data->process();

        $this->assertCount(1, $logger->messages);
        $this->assertStringContainsString('device.notaproperty', $logger->messages[0]);
        $this->assertStringContainsString(
            'not properties the cloud service knows',
            $logger->messages[0]
        );
        $this->assertStringNotContainsString('keys in use', $logger->messages[0]);
    }

    public function testNothingIsLoggedWhenEveryPropertyCameBackOrNoneWasNamed(): void
    {
        foreach ([
            ['licenseKey' => self::licenseKey, 'requestedProperties' => ['device.IsMobile']],
            ['resourceKey' => self::resourceKey]
        ] as $settings) {
            $result = $this->process($settings);

            $this->assertSame([], $result['thrown']);
            $this->assertSame([], $result['logged']);
        }
    }
}
