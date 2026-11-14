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

// Writes compiled/<Name>.php for every built in script, holding the
// script's fields and the SHA-256 of its text, which BuiltInScripts reads
// in place of the script file while the hash matches. ci/update-packages.ps1
// runs it after refreshing the scripts, and tests/BuiltInScriptsTests.php
// fails when a compiled script is not the script file beside it.

declare(strict_types=1);

use fiftyone\pipeline\derivedproperty\BuiltInScripts;
use fiftyone\pipeline\derivedproperty\ScriptValidator;

$autoloads = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];
foreach ($autoloads as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

// Each compiled file starts with the licence header of this file.
$self = (string) file_get_contents(__FILE__);
$header = substr($self, 0, (int) strpos($self, '*/') + 2);

$folder = BuiltInScripts::compiledFolder();
if (!is_dir($folder)) {
    mkdir($folder);
}
foreach (glob($folder . DIRECTORY_SEPARATOR . '*.php') ?: [] as $old) {
    unlink($old);
}
foreach (BuiltInScripts::names() as $name) {
    $path = (string) BuiltInScripts::path($name);
    $text = (string) file_get_contents($path);
    $script = ScriptValidator::read($text, $name, 'built in ' . $name);
    $compiled = $header . "\n\n" .
        '// Written by ci/compile-built-in-scripts.php from scripts/' .
        basename($path) . ". Do not edit.\n\n" .
        'return ' . var_export([
            'sha256' => BuiltInScripts::hash($text),
            'script' => $script->toArray(),
        ], true) . ";\n";
    file_put_contents($folder . DIRECTORY_SEPARATOR . $name . '.php', $compiled);
    echo "Compiled $name\n";
}
