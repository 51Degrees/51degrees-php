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
use fiftyone\pipeline\devicedetection\tests\classes\Constants;
use fiftyone\pipeline\devicedetection\tests\classes\Process;
use fiftyone\pipeline\devicedetection\tests\classes\ResourceKeys;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystem;
use PHPUnit\Framework\TestCase;

/**
 * The Accept-CH header a page sets for each browser.
 *
 * With the resource key, the User Agent Client Hints example asks for every
 * property the key has, so Chrome is asked for every client hint. With the
 * license key, a page asks the cloud for one property, so Chrome is asked
 * only for the client hints of that property's component, and for none when
 * the property needs none. Each test skips, naming the variable, when its
 * key is not set.
 *
 * @requires OS Linux
 */
#[RequiresOperatingSystem('Linux')]
class UACHCloudTests extends TestCase
{
    /** @var Process The User Agent Client Hints example */
    public static $process;

    /** @var null|Process The page that asks for one property */
    public static $licenseProcess;

    public static function setUpBeforeClass(): void
    {
        self::$process = self::start(
            'php -S localhost:5151 examples/cloud/userAgentClientHints-Web.php',
            'User Agent Client Hints-Cloud Web example'
        );
        if (ResourceKeys::find(Constants::LICENSE_ENV_VAR) !== null) {
            self::$licenseProcess = self::start(
                'php -S localhost:3001 tests/accept-ch-license-server.php',
                'Accept-CH license key page'
            );
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::$process, self::$licenseProcess] as $process) {
            if ($process !== null && $process->stop()) {
                echo "\nProcess stopped. \n";
            }
        }
        self::$licenseProcess = null;
    }

    /**
     * Data Provider for testAcceptCH. Each row is the browser, the variable
     * holding the key it is asked with, the property asked for with the
     * license key, the client hints that must be in Accept-CH and those that
     * must not.
     */
    public static function provider_testAcceptCH()
    {
        // TODO - Edge removed from test until cloud has been updated with new data file.
        $userAgents = [
            'Chrome' => Constants::CHROME_UA,
            /* 'Edge' => Constants::EDGE_UA, */
            'Firefox' => Constants::FIREFOX_UA,
            'Safari' => Constants::SAFARI_UA,
            'curl' => Constants::CURL_UA
        ];

        // Each property asked for with the license key, the client hints
        // Chrome must be asked for, and the ones of the other components it
        // must not be asked for.
        $properties = [
            Constants::BROWSER_PROPERTY => [
                Constants::BROWSER_ACCEPT_CH,
                ['Sec-CH-UA-Model', 'Sec-CH-UA-Platform-Version']
            ],
            Constants::HARDWARE_PROPERTY => [
                Constants::HARDWARE_ACCEPT_CH,
                ['Sec-CH-UA', 'Sec-CH-UA-Platform-Version']
            ],
            Constants::PLATFORM_PROPERTY => [
                Constants::PLATFORM_ACCEPT_CH,
                ['Sec-CH-UA', 'Sec-CH-UA-Model']
            ],
            Constants::NO_ACCEPTCH_PROPERTY => [Constants::EMPTY_ACCEPT_CH, []]
        ];

        $testParameters = [];
        foreach ($userAgents as $browser => $ua) {
            $chrome = $ua == Constants::CHROME_UA || $ua == Constants::EDGE_UA;
            $testParameters["$browser, every property"] = [
                $ua,
                Constants::RESOURCE_ENV_VAR,
                null,
                $chrome ? Constants::SUPER_ACCEPT_CH : Constants::EMPTY_ACCEPT_CH,
                []
            ];
            foreach ($properties as $property => [$expected, $absent]) {
                $testParameters["$browser, $property"] = [
                    $ua,
                    Constants::LICENSE_ENV_VAR,
                    $property,
                    $chrome ? $expected : Constants::EMPTY_ACCEPT_CH,
                    $chrome ? $absent : []
                ];
            }
        }

        return $testParameters;
    }

    /**
     * Tests response header value to set in Accept-CH response header.
     *
     * @dataProvider provider_testAcceptCH
     * @param string $userAgent
     * @param string $keyVariable The variable holding the key
     * @param null|string $property The property asked for with the license
     * key, or null for every property of the resource key
     * @param array<string> $expectedValue
     * @param array<string> $absentValue
     */
    #[DataProvider('provider_testAcceptCH')]
    public function testAcceptCH($userAgent, $keyVariable, $property, $expectedValue, $absentValue)
    {
        $key = ResourceKeys::find($keyVariable);
        if ($key === null) {
            $this->markTestSkipped(ResourceKeys::missingMessage($keyVariable));
        }

        $requestHeaders = Constants::UA_HEADER . $userAgent . "\r\n";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $requestHeaders,
                'ignore_errors' => true
            ]
        ]);

        $url = $property === null
            ? Constants::URL . '?' . ExampleUtils::RESOURCE_KEY_QUERY_PARAM . '=' . urlencode($key)
            : Constants::LICENSE_URL . '?' . Constants::PROPERTY_QUERY_PARAM . '=' . urlencode($property);
        $body = @file_get_contents($url, false, $context);
        $responseHeaders = self::parseHeaders($http_response_header ?? []);

        $this->assertSame(200, $responseHeaders['response_code'] ?? null, (string) $body);

        if (is_null($expectedValue) || count($expectedValue) == 0) {
            $this->assertArrayNotHasKey('Accept-CH', $responseHeaders);
        } else {
            $this->assertArrayHasKey('Accept-CH', $responseHeaders);

            // We don't require the expected list of values to match exactly, as the headers
            // used by detection change over time. However, we do make sure that the most
            // critical ones are present in Accept-CH, and that a property asked for alone
            // asks for none of the client hints of the other components.
            $actualValue = array_map('strtolower', array_map('trim',
                explode(',', $responseHeaders['Accept-CH'])));
            foreach ($expectedValue as $value) {
                $this->assertContains(strtolower($value), $actualValue);
            }
            foreach ($absentValue as $value) {
                $this->assertNotContains(strtolower($value), $actualValue);
            }
        }
    }

    /**
     * Starts a page with the PHP built-in server.
     */
    private static function start(string $command, string $name): Process
    {
        $process = new Process($command);
        $process->start();
        sleep(5);
        if ($process->status()) {
            echo "$name has started running.\n";
        } else {
            throw new \Exception("Could not start the $name. \n");
        }

        return $process;
    }

    /**
     * Converts response headers string to an indexed array.
     *
     * @param array $headers
     * @return array
     */
    private static function parseHeaders($headers)
    {
        $head = [];
        foreach ($headers as $k => $v) {
            $t = explode(':', $v, 2);
            if (isset($t[1])) {
                $head[trim($t[0])] = trim($t[1]);
            } else {
                $head[] = $v;
                if (preg_match('#HTTP/[0-9\\.]+\\s+([0-9]+)#', $v, $out)) {
                    $head['response_code'] = intval($out[1]);
                }
            }
        }

        return $head;
    }
}
