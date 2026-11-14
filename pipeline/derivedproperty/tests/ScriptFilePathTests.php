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

namespace fiftyone\pipeline\derivedproperty\tests;

use fiftyone\pipeline\derivedproperty\DerivedPropertyElement;
use PHPUnit\Framework\TestCase;

/**
 * A relative script file path, with or without a wildcard, is read against
 * the working directory of the process, as file-system-paths.md in the
 * Pipeline specification requires.
 */
class ScriptFilePathTests extends TestCase
{
    /**
     * A script named Relative. The line ends are written out, so that the
     * text is the same on a checkout that turns them into CR LF.
     */
    private const SCRIPT = "Format: 1\nName: Relative\nVersion: 1.0.0\n" .
        "Output:\n  Name: Relative\n  Description: A demonstration.\n" .
        "  ValueType: string\n  IsList: false\nRules:\n" .
        "  - When: { Property: src.N, Lt: 2 }\n    Then: \"yes\"\n" .
        "  - Else: \"no\"\n";

    private string $workingDirectory;

    private string $folder;

    protected function setUp(): void
    {
        $this->workingDirectory = (string) getcwd();
        $this->folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            uniqid('scripts', true);
        mkdir($this->folder . DIRECTORY_SEPARATOR . 'scripts', 0777, true);
        mkdir($this->folder . DIRECTORY_SEPARATOR . 'other');
        file_put_contents($this->file(), self::SCRIPT);
    }

    protected function tearDown(): void
    {
        chdir($this->workingDirectory);
        unlink($this->file());
        rmdir($this->folder . DIRECTORY_SEPARATOR . 'scripts');
        rmdir($this->folder . DIRECTORY_SEPARATOR . 'other');
        rmdir($this->folder);
    }

    private function file(): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'scripts' .
            DIRECTORY_SEPARATOR . 'Relative.yaml';
    }

    private function assertReadsFrom(string $path): void
    {
        $element = new DerivedPropertyElement(['ScriptFiles' => $path]);

        $this->assertSame(
            ['Relative'],
            array_map(
                fn ($script) => $script->name,
                $element->getScripts()
            ),
            $path
        );
    }

    public function testAPathBelowTheWorkingDirectoryIsRead(): void
    {
        chdir($this->folder);

        $this->assertReadsFrom('scripts' . DIRECTORY_SEPARATOR . 'Relative.yaml');
        $this->assertReadsFrom('scripts/Relative.yaml');
        $this->assertReadsFrom('./scripts/Relative.yaml');
        $this->assertReadsFrom('scripts' . DIRECTORY_SEPARATOR . '*.yaml');
    }

    public function testAPathThroughTheParentFolderIsRead(): void
    {
        chdir($this->folder . DIRECTORY_SEPARATOR . 'other');

        $this->assertReadsFrom('..' . DIRECTORY_SEPARATOR . 'scripts' .
            DIRECTORY_SEPARATOR . 'Relative.yaml');
        $this->assertReadsFrom('../scripts/*.yaml');
    }
}
