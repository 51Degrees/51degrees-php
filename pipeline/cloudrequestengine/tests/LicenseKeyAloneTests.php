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
use fiftyone\pipeline\cloudrequestengine\Constants;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The combinations of resource key, license key and requested properties in
 * the Credentials section of the cloud request engine specification, and
 * where each credential travels in the requests the engine makes.
 */
class LicenseKeyAloneTests extends TestCase
{
    public const resourceKey = 'AAAAAAAAAAAA';
    public const licenseKey = 'LICENSEKEY1234567890';

    /** @var array<array{string, string, null|string}> */
    private array $requests = [];

    /**
     * A client that records every request as [method, url, body] and
     * answers each endpoint with canned data.
     */
    private function recordingClient(): HttpClient
    {
        $this->requests = [];
        $client = $this->createMock(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function (string $type, string $url, ?string $content): string {
                $this->requests[] = [$type, $url, $content];
                $lower = strtolower($url);
                if (strpos($lower, 'accessibleproperties') !== false) {
                    return '{"Products": {"device": {"Properties": [' .
                        '{"Name": "IsMobile", "Type": "Boolean"}]}}}';
                }
                if (strpos($lower, 'evidencekeys') !== false) {
                    return '["query.User-Agent"]';
                }

                return '{"device":{"ismobile":true}}';
            }
        );

        return $client;
    }

    /**
     * Builds the engine, processes one request through a pipeline holding
     * it, and gives back the requests made, as [method, url, fields].
     *
     * @param array<string, mixed> $settings
     * @return array<array{string, string, array<string, string>}>
     */
    private function process(array $settings): array
    {
        $engine = new CloudRequestEngine(array_merge(
            ['httpClient' => $this->recordingClient()],
            $settings
        ));
        $data = (new PipelineBuilder())->add($engine)->build()->createFlowData();
        $data->evidence->set('query.User-Agent', 'iPhone');
        $data->process();

        $requests = [];
        foreach ($this->requests as [$type, $url, $content]) {
            $fields = [];
            parse_str((string) $content, $fields);
            $requests[] = [$type, $url, $fields];
        }

        return $requests;
    }

    /**
     * @param array<array{string, string, array<string, string>}> $requests
     * @return array{string, string, array<string, string>}
     */
    private function propertiesRequest(array $requests): array
    {
        foreach ($requests as $request) {
            if (stripos($request[1], 'accessibleproperties') !== false) {
                return $request;
            }
        }
        $this->fail('No accessible properties request was made');
    }

    public function testResourceKeyAlone(): void
    {
        $requests = $this->process(['resourceKey' => self::resourceKey]);

        [$type, $url, $fields] = end($requests);
        $this->assertSame('POST', $type);
        $this->assertSame(Constants::BASE_URL_DEFAULT . self::resourceKey . '.json?', $url);
        $this->assertSame(self::resourceKey, $fields['resource']);
        $this->assertArrayNotHasKey('license', $fields);
        $this->assertArrayNotHasKey('values', $fields);
        $this->assertSame('iPhone', $fields['user-agent']);

        [$type, $url] = $this->propertiesRequest($requests);
        $this->assertSame('GET', $type);
        $this->assertSame(
            Constants::BASE_URL_DEFAULT . 'accessibleProperties?resource=' . self::resourceKey,
            $url
        );
    }

    public function testResourceKeyWithLicenseKeySendsBothInTheBody(): void
    {
        $requests = $this->process([
            'resourceKey' => self::resourceKey,
            'licenseKey' => self::licenseKey
        ]);

        [$type, , $fields] = $this->propertiesRequest($requests);
        $this->assertSame('POST', $type);
        $this->assertSame(self::resourceKey, $fields['resource']);
        $this->assertSame(self::licenseKey, $fields['license']);

        [, $url, $fields] = end($requests);
        $this->assertSame(Constants::BASE_URL_DEFAULT . self::resourceKey . '.json?', $url);
        $this->assertSame(self::resourceKey, $fields['resource']);
        $this->assertSame(self::licenseKey, $fields['license']);
        $this->assertArrayNotHasKey('values', $fields);
    }

