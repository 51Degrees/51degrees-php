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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\ListedEvidenceElement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The web integration adds the request line to Evidence exactly as it was
 * sent, under server.request-method, server.request-path and
 * server.request-query. It adds all three together, and only when an
 * element in the Pipeline accepts them.
 */
class RequestLineEvidenceTests extends TestCase
{
    private const KEYS = [
        'server.request-method',
        'server.request-path',
        'server.request-query',
    ];

    /**
     * A Flow Data from a Pipeline whose one element accepts the given keys,
     * with Evidence read from a request described by $server.
     *
     * @param array<string> $accepted
     * @param array<string, string> $server
     */
    private static function flowData(array $accepted, array $server): FlowData
    {
        $flowData = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->add(new ListedEvidenceElement($accepted))
            ->build()
            ->createFlowData();
        $flowData->evidence->setFromWebRequest($server, [], []);

        return $flowData;
    }

    public static function provider_requestLine(): array
    {
        return [
            'query string' => [
                'GET',
                '/page?x=1&y=2',
                ['GET', '/page', 'x=1&y=2']
            ],
            'no query string' => ['GET', '/page', ['GET', '/page', '']],
            'empty query string' => ['GET', '/page?', ['GET', '/page', '']],
            'root' => ['HEAD', '/', ['HEAD', '/', '']],
            'encoded path and query' => [
                'POST',
                '/a%20b/%7Euser/caf%C3%A9?q=a%2Bb%20c+d&r=%3D&s=%2f',
                [
                    'POST',
                    '/a%20b/%7Euser/caf%C3%A9',
                    'q=a%2Bb%20c+d&r=%3D&s=%2f'
                ]
            ],
            'a question mark in the query' => [
                'GET',
                '/p?a=?b&c',
                ['GET', '/p', 'a=?b&c']
            ],
        ];
    }

    /**
     * The three values are the request line byte for byte, with no
     * decoding, and the query is empty when the request has none.
     * @dataProvider provider_requestLine
     */
    #[DataProvider('provider_requestLine')]
    public function testTheRequestLineIsAddedAsSent(
        string $method,
        string $target,
        array $expected
    ) {
        $flowData = self::flowData(self::KEYS, [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $target,
        ]);

        $found = [];
        foreach (self::KEYS as $key) {
            $found[$key] = $flowData->evidence->get($key);
        }
        $this->assertSame(array_combine(self::KEYS, $expected), $found);
    }

    /**
     * An element that asks for one of the three gets all three, and keys
     * are matched in any letters.
     */
    public function testOneAcceptedKeyBringsAllThree()
    {
        $flowData = self::flowData(['Server.Request-Path'], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/page?x=1',
        ]);

        $this->assertSame('/page', $flowData->evidence->get('server.request-path'));
        $this->assertSame(
            ['GET', '/page', 'x=1'],
            array_map(
                fn ($key) => $flowData->evidence->getAllIncludingUnaccepted()[$key] ?? null,
                self::KEYS
            )
        );
    }

    /**
     * A Pipeline whose elements do not ask for the request line carries
     * none of it, not even among the values no element accepts.
     */
    public function testAPipelineThatDoesNotAskCarriesNone()
    {
        $flowData = self::flowData(['header.user-agent'], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/page?x=1',
            'HTTP_USER_AGENT' => 'agent',
        ]);

        $all = $flowData->evidence->getAllIncludingUnaccepted();
        $this->assertSame('agent', $all['header.user-agent'] ?? null);
        foreach (self::KEYS as $key) {
            $this->assertArrayNotHasKey($key, $all);
        }
    }

    /**
     * Without a request target, as when PHP runs from the command line,
     * none of the three is added, because the three go together.
     */
    public function testNoneWithoutARequestTarget()
    {
        $flowData = self::flowData(self::KEYS, ['REQUEST_METHOD' => 'GET']);

        $all = $flowData->evidence->getAllIncludingUnaccepted();
        foreach (self::KEYS as $key) {
            $this->assertArrayNotHasKey($key, $all);
        }
    }
}
