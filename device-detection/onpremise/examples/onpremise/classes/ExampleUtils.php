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

    // Sent with each download of a data file, so that downloads can be
    // counted by where they come from. It is left out inside GitHub Actions,
    // where the data repository's script names the build instead, so that
    // test runs are not counted as people using the examples.
    public const DATA_DOWNLOAD_SOURCE = 'examples-51degrees-php';

    // Shown in place of a value for a property that the data file in use
    // does not have. Every property the examples ask for is in the
    // Enterprise data file.
    public const NEEDS_ENTERPRISE = 'needs the Enterprise data file';

    // The repositories every example lists under "Find out more".
    private const FIND_OUT_MORE_REPOSITORIES = [
        'This example' =>
            'https://github.com/51Degrees/51degrees-php/tree/main/device-detection/onpremise',
        'The device detection engine' => 'https://github.com/51Degrees/51degrees-cxx',
        'The free Lite data file' => 'https://github.com/51Degrees/data'
    ];

    // The 51degrees.com pages every example lists under "Find out more",
    // each with the utm_term its link carries.
    private const FIND_OUT_MORE_PAGES = [
        'Device detection' => ['device-detection', 'find-out-more-device-detection'],
        'Data files and pricing' => ['pricing', 'find-out-more-pricing'],
        'Contact us' => ['contact-us', 'find-out-more-contact']
    ];

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

    /**
     * A link to a page on 51degrees.com, tagged as printed by an example so
     * that a visit can be traced to the example that prompted it.
     *
     * @param string $page The page's path, such as contact-us
     * @param string $exampleFile The example's file, __FILE__
     * @param string $term Where in the example the link is
     */
    public static function getTaggedLink($page, $exampleFile, $term)
    {
        return 'https://51degrees.com/' .
            "{$page}?utm_source=code&utm_medium=example" .
            '&utm_campaign=51degrees-php&utm_content=' .
            ExampleUtils::getContentSlug($exampleFile) . "&utm_term={$term}";
    }

    /**
     * The "Find out more" section every example ends with, as text, giving
     * the person running it the links to follow up.
     *
     * @param string $exampleFile The example's file, __FILE__
     */
    public static function getFindOutMore($exampleFile)
    {
        $lines = ['', 'Find out more', '-------------'];
        foreach (ExampleUtils::FIND_OUT_MORE_REPOSITORIES as $label => $url) {
            $lines[] = "{$label}: {$url}";
        }
        foreach (ExampleUtils::FIND_OUT_MORE_PAGES as $label => [$page, $term]) {
            $lines[] = "{$label}: " .
                ExampleUtils::getTaggedLink($page, $exampleFile, $term);
        }

        return implode("\n", $lines);
    }

    /**
     * The "Find out more" section for the end of a web page.
     *
     * @param string $exampleFile The page's file, __FILE__
     */
    public static function getFindOutMoreHtml($exampleFile)
    {
        $items = '';
        foreach (ExampleUtils::FIND_OUT_MORE_REPOSITORIES as $label => $url) {
            $url = htmlspecialchars($url);
            $items .= "        <li>{$label}: <a href=\"{$url}\">{$url}</a></li>\n";
        }
        foreach (ExampleUtils::FIND_OUT_MORE_PAGES as $label => [$page, $term]) {
            $url = htmlspecialchars(
                ExampleUtils::getTaggedLink($page, $exampleFile, $term)
            );
            $items .= "        <li><a href=\"{$url}\">{$label}</a></li>\n";
        }

        return "<div id=\"find-out-more\">\n" .
            "    <h3 class=\"c-eg-page__heading\">Find out more</h3>\n" .
            "    <ul>\n{$items}    </ul>\n</div>\n";
    }

    /**
     * Writes the "Find out more" section, as text from the command line and
     * as HTML from a web server.
     *
     * @param string $exampleFile The example's file, __FILE__
     */
    public static function printFindOutMore($exampleFile)
    {
        echo php_sapi_name() === 'cli'
            ? ExampleUtils::getFindOutMore($exampleFile) . "\n"
            : ExampleUtils::getFindOutMoreHtml($exampleFile);
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

    /**
     * The example's path from the package's folder, in lower case with a
     * hyphen for each folder separator, such as
     * examples-onpremise-gettingstartedconsole.php.
     *
     * @param string $exampleFile The example's file
     */
    private static function getContentSlug($exampleFile)
    {
        $path = str_replace('\\', '/', $exampleFile);
        $at = strrpos($path, '/examples/');
        $path = $at === false ? basename($path) : substr($path, $at + 1);

        return strtolower(str_replace('/', '-', $path));
    }
}
