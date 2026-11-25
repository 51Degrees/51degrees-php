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

namespace fiftyone\pipeline\devicedetection\tests;

use fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\tests\classes\DistributorStandIn;
use PHPUnit\Framework\TestCase;

/**
 * Downloads a free data file from nothing with the examples' own code, as
 * an example does in a fresh clone, from a stand-in for the distributor on
 * this machine, so that no test reaches the distributor. The file is
 * downloaded into a new folder holding only a copy of the data repository's
 * scripts and the file's link, which names the stand-in in place of the
 * distributor. The stand-in serves the copy of the file the build fetched,
 * which ci/fetch-assets.ps1 links into the data repository. The free Lite
 * data file is the one the Device Detection examples download.
 */
class DownloadDataFileTests extends TestCase
{
    // The data repository, which a fresh clone has as cxx/data.
    private const DATA = __DIR__ . '/../../../cxx/data';

    // The data repository's download scripts, for Windows and for the rest.
    private const SCRIPTS = ['get-data-files.ps1', 'get-data-files.sh'];

    // A PHP error, warning, notice or deprecation, as PHP displays it at
    // the start of a line or logs it after "PHP ".
    private const PHP_DIAGNOSTIC =
        '/(^|\bPHP )(Fatal error|Parse error|Warning|Notice|Deprecated):/m';

    /** @var string The folder the file is downloaded into */
    private $folder = '';

    /** @var DistributorStandIn|null The stand-in for the distributor */
    private $distributor;

    /**
     * Every PHP diagnostic, deprecations included, fails the test.
     */
    protected function setUp(): void
    {
        set_error_handler(
            static function (int $level, string $message, string $file, int $line): bool {
                throw new \ErrorException($message, 0, $level, $file, $line);
            }
        );
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        if ($this->distributor !== null) {
            $this->distributor->stop();
            $this->distributor = null;
        }
        if ($this->folder === '' || !is_dir($this->folder)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->folder,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir()
                ? rmdir($file->getPathname())
                : unlink($file->getPathname());
        }
        rmdir($this->folder);
    }

    public function testDownloadsTheFreeLiteFileFromNothing(): void
    {
        $name = ExampleUtils::LITE_DATA_FILE_NAME;
        $link = self::DATA . "/device-detection/{$name}.link";
        $this->assertFileExists(
            $link,
            "{$link} is missing. Fetch the cxx submodule and its data submodule."
        );
        $fetched = self::DATA . "/device-detection/{$name}";
        $this->assertFileExists(
            $fetched,
            "{$fetched}, the fetched copy of {$name} that the stand-in for " .
            'the distributor serves, is missing. CI links it there from the ' .
            "run's asset cache. Elsewhere, download it once with the data " .
            "repository's script."
        );

        // The stand-in answers at the path of the distributor's link.
        $address = strtok((string) file_get_contents($link), "\r\n");
        $path = (string) parse_url(trim((string) $address), PHP_URL_PATH);
        $this->assertNotSame('', $path, "{$link} holds no address.");
        $this->distributor = new DistributorStandIn($fetched, $path);

        $this->folder = sys_get_temp_dir() . '/fiftyone-data-' .
            getmypid() . '-' . uniqid();
        mkdir($this->folder . '/device-detection', 0777, true);
        foreach (self::SCRIPTS as $script) {
            copy(self::DATA . "/{$script}", "{$this->folder}/{$script}");
        }
        file_put_contents(
            "{$this->folder}/device-detection/{$name}.link",
            $this->distributor->url() . "\n"
        );
        $target = "{$this->folder}/device-detection/{$name}";
        $this->assertNotNull(
            ExampleUtils::getDataFileCommand($target),
            "The examples do not find the data repository's script for {$name}."
        );

        [$status, $printed] = $this->download($target);
        $printed .= "\nThe stand-in for the distributor logged:\n" .
            $this->distributor->log();

        $this->assertSame(0, $status, $printed);
        $this->assertStringContainsString(
            "{$name} is missing, so it is being downloaded",
            $printed
        );
        $this->assertSame(
            [
                [
                    'method' => 'GET',
                    'path' => $path,
                    'ifModifiedSince' => null,
                    'status' => 200,
                ],
            ],
            $this->distributor->requests(),
            $printed
        );
        $this->assertDoesNotMatchRegularExpression(
            self::PHP_DIAGNOSTIC,
            $printed,
            'PHP reported a problem in the download or in the stand-in.'
        );
        $this->assertFileExists($target, $printed);
        $this->assertSame(
            md5_file($fetched),
            md5_file($target),
            "{$name} was not saved as the stand-in sent it, unpacked.\n{$printed}"
        );
        clearstatcache(true, $target);
        $this->assertSame(
            $this->distributor->published(),
            filemtime($target),
            "{$name} does not have the published date the stand-in sent as " .
            "Last-Modified, which a later check for a newer file sends.\n{$printed}"
        );
    }

    /**
     * Downloads the data file with ExampleUtils::downloadMissingDataFile in
     * a new PHP, so that what it writes can be read, and returns that PHP's
     * exit status and what it wrote. The PHP reads only its own ini files,
     * so that it builds no engine, and writes every diagnostic it has.
     *
     * @return array{0: int, 1: string}
     */
    private function download(string $path): array
    {
        $code = 'require ' .
            var_export((string) realpath(__DIR__ . '/../vendor/autoload.php'), true) .
            '; exit(\\' . ExampleUtils::class .
            '::downloadMissingDataFile($argv[1]) ? 0 : 1);';
        $process = proc_open(
            [
                PHP_BINARY,
                '-d',
                'error_reporting=-1',
                '-d',
                'display_errors=stderr',
                '-r',
                $code,
                $path,
            ],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            null,
            ['PHP_INI_SCAN_DIR' => PHP_CONFIG_FILE_SCAN_DIR] + getenv()
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $printed = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return [proc_close($process), $printed];
    }
}
