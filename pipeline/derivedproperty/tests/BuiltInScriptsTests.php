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
use fiftyone\pipeline\derivedproperty\DerivedScript;
use fiftyone\pipeline\derivedproperty\ScriptValidationException;
use fiftyone\pipeline\derivedproperty\ScriptValidator;
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

    /**
     * Every built in script is carried compiled from the text of its file,
     * so building an element with it reads no YAML.
     */
    public function testEveryBuiltInScriptIsCarriedCompiledFromItsFile(): void
    {
        $this->assertNotEmpty(BuiltInScripts::names());
        foreach (BuiltInScripts::names() as $name) {
            $path = (string) BuiltInScripts::path($name);
            $fields = BuiltInScripts::readCompiled(
                BuiltInScripts::compiledFolder() . DIRECTORY_SEPARATOR . "$name.php",
                $path
            );
            $this->assertNotNull(
                $fields,
                "compiled/$name.php is missing or was compiled from other " .
                    "text than scripts/" . basename($path) . '. Run ' .
                    'pipeline/derivedproperty/ci/compile-built-in-scripts.php ' .
                    'and commit the result.'
            );
            $read = ScriptValidator::read(
                (string) file_get_contents($path),
                $name,
                'built in ' . $name
            );
            $this->assertSame(
                $read->toArray(),
                $fields,
                "compiled/$name.php is not what scripts/" . basename($path) .
                    ' compiles to. Run ' .
                    'pipeline/derivedproperty/ci/compile-built-in-scripts.php ' .
                    'and commit the result.'
            );

            $compiled = BuiltInScripts::compiled($path);
            $this->assertNotNull($compiled);
            $this->assertSame($read->toArray(), $compiled->toArray());
            $this->assertSame('built in ' . $name, $compiled->source);
        }
    }

    /**
     * A compiled file is read only while it holds the hash of the text the
     * script file has now, whatever its line ends, and holds a script.
     */
    public function testACompiledScriptIsReadOnlyForTheTextItCameFrom(): void
    {
        $folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('derived', true);
        mkdir($folder);
        $script = $folder . DIRECTORY_SEPARATOR . 'Demo.yaml';
        // Each compiled file is new, as opcache can keep a file it has
        // already included when the file is written again within a second.
        $count = 0;
        $write = function (string $hash, string $fields) use ($folder, &$count): string {
            $compiled = $folder . DIRECTORY_SEPARATOR . 'Demo' . ++$count . '.php';
            file_put_contents(
                $compiled,
                "<?php return ['sha256' => '$hash', 'script' => $fields];"
            );
            return $compiled;
        };
        try {
            file_put_contents($script, "Name: Demo\nVersion: 1.0.0\n");
            $this->assertNull(
                BuiltInScripts::readCompiled($folder . DIRECTORY_SEPARATOR . 'None.php', $script),
                'a missing compiled file was read'
            );

            $compiled = $write(BuiltInScripts::hash("Name: Demo\nVersion: 1.0.0\n"), "['name' => 'Demo']");
            $this->assertSame(
                ['name' => 'Demo'],
                BuiltInScripts::readCompiled($compiled, $script)
            );

            file_put_contents($script, "Name: Demo\r\nVersion: 1.0.0\r\n");
            $this->assertSame(
                ['name' => 'Demo'],
                BuiltInScripts::readCompiled($compiled, $script),
                'line ends changed by a checkout stopped the compiled file being read'
            );

            file_put_contents($script, "Name: Demo\nVersion: 1.0.1\n");
            $this->assertNull(
                BuiltInScripts::readCompiled($compiled, $script),
                'a compiled file of other text was read'
            );

            $compiled = $write(BuiltInScripts::hash("Name: Demo\nVersion: 1.0.1\n"), "'not a script'");
            $this->assertNull(
                BuiltInScripts::readCompiled($compiled, $script),
                'a compiled file holding no script was read'
            );
        } finally {
            array_map('unlink', glob($folder . DIRECTORY_SEPARATOR . '*') ?: []);
            rmdir($folder);
        }
    }

    /**
     * A script's fields give the same script back, and fields that are
     * missing or of the wrong type are refused rather than half read.
     */
    public function testAScriptsFieldsGiveTheSameScriptBack(): void
    {
        foreach (BuiltInScripts::names() as $name) {
            $path = (string) BuiltInScripts::path($name);
            $read = ScriptValidator::read(
                (string) file_get_contents($path),
                $name,
                'built in ' . $name
            );
            $again = DerivedScript::fromArray($read->toArray(), $read->source);
            $this->assertEquals($read, $again);
        }

        $fields = $read->toArray();
        unset($fields['rules']);
        try {
            DerivedScript::fromArray($fields, 'code');
            $this->fail('fields with no rules were accepted');
        } catch (\TypeError $e) {
            $this->addToAssertionCount(1);
        }
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
