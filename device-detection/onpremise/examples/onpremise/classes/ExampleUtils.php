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

namespace fiftyone\pipeline\devicedetection\examples\onpremise\classes;

class ExampleUtils
{
    // If data file is older than this number of days then a warning will
    // be displayed.
    public const DATA_FILE_AGE_WARNING = 30;

    // Environment variable that can name the device detection data file. It
    // is the name the 51Degrees examples in every language read.
    public const DD_PATH_VARIABLE = '_51DEGREES_DD_PATH';

    /**
     * Environment variable read for the data file when DD_PATH_VARIABLE is
     * not set or is empty.
     *
     * @deprecated use {@see ExampleUtils::DD_PATH_VARIABLE}
     */
    public const DATA_FILE_ENV_VAR = '51DEGREES_DD_PATH';

    // The php.ini setting naming the data file the extension builds its
    // engine from when PHP starts.
    public const DATA_FILE_SETTING = 'FiftyOneDegreesHashEngine.data_file';

    // The free data file an example uses when no other is named. The data
    // repository's script downloads it into cxx/data/device-detection.
    public const LITE_DATA_FILE_NAME = '51Degrees-LiteV4.1.hash';

    // Sent with each download of a data file, so that downloads can be
    // counted by where they come from. It is left out inside GitHub Actions,
    // where the data repository's script names the build instead, so that
    // test runs are not counted as people using the examples.
    public const DATA_DOWNLOAD_SOURCE = 'examples-51degrees-php';

    // Shown in place of a value for a property that the data file in use
    // does not have. Every property the examples ask for is in the
    // Enterprise data file.
    public const NEEDS_ENTERPRISE = 'needs the Enterprise data file';

    /**
     * Use the data file named by the _51DEGREES_DD_PATH environment variable,
     * when it is set, in place of the one set in php.ini.
     *
     * The engine is created once, when PHP starts, from the
     * FiftyOneDegreesHashEngine.data_file setting in php.ini, so that setting
     * must still name a data file that exists. When the environment variable
     * names a different file, the engine is reloaded from that file. The
     * reload lasts for the life of the PHP process, so each process reloads
     * once and later requests find the engine already using the file. A free
     * file from cxx/data that has not been downloaded yet is downloaded
     * first.
     *
     * @param mixed $engine The device detection engine in the pipeline
     * @param mixed $logger
     */
    public static function useDataFileFromEnv($engine, $logger)
    {
        $path = EnvironmentVariable::read(
            ExampleUtils::DD_PATH_VARIABLE,
            ExampleUtils::DATA_FILE_ENV_VAR
        );
        if ($path === '') {
            return;
        }

        ExampleUtils::downloadMissingDataFile($path);
        $wanted = realpath($path);
        if ($wanted === false) {
            throw new \Exception(
                "The data file '{$path}' named by the environment variable '" .
                ExampleUtils::DD_PATH_VARIABLE . "' does not exist."
            );
        }

        if (realpath($engine->engine->getDataFilePath()) === $wanted) {
            return;
        }

        $logger->log(
            'info',
            "Loading the data file '{$wanted}' named by the environment " .
            "variable '" . ExampleUtils::DD_PATH_VARIABLE . "'."
        );
        $engine->refreshData($wanted);
    }

    /**
     * Download a free data file that is missing, with the data repository's
     * script, when the file's link is in the folder where it goes.
     *
     * The data repository keeps each free data file as <file>.link, holding
     * the address it is downloaded from, and the repository is the folder
     * above the link's folder. The command is printed before it runs, and
     * again if the file is still missing.
     *
     * @param string $path Where the data file goes
     * @return bool True when the data file is there
     */
    public static function downloadMissingDataFile($path)
    {
        if (file_exists($path)) {
            return true;
        }
        $command = ExampleUtils::getDataFileCommand($path);
        if ($command === null) {
            return false;
        }

        $name = basename($path);
        ExampleUtils::writeError(
            "{$name} is missing, so it is being downloaded by running\n{$command}"
        );
        $status = 1;
        if (function_exists('exec')) {
            $lines = [];
            exec($command, $lines, $status);
            if (count($lines) > 0) {
                ExampleUtils::writeError(implode("\n", $lines));
            }
        }
        clearstatcache(true, $path);
        if ($status === 0 && file_exists($path)) {
            return true;
        }
        ExampleUtils::writeError(
            "{$name} could not be downloaded. Run this command to try " .
            "again.\n{$command}"
        );

        return false;
    }

