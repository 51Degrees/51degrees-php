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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\ConfiguredElement;
use PHPUnit\Framework\TestCase;

/**
 * A relative path to a configuration file is read against the working
 * directory of the process, as file-system-paths.md in the Pipeline
 * specification requires.
 */
class FileSystemPathTests extends TestCase
{
    private const DATA_KEY = 'relative';

    private string $workingDirectory;

    private string $folder;

    protected function setUp(): void
    {
        $this->workingDirectory = (string) getcwd();
        $this->folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            uniqid('paths', true);
        mkdir($this->folder . DIRECTORY_SEPARATOR . 'config', 0777, true);
        mkdir($this->folder . DIRECTORY_SEPARATOR . 'other');
        file_put_contents($this->file(), json_encode([
            'PipelineOptions' => [
                'Elements' => [[
                    'BuilderName' => ConfiguredElement::class,
                    'BuildParameters' => ['dataKey' => self::DATA_KEY],
                ]],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        chdir($this->workingDirectory);
        unlink($this->file());
        rmdir($this->folder . DIRECTORY_SEPARATOR . 'config');
        rmdir($this->folder . DIRECTORY_SEPARATOR . 'other');
        rmdir($this->folder);
    }

    private function file(): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'pipeline.json';
    }

    private function assertBuildsFrom(string $path): void
    {
        $pipeline = (new PipelineBuilder())->buildFromConfig($path);

        $this->assertSame(
            [self::DATA_KEY],
            array_keys($pipeline->flowElementsList),
            $path
        );
    }

    public function testAPathBelowTheWorkingDirectoryIsRead(): void
    {
        chdir($this->folder);

        $this->assertBuildsFrom('config' . DIRECTORY_SEPARATOR . 'pipeline.json');
        $this->assertBuildsFrom('config/pipeline.json');
        $this->assertBuildsFrom('.' . DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'pipeline.json');
    }

    public function testAPathThroughTheParentFolderIsRead(): void
    {
        chdir($this->folder . DIRECTORY_SEPARATOR . 'other');

        $this->assertBuildsFrom('..' . DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'pipeline.json');
        $this->assertBuildsFrom('../config/pipeline.json');
    }

    public function testAFileNameAloneIsReadFromTheWorkingDirectory(): void
    {
        chdir($this->folder . DIRECTORY_SEPARATOR . 'config');

        $this->assertBuildsFrom('pipeline.json');
    }
}
