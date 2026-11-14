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

namespace fiftyone\pipeline\ipintelligence\tests;

use fiftyone\pipeline\ipintelligence\examples\classes\ExampleConstants;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use PHPUnit\Framework\TestCase;

/**
 * A value read through a chain of calls, such as
 * `$results->getValuesAsWeightedStringList($name)->getValue()->get(0)->getValue()`,
 * is the value read with each step held in a variable. PHP frees a value
 * returned in the middle of a chain once the next call returns, so
 * anything the chain goes on to read must not point into it. The chain runs
 * in its own PHP, because reading freed memory can stop PHP.
 */
class ChainedCallTests extends TestCase
{
    // The properties of every IP Intelligence data file, the free Lite one
    // included, each a weighted list of strings.
    private const PROPERTIES = ['RegisteredCountry', 'RegisteredName', 'RegisteredOwner'];

    // Each read is repeated so that memory freed in the chain is used again
    // by what follows, which is when reading it gives a wrong value.
    private const ROUNDS = 1000;

    private const SCRIPT = <<<'PHP'
<?php
[, $dataFile, $rounds] = $argv;
$properties = array_slice($argv, 3);
$engine = new \FiftyOneDegreesIpiEngineIpiSwig(
    $dataFile,
    new \FiftyOneDegreesIpiConfigIpiSwig(),
    new \FiftyOneDegreesIpiRequiredPropertiesConfigSwig(
        new \FiftyOneDegreesIpiVectorStringSwig()
    )
);
$results = $engine->process('116.154.188.222');
for ($round = 0; $round < (int) $rounds; ++$round) {
    foreach ($properties as $name) {
        $value = $results->getValuesAsWeightedStringList($name);
        $list = $value->getValue();
        $item = $list->get(0);
        $held = $item->getValue() . ' ' . $item->getShortRawWeight();

        $chained = $results->getValuesAsWeightedStringList($name)
            ->getValue()->get(0)->getValue() . ' ' .
            $results->getValuesAsWeightedStringList($name)
                ->getValue()->get(0)->getShortRawWeight();
        $kept = $results->getValuesAsWeightedStringList($name)->getValue()->get(0);
        $later = str_repeat('x', 4096) . $round;
        $keptRead = $kept->getValue() . ' ' . $kept->getShortRawWeight();

        if ($chained !== $held || $keptRead !== $held) {
            echo "{$name} round {$round}: held '{$held}', chained '{$chained}', kept '{$keptRead}'\n";
            exit(1);
        }
        if ($round === 0) {
            echo "{$name}: {$held}\n";
        }
    }
}
echo "same\n";
PHP;

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped(
                'The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.'
            );
        }
    }

    public function testAChainedReadOfAWeightedValueIsTheHeldRead(): void
    {
        $dataFile = realpath($this->liteDataFile());
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $script = tempnam(sys_get_temp_dir(), 'chained');
        file_put_contents($script, self::SCRIPT);
        try {
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) .
                ' ' . escapeshellarg($dataFile) . ' ' . self::ROUNDS . ' ' .
                implode(' ', array_map('escapeshellarg', self::PROPERTIES)) .
                ' 2>&1';
            exec($command, $lines, $status);
        } finally {
            unlink($script);
        }
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);
        $this->assertSame('same', end($lines), $printed);
        foreach (self::PROPERTIES as $name) {
            $this->assertStringContainsString("{$name}: ", $printed);
        }
    }

    /**
     * The free Lite data file. CI fetches it into the package's assets
     * folder under its asset name, and a fresh clone downloads it into the
     * data repository.
     */
    private function liteDataFile(): string
    {
        $asset = __DIR__ . '/../assets/51Degrees-LiteIpiV41.ipi';
        if (is_file($asset)) {
            return $asset;
        }
        $path = __DIR__ . '/../../cxx/data/ip-intelligence/' .
            ExampleConstants::LITE_DATA_FILE_NAME;
        $this->assertTrue(
            ExampleUtils::downloadMissingDataFile($path),
            'The free Lite data file could not be found or downloaded.'
        );

        return $path;
    }
}
