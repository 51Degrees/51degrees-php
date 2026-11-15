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
 * Downloads a free data file from nothing with the examples' own code, as
 * an example does in a fresh clone. CI links its cached copies of the free
 * files where the examples look for them, so the file is downloaded into a
 * new folder holding only a copy of the data repository's scripts and the
 * file's link. The free ASN file is used because it is the smallest.
 */
class DownloadDataFileTests extends TestCase
{
    // The data repository, which a fresh clone has as cxx/data.
    private const DATA = __DIR__ . '/../../cxx/data';

    // The data repository's download scripts, for Windows and for the rest.
    private const SCRIPTS = ['get-data-files.ps1', 'get-data-files.sh'];

    // The line the data repository's script writes when it does not save a
    // file, after the reason the distributor gave or the download failing.
    private const NOT_SAVED = ' was not saved';

    /** @var string The folder the file is downloaded into */
    private $folder = '';

    protected function tearDown(): void
    {
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

    public function testDownloadsTheFreeAsnFileFromNothing(): void
    {
        $name = ExampleConstants::ASN_DATA_FILE_NAME;
        $link = self::DATA . "/ip-intelligence/{$name}.link";
        if (!is_file($link)) {
            // CI checks out the data repository wherever it runs these
            // tests, so a missing link there is a failure.
            $message = "{$link} is missing. Fetch the cxx submodule and " .
                'its data submodule.';
            if (getenv('GITHUB_ACTIONS') === 'true') {
                $this->fail($message);
            }
            $this->markTestSkipped($message);
        }

        $this->folder = sys_get_temp_dir() . '/fiftyone-data-' .
            getmypid() . '-' . uniqid();
        mkdir($this->folder . '/ip-intelligence', 0777, true);
        foreach (self::SCRIPTS as $script) {
            copy(self::DATA . "/{$script}", "{$this->folder}/{$script}");
        }
        copy($link, "{$this->folder}/ip-intelligence/{$name}.link");
        $path = "{$this->folder}/ip-intelligence/{$name}";
        $this->assertNotNull(
            ExampleUtils::getDataFileCommand($path),
            "The examples do not find the data repository's script for {$name}."
        );

        [$status, $printed] = $this->download($path);

        if ($status !== 0 && strpos($printed, self::NOT_SAVED) !== false) {
            $this->fail(
                "The distributor, distributor.51degrees.com, refused {$name} " .
                'or did not send all of it, so the download a fresh clone ' .
                'makes could not be tested. The examples are not at fault. ' .
                "The data repository's script gave the reason.\n{$printed}"
            );
        }
        $this->assertSame(0, $status, $printed);
        $this->assertStringContainsString(
            "{$name} is missing, so it is being downloaded",
            $printed
        );
        $this->assertGreaterThan(0, (int) filesize($path), $printed);
    }

    /**
     * Downloads the data file with ExampleUtils::downloadMissingDataFile in
     * a new PHP, so that what it writes can be read, and returns that PHP's
     * exit status and what it wrote. The PHP reads only its own ini files,
     * so that it builds no engine.
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
            [PHP_BINARY, '-r', $code, $path],
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
