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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\JavascriptBuilderElement;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the errors the pipeline logs, so the tests can read them.
 */
class CallbackUrlTestLogger extends Logger
{
    public array $errors = [];

    public function logInternal(array $log): void
    {
        if ($log['level'] === 'error') {
            $this->errors[] = $log['message'];
        }
    }
}

/**
 * Stands in for device detection, offering only the one property given.
 */
class ScriptDeviceElement extends FlowElement
{
    public string $dataKey = 'device';

    public array $properties = [];

    private $name;
    private $value;

    public function __construct(string $name, $value)
    {
        $this->name = $name;
        $this->value = $value;
        $this->properties = [$name => ['type' => 'string']];
        parent::__construct();
    }

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            $this->name => new AspectPropertyValue(null, $this->value)
        ]));
    }
}

/**
 * Every builder is expected to render the same script from the same
 * evidence, and the .NET builder is the reference. These tests pin the
 * places where this builder used to differ from it, being which evidence
 * becomes a parameter, how a parameter is written, the callback URL, the
 * choice between fetch and XMLHttpRequest and whether a promise is created.
 */
class JavaScriptBuilderParametersTests extends TestCase
{
    public function testOnlyQueryEvidenceBecomesAParameter()
    {
        $script = $this->render([
            'header.host' => 'example.com',
            'header.protocol' => 'https',
            'query.mark' => 'kept'
        ]);

        $this->assertSame(
            '{"mark":"kept"}',
            $this->parameters($script)
        );
    }

    /**
     * A key such as 'query.id.usage' keeps everything after the prefix. The
     * usage key a 51Did depends on is one of these, and cutting it at the
     * second dot sent it as 'id'.
     */
    public function testParameterKeyKeepsEverythingAfterThePrefix()
    {
        $script = $this->render([
            'header.host' => 'example.com',
            'query.id.usage' => 'personalized'
        ]);

        $this->assertSame(
            '{"id.usage":"personalized"}',
            $this->parameters($script)
        );
    }

    /**
     * The script puts the parameters into the request body as they are, so
     * they are rendered already encoded, with a space as '+' and the
     * characters '!', '*', '(' and ')' left alone, as the .NET builder does.
     */
    public function testParametersAreRenderedEncoded()
    {
        $script = $this->render([
            'header.host' => 'example.com',
            'query.mark' => 'a b&c=d!*()~',
            'query.odd key' => '1'
        ]);

        $this->assertSame(
            '{"mark":"a+b%26c%3Dd!*()%7E","odd+key":"1"}',
            $this->parameters($script)
        );
    }

    public function testNoParametersRendersAnEmptyObject()
    {
        $script = $this->render(['header.host' => 'example.com']);

        $this->assertSame('{}', $this->parameters($script));
    }

    /**
     * Evidence keys ignore letter case, and the evidence is handed back with
     * each key in the letters it was first set with, so the session id and
     * the sequence are left out of the parameters whatever those letters
     * are. Both already travel in the request body as their own values, so a
     * spelling that slipped past sent each of them a second time and the
     * session id changing on every page view stopped the record of a
     * request's inputs ever matching.
     */
    public function testSessionIdAndSequenceAreLeftOutInAnyLetterCase()
    {
        $spellings = [
            ['Query.Session-ID', 'Query.Sequence'],
            ['QUERY.SESSION-ID', 'QUERY.SEQUENCE'],
            ['query.session-id', 'query.sequence'],
        ];

        foreach ($spellings as [$sessionId, $sequence]) {
            $script = $this->render([
                'header.host' => 'example.com',
                $sessionId => 'abc',
                $sequence => '2',
                'query.mark' => 'kept'
            ]);

            $this->assertSame(
                '{"mark":"kept"}',
                $this->parameters($script),
                $sessionId
            );
        }
    }

