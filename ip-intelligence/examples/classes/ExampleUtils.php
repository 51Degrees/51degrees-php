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

namespace fiftyone\pipeline\ipintelligence\examples\classes;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\FlowElement;

class ExampleUtils
{
    // Shown in place of a value for a property that the data file in use
    // does not have. Every property the examples ask for is in the
    // Enterprise data file.
    public const NEEDS_ENTERPRISE = 'needs the Enterprise data file';

    // The php.ini setting naming the data file the extension builds its
    // engine from when PHP starts.
    public const DATA_FILE_SETTING = 'FiftyOneDegreesIpIntelligenceEngine.data_file';

    // The Device Detection on-premise engine, which the mixed examples use
    // with this package's engine.
    public const DEVICE_DETECTION_ONPREMISE_CLASS =
        'fiftyone\\pipeline\\devicedetection\\DeviceDetectionOnPremise';

    // Where the Device Detection on-premise package's classes are in the
    // 51degrees-php repository, beside this package.
    private const DEVICE_DETECTION_ONPREMISE_SOURCE =
        __DIR__ . '/../../../device-detection/onpremise/src';

    /**
     * True when the Device Detection on-premise package's classes can be
     * loaded. This package does not depend on that package, so when it is
     * not installed, its classes are loaded from the repository this package
     * is in, where it sits beside this one.
     */
    public static function findDeviceDetectionOnPremise(): bool
    {
        if (class_exists(self::DEVICE_DETECTION_ONPREMISE_CLASS)) {
            return true;
        }
        $source = realpath(self::DEVICE_DETECTION_ONPREMISE_SOURCE);
        if ($source === false) {
            return false;
        }

        $prefix = 'fiftyone\\pipeline\\devicedetection\\';
        spl_autoload_register(
            static function (string $class) use ($prefix, $source): void {
                if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                    return;
                }
                $file = $source . '/' .
                    str_replace('\\', '/', substr($class, strlen($prefix))) .
                    '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        );

        return class_exists(self::DEVICE_DETECTION_ONPREMISE_CLASS);
    }

    /**
     * Returns when the extension built its engine when PHP started, which is
     * the engine every on-premise example uses. Otherwise runs the example
     * again in a new PHP started with the data file getDataFile finds, the
     * free file downloaded when there is none, so that the example runs with
     * nothing set, and exits with that PHP's status. It exits with 1 and a
     * message on standard error when it cannot.
     *
     * When the engine is there, a data file named by the first argument or
     * the _51DEGREES_IPI_PATH environment variable that is not the one
     * php.ini names is not loaded. The example says so and how to start PHP
     * with it.
     *
     * @param array<string> $argv The script's arguments, its own name first
     * @param string $freeFile The free file to download when none is found
     */
    public static function startWithEngine(
        array $argv,
        string $freeFile = ExampleConstants::LITE_DATA_FILE_NAME
    ): void {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            self::writeError(
                'The FiftyOneDegreesIpIntelligenceEngine extension is not ' .
                'loaded. OnPremise.md says how to build and enable it.'
            );
            exit(1);
        }

        $args = array_slice($argv, 1);
        $configured = (string) ini_get(self::DATA_FILE_SETTING);
        if (function_exists('ipi_engine_get') && \ipi_engine_get() !== null) {
            $named = self::getNamedDataFile($args);
            if ($named !== null && realpath($named) !== realpath($configured)) {
                self::writeError(
                    "This example uses the engine PHP built when it started " .
                    "from {$configured}, which php.ini names. To use " .
                    "{$named} instead, run\n" .
                    self::startCommand($named, $argv)
                );
            }

            return;
        }

        $path = self::getDataFile($args, $freeFile);
        if ($path === null) {
            self::writeError(
                'No IP intelligence engine was built when PHP started and ' .
                'no data file was found. Set ' . self::DATA_FILE_SETTING .
                ' in php.ini to the path of an .ipi data file.'
            );
            exit(1);
        }
        if ($configured !== '' && realpath($configured) === realpath($path)) {
            // This PHP was started with the data file and built no engine
            // from it, so a new one would do the same.
            self::writeError(
                "No IP intelligence engine was built from {$path}, which " .
                self::DATA_FILE_SETTING . ' names.'
            );
            exit(1);
        }

        $command = self::startCommand($path, $argv);
        if (!function_exists('passthru')) {
            self::writeError(
                'No IP intelligence engine was built when PHP started. Set ' .
                self::DATA_FILE_SETTING . " in php.ini to {$path}, or run\n" .
                $command
            );
            exit(1);
        }
        self::writeError(
            'No IP intelligence engine was built when PHP started, so the ' .
            "example runs again in a PHP that builds one from {$path}. Set " .
            self::DATA_FILE_SETTING . ' in php.ini, so that PHP builds the ' .
            "engine once when it starts.\n{$command}"
        );
        passthru($command, $status);
        exit($status);
    }

