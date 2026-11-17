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

use fiftyone\pipeline\devicedetection\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * The device detection required examples say "The example SHOULD
 * illustrate various techniques for obtaining "high entropy values" for
 * presentation to the origin server as well as to the 51degrees cloud
 * service."
 *
 * The client hints conversion example takes the result of the browser's
 * getHighEntropyValues() and gives it as the HTTP headers a browser sends
 * an origin server, and as the Evidence a request to the cloud service
 * carries, both as those headers and as the encoded result itself. It
 * needs no resource key and no network.
 */
class HighEntropyValuesExampleTests extends TestCase
{
    private const EXAMPLE = __DIR__ .
        '/../examples/ghev-http-conversion/ghev-to-http.php';

    // The headers the example's getHighEntropyValues() result gives, as the
    // example's README lists them.
    private const HEADERS = [
        'Sec-CH-UA' => '"Chromium";v="116", "Not)A;Brand";v="24", "Google Chrome";v="116"',
        'Sec-CH-UA-Arch' => '"x86"',
        'Sec-CH-UA-Bitness' => '"64"',
        'Sec-CH-UA-Full-Version-List' => '"Chromium";v="116.0.5845.187", "Not)A;Brand";v="24.0.0.0", "Google Chrome";v="116.0.5845.187"',
        'Sec-CH-UA-Mobile' => '?0',
        'Sec-CH-UA-Platform' => '"macOS"',
        'Sec-CH-UA-Platform-Version' => '"13.5.2"'
    ];

    public function testGivesTheHeadersForTheOriginServer(): void
    {
        $output = $this->runExample();

        foreach (self::HEADERS as $name => $value) {
            $this->assertStringContainsString("{$name}: {$value}\n", $output, $output);
        }
    }

    public function testGivesTheHeadersAsEvidenceForTheCloudService(): void
    {
        $output = $this->runExample();

        foreach (self::HEADERS as $name => $value) {
            $key = 'header.' . strtolower($name);
            $this->assertStringContainsString("{$key}: {$value}\n", $output, $output);
        }
    }

    public function testGivesTheEncodedValuesAsEvidenceForTheCloudService(): void
    {
        $output = $this->runExample();

        $found = preg_match(
            '/^query\.51D_GetHighEntropyValues: ([A-Za-z0-9+\/=]+)$/m',
            $output,
            $match
        );
        $this->assertSame(1, $found, $output);
        $values = json_decode((string) base64_decode($match[1], true), true);
        $this->assertIsArray($values, $output);
        $this->assertSame('macOS', $values['platform']);
        $this->assertSame('13.5.2', $values['platformVersion']);
        $this->assertSame(
            ['brand' => 'Google Chrome', 'version' => '116.0.5845.187'],
            $values['fullVersionList'][2]
        );
    }

    private function runExample(): string
    {
        $run = ExampleRun::run(self::EXAMPLE, [], ExampleRun::RESOURCE_KEY_VARIABLES);
        $this->assertSame(0, $run->status, $run->printed());
        $run->assertEndsWithFindOutMore();

        return str_replace("\r\n", "\n", $run->output);
    }
}