    /**
     * The command that downloads a free data file with the data repository's
     * script, or null when the file's link is not in the folder where it
     * goes.
     *
     * @param string $path Where the data file goes
     */
    public static function getDataFileCommand($path)
    {
        $link = realpath($path . '.link');
        if ($link === false || !is_file($link)) {
            return null;
        }
        $data = dirname($link, 2);
        $name = escapeshellarg(basename($path));
        $source = getenv('GITHUB_ACTIONS') === 'true'
            ? ''
            : ExampleUtils::DATA_DOWNLOAD_SOURCE;
        if (PHP_OS_FAMILY === 'Windows') {
            // PowerShell 7 when it is on the path, otherwise Windows
            // PowerShell, which, started from PowerShell 7, inherits its
            // module path and then cannot load its own commands.
            exec('where pwsh 2>NUL', $found, $status);
            $shell = $status === 0 ? 'pwsh' : 'set "PSModulePath=" && powershell';
            $command = "{$shell} -NoProfile -ExecutionPolicy Bypass -File " .
                escapeshellarg($data . '\\get-data-files.ps1') . " {$name}";

            return $source === '' ? $command : "{$command} -Source {$source}";
        }

        $command = 'sh ' . escapeshellarg($data . '/get-data-files.sh');

        return $source === ''
            ? "{$command} {$name}"
            : "{$command} -s {$source} {$name}";
    }

    /**
     * Returns when the extension built its engine when PHP started, which is
     * the engine every on-premise example uses. Otherwise, on the command
     * line, runs the example again in a new PHP started with the data file
     * getDataFile finds, the free Lite file downloaded when there is none,
     * so that the example runs with nothing set, and exits with that PHP's
     * status. It exits with 1 and a message on standard error when it
     * cannot. A web server cannot be started again from a request, so under
     * one it shows a page saying which php.ini setting to set and where the
     * free file is, and exits.
     *
     * When the engine is there, a data file named by the first argument or
     * the _51DEGREES_DD_PATH environment variable that is not the one
     * php.ini names is not loaded. A command line example says so and how
     * to start PHP with it.
     *
     * @param array<string> $argv The script's arguments, its own name first
     */
    public static function startWithEngine($argv = [])
    {
        $loaded = extension_loaded('FiftyOneDegreesHashEngine');
        $configured = (string) ini_get(ExampleUtils::DATA_FILE_SETTING);
        $args = array_slice($argv, 1);
        if ($loaded && \FiftyOneDegreesHashEngine::engine_get() !== null) {
            $named = ExampleUtils::getNamedDataFile($args);
            if (PHP_SAPI === 'cli' && $named !== null &&
                realpath($named) !== realpath($configured)) {
                ExampleUtils::writeError(
                    'This example uses the engine PHP built when it started ' .
                    "from {$configured}, which php.ini names. To use " .
                    "{$named} instead, run\n" .
                    ExampleUtils::startCommand($named, $argv)
                );
            }

            return;
        }

        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
            echo ExampleUtils::getSetupPage($loaded);
            exit;
        }

        if (!$loaded) {
            ExampleUtils::writeError(
                'The FiftyOneDegreesHashEngine extension is not loaded. The ' .
                'readme says how to build it and add it to php.ini.'
            );
            exit(1);
        }

        $path = ExampleUtils::getDataFile($args);
        if ($path === null) {
            ExampleUtils::writeError(
                'No device detection engine was built when PHP started and ' .
                'no data file was found. Set ' . ExampleUtils::DATA_FILE_SETTING .
                ' in php.ini to the path of a .hash data file.'
            );
            exit(1);
        }
        if ($configured !== '' && realpath($configured) === $path) {
            // This PHP was started with the data file and built no engine
            // from it, so a new one would do the same.
            ExampleUtils::writeError(
                "No device detection engine was built from {$path}, which " .
                ExampleUtils::DATA_FILE_SETTING . ' names.'
            );
            exit(1);
        }