    /**
     * The prefix is matched without regard to letter case already, so a
     * query key in other letters still becomes a parameter.
     */
    public function testQueryEvidenceInAnyLetterCaseBecomesAParameter()
    {
        $script = $this->render([
            'header.host' => 'example.com',
            'Query.Mark' => 'kept'
        ]);

        $this->assertSame('{"Mark":"kept"}', $this->parameters($script));
    }

    /**
     * The parameters, the session id and the sequence all travel in the
     * request body, so the callback URL carries no query string of its own.
     * A web request's query string wins over its body when the evidence is
     * read back, so values rendered into the URL used to override the newer
     * values the script put in the body.
     */
    public function testCallbackUrlCarriesNoQueryString()
    {
        $script = $this->render([
            'header.host' => 'example.com',
            'query.mark' => 'kept'
        ]);

        $this->assertStringContainsString(
            "createCORSRequest('POST', 'https://example.com/json')",
            $script
        );
    }

    public function testCallbackUrlHasOneSlashBetweenHostAndEndpoint()
    {
        $noSlash = $this->render(['header.host' => 'example.com'], 'json');
        $twoSlashes = $this->render(['header.host' => 'example.com/'], '/json');

        $this->assertStringContainsString(
            "'https://example.com/json'",
            $noSlash
        );
        $this->assertStringContainsString(
            "'https://example.com/json'",
            $twoSlashes
        );
    }

    public function testFetchIsUsedWhereTheDeviceSupportsIt()
    {
        $script = $this->render(
            ['header.host' => 'example.com'],
            '/json',
            new ScriptDeviceElement('fetch', true)
        );

        $this->assertStringContainsString(
            "fetch('https://example.com/json'",
            $script
        );
        $this->assertStringNotContainsString('createCORSRequest', $script);
    }

    public function testXmlHttpRequestIsUsedWhereTheDeviceDoesNot()
    {
        $without = $this->render(['header.host' => 'example.com']);
        $unsupported = $this->render(
            ['header.host' => 'example.com'],
            '/json',
            new ScriptDeviceElement('fetch', false)
        );

        foreach ([$without, $unsupported] as $script) {
            $this->assertStringContainsString(
                "createCORSRequest('POST', 'https://example.com/json')",
                $script
            );
            $this->assertStringNotContainsString("fetch('", $script);
        }
    }

    /**
     * The script creates a promise only where device detection says the
     * browser fully supports them, which the .NET builder reads as the
     * 'promise' property being 'Full'.
     */
    public function testPromiseIsCreatedWhereTheDeviceFullySupportsThem()
    {
        $script = $this->render(
            ['header.host' => 'example.com'],
            '/json',
            new ScriptDeviceElement('promise', 'Full')
        );

        $this->assertStringContainsString(
            'this.promise = new Promise(',
            $script
        );
    }

    public function testNoPromiseWhereTheDeviceDoesNotFullySupportThem()
    {
        $scripts = [
            $this->render(['header.host' => 'example.com']),
            $this->render(
                ['header.host' => 'example.com'],
                '/json',
                new ScriptDeviceElement('promise', 'None')
            ),
            $this->render(
                ['header.host' => 'example.com'],
                '/json',
                new ScriptDeviceElement('promise', 'Polyfill')
            )
        ];

        foreach ($scripts as $script) {
            $this->assertStringNotContainsString(
                'this.promise = new Promise(',
                $script
            );
        }
    }

    /**
     * A host the check refuses gives a script with no callback, and the
     * refusal is logged as an error the first time only, whatever the
     * requests in between.
     */
    public function testRefusedUrlIsLeftOutAndLoggedOnce()
    {
        [$scripts, $errors] = $this->renderWithHosts(
            ['exa mple.com', 'exa mple.com', 'example.com', 'exa mple.com']
        );

        foreach ($scripts as $i => $script) {
            $this->assertStringContainsString(
                'new fiftyoneDegreesManager()',
                $script
            );
            if ($i === 2) {
                $this->assertContains(
                    'https://example.com/json',
                    $this->requestUrls($script)
                );
            } else {
                $this->assertSame([], $this->requestUrls($script));
                $this->assertStringNotContainsString('exa mple.com', $script);
            }
        }
        $this->assertCount(1, $errors, json_encode($errors));
        $this->assertStringContainsString(
            '"https://exa mple.com/json"',
            $errors[0]
        );
    }

