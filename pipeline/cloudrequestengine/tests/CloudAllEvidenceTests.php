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
use fiftyone\pipeline\cloudrequestengine\Constants;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\Evidence;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The Engine sends every Evidence value in the Flow Data apart from the
 * reserved names and the web request view, as the Processing section of the
 * cloud request engine specification requires.
 *
 * Each test builds a pipeline whose only element is the Engine, so the
 * Engine's own evidence key filter is the only filter the Evidence is
 * offered to. That filter is a list of the keys the cloud service's
 * evidencekeys answer gives, and it names query.User-Agent alone here, so a
 * key the service does not list has nowhere else to be accepted.
 */
class CloudAllEvidenceTests extends TestCase
{
    public const resourceKey = 'AAAAAAAAAAAA';

    /**
     * The one key the cloud service's evidencekeys answer lists below. Every
     * other key a test sets is one the service has never heard of.
     */
    public const acceptedKey = 'query.User-Agent';

    /** @var array<array{string, string, null|string}> */
    private array $requests = [];

    /**
     * Keys the service does not list, one under each of the three prefixes
     * the Engine orders its request by, so none of them is accepted by the
     * Engine's own filter.
     */
    private const UNLISTED = [
        'query.my-own-thing' => 'alpha',
        'header.sec-ch-ua-platform' => 'Windows',
        'cookie.51D_experiment' => 'beta',
    ];

    /**
     * Sends every Evidence value the cloud service does not list, under each
     * of the three prefixes, which is the behaviour decision on cloud
     * Evidence.
     */
    public function testEvidenceTheServiceDoesNotListIsStillSent(): void
    {
        $fields = $this->sentFields(self::UNLISTED);

        $this->assertSame('alpha', $fields['my-own-thing'] ?? null);
        $this->assertSame('Windows', $fields['sec-ch-ua-platform'] ?? null);
        $this->assertSame('beta', $fields['51d_experiment'] ?? null);
    }

    /**
     * The accepted Evidence is still sent, so reading the unaccepted
     * Evidence has not displaced it.
     */
    public function testEvidenceTheServiceListsIsStillSent(): void
    {
        $fields = $this->sentFields(self::UNLISTED + [self::acceptedKey => 'iPhone']);

        $this->assertSame('iPhone', $fields['user-agent'] ?? null);
    }

    /**
     * The reserved names are left out under every prefix and in any letter
     * case, so that none of them puts a second field of the same name, or a
     * second spelling of a credential, in the request.
     */
    public function testTheReservedNamesAreNeverSentFromEvidence(): void
    {
        $fields = $this->sentFields([
            'query.resource' => 'not-the-resource-key',
            'header.License' => 'not-the-license-key',
            'cookie.LICENCE' => 'not-the-license-key-either',
            'query.values' => 'not-the-property-list',
            'resource' => 'bare-and-still-reserved',
            'VALUES' => 'bare-upper-and-still-reserved',
        ]);

        $this->assertSame(self::resourceKey, $fields['resource'] ?? null);
        $this->assertArrayNotHasKey('license', $fields);
        $this->assertArrayNotHasKey('licence', $fields);
        $this->assertArrayNotHasKey('values', $fields);
    }

    /**
     * The web request view a web integration adds under server.http-request
     * is a view of the whole request and not a value to send, so it is left
     * out of the request.
     */
    public function testTheWebRequestViewIsNeverSent(): void
    {
        $engine = new CloudRequestEngine([
            'httpClient' => $this->recordingClient(),
            'resourceKey' => self::resourceKey,
        ]);
        $pipeline = (new PipelineBuilder())->add($engine)->build();
        $data = $pipeline->createFlowData();

        // Fills the Evidence the way a web integration does, which is the
        // only route that creates the web request view.
        $data->evidence->setFromWebRequest(
            [
                'HTTP_USER_AGENT' => 'iPhone',
                'HTTP_SEC_CH_UA_PLATFORM' => 'Windows',
                'REMOTE_ADDR' => '1.2.3.4',
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/page',
            ],
            [],
            []
        );
        $data->process();

        $fields = $this->dataRequestFields();

        // The view itself is never a field, under its own name or the name
        // the prefix strips it to.
        $this->assertArrayNotHasKey('http-request', $fields);
        $this->assertArrayNotHasKey(Evidence::HTTP_REQUEST_KEY, $fields);
        foreach ($fields as $value) {
            $this->assertIsNotObject($value);
        }

        // The header the service does not list is sent all the same, so the
        // view being left out has not taken the headers with it.
        $this->assertSame('Windows', $fields['sec-ch-ua-platform'] ?? null);
    }

    /**
     * Processes one request through a cloud-only pipeline with the Evidence
     * given, and returns the fields of the data request, with the fields the
     * Sequence Element sets on every request removed.
     *
     * @param array<string, int|string> $evidence
     * @return array<string, string>
     */
    private function sentFields(array $evidence): array
    {
        $engine = new CloudRequestEngine([
            'httpClient' => $this->recordingClient(),
            'resourceKey' => self::resourceKey,
        ]);
        $pipeline = (new PipelineBuilder())->add($engine)->build();
        $data = $pipeline->createFlowData();
        foreach ($evidence as $key => $value) {
            $data->evidence->set($key, $value);
        }
        $data->process();

        return $this->dataRequestFields();
    }

    /**
     * The fields of the last request recorded, which is the data request,
     * read back from the form encoded body.
     *
     * @return array<string, string>
     */
    private function dataRequestFields(): array
    {
        $this->assertNotSame([], $this->requests, 'No request was made');
        $content = $this->requests[count($this->requests) - 1][2];
        $fields = [];
        parse_str((string) $content, $fields);
        // The Sequence Element the builder adds sets these on every request.
        unset($fields['session-id'], $fields['sequence']);

        return $fields;
    }

    /**
     * A client that records every request as [method, url, body] and answers
     * each endpoint with canned data. The evidencekeys answer lists one key
     * alone, so every other key a test sets is one the service does not
     * list.
     */
    private function recordingClient(): HttpClient
    {
        $this->requests = [];
        $client = $this->createStub(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function (string $type, string $url, ?string $content): string {
                $this->requests[] = [$type, $url, $content];
                $lower = strtolower($url);
                if (strpos($lower, 'accessibleproperties') !== false) {
                    return '{"Products": {"device": {"Properties": [' .
                        '{"Name": "IsMobile", "Type": "Boolean"}]}}}';
                }
                if (strpos($lower, 'evidencekeys') !== false) {
                    return '["' . self::acceptedKey . '"]';
                }

                return '{"device":{"ismobile":true}}';
            }
        );

        return $client;
    }
}