        $command = count($argv) > 0 && is_file($argv[0])
            ? ExampleUtils::startCommand($path, $argv)
            : null;
        if ($command === null || !function_exists('passthru')) {
            ExampleUtils::writeError(
                'No device detection engine was built when PHP started. Set ' .
                ExampleUtils::DATA_FILE_SETTING . " in php.ini to {$path}" .
                ($command === null ? '.' : ", or run\n{$command}")
            );
            exit(1);
        }
        ExampleUtils::writeError(
            'No device detection engine was built when PHP started, so the ' .
            "example runs again in a PHP that builds one from {$path}. Set " .
            ExampleUtils::DATA_FILE_SETTING . ' in php.ini, so that PHP ' .
            "builds the engine once when it starts.\n{$command}"
        );
        passthru($command, $status);
        exit($status);
    }

    /**
     * The full path of the data file an example uses when PHP started
     * without the engine, or null, with the reason on standard error, when
     * there is none. That is the file the first argument names, then the
     * one the _51DEGREES_DD_PATH environment variable names, then the free
     * Lite file in cxx/data/device-detection, downloaded when it is missing.
     * A free file named by the environment variable that has not been
     * downloaded yet is downloaded first.
     *
     * @param array<string> $args The script's arguments, without its name
     */
    public static function getDataFile($args = [])
    {
        if (count($args) > 0 && $args[0] !== '') {
            if (!is_file($args[0])) {
                ExampleUtils::writeError(
                    "There is no data file at {$args[0]}, which the first " .
                    'argument names.'
                );

                return null;
            }

            return realpath($args[0]);
        }

        $env = EnvironmentVariable::read(
            ExampleUtils::DD_PATH_VARIABLE,
            ExampleUtils::DATA_FILE_ENV_VAR
        );
        if ($env !== '') {
            if (!ExampleUtils::downloadMissingDataFile($env)) {
                ExampleUtils::writeError(
                    "There is no data file at {$env}, which the environment " .
                    'variable ' . ExampleUtils::DD_PATH_VARIABLE . ' names.'
                );

                return null;
            }

            return realpath($env);
        }

        $free = __DIR__ . '/../../../../../cxx/data/device-detection/' .
            ExampleUtils::LITE_DATA_FILE_NAME;

        return ExampleUtils::downloadMissingDataFile($free)
            ? realpath($free)
            : null;
    }

    /**
     * The data file named by the first argument or the _51DEGREES_DD_PATH
     * environment variable, when it exists.
     *
     * @param array<string> $args The script's arguments, without its name
     */
    private static function getNamedDataFile($args)
    {
        if (count($args) > 0 && $args[0] !== '' && is_file($args[0])) {
            return $args[0];
        }
        $env = EnvironmentVariable::read(
            ExampleUtils::DD_PATH_VARIABLE,
            ExampleUtils::DATA_FILE_ENV_VAR
        );

        return $env !== '' && is_file($env) ? $env : null;
    }

    /**
     * The command that runs the script again with PHP building its engine
     * from the data file when it starts. Where the system gives PHP's own
     * command line, its options, such as one loading the extension, are
     * kept, and the data file setting goes after them, just before the
     * script, so that it replaces any setting of the same name.
     *
     * @param string $path The data file
     * @param array<string> $argv The script's arguments, its own name first
     */
    private static function startCommand($path, $argv)
    {
        $parts = array_merge([PHP_BINARY], $argv);
        $script = 1;
        $line = is_readable('/proc/self/cmdline')
            ? (string) file_get_contents('/proc/self/cmdline')
            : '';
        $started = $line === '' ? [] : explode("\0", rtrim($line, "\0"));
        $found = array_search($argv[0], array_slice($started, 1), true);
        if ($found !== false &&
            array_slice($started, $found + 1) === $argv) {
            $parts = $started;
            $parts[0] = PHP_BINARY;
            $script = $found + 1;
        }

        $quoted = array_map('escapeshellarg', $parts);
        array_splice($quoted, $script, 0, [
            '-d',
            escapeshellarg(ExampleUtils::DATA_FILE_SETTING . '=' . $path)
        ]);

        return implode(' ', $quoted);
    }

    /**
     * The page a web example shows when PHP started without the engine. It
     * says which php.ini setting to set and where the free Lite data file
     * is, or how to download it, as a request cannot start the server
     * again.
     *
     * @param bool $loaded Whether the extension is loaded
     */
    public static function getSetupPage($loaded)
    {
        $html = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES);
        };
        $free = __DIR__ . '/../../../../../cxx/data/device-detection/' .
            ExampleUtils::LITE_DATA_FILE_NAME;
        $path = realpath($free) ?: $free;
        $ini = php_ini_loaded_file();
        $setting = ExampleUtils::DATA_FILE_SETTING;

        $page = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">' .
            '<title>Set the device detection data file</title></head><body>' .
            '<h1>Set the device detection data file</h1>';
        if (!$loaded) {
            $page .= '<p>The FiftyOneDegreesHashEngine extension is not ' .
                'loaded. The readme says how to build it and add it to ' .
                'php.ini.</p>';
        }
        $page .= '<p>No device detection engine was built when PHP ' .
            'started. Set <code>' . $html($setting) . '</code> in php.ini' .
            ($ini === false ? '' : ' (<code>' . $html($ini) . '</code>)') .
            ' to the path of a .hash data file, then start PHP again, so ' .
            'that PHP builds the engine once when it starts.</p>';
        if (is_file($path)) {
            $page .= '<p>The free Lite data file is at <code>' .
                $html($path) . '</code>.</p>';
        } else {
            $command = ExampleUtils::getDataFileCommand($free);
            $page .= '<p>The free Lite data file goes in <code>' .
                $html($path) . '</code>. ' . ($command === null
                    ? 'The readme says how to download it.'
                    : 'Download it by running <code>' . $html($command) .
                        '</code>.') . '</p>';
        }
        if (PHP_SAPI === 'cli-server') {
            $page .= '<p>Or start this example with the setting, by running ' .
                '<code>' . $html(ExampleUtils::serverCommand($path)) .
                '</code></p>';
        }

        return $page . '</body></html>';
    }

    /**
     * The command that starts the PHP built-in server again with the data
     * file setting. Where the system gives the server's own command line,
     * it is kept, with the setting added after the PHP binary.
     *
     * @param string $path The data file
     */
    private static function serverCommand($path)
    {
        $line = is_readable('/proc/self/cmdline')
            ? (string) file_get_contents('/proc/self/cmdline')
            : '';
        $parts = $line === ''
            ? [PHP_BINARY, '-S', $_SERVER['HTTP_HOST'] ?? 'localhost:3000',
                basename($_SERVER['SCRIPT_FILENAME'])]
            : explode("\0", rtrim($line, "\0"));
        $parts[0] = PHP_BINARY;
        $quoted = array_map('escapeshellarg', $parts);
        array_splice($quoted, 1, 0, [
            '-d',
            escapeshellarg(ExampleUtils::DATA_FILE_SETTING . '=' . $path)
        ]);

        return implode(' ', $quoted);
    }

    public static function output($message)
    {
        if (php_sapi_name() == 'cli') {
            echo $message . "\n";
        } else {
            echo "<pre>{$message}\n</pre>";
        }
    }

    public static function getDataFileDate($engine)
    {
        $date = $engine->engine->getPublishedTime();

        return mktime(0, 0, 0, $date->getMonth(), $date->getDay(), $date->getYear());
    }

    public static function dataFileIsOld($engine)
    {
        $dataFileDate = ExampleUtils::getDataFileDate($engine);

        return strtotime('today') > $dataFileDate + mktime(0, 0, 0, 0, ExampleUtils::DATA_FILE_AGE_WARNING, 0);
    }

    public static function getDataFileTier($engine)
    {
        return $engine->engine->getProduct();
    }

    /**
     * Get the 'engine' element within the pipeline that
     * performs device detection. We can use this to get
     * details about the data file as well as meta-data
     * describing things such as the available properties.
     *
     * @param mixed $engine
     * @param mixed $logger
     */
    public static function checkDataFile($engine, $logger)
    {
        if (isset($engine)) {
            $dataFileDate = ExampleUtils::getDataFileDate($engine);
            $logger->log(
                'info',
                "Using a '" . ExampleUtils::getDataFileTier($engine) . "' data file created " .
                "'" . date('d/m/Y', $dataFileDate) . "' from location " .
                "'" . $engine->engine->getDataFilePath() . "'"
            );

            if (ExampleUtils::dataFileIsOld($engine)) {
                $logger->log(
                    'warn',
                    'This example is using a data file ' .
                    "that is more than '" . ExampleUtils::DATA_FILE_AGE_WARNING . "' days " .
                    'old. A more recent data file may be needed to ' .
                    'correctly detect the latest devices, browsers, ' .
                    'etc. Get the latest lite data file by running ' .
                    'cxx/data/get-data-files.sh -u 51Degrees-LiteV4.1.hash, ' .
                    'or cxx/data/get-data-files.ps1 51Degrees-LiteV4.1.hash ' .
                    '-Update on Windows. The scripts are in the data ' .
                    'repository https://github.com/51Degrees/data. ' .
                    'Contact us about the Enterprise data file, which ' .
                    'includes automatic updates, at ' .
                    'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-onpremise-classes-exampleutils.php&utm_term=data-file-age-warning'
                );
            }
        }
    }

    /**
     * True when the engine uses the free Lite data file.
     *
     * @param mixed $engine The device detection engine
     */
    public static function isLiteDataFile($engine)
    {
        return ExampleUtils::getDataFileTier($engine) === 'Lite';
    }

    /**
     * True when the engine's data file has the property. The Lite data file
     * leaves out the properties only the Enterprise data file has, such as
     * HardwareVendor, HardwareName and DeviceType. The data file's own list
     * is read, so the answer follows a data file loaded with refreshData.
     *
     * @param mixed $engine The device detection engine
     * @param string $name The property name, in any case
     */
    public static function hasProperty($engine, $name)
    {
        $properties = $engine->engine->getMetaData()->getProperties();
        for ($i = 0; $i < $properties->getSize(); ++$i) {
            if (strcasecmp($properties->getByIndex($i)->getName(), $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format a property for display, or say that it needs the Enterprise
     * data file when the engine's data file does not have it. The value is
     * only read when the data file has the property.
     *
     * @param mixed $engine The device detection engine
     * @param mixed $device The device results
     * @param string $name The property name, in lower case
     */
    public static function getEnterpriseValue($engine, $device, $name)
    {
        if (!ExampleUtils::hasProperty($engine, $name)) {
            return ExampleUtils::NEEDS_ENTERPRISE;
        }

        return ExampleUtils::getHumanReadable($device, $name);
    }

    /**
     * Where to ask for a copy of the Enterprise data file, written after an
     * example has shown properties that the data file in use does not have.
     *
     * @param string $contactUs The contact page, tagged with the example's
     * file, so that an enquiry can be traced to the example
     */
    public static function getEnterpriseNote($contactUs)
    {
        return 'Some of these properties need the Enterprise data file. ' .
            "Contact us for a copy at {$contactUs}";
    }

    /**
     * What the Enterprise data file adds and where to ask for a copy,
     * written after an example that shows no properties has run on the free
     * Lite data file.
     *
     * @param string $contactUs The contact page, tagged with the example's
     * file
     */
    public static function getEnterpriseOffer($contactUs)
    {
        return 'This example used the free Lite data file. The Enterprise ' .
            'data file adds many more properties, such as HardwareVendor, ' .
            "HardwareName and DeviceType. Contact us for a copy at {$contactUs}";
    }

    public static function getHumanReadable($device, $name)
    {
        try {
            $value = $device->{$name};
            if ($value->hasValue) {
                if (is_array($value->value)) {
                    return implode(', ', $value->value);
                }
                if (is_bool($value->value)) {
                    return $value->value ? 'True' : 'False';
                }

                return $value->value;
            }

            return 'Unknown (' . $value->noValueMessage . ')';
        } catch (\Exception $e) {
            return 'Property not found in the current data file.';
        }
    }

    public static function containsAcceptCh()
    {
        foreach (headers_list() as $header) {
            $parts = explode(': ', $header);
            if (strtolower($parts[0]) === 'accept-ch') {
                return true;
            }
        }

        return false;
    }

    /**
     * Write a message to standard error. The web example's server shows it
     * in its console, and it never reaches the page.
     */
    private static function writeError($message)
    {
        file_put_contents('php://stderr', $message . "\n");
    }
}
