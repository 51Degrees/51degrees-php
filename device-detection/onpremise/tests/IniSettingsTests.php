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

use fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils;
use PHPUnit\Framework\TestCase;

/**
 * The on or off php.ini settings are read the way PHP reads its own, so a
 * bare true or 1 is on. Each case starts its own PHP process with the
 * settings on the command line, because the engine reads them when PHP
 * starts.
 */
class IniSettingsTests extends TestCase
{
    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    // Detects a Chrome User-Agent with the engine built when PHP started,
    // and prints the match method and the first matched User-Agent as JSON.
    private const DETECT = <<<'PHP'
        $evidence = new EvidenceDeviceDetectionSwig();
        $evidence->set('header.user-agent', 'Mozilla/5.0 (Windows NT 10.0; ' .
            'Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) ' .
            'Chrome/98.0.4758.102 Safari/537.36');
        $results = FiftyOneDegreesHashEngine::engine_get()->process($evidence);
        $methods = ['NONE', 'PERFORMANCE', 'COMBINED', 'PREDICTIVE'];
        echo json_encode([
            'method' => $methods[$results->getMethod()],
            'matched' => $results->getUserAgent(0),
        ]);
        PHP;

    public static function setUpBeforeClass(): void
    {
        ExampleUtils::downloadMissingDataFile(self::LITE_DATA_FILE);
    }

    /**
     * A bare true turns the performance graph on. Read as off, the engine
     * would have neither graph and PHP would stop when it starts.
     */
    public function testABareTrueIsOn()
    {
        $detected = $this->detect([
            'FiftyOneDegreesHashEngine.use_predictive_graph' => 'off',
            'FiftyOneDegreesHashEngine.use_performance_graph' => 'true',
        ]);
        $this->assertSame('PERFORMANCE', $detected['method']);
    }

    /**
     * 1 records the matched User-Agent characters, and off records none.
     */
    public function testOneIsOnAndOffIsOff()
    {
        $on = $this->detect([
            'FiftyOneDegreesHashEngine.update_matched_useragent' => '1',
        ]);
        $this->assertMatchesRegularExpression('/[A-Za-z]/', $on['matched']);

        $off = $this->detect([
            'FiftyOneDegreesHashEngine.update_matched_useragent' => 'off',
        ]);
        $this->assertSame('', $off['matched']);
    }

    /**
     * Starts PHP with the Lite data file and the given settings, detects
     * one User-Agent, and returns the match method and matched User-Agent.
     *
     * @param array<string, string> $settings
     * @return array<string, string>
     */
    private function detect(array $settings): array
    {
        $dataFile = realpath(self::LITE_DATA_FILE);
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $settings['FiftyOneDegreesHashEngine.data_file'] = $dataFile;
        $command = escapeshellarg(PHP_BINARY);
        foreach ($settings as $name => $value) {
            $command .= ' -d ' . escapeshellarg("{$name}={$value}");
        }
        $command .= ' -r ' . escapeshellarg(self::DETECT) . ' 2>&1';
        exec($command, $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);
        $detected = json_decode($printed, true);
        $this->assertIsArray($detected, $printed);

        return $detected;
    }
}
