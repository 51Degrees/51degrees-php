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
use fiftyone\pipeline\devicedetection\Messages;
use PHPUnit\Framework\TestCase;

/**
 * refreshData makes the engine use another data file, or the updated file
 * it was built from. A data file it cannot use fails the refresh with an
 * exception, and the engine goes on with the data it had, as it does when
 * it is given a data file held in memory, which it refuses. Each case starts
 * its own PHP process, as the engine is built when PHP starts and a refresh
 * changes it for the rest of the process.
 *
 * A copy of the Lite data file with another published date stands in for a
 * new data file, because the engine gives that date and nothing else in the
 * copy differs.
 */
class RefreshDataTests extends TestCase
{
    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    /**
     * Where the published date sits in the header of a version 4.1 data
     * file, as a little-endian 16-bit year, a month byte and a day byte.
     */
    private const PUBLISHED_OFFSET = 66;

    private const NEW_PUBLISHED = '2001-02-03';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like ' .
        'Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114';

    // Detects a User-Agent, refreshes the engine, and detects it again.
    // Prints, as JSON, the published date, the data file path and the
    // values before and after, and the exception the refresh threw, if
    // any. The arguments are the autoloader, the User-Agent, how to
    // refresh ('file' from the source, 'memory' from the source's bytes
    // and length, or 'original' and 'original-null' with no file name and
    // a null one, after copying the source over the file the engine was
    // built from), the source, and 'stale' to set the accepted keys to one
    // no data file has before the refresh.
    private const REFRESH = <<<'PHP'
        require $argv[1];
        [, , $agent, $how, $source] = $argv;
        $engine = new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise();
        $pipeline = (new fiftyone\pipeline\core\PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
        $state = static function () use ($engine, $pipeline, $agent): array {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('header.user-agent', $agent);
            $flowData->process();
            $values = [];
            foreach (['deviceid', 'ismobile', 'platformname', 'browsername'] as $name) {
                $value = $flowData->device->get($name);
                $values[$name] = $value->hasValue ? $value->value : $value->noValueMessage;
            }
            $flowData->close();
            $date = $engine->engine->getPublishedTime();
            return [
                'published' => sprintf('%04d-%02d-%02d',
                    $date->getYear(), $date->getMonth(), $date->getDay()),
                'path' => $engine->engine->getDataFilePath(),
                'values' => $values,
                'keys' => $engine->getEvidenceKeyFilter()->getList(),
            ];
        };
        $before = $state();
        if (($argv[5] ?? '') === 'stale') {
            $keys = new ReflectionProperty($engine, 'evidenceKeys');
            if (PHP_VERSION_ID < 80100) {
                $keys->setAccessible(true);
            }
            $keys->setValue($engine, ['header.stale']);
        }
        $error = null;
        try {
            if ($how === 'original') {
                copy($source, $engine->engine->getDataFilePath());
                $engine->refreshData();
            } elseif ($how === 'original-null') {
                copy($source, $engine->engine->getDataFilePath());
                $engine->refreshData(null);
            } elseif ($how === 'memory') {
                $data = file_get_contents($source);
                $engine->refreshData($data, strlen($data));
            } else {
                $engine->refreshData($source);
            }
        } catch (Throwable $e) {
            $error = ['exception' => $e instanceof Exception,
                'class' => get_class($e), 'message' => $e->getMessage()];
        }
        $after = $state();
        $fresh = (new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise())
            ->getEvidenceKeyFilter()->getList();
        $pipeline->close();
        echo json_encode(['before' => $before, 'after' => $after,
            'error' => $error, 'fresh' => $fresh]);
        PHP;

    private string $folder;

    public static function setUpBeforeClass(): void
    {
        ExampleUtils::downloadMissingDataFile(self::LITE_DATA_FILE);
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesHashEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesHashEngine native extension is not loaded.');
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
     * goes on detecting as before.
     */
    public function testARefreshFromAnotherFileUsesThatFile()
    {
        $lite = $this->lite();
        $newer = $this->copyPublished($lite, 'newer.hash', self::NEW_PUBLISHED);

        $printed = $this->refresh($lite, 'file', $newer);

        $this->assertNull($printed['error']);
        $this->assertSame($this->publishedIn($lite), $printed['before']['published']);
        $this->assertNotSame(self::NEW_PUBLISHED, $printed['before']['published']);
        $this->assertSame(self::NEW_PUBLISHED, $printed['after']['published']);
        $this->assertSame($newer, $printed['after']['path']);
        $this->assertTrue($printed['before']['values']['ismobile']);
        $this->assertSame($printed['before']['values'], $printed['after']['values']);
    }

    /**
     * A refresh with no arguments reloads the file the engine was built
     * from, once that file has been updated.
     */
    public function testARefreshReloadsTheUpdatedOriginalFile()
    {
        $original = $this->copyPublished($this->lite(), 'original.hash', null);
        $newer = $this->copyPublished($original, 'newer.hash', self::NEW_PUBLISHED);

        $printed = $this->refresh($original, 'original', $newer);

        $this->assertNull($printed['error']);
        $this->assertSame($this->publishedIn($this->lite()), $printed['before']['published']);
        $this->assertSame(self::NEW_PUBLISHED, $printed['after']['published']);
        $this->assertSame($original, $printed['after']['path']);
        $this->assertSame($printed['before']['values'], $printed['after']['values']);
    }

    /**
     * After a refresh the element accepts the Evidence the new data
     * accepts, which an element built afresh accepts too.
     */
    public function testARefreshReadsTheAcceptedEvidenceOfTheNewData()
    {
        $lite = $this->lite();
        $newer = $this->copyPublished($lite, 'newer.hash', self::NEW_PUBLISHED);

        $printed = $this->refresh($lite, 'file', $newer, 'stale');

        $this->assertNull($printed['error']);
        $this->assertSame($printed['fresh'], $printed['after']['keys']);
        $this->assertNotContains('header.stale', $printed['after']['keys']);
        $this->assertContains('header.user-agent', $printed['after']['keys']);
    }

    /**
     * A refresh from a file that is not a data file throws, and the engine
     * goes on with the data it had.
     */
    public function testARefreshFromAFileThatIsNotDataFailsAndKeepsTheData()
    {
        $lite = $this->lite();

        $printed = $this->refresh($lite, 'file', $this->notData());

        $this->assertFailedAndKept($printed, $lite);
    }

    /**
     * A refresh from a file that does not exist throws, and the engine goes
     * on with the data it had.
     */
    public function testARefreshFromAMissingFileFailsAndKeepsTheData()
    {
        $lite = $this->lite();

        $printed = $this->refresh($lite, 'file', $this->folder . '/missing.hash');

        $this->assertFailedAndKept($printed, $lite);
    }

    /**
     * A refresh with no arguments, once the file the engine was built from
     * is no longer a data file, throws, and the engine goes on with the
     * data it had.
     */
    public function testARefreshFromAnOriginalFileThatIsNoLongerDataFailsAndKeepsTheData()
    {
        $original = $this->copyPublished($this->lite(), 'original.hash', null);

        $printed = $this->refresh($original, 'original', $this->notData());

        $this->assertFailedAndKept($printed, $original);
    }

    /**
     * A null file name is the same as none, so the engine reloads the
     * updated file it was built from.
     */
    public function testARefreshWithANullFileNameReloadsTheOriginalFile()
    {
        $original = $this->copyPublished($this->lite(), 'original.hash', null);
        $newer = $this->copyPublished($original, 'newer.hash', self::NEW_PUBLISHED);

        $printed = $this->refresh($original, 'original-null', $newer);

        $this->assertNull($printed['error']);
        $this->assertSame(self::NEW_PUBLISHED, $printed['after']['published']);
        $this->assertSame($original, $printed['after']['path']);
        $this->assertSame($printed['before']['values'], $printed['after']['values']);
    }

    /**
     * A data file held in memory cannot be passed to the engine from PHP,
     * so a refresh given one throws an exception saying how to refresh
     * instead, and the engine goes on with the data it had.
     */
    public function testARefreshFromDataInMemoryIsRefusedAndKeepsTheData()
    {
        $lite = $this->lite();
        $newer = $this->copyPublished($lite, 'newer.hash', self::NEW_PUBLISHED);

        $printed = $this->refresh($lite, 'memory', $newer);

        $this->assertNotNull($printed['error'], 'The refresh did not throw.');
        $this->assertSame('Exception', $printed['error']['class'], $printed['error']['message']);
        $this->assertSame(Messages::REFRESH_FROM_MEMORY, $printed['error']['message']);
        $this->assertSame($printed['before'], $printed['after']);
        $this->assertTrue($printed['after']['values']['ismobile']);
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
        $this->assertTrue($printed['after']['values']['ismobile']);
    }

    /**
     * Starts PHP with the engine built from $dataFile, runs REFRESH and
     * returns what it printed.
     *
     * @return array<string, mixed>
     */
    private function refresh(
        string $dataFile,
        string $how,
        string $source,
        string $stale = ''
    ): array {
        $autoload = dirname(
            (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))
                ->getFileName(),
            2
        ) . '/autoload.php';
        $command = escapeshellarg(PHP_BINARY) . ' -d ' .
            escapeshellarg("FiftyOneDegreesHashEngine.data_file={$dataFile}") .
            ' -r ' . escapeshellarg(self::REFRESH) . ' -- ' .
            implode(' ', array_map('escapeshellarg', [
                $autoload, self::IPHONE, $how, $source, $stale
            ])) . ' 2>&1';
        exec($command, $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);
        $decoded = json_decode($printed, true);
        $this->assertIsArray($decoded, $printed);

        return $decoded;
    }

    private function lite(): string
    {
        $lite = realpath(self::LITE_DATA_FILE);
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
     * header says it is version 4.1, where the date is where
     * PUBLISHED_OFFSET says.
     */
    private function publishedIn(string $file): string
    {
        $header = file_get_contents($file, false, null, 0, self::PUBLISHED_OFFSET + 4);
        $this->assertSame(
            ['major' => 4, 'minor' => 1],
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
        $path = $this->folder . '/not-data.hash';
        file_put_contents($path, str_repeat('This is not a data file. ', 200));

        return realpath($path);
    }
}
