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

use fiftyone\pipeline\derivedproperty\BuiltInScripts;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElement;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElementBuilder;
use fiftyone\pipeline\derivedproperty\ScriptValidationException;
use fiftyone\pipeline\derivedproperty\tests\classes\SharedFolder;
use PHPUnit\Framework\TestCase;

/**
 * The package carries every script of the derived-properties repository,
 * as a copy of its scripts folder that ci/update-packages.ps1 refreshes.
 */
class BuiltInScriptsTests extends TestCase
{
    /**
     * The text of a file with its line ends as line feeds. A checkout on
     * Windows may write this repository's files with CR LF, whilst the
     * submodule's .gitattributes keeps its files at LF.
     */
    private static function text(string $file): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($file));
    }

    /**
     * The copy holds every script in the submodule with the same text, and
     * no other script, so the package offers what .NET and Rust offer.
     */
    public function testTheCopyIsTheScriptsFolderOfTheSubmodule(): void
    {
        $shared = [];
        foreach (SharedFolder::files('scripts', '/\.(ya?ml|json)$/i') as $file) {
            $shared[basename($file)] = hash('sha256', self::text($file));
        }
        $copied = [];
        foreach (BuiltInScripts::names() as $name) {
            $file = (string) BuiltInScripts::path($name);
            $copied[basename($file)] = hash('sha256', self::text($file));
        }
        ksort($shared);
        ksort($copied);

        $this->assertNotEmpty($shared, 'the submodule has no scripts');
        $this->assertSame(
            $shared,
            $copied,
            'pipeline/derivedproperty/scripts is not the scripts folder of the ' .
                'derived-properties submodule. Run ' .
                'pipeline/derivedproperty/ci/update-packages.ps1, or copy the ' .
                'files by hand, and commit the result.'
        );
    }

    public function testEveryBuiltInScriptHasSharedCases(): void
    {
        foreach (BuiltInScripts::names() as $name) {
            $this->assertFileExists(
                SharedFolder::path() . DIRECTORY_SEPARATOR . 'tests' .
                    DIRECTORY_SEPARATOR . "$name.cases.yaml"
            );
        }
    }

    public function testOneElementRunsEveryBuiltInScript(): void
    {
        $names = BuiltInScripts::names();
        $element = new DerivedPropertyElement(['Scripts' => $names]);

        $this->assertSame(
            $names,
            array_map(fn ($script) => $script->name, $element->getScripts())
        );
        foreach ($element->getScripts() as $script) {
            $this->assertSame('built in ' . $script->name, $script->source);
            $this->assertArrayHasKey(
                strtolower($script->outputName()),
                $element->getProperties()
            );
        }
    }

    public function testBuiltInNamesIgnoreCase(): void
    {
        $element = (new DerivedPropertyElementBuilder())
            ->addBuiltInScript('humanconfidence')
            ->build();

        $this->assertSame('HumanConfidence', $element->getScripts()[0]->name);
    }

    public function testAnUnknownBuiltInScriptNamesTheOnesThereAre(): void
    {
        try {
            (new DerivedPropertyElementBuilder())
                ->addBuiltInScript('BrowserCurrency')
                ->build();
            $this->fail('an unknown script was accepted');
        } catch (ScriptValidationException $e) {
            $this->assertStringContainsString(
                "there is no script called 'BrowserCurrency' in this package",
                $e->getMessage()
            );
            $this->assertStringContainsString(
                implode(', ', BuiltInScripts::names()),
                $e->getMessage()
            );
        }
    }
}