    public function testLicenseKeyWithPropertiesSendsLicenseAndValuesOnly(): void
    {
        $requests = $this->process([
            'licenseKey' => self::licenseKey,
            'requestedProperties' => ['device.ismobile', 'device.istablet']
        ]);

        [$type, $url, $fields] = end($requests);
        $this->assertSame('POST', $type);
        $this->assertSame(Constants::BASE_URL_DEFAULT . 'json', $url);
        $this->assertArrayNotHasKey('resource', $fields);
        $this->assertSame(self::licenseKey, $fields['license']);
        $this->assertSame('device.ismobile,device.istablet', $fields['values']);
        $this->assertSame('iPhone', $fields['user-agent']);
    }

    public function testLicenseKeyAloneDiscoversPropertiesAndEvidenceKeys(): void
    {
        $requests = $this->process([
            'licenseKey' => self::licenseKey,
            'requestedProperties' => ['device.ismobile']
        ]);

        [$type, $url, $fields] = $this->propertiesRequest($requests);
        $this->assertSame('POST', $type);
        $this->assertSame(Constants::BASE_URL_DEFAULT . 'accessibleProperties', $url);
        $this->assertSame(['license' => self::licenseKey], $fields);

        $urls = array_column($requests, 1);
        $this->assertContains(Constants::BASE_URL_DEFAULT . 'evidencekeys', $urls);
    }

    public function testLicenseKeyNeverAppearsInAUrl(): void
    {
        foreach ([
            ['resourceKey' => self::resourceKey, 'licenseKey' => self::licenseKey],
            ['licenseKey' => self::licenseKey, 'requestedProperties' => ['device.ismobile']]
        ] as $settings) {
            foreach ($this->process($settings) as [, $url]) {
                $this->assertStringNotContainsString(self::licenseKey, $url);
            }
        }
    }

    public function testRequestedPropertiesMayBeOneCommaSeparatedString(): void
    {
        $engine = new CloudRequestEngine([
            'licenseKey' => self::licenseKey,
            'requestedProperties' => ' Device.IsMobile , device.IsTablet,',
            'httpClient' => $this->recordingClient()
        ]);

        $this->assertSame(['device.ismobile', 'device.istablet'], $engine->requestedProperties);
    }

    public function testWhitespaceCountsAsAbsent(): void
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => ' ' . self::resourceKey . "\n",
            'licenseKey' => '   ',
            'requestedProperties' => [' ', ''],
            'httpClient' => $this->recordingClient()
        ]);

        $this->assertSame(self::resourceKey, $engine->resourceKey);
        $this->assertSame('', $engine->licenseKey);
        $this->assertSame([], $engine->requestedProperties);
    }

    public function testLicenseKeyWithoutPropertiesIsRefused(): void
    {
        foreach ([[], ['requestedProperties' => []]] as $extra) {
            try {
                new CloudRequestEngine(array_merge(['licenseKey' => self::licenseKey], $extra));
                $this->fail('The engine should not build');
            } catch (\Exception $e) {
                $this->assertSame(Constants::MESSAGE_LICENSE_KEY_NEEDS_PROPERTIES, $e->getMessage());
            }
        }
        $this->assertStringContainsString('400', Constants::MESSAGE_LICENSE_KEY_NEEDS_PROPERTIES);
    }

    public function testPropertiesWithAResourceKeyAreRefused(): void
    {
        foreach ([null, self::licenseKey] as $licenseKey) {
            try {
                new CloudRequestEngine([
                    'resourceKey' => self::resourceKey,
                    'licenseKey' => $licenseKey,
                    'requestedProperties' => ['device.ismobile']
                ]);
                $this->fail('The engine should not build');
            } catch (\Exception $e) {
                $this->assertSame(Constants::MESSAGE_PROPERTIES_IGNORED_WITH_RESOURCE_KEY, $e->getMessage());
            }
        }
        $this->assertStringContainsString('ignores the list', Constants::MESSAGE_PROPERTIES_IGNORED_WITH_RESOURCE_KEY);
    }

    public function testNoCredentialIsRefused(): void
    {
        foreach ([[], ['requestedProperties' => ['device.ismobile']], ['licenseKey' => "\t", 'requestedProperties' => ['device.ismobile']]] as $settings) {
            try {
                new CloudRequestEngine($settings);
                $this->fail('The engine should not build');
            } catch (\Exception $e) {
                $this->assertSame(Constants::MESSAGE_NO_CREDENTIAL, $e->getMessage());
            }
        }
        $this->assertStringContainsString('401', Constants::MESSAGE_NO_CREDENTIAL);
    }
}
