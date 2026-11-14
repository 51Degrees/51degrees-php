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
 * data file it needs, so that the example runs from a fresh clone with
 * nothing set.
 *
 *   php serve.php <example> [data file] [port]
 *
 * <example> is gettingStartedWeb.php or userAgentClientHints-Web.php, and
 * the port is 3000 unless another is given. The data file is the one named
 * here, then the one the _51DEGREES_DD_PATH environment variable names,
 * then the free Lite file in cxx/data/device-detection, downloaded when it
 * is missing. With none of those named and an engine PHP built from
 * php.ini, the server uses that file. The server runs until it is stopped.
 *
 * The server is started with the options this PHP was started with, such
 * as -d extension=..., where the system gives PHP's own command line, as
 * Linux does. Elsewhere, load the extension from php.ini.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use fiftyone\pipeline\devicedetection\examples\onpremise\classes\EnvironmentVariable;
use fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils;

// The web examples, each the router script of its server.
const SERVE_EXAMPLES = ['gettingStartedWeb.php', 'userAgentClientHints-Web.php'];

const SERVE_DEFAULT_PORT = 3000;

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

$args = array_slice($argv, 1);
$example = (string) array_shift($args);
if (!in_array($example, SERVE_EXAMPLES, true)) {
    fwrite(STDERR, "Start a web example with\n" .
        "  php serve.php <example> [data file] [port]\n" .
        'where <example> is ' . implode(' or ', SERVE_EXAMPLES) . ".\n");
    exit(1);
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

if (!extension_loaded('FiftyOneDegreesHashEngine')) {
    fwrite(STDERR, 'The FiftyOneDegreesHashEngine extension is not ' .
        'loaded. The readme says how to build it and add it to php.ini.' .
        "\n");
    exit(1);
}

$setting = [];
$fromEnvironment = EnvironmentVariable::read(
    ExampleUtils::DD_PATH_VARIABLE,
    ExampleUtils::DATA_FILE_ENV_VAR
);
if ($named === [] && $fromEnvironment === '' &&
    \FiftyOneDegreesHashEngine::engine_get() !== null) {
    $path = (string) ini_get(ExampleUtils::DATA_FILE_SETTING);
} else {
    $path = ExampleUtils::getDataFile($named);
    if ($path === null) {
        fwrite(STDERR, 'No data file was found. Name one with ' .
            "php serve.php {$example} <data file>.\n");
        exit(1);
    }
    $setting = ['-d', ExampleUtils::DATA_FILE_SETTING . '=' . $path];
}

echo "The example is at http://localhost:{$port}/, using the data file " .
    "{$path}. Stop it with Ctrl+C.\n" .
    'For real use, set ' . ExampleUtils::DATA_FILE_SETTING . ' in php.ini ' .
    'to the data file, so that the web server builds the engine once when ' .
    "it starts.\n";

$server = proc_open(
    array_merge(
        [PHP_BINARY],
        serveOptions($argv),
        $setting,
        ['-S', "localhost:{$port}", $example]
    ),
    [0 => STDIN, 1 => STDOUT, 2 => STDERR],
    $pipes,
    __DIR__
);
exit($server === false ? 1 : proc_close($server));