    /**
     * The data file named by the first argument or the _51DEGREES_IPI_PATH
     * environment variable, when it exists.
     *
     * @param array<string> $args
     */
    private static function getNamedDataFile(array $args): ?string
    {
        if (count($args) > 0 && !empty($args[0]) && file_exists($args[0])) {
            return $args[0];
        }
        $env = EnvironmentVariable::read(
            ExampleConstants::IPI_PATH_VARIABLE,
            ExampleConstants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR
        );

        return $env !== '' && file_exists($env) ? $env : null;
    }

    /**
     * The command that runs the script again with PHP building its engine
     * from the data file when it starts. Where the system gives PHP's own
     * command line, its options, such as one loading the extension, are
     * kept, and the data file setting goes after them, just before the
     * script, so that it replaces any setting of the same name.
     *
     * @param array<string> $argv The script's arguments, its own name first
     */
    private static function startCommand(string $path, array $argv): string
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
            escapeshellarg(self::DATA_FILE_SETTING . '=' . $path)
        ]);

        return implode(' ', $quoted);
    }

    /**
     * Resolve the cloud resource key from a CLI argument or environment
     * variable.
     *
     * @param array<string> $args
     */
    public static function getResourceKey(array $args = []): ?string
    {
        if (count($args) > 0 && !empty($args[0])) {
            return $args[0];
        }

        $key = EnvironmentVariable::read(
            ExampleConstants::RESOURCE_KEY_VARIABLE,
            ExampleConstants::CLOUD_RESOURCE_KEY_ENV_VAR,
            '!!YOUR_RESOURCE_KEY!!'
        );

        return $key === '' ? null : $key;
    }

    /**
     * Resolve an on-premise data file path from a CLI argument, environment
     * variable, or by searching known repository locations. When none is
     * found, the free file is downloaded into cxx/data/ip-intelligence.
     *
     * @param array<string> $args
     * @param string $freeFile The free file to look for after the others,
     * and to download when nothing is found, the Lite file unless another
     * is named
     */
    public static function getDataFile(
        array $args = [],
        string $freeFile = ExampleConstants::LITE_DATA_FILE_NAME
    ): ?string {
        if (count($args) > 0 && !empty($args[0]) && file_exists($args[0])) {
            return $args[0];
        }

        $env = EnvironmentVariable::read(
            ExampleConstants::IPI_PATH_VARIABLE,
            ExampleConstants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR
        );
        if ($env && file_exists($env)) {
            return $env;
        }

        $assets = __DIR__ . '/../../assets';
        $data = __DIR__ . '/../../../cxx/data/ip-intelligence';

        $names = array_unique(
            array_merge(ExampleConstants::IPI_DATA_FILE_NAMES, [$freeFile])
        );
        foreach ([$assets, $data] as $dir) {
            foreach ($names as $name) {
                $candidate = $dir . '/' . $name;
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
            // Any other .ipi file in assets was put there to be used. The
            // data folder is searched by name only, as it can also hold the
            // Asn file.
            if ($dir === $assets) {
                $any = glob($dir . '/*.ipi') ?: [];
                if (!empty($any)) {
                    return $any[0];
                }
            }
        }

        $free = $data . '/' . $freeFile;

        return self::downloadMissingDataFile($free) ? $free : null;
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
    public static function downloadMissingDataFile(string $path): bool
    {
        if (file_exists($path)) {
            return true;
        }
        $command = self::getDataFileCommand($path);
        if ($command === null) {
            return false;
        }

        $name = basename($path);
        self::writeError(
            "{$name} is missing, so it is being downloaded by running\n{$command}"
        );
        $status = 1;
        if (function_exists('exec')) {
            $lines = [];
            exec($command, $lines, $status);
            if (count($lines) > 0) {
                self::writeError(implode("\n", $lines));
            }
        }
        clearstatcache(true, $path);
        if ($status === 0 && file_exists($path)) {
            return true;
        }
        self::writeError(
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
    public static function getDataFileCommand(string $path): ?string
    {
        $link = realpath($path . '.link');
        if ($link === false || !is_file($link)) {
            return null;
        }
        $data = dirname($link, 2);
        $name = escapeshellarg(basename($path));
        $source = getenv('GITHUB_ACTIONS') === 'true'
            ? ''
            : ExampleConstants::DATA_DOWNLOAD_SOURCE;
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
     * True when the engine's data file has the property. The free Lite data
     * file has the registration properties, such as RegisteredName, and the
     * free ASN data file the ASN properties, such as AsnName. The data
     * file's own list is read, because an engine's properties are only
     * those it was built to give.
     *
     * @param mixed $engine A pipeline element, such as the IP intelligence
     * or device detection engine, or a native engine
     * @param string $name The property name, in any case
     */
    public static function hasProperty($engine, string $name): bool
    {
        $native = $engine instanceof FlowElement ? $engine->engine : $engine;
        $properties = $native->getMetaData()->getProperties();
        for ($i = 0; $i < $properties->getSize(); ++$i) {
            if (strcasecmp($properties->getByIndex($i)->getName(), $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the engine uses one of the free data files, Lite or ASN.
     * Only the Enterprise data file has the range properties, so a check for
     * IpRangeStart tells them apart.
     *
     * @param mixed $engine A pipeline element or a native engine
     */
    public static function isFreeDataFile($engine): bool
    {
        return !self::hasProperty($engine, 'IpRangeStart');
    }

    /**
     * For an example that needs properties only the Enterprise data file
     * has, report when the engine's data file has none of them, and then
     * write which and where to ask for a copy of the Enterprise data file.
     *
     * @param mixed $engine A pipeline element or a native engine
     * @param string $purpose What the example does, as the start of a
     * sentence, for example "Finding suspicious IP addresses"
     * @param array<string> $properties
     * @param resource $output
     * @param string $contactUs The contact page, tagged with the example's
     * file, so that an enquiry can be traced to the example
     */
    public static function lacksEnterpriseProperties(
        $engine,
        string $purpose,
        array $properties,
        $output,
        string $contactUs
    ): bool {
        foreach ($properties as $name) {
            if (self::hasProperty($engine, $name)) {
                return false;
            }
        }
        $names = count($properties) === 1
            ? $properties[0]
            : implode(', ', array_slice($properties, 0, -1)) . ' and ' .
                $properties[count($properties) - 1];
        fwrite($output, "{$purpose} needs the {$names} properties, which " .
            'only the Enterprise data file has. The data file in use has ' .
            'none of them, so the example stops here. Contact us for a copy ' .
            "of the Enterprise data file at {$contactUs}\n");

        return true;
    }

    /**
     * Where to ask for a copy of the Enterprise data file, written after an
     * example has shown properties that the data file in use does not have.
     *
     * @param string $contactUs The contact page, tagged with the example's
     * file
     */
    public static function getEnterpriseNote(string $contactUs): string
    {
        return 'Some of these properties need the Enterprise data file. ' .
            "Contact us for a copy at {$contactUs}";
    }

    /**
     * What the Enterprise data file adds and where to ask for a copy,
     * written after an example that shows no location properties has run on
     * a free data file.
     *
     * @param string $contactUs The contact page, tagged with the example's
     * file
     */
    public static function getEnterpriseOffer(string $contactUs): string
    {
        return 'This example used a free data file. The Enterprise data file ' .
            'adds the location, range and connection properties. Contact us ' .
            "for a copy at {$contactUs}";
    }

    /**
     * Format an AspectPropertyValue (or a missing value) for printing.
     *
     * @param mixed $apv
     */
    public static function formatValue($apv): string
    {
        if (!($apv instanceof AspectPropertyValue)) {
            return (string) $apv;
        }

        if (!$apv->hasValue) {
            return '(' . $apv->noValueMessage . ')';
        }

        $value = $apv->value;
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            // Weighted values are arrays of ['value' => ..., 'rawweighting' => ...].
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item) && array_key_exists('value', $item)) {
                    $weight = $item['rawweighting'] ?? '';
                    $parts[] = $item['value'] . ' (' . $weight . ')';
                } else {
                    $parts[] = (string) $item;
                }
            }

            return implode(', ', $parts);
        }

        return (string) $value;
    }

    /**
     * Write a property to the output stream, or that it needs the
     * Enterprise data file when the engine's data file does not have it.
     * Returns true when it needs the Enterprise data file.
     *
     * @param mixed $engine The engine that produced the data
     * @param mixed $data An ElementData instance
     * @param resource $output
     */
    public static function outputEnterpriseProperty(
        $engine,
        $data,
        string $name,
        $output
    ): bool {
        if (!self::hasProperty($engine, $name)) {
            fwrite($output, sprintf("\t%s: %s\n", $name, self::NEEDS_ENTERPRISE));

            return true;
        }
        self::outputProperty($data, $name, $output);

        return false;
    }

    /**
     * Write a single property to the output stream.
     *
     * @param mixed $data An ElementData instance
     * @param resource $output
     */
    public static function outputProperty($data, string $name, $output): void
    {
        try {
            $apv = $data->get(strtolower($name));
            fwrite($output, sprintf("\t%s: %s\n", $name, self::formatValue($apv)));
        } catch (\Throwable $e) {
            // The property is not present for this engine / resource key /
            // data file. Report it rather than aborting the whole example.
            fwrite($output, sprintf("\t%s: (not available - %s)\n", $name, $e->getMessage()));
        }
    }

    // The repositories every example lists under "Find out more".
    private const FIND_OUT_MORE_REPOSITORIES = [
        'This example' =>
            'https://github.com/51Degrees/51degrees-php/tree/main/ip-intelligence',
        'The IP intelligence engine' => 'https://github.com/51Degrees/51degrees-cxx',
        'The free data files' => 'https://github.com/51Degrees/data'
    ];

    // The 51degrees.com pages every example lists under "Find out more",
    // each with the utm_term its link carries.
    private const FIND_OUT_MORE_PAGES = [
        'IP intelligence' => ['ip-intelligence', 'find-out-more-ip-intelligence'],
        'Device detection' => ['device-detection', 'find-out-more-device-detection'],
        'Resource keys for the cloud service' => [
            'documentation/_services__cloud__resource_keys.html',
            'find-out-more-resource-keys'
        ],
        'Data files and pricing' => ['pricing', 'find-out-more-pricing'],
        'Contact us' => ['contact-us', 'find-out-more-contact']
    ];

    /**
     * A link to a page on 51degrees.com, tagged as printed by an example so
     * that a visit can be traced to the example that prompted it.
     *
     * @param string $page The page's path, such as contact-us
     * @param string $exampleFile The example's file, __FILE__
     * @param string $term Where in the example the link is
     */
    public static function getTaggedLink(
        string $page,
        string $exampleFile,
        string $term
    ): string {
        return 'https://51degrees.com/' .
            "{$page}?utm_source=code&utm_medium=example" .
            '&utm_campaign=51degrees-php&utm_content=' .
            self::getContentSlug($exampleFile) . "&utm_term={$term}";
    }

    /**
     * The "Find out more" section every example ends with, as text, giving
     * the person running it the links to follow up.
     *
     * @param string $exampleFile The example's file, __FILE__
     */
    public static function getFindOutMore(string $exampleFile): string
    {
        $lines = ['', 'Find out more', '-------------'];
        foreach (self::FIND_OUT_MORE_REPOSITORIES as $label => $url) {
            $lines[] = "{$label}: {$url}";
        }
        foreach (self::FIND_OUT_MORE_PAGES as $label => [$page, $term]) {
            $lines[] = "{$label}: " .
                self::getTaggedLink($page, $exampleFile, $term);
        }

        return implode("\n", $lines);
    }

    /**
     * The "Find out more" section for the end of a web page.
     *
     * @param string $exampleFile The page's file, __FILE__
     */
    public static function getFindOutMoreHtml(string $exampleFile): string
    {
        $items = '';
        foreach (self::FIND_OUT_MORE_REPOSITORIES as $label => $url) {
            $url = htmlspecialchars($url);
            $items .= "        <li>{$label}: <a href=\"{$url}\">{$url}</a></li>\n";
        }
        foreach (self::FIND_OUT_MORE_PAGES as $label => [$page, $term]) {
            $url = htmlspecialchars(
                self::getTaggedLink($page, $exampleFile, $term)
            );
            $items .= "        <li><a href=\"{$url}\">{$label}</a></li>\n";
        }

        return "<div id=\"find-out-more\">\n" .
            "    <h3 class=\"c-eg-page__heading\">Find out more</h3>\n" .
            "    <ul>\n{$items}    </ul>\n</div>\n";
    }

    /**
     * Writes the "Find out more" section to standard output, which a
     * console example ends with.
     *
     * @param string $exampleFile The example's file, __FILE__
     */
    public static function printFindOutMore(string $exampleFile): void
    {
        fwrite(STDOUT, self::getFindOutMore($exampleFile) . "\n");
    }

    /**
     * Write a message to standard error. A web example's server shows it in
     * its console, and it never reaches the page.
     */
    private static function writeError(string $message): void
    {
        file_put_contents('php://stderr', $message . "\n");
    }

    /**
     * The example's path from the package's folder, in lower case with a
     * hyphen for each folder separator, such as
     * examples-onpremise-gettingstarted-console.php.
     *
     * @param string $exampleFile The example's file
     */
    private static function getContentSlug(string $exampleFile): string
    {
        $path = str_replace('\\', '/', $exampleFile);
        $at = strrpos($path, '/examples/');
        $path = $at === false ? basename($path) : substr($path, $at + 1);

        return strtolower(str_replace('/', '-', $path));
    }
}
