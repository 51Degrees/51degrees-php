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
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use PHPUnit\Framework\TestCase;

/**
 * refreshData makes the engine use another data file, or the updated file
 * it was built from. A data file it cannot use fails the refresh with an
 * exception, and the engine goes on with the data it had. Each case starts
 * its own PHP process, as the engine is built when PHP starts and a refresh
 * changes it for the rest of the process.
 *
 * A copy of the Lite data file with another published date stands in for a
 * new data file, because the engine gives that date and nothing else in the
 * copy differs.
 */
class RefreshDataTests extends TestCase
{
    /**
     * Where the published date sits in the header of a version 4.6 data
     * file, as a little-endian 16-bit year, a month byte and a day byte.
     */
    private const PUBLISHED_OFFSET = 66;

    private const NEW_PUBLISHED = '2001-02-03';

    // Looks up an address, refreshes the engine, and looks it up again.
    // Prints, as JSON, the published date, the data file path, the accepted
    // Evidence, a digest of every property's value and how many have a
    // value, before and after, and the exception the refresh threw, if any.
    // The arguments are the autoloader, the address, how to refresh ('file'
    // from the source, or 'original' after copying the source over the file
    // the engine was built from) and the source.
    private const REFRESH = <<<'PHP'
        require $argv[1];
        [, , $address, $how, $source] = $argv;
        $engine = new fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise();
        $pipeline = (new fiftyone\pipeline\core\PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
        $state = static function () use ($engine, $pipeline, $address): array {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('query.client-ip', $address);
            $flowData->process();
            $values = [];
            $withValue = 0;
            foreach (array_keys($engine->getProperties()) as $name) {
                $value = $flowData->ip->get($name);
                $values[$name] = $value->hasValue ? $value->value : $value->noValueMessage;
                $withValue += $value->hasValue ? 1 : 0;
            }
            $flowData->close();
            $date = $engine->engine->getPublishedTime();
            return [
                'published' => sprintf('%04d-%02d-%02d',
                    $date->getYear(), $date->getMonth(), $date->getDay()),
                'path' => $engine->engine->getDataFilePath(),
                'keys' => $engine->getEvidenceKeyFilter()->getList(),
                'values' => md5(serialize($values)),
                'withValue' => $withValue,
            ];
        };
        $before = $state();
        $error = null;
        try {
            if ($how === 'original') {
                copy($source, $engine->engine->getDataFilePath());
                $engine->refreshData();
            } else {
                $engine->refreshData($source);
            }
        } catch (Throwable $e) {
            $error = ['exception' => $e instanceof Exception,
                'message' => $e->getMessage()];
        }
        $after = $state();
        $pipeline->close();
        echo json_encode(['before' => $before, 'after' => $after,
            'error' => $error]);
        PHP;

    private string $folder;

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.');
        }
        $this->folder = sys_get_temp_dir() . '/51d-refresh-' . uniqid();
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        if (isset($this->folder) && is_dir($this->folder)) {
            array_map('unlink', glob($this->folder . '/*') ?: []);
            rmdir($this->folder);
        }
    }

    /**
     * A refresh from another file makes the engine use that file, and it
     * goes on looking up addresses as before.
     */
    public function testARefreshFromAnotherFileUsesThatFile(): void
    {
        $lite = $this->liteDataFile();
        $newer = $this->copyPublished($lite, 'newer.ipi', self::NEW_PUBLISHED);

        $printed = $this->refresh($lite, 'file', $newer);

        $this->assertNull($printed['error']);
        $this->assertSame($this->publishedIn($lite), $printed['before']['published']);
        $this->assertNotSame(self::NEW_PUBLISHED, $printed['before']['published']);
        $this->assertSame(self::NEW_PUBLISHED, $printed['after']['published']);
        $this->assertSame($newer, $printed['after']['path']);
        $this->assertGreaterThan(0, $printed['before']['withValue']);
        $this->assertSame($printed['before']['values'], $printed['after']['values']);
        $this->assertSame($printed['before']['keys'], $printed['after']['keys']);
    }

    /**
     * A refresh with no arguments reloads the file the engine was built
     * from, once that file has been updated.
     */
    public function testARefreshReloadsTheUpdatedOriginalFile(): void
    {
        $lite = $this->liteDataFile();
        $original = $this->copyPublished($lite, 'original.ipi', null);
        $newer = $this->copyPublished($lite, 'newer.ipi', self::NEW_PUBLISHED);

        $printed = $this->refresh($original, 'original', $newer);

        $this->assertNull($printed['error']);
        $this->assertSame($this->publishedIn($lite), $printed['before']['published']);
        $this->assertSame(self::NEW_PUBLISHED, $printed['after']['published']);
        $this->assertSame($original, $printed['after']['path']);
        $this->assertSame($printed['before']['values'], $printed['after']['values']);
    }

    /**
     * A refresh from a file that is not a data file throws, and the engine
     * goes on with the data it had.
     */
    public function testARefreshFromAFileThatIsNotDataFailsAndKeepsTheData(): void
    {
        $lite = $this->liteDataFile();

        $printed = $this->refresh($lite, 'file', $this->notData());

        $this->assertFailedAndKept($printed, $lite);
    }

    /**
     * A refresh from a file that does not exist throws, and the engine goes
     * on with the data it had.
     */
    public function testARefreshFromAMissingFileFailsAndKeepsTheData(): void
    {
        $lite = $this->liteDataFile();

        $printed = $this->refresh($lite, 'file', $this->folder . '/missing.ipi');

        $this->assertFailedAndKept($printed, $lite);
    }

    /**
     * A refresh with no arguments, once the file the engine was built from
     * is no longer a data file, throws, and the engine goes on with the
     * data it had.
     */
    public function testARefreshFromAnOriginalFileThatIsNoLongerDataFailsAndKeepsTheData(): void
    {
        $original = $this->copyPublished($this->liteDataFile(), 'original.ipi', null);

        $printed = $this->refresh($original, 'original', $this->notData());

        $this->assertFailedAndKept($printed, $original);
    }

    /**
     * @param array<string, mixed> $printed
     */
    private function assertFailedAndKept(array $printed, string $path): void
    {
        $this->assertNotNull($printed['error'], 'The refresh did not throw.');
        $this->assertTrue($printed['error']['exception'], $printed['error']['message']);
        $this->assertNotSame('', $printed['error']['message']);
        $this->assertSame($printed['before'], $printed['after']);
        $this->assertSame($path, $printed['after']['path']);
        $this->assertGreaterThan(0, $printed['after']['withValue']);
    }

    /**
     * Starts PHP with the engine built from $dataFile, runs REFRESH and
     * returns what it printed.
     *
     * @return array<string, mixed>
     */
    private function refresh(string $dataFile, string $how, string $source): array
    {
        $autoload = dirname(
            (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))
                ->getFileName(),
            2
        ) . '/autoload.php';
        $command = escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg(
            "FiftyOneDegreesIpIntelligenceEngine.data_file={$dataFile}"
        ) . ' -r ' . escapeshellarg(self::REFRESH) . ' -- ' .
            implode(' ', array_map('escapeshellarg', [
                $autoload, Constants::IPV4_ADDRESS, $how, $source
            ])) . ' 2>&1';
        exec($command, $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);
        $decoded = json_decode($printed, true);
        $this->assertIsArray($decoded, $printed);

        return $decoded;
    }

    /**
     * The free Lite data file. CI fetches it into the package's assets
     * folder under its asset name, and a fresh clone downloads it into the
     * data repository.
     */
    private function liteDataFile(): string
    {
        $path = __DIR__ . '/../assets/51Degrees-LiteIpiV41.ipi';
        if (!is_file($path)) {
            $path = __DIR__ . '/../../cxx/data/ip-intelligence/' .
                ExampleConstants::LITE_DATA_FILE_NAME;
            $this->assertTrue(
                ExampleUtils::downloadMissingDataFile($path),
                'The free Lite data file could not be found or downloaded.'
            );
        }
        $lite = realpath($path);
        $this->assertNotFalse($lite, 'The Lite data file is missing.');

        return $lite;
    }

    /**
     * Copies a data file into the test's folder, with the published date
     * given as YYYY-MM-DD, or unchanged when it is null.
     */
    private function copyPublished(string $from, string $name, ?string $published): string
    {
        $this->publishedIn($from);
        $to = $this->folder . '/' . $name;
        $this->assertTrue(copy($from, $to));
        if ($published !== null) {
            [$year, $month, $day] = array_map('intval', explode('-', $published));
            $file = fopen($to, 'r+b');
            fseek($file, self::PUBLISHED_OFFSET);
            fwrite($file, pack('vCC', $year, $month, $day));
            fclose($file);
        }

        return realpath($to);
    }

    /**
     * The published date in a data file's header as YYYY-MM-DD, once the
     * header says it is version 4.6, where the date is where
     * PUBLISHED_OFFSET says.
     */
    private function publishedIn(string $file): string
    {
        $header = file_get_contents($file, false, null, 0, self::PUBLISHED_OFFSET + 4);
        $this->assertSame(
            ['major' => 4, 'minor' => 6],
            unpack('Vmajor/Vminor', $header)
        );
        $date = unpack('vyear/Cmonth/Cday', $header, self::PUBLISHED_OFFSET);

        return sprintf('%04d-%02d-%02d', $date['year'], $date['month'], $date['day']);
    }

    /**
     * A file in the test's folder that is not a data file.
     */
    private function notData(): string
    {
        $path = $this->folder . '/not-data.ipi';
        file_put_contents($path, str_repeat('This is not a data file. ', 200));

        return realpath($path);
    }
}
