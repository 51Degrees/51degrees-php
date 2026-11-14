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

/*
 * Starts an on-premise web example with the PHP built-in server and the
 * data files it needs, so that the example runs from a fresh clone with
 * nothing set.
 *
 *   php serve.php <example> [data file] [port]
 *
 * <example> is web or web-mixed, and the port is 3000 unless another is
 * given. The IP Intelligence data file is found as the console examples
 * find it (getDataFile in examples/classes/ExampleUtils.php), the free Lite
 * file downloaded when there is none. With nothing named and an engine PHP
 * built from php.ini, the server uses that file. web-mixed also needs the
 * Device Detection extension, and when PHP built no Device Detection engine
 * it gets the file the _51DEGREES_DD_PATH environment variable names, or
 * else the free Lite file in cxx/data/device-detection, downloaded when it
 * is missing. The server runs until it is stopped.
 *
 * The server is started with the options this PHP was started with, such
 * as -d extension=..., where the system gives PHP's own command line, as
 * Linux does. Elsewhere, load the extensions from php.ini.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use fiftyone\pipeline\ipintelligence\examples\classes\EnvironmentVariable;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleConstants;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;

// The web examples, each a folder served as the document root.
const SERVE_EXAMPLES = ['web', 'web-mixed'];

const SERVE_DEFAULT_PORT = 3000;

// What web-mixed needs for Device Detection.
const SERVE_DD_EXTENSION = 'FiftyOneDegreesHashEngine';
const SERVE_DD_SETTING = 'FiftyOneDegreesHashEngine.data_file';
const SERVE_DD_PATH_VARIABLE = '_51DEGREES_DD_PATH';
const SERVE_DD_LITE_DATA_FILE = __DIR__ .
    '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

/**
 * The options PHP was started with before this script, where the system
 * gives PHP's own command line, so that the server starts the same way.
 *
 * @param array<string> $argv The script's arguments, its own name first
 * @return array<string>
 */
function serveOptions(array $argv): array
{
    $line = is_readable('/proc/self/cmdline')
        ? (string) file_get_contents('/proc/self/cmdline')
        : '';
    $started = $line === '' ? [] : explode("\0", rtrim($line, "\0"));
    $found = array_search($argv[0], array_slice($started, 1), true);
    if ($found === false || array_slice($started, $found + 1) !== $argv) {
        return [];
    }

    return array_slice($started, 1, $found);
}

/**
 * Stops with the message on standard error.
 */
function serveStop(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$args = array_slice($argv, 1);
$example = preg_replace('#/(index\.php)?$#', '', (string) array_shift($args));
if (!in_array($example, SERVE_EXAMPLES, true)) {
    serveStop("Start a web example with\n" .
        "  php serve.php <example> [data file] [port]\n" .
        'where <example> is ' . implode(' or ', SERVE_EXAMPLES) . '.');
}
$port = SERVE_DEFAULT_PORT;
$named = [];
foreach ($args as $arg) {
    if (ctype_digit($arg)) {
        $port = (int) $arg;
    } else {
        $named[] = $arg;
    }
}

if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
    serveStop('The FiftyOneDegreesIpIntelligenceEngine extension is not ' .
        'loaded. OnPremise.md says how to build it and add it to php.ini.');
}

$settings = [];
$fromEnvironment = EnvironmentVariable::read(
    ExampleConstants::IPI_PATH_VARIABLE,
    ExampleConstants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR
);
if ($named === [] && $fromEnvironment === '' &&
    function_exists('ipi_engine_get') && \ipi_engine_get() !== null) {
    $path = (string) ini_get(ExampleUtils::DATA_FILE_SETTING);
} else {
    $found = ExampleUtils::getDataFile($named);
    $path = $found === null ? false : realpath($found);
    if ($path === false) {
        serveStop('No IP Intelligence data file was found. Name one with ' .
            "php serve.php {$example} <data file>.");
    }
    $settings = ['-d', ExampleUtils::DATA_FILE_SETTING . '=' . $path];
}

if ($example === 'web-mixed') {
    if (!extension_loaded(SERVE_DD_EXTENSION)) {
        serveStop('web-mixed needs the ' . SERVE_DD_EXTENSION . ' extension ' .
            'as well. The Device Detection on-premise readme says how to ' .
            'build it.');
    }
    if (\FiftyOneDegreesHashEngine::engine_get() === null) {
        $ddNamed = (string) getenv(SERVE_DD_PATH_VARIABLE);
        $ddPath = $ddNamed !== '' ? $ddNamed : SERVE_DD_LITE_DATA_FILE;
        if (!ExampleUtils::downloadMissingDataFile($ddPath)) {
            serveStop("There is no Device Detection data file at {$ddPath}.");
        }
        $settings[] = '-d';
        $settings[] = SERVE_DD_SETTING . '=' . realpath($ddPath);
    }
}

echo "The example is at http://localhost:{$port}/, using the data file " .
    "{$path}. Stop it with Ctrl+C.\n" .
    'For real use, set ' . ExampleUtils::DATA_FILE_SETTING . ' in php.ini ' .
    'to the data file, so that the web server builds the engine once when ' .
    "it starts.\n";

$root = __DIR__ . '/' . $example;
$server = proc_open(
    array_merge(
        [PHP_BINARY],
        serveOptions($argv),
        $settings,
        ['-S', "localhost:{$port}", '-t', $root]
    ),
    [0 => STDIN, 1 => STDOUT, 2 => STDERR],
    $pipes,
    $root
);
exit($server === false ? 1 : proc_close($server));
