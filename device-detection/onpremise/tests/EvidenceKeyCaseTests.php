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
 * Evidence keys ignore letter case, so a key in any letters gives the
 * engine the same Evidence as the key in lower case. Each case starts its
 * own PHP process with the Lite data file, as the engine is built when PHP
 * starts.
 */
class EvidenceKeyCaseTests extends TestCase
{
    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
        'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 ' .
        'Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like ' .
        'Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114';

    // Processes each named set of Evidence and prints, as JSON, the values
    // of a few properties for each, then the keys the engine accepts. A
    // property the data file does not have gives its exception's message.
    // The arguments are the autoloader and the sets as JSON.
    private const DETECT = <<<'PHP'
        require $argv[1];
        $engine = new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise();
        $pipeline = (new fiftyone\pipeline\core\PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
        $results = [];
        foreach (json_decode($argv[2], true) as $name => $evidence) {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->setArray($evidence);
            $flowData->process();
            $values = [];
            foreach (['deviceid', 'ismobile', 'platformname',
                'platformversion', 'browsername', 'browserversion'] as $property) {
                try {
                    $value = $flowData->device->get($property);
                    $values[$property] = $value->hasValue
                        ? $value->value
                        : $value->noValueMessage;
                } catch (Throwable $e) {
                    $values[$property] = $e->getMessage();
                }
            }
            $results[$name] = $values;
            $flowData->close();
        }
        $results['keys'] = $engine->getEvidenceKeyFilter()->getList();
        $pipeline->close();
        echo json_encode($results);
        PHP;

    public static function setUpBeforeClass(): void
    {
        ExampleUtils::downloadMissingDataFile(self::LITE_DATA_FILE);
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesHashEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesHashEngine native extension is not loaded.');
        }
    }

    /**
     * A User-Agent header given as Header.User-Agent, or in capitals, is
     * detected as it is given as header.user-agent.
     */
    public function testAHeaderKeyInAnyLettersGivesTheSameResult()
    {
        $results = $this->detect([
            'none' => [],
            'lower' => ['header.user-agent' => self::CHROME],
            'mixed' => ['Header.User-Agent' => self::CHROME],
            'upper' => ['HEADER.USER-AGENT' => self::CHROME],
        ]);

        $this->assertNotEquals($results['none'], $results['lower']);
        $this->assertSame($results['lower'], $results['mixed']);
        $this->assertSame($results['lower'], $results['upper']);
    }

    /**
     * A User-Agent given as Query.User-Agent is detected as it is given as
     * query.user-agent.
     */
    public function testAQueryKeyInAnyLettersGivesTheSameResult()
    {
        $results = $this->detect([
            'none' => [],
            'lower' => ['query.user-agent' => self::IPHONE],
            'mixed' => ['Query.User-Agent' => self::IPHONE],
        ]);

        $this->assertNotEquals($results['none'], $results['lower']);
        $this->assertTrue($results['lower']['ismobile']);
        $this->assertSame($results['lower'], $results['mixed']);
    }

    /**
     * User-Agent Client Hints given in mixed letters, beside a User-Agent
     * in lower case, change the result as they do in lower case.
     */
    public function testClientHintKeysInAnyLettersGiveTheSameResult()
    {
        $results = $this->detect([
            'agent' => ['header.user-agent' => self::CHROME],
            'lower' => [
                'header.user-agent' => self::CHROME,
                'header.sec-ch-ua-platform' => '"Windows"',
                'header.sec-ch-ua-platform-version' => '"14.0.0"',
            ],
            'mixed' => [
                'header.user-agent' => self::CHROME,
                'Header.Sec-CH-UA-Platform' => '"Windows"',
                'Header.Sec-CH-UA-Platform-Version' => '"14.0.0"',
            ],
        ]);

        // The hints change the result with this data file, so a hint the
        // engine was not given would show.
        $this->assertContains('header.sec-ch-ua-platform-version', $results['keys']);
        $this->assertNotEquals($results['agent'], $results['lower']);
        $this->assertSame($results['lower'], $results['mixed']);
    }

    /**
     * @param array<string, array<string, string>> $sets
     * @return array<string, mixed>
     */
    private function detect(array $sets): array
    {
        $dataFile = realpath(self::LITE_DATA_FILE);
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $autoload = dirname(
            (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))
                ->getFileName(),
            2
        ) . '/autoload.php';
        $command = escapeshellarg(PHP_BINARY) . ' -d ' .
            escapeshellarg("FiftyOneDegreesHashEngine.data_file={$dataFile}") .
            ' -r ' . escapeshellarg(self::DETECT) . ' -- ' .
            escapeshellarg($autoload) . ' ' .
            escapeshellarg(json_encode($sets)) . ' 2>&1';
        exec($command, $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);
        $decoded = json_decode($printed, true);
        $this->assertIsArray($decoded, $printed);

        return $decoded;
    }
}