    public static function provider_loggedHosts(): array
    {
        return [
            ['user:secret@example.com', 'https://***@example.com/json'],
            ['a@b@example.com', 'https://***@example.com/json'],
            ['u@[::1]:80x', 'https://***@[::1]:80x/json'],
            ['user:pa/ss@example.com', 'https://***@example.com/json'],
            ['user:pa?ss@example.com', 'https://***@example.com/json'],
            ['exa mple.com/a@b', 'https://***@b/json']
        ];
    }

    /**
     * The log never holds what comes before the last '@' of the host value.
     * Each host has an element of its own because a refusal is logged once
     * per element.
     *
     * @dataProvider provider_loggedHosts
     */
    #[DataProvider('provider_loggedHosts')]
    public function testRefusedHostIsLoggedWithoutItsUserInformation(
        string $host,
        string $logged
    ) {
        [$scripts, $errors] = $this->renderWithHosts([$host]);

        $this->assertSame([], $this->requestUrls($scripts[0]));
        $this->assertCount(1, $errors, json_encode($errors));
        $this->assertStringContainsString('"' . $logged . '"', $errors[0]);
        $this->assertStringNotContainsString('secret', $errors[0]);
        $this->assertDoesNotMatchRegularExpression('/\\bpa\\b/', $errors[0]);
    }

    /**
     * A host from the settings is refused and logged the same way.
     */
    public function testRefusedConfiguredHostIsLoggedWithoutUserInformation()
    {
        $logger = new CallbackUrlTestLogger('error');
        $pipeline = (new PipelineBuilder([
            'javascriptBuilderSettings' => [
                'host' => 'user:secret@example.com',
                'endpoint' => '/json',
                'minify' => false
            ]
        ]))->addLogger($logger)->build();
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame(
            [],
            $this->requestUrls($flowData->javascriptbuilder->javascript)
        );
        $this->assertCount(1, $logger->errors, json_encode($logger->errors));
        $this->assertStringContainsString(
            '"https://***@example.com/json"',
            $logger->errors[0]
        );
        $this->assertStringNotContainsString('secret', $logger->errors[0]);
    }

    /**
     * The URL is quoted and escaped in the log, so a line break in it cannot
     * start a new log line.
     */
    public function testRefusedUrlIsLoggedOnOneLine()
    {
        [, $errors] = $this->renderWithHosts(["a\nb.com\r\u{2028}"]);

        $this->assertCount(1, $errors, json_encode($errors));
        foreach (["\n", "\r", "\u{2028}"] as $c) {
            $this->assertStringNotContainsString($c, $errors[0]);
        }
        $this->assertStringContainsString('a\nb.com\r\u2028', $errors[0]);
    }

    /**
     * A protocol from the request that is neither http nor https gives a
     * script with no callback, and is logged once like any other URL that is
     * not valid.
     */
    public function testRefusedProtocolFromTheRequestIsLeftOutAndLoggedOnce()
    {
        $logger = new CallbackUrlTestLogger('error');
        $pipeline = (new PipelineBuilder([
            'javascriptBuilderSettings' => [
                'endpoint' => '/json',
                'minify' => false
            ]
        ]))->addLogger($logger)->build();
        foreach ([1, 2] as $request) {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('header.host', 'example.com');
            $flowData->evidence->set('header.protocol', 'ftp');
            $flowData->process();
            $this->assertSame(
                [],
                $this->requestUrls($flowData->javascriptbuilder->javascript),
                'request ' . $request
            );
        }
        $this->assertCount(1, $logger->errors, json_encode($logger->errors));
        $this->assertStringContainsString(
            '"ftp://example.com/json"',
            $logger->errors[0]
        );
    }

