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

namespace fiftyone\pipeline\derivedproperty;

/**
 * Collects scripts from the three places they come from, being the package,
 * a file and code, along with every fault found, so that one build reports
 * everything wrong at once.
 *
 * @internal
 */
final class ScriptSet
{
    /**
     * @var array<DerivedScript>
     */
    private array $scripts = [];

    /**
     * @var array<ScriptFault>
     */
    private array $faults = [];

    public function add(DerivedScript $script): void
    {
        $this->scripts[] = $script;
    }

    /**
     * Adds a script this package carries.
     */
    public function addBuiltIn(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            $this->fault(null, 'built in', 'a built in script name is empty');

            return;
        }
        $path = BuiltInScripts::path($name);
        if ($path === null) {
            $names = BuiltInScripts::names();
            $this->fault($name, 'built in', sprintf(
                "there is no script called '%s' in this package. The scripts " .
                    'in this package are %s. A script from your own ' .
                    'environment is added with ScriptFiles rather than Scripts.',
                $name,
                $names ? implode(', ', $names) : '(none)'
            ));

            return;
        }
        $script = pathinfo($path, PATHINFO_FILENAME);
        $this->read($path, $script, 'built in ' . $script);
    }

    /**
     * Adds a script file, or every file a wildcard in the file name
     * matches, in ordinal order of their paths so that every machine
     * builds the same element.
     */
    public function addFile(string $path): void
    {
        $path = trim($path);
        if ($path === '') {
            $this->fault(null, 'file', 'a script file path is empty');

            return;
        }
        if (strpbrk($path, '*?') === false) {
            $this->addOneFile($path);

            return;
        }
        $folder = dirname($path);
        if (!is_dir($folder)) {
            $this->fault(null, $path, "the folder '$folder' does not exist");

            return;
        }
        $files = glob($path);
        $files = $files === false ? [] : array_values(array_filter($files, 'is_file'));
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $this->addOneFile($file);
        }
    }

    /**
     * Adds the text of a script, as YAML or as JSON, whose Name must equal
     * the name given.
     */
    public function addText(string $name, string $text): void
    {
        $this->parse($text, $name, 'code');
    }

    /**
     * @return array<DerivedScript>
     */
    public function scripts(): array
    {
        return $this->scripts;
    }

    /**
     * @return array<ScriptFault>
     */
    public function faults(): array
    {
        return $this->faults;
    }

    private function addOneFile(string $path): void
    {
        if (!is_file($path)) {
            $this->fault(null, $path, "the file '$path' does not exist");

            return;
        }
        $this->read($path, pathinfo($path, PATHINFO_FILENAME), $path);
    }

    private function read(string $path, string $name, string $source): void
    {
        $text = @file_get_contents($path);
        if ($text === false) {
            $this->fault($name, $source, "the file '$path' could not be read");

            return;
        }
        $this->parse($text, $name, $source);
    }

    private function parse(string $text, string $name, string $source): void
    {
        try {
            $this->scripts[] = ScriptValidator::read($text, $name, $source);
        } catch (ScriptValidationException $e) {
            array_push($this->faults, ...$e->getFaults());
        }
    }

    private function fault(?string $script, string $source, string $message): void
    {
        $this->faults[] = new ScriptFault($script, $source, '', null, $message);
    }
}