    /**
     * A protocol in the settings is used for every request, so one the
     * script cannot send its request with is refused when the builder is
     * created.
     */
    public function testConfiguredProtocolOtherThanHttpOrHttpsIsRefused()
    {
        $refused = ['ftp', 'ws', 'javascript', 'https ', "http\u{017F}"];
        foreach ($refused as $protocol) {
            try {
                new JavascriptBuilderElement(['protocol' => $protocol]);
                $this->fail(json_encode($protocol) . ' was accepted');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('protocol', $e->getMessage());
            }
        }
    }

    public function testConfiguredHttpOrHttpsIsAccepted()
    {
        foreach (['http', 'https', 'HTTPS'] as $protocol) {
            $element = new JavascriptBuilderElement(['protocol' => $protocol]);
            $this->assertSame($protocol, $element->settings['_protocol']);
        }
    }

    /**
     * A host made only of whitespace is not set, so the script has no
     * callback and nothing is logged.
     */
    public function testBlankHostGivesNoCallbackAndIsNotLogged()
    {
        [$scripts, $errors] = $this->renderWithHosts(['  ']);

        $this->assertSame([], $this->requestUrls($scripts[0]));
        $this->assertSame([], $errors);
    }

    /**
     * A host from the request reaches the script as the check writes it, in
     * lower case and with anything that could end the string the script
     * holds it in percent-encoded.
     */
    public function testHostFromTheRequestIsWrittenAsChecked()
    {
        [$scripts] = $this->renderWithHosts(["EXAMPLE.com/');alert(1);//"]);

        $urls = $this->requestUrls($scripts[0]);
        $this->assertNotEmpty($urls);
        foreach ($urls as $url) {
            $this->assertSame('https://example.com/%27);alert(1);//json', $url);
        }
        $this->assertStringNotContainsString("');alert(1)", $scripts[0]);
    }

    /**
     * Renders one script for each host given, all through the same element,
     * and returns them with the errors the pipeline logged.
     */
    private function renderWithHosts(array $hosts): array
    {
        $logger = new CallbackUrlTestLogger('error');
        $pipeline = (new PipelineBuilder([
            'javascriptBuilderSettings' => [
                'endpoint' => '/json',
                'minify' => false
            ]
        ]))->addLogger($logger)->build();
        $scripts = [];
        foreach ($hosts as $host) {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('header.host', $host);
            $flowData->process();
            $scripts[] = $flowData->javascriptbuilder->javascript;
        }

        return [$scripts, $logger->errors];
    }

    /**
     * The URLs the script sends its request to, from the fetch and the
     * XMLHttpRequest calls.
     */
    private function requestUrls(string $script): array
    {
        preg_match_all(
            "/(?:fetch\\(|createCORSRequest\\('POST', )'([^']*)'/",
            $script,
            $matches
        );

        return $matches[1];
    }

    private function render(
        array $evidence,
        string $endpoint = '/json',
        ?FlowElement $element = null
    ): string {
        $builder = new PipelineBuilder([
            'javascriptBuilderSettings' => [
                'endpoint' => $endpoint,
                'minify' => false
            ]
        ]);
        if ($element !== null) {
            $builder->add($element);
        }
        $flowData = $builder->build()->createFlowData();
        foreach ($evidence as $key => $value) {
            $flowData->evidence->set($key, $value);
        }
        $flowData->process();

        return $flowData->javascriptbuilder->javascript;
    }

    /**
     * The JSON the script is configured with, read from its one line.
     */
    private function parameters(string $script): string
    {
        $found = preg_match(
            '/var renderedParameters = function\(\) \{ return (.*); \};/',
            $script,
            $matches
        );
        $this->assertSame(1, $found, 'No parameters line in the script');

        return $matches[1];
    }
}
