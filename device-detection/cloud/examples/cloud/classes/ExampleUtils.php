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

namespace fiftyone\pipeline\devicedetection\examples\cloud\classes;

use fiftyone\pipeline\core\AspectPropertyValue;

class ExampleUtils
{
    // The environment variable used to get the resource key to use when
    // running examples. This follows the 51Degrees convention that every
    // resource key variable starts with '_51DEGREES_RESOURCE_KEY'.
    public const RESOURCE_KEY_ENV_VAR = '_51DEGREES_RESOURCE_KEY';

    /**
     * The environment variable read for the resource key when
     * RESOURCE_KEY_ENV_VAR is not set or is empty.
     *
     * @deprecated use {@see ExampleUtils::RESOURCE_KEY_ENV_VAR}
     */
    public const LEGACY_RESOURCE_KEY_ENV_VAR = 'resource_key';

    // The query string parameter the web examples accept a resource key on.
    // This is part of the URL rather than the environment, so it keeps its
    // own name.
    public const RESOURCE_KEY_QUERY_PARAM = 'resource_key';

    // The environment variable that can name the cloud service to use.
    public const CLOUD_ENDPOINT_VARIABLE = '_51DEGREES_CLOUD_ENDPOINT';

    /**
     * The environment variable read for the cloud service when
     * CLOUD_ENDPOINT_VARIABLE is not set or is empty.
     *
     * @deprecated use {@see ExampleUtils::CLOUD_ENDPOINT_VARIABLE}
     */
    public const ENDPOINT_ENV_VAR = 'cloud_endpoint';

    // The repository every example lists under "Find out more".
    private const FIND_OUT_MORE_REPOSITORIES = [
        'This example' =>
            'https://github.com/51Degrees/51degrees-php/tree/main/device-detection/cloud'
    ];

    // The 51degrees.com pages every example lists under "Find out more",
    // each with the utm_term its link carries.
    private const FIND_OUT_MORE_PAGES = [
        'Device detection' => ['device-detection', 'find-out-more-device-detection'],
        'Resource keys' => [
            'documentation/_services__cloud__resource_keys.html',
            'find-out-more-resource-keys'
        ],
        'Pricing' => ['pricing', 'find-out-more-pricing'],
        'Contact us' => ['contact-us', 'find-out-more-contact']
    ];

    public static function getResourceKeyFromEnv()
    {
        $key = EnvironmentVariable::read(
            self::RESOURCE_KEY_ENV_VAR,
            self::LEGACY_RESOURCE_KEY_ENV_VAR
        );

        return $key === '' ? null : $key;
    }

    public static function getCloudEndpoint()
    {
        $endpoint = EnvironmentVariable::read(
            self::CLOUD_ENDPOINT_VARIABLE,
            self::ENDPOINT_ENV_VAR
        );

        return $endpoint === '' ? null : $endpoint;
    }

    public static function getResourceKeyFromCliArgs($argv)
    {
        if (is_array($argv) && count($argv) > 0) {
            return $argv[0];
        }

        return null;
    }

    public static function getResourceKeyFromQueryParameter()
    {
        return $_GET[self::RESOURCE_KEY_QUERY_PARAM] ?? null;
    }

    public static function getResourceKeyFromConfig($config)
    {
        $key = null;

        foreach ($config['PipelineOptions']['Elements'] as $element) {
            if (
                $element['BuilderName'] === 'fiftyone\\pipeline\\cloudrequestengine\\CloudRequestEngine' &&
                !empty($element['BuildParameters']['resourceKey']) &&
                strpos($element['BuildParameters']['resourceKey'], '!!') !== 0
            ) {
                $key = $element['BuildParameters']['resourceKey'];
                break;
            }
        }

        return $key;
    }

    public static function setResourceKeyInConfig(&$config, $key)
    {
        foreach ($config['PipelineOptions']['Elements'] as &$element) {
            if ($element['BuilderName'] === 'fiftyone\\pipeline\\cloudrequestengine\\CloudRequestEngine') {
                $element['BuildParameters']['resourceKey'] = $key;
            }
        }
    }

    public static function output($message)
    {
        if (php_sapi_name() == 'cli') {
            echo $message . "\n";
        } else {
            echo "<pre>{$message}\n</pre>";
        }
    }

    /**
     * Format a property value for display.
     *
     * A property can be unavailable for three reasons, and each one reads
     * differently so the person running the example can tell them apart.
     * The property may have a value, it may have no value with the cloud
     * service giving a reason (most often that the resource key is not
     * entitled to it), or it may not be in the results at all.
     *
     * @param mixed $device An AspectDataDictionary or an array of values
     * @param string $name The property name
     * @return string
     */
    public static function getHumanReadable($device, $name)
    {
        $value = self::getPropertyValue($device, $name);

        if ($value === null) {
            return "Unknown (the property '" . $name . "' is not in the " .
                'results, so the current resource key does not include it)';
        }

        if ($value->hasValue) {
            if (is_array($value->value)) {
                return implode(', ', $value->value);
            }

            return $value->value;
        }

        $reason = $value->noValueMessage;

        if (empty($reason)) {
            $reason = "the cloud service returned no value for '" . $name .
                "' and gave no reason";
        }

        return 'Unknown (' . $reason . ')';
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
     * Log and print the message, then the "Find out more" section when the
     * example's file is given, and exit with status 1.
     *
     * @param mixed $logger
     * @param string $message
     * @param null|string $exampleFile The example's file, __FILE__
     */
    public static function logErrorAndExit($logger, $message, $exampleFile = null)
    {
        $logger->log('error', $message);

        echo $message . PHP_EOL;

        if ($exampleFile !== null) {
            self::printFindOutMore($exampleFile);
        }

        exit(1);
    }

    /**
     * A link to a page on 51degrees.com, tagged as printed by an example so
     * that a visit can be traced to the example that prompted it.
     *
     * @param string $page The page's path, such as contact-us
     * @param string $exampleFile The example's file, __FILE__
     * @param string $term Where in the example the link is
     * @return string
     */
    public static function getTaggedLink($page, $exampleFile, $term)
    {
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
     * @return string
     */
    public static function getFindOutMore($exampleFile)
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
     * @return string
     */
    public static function getFindOutMoreHtml($exampleFile)
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
     * Writes the "Find out more" section, as text from the command line and
     * as HTML from a web server.
     *
     * @param string $exampleFile The example's file, __FILE__
     */
    public static function printFindOutMore($exampleFile)
    {
        echo php_sapi_name() === 'cli'
            ? self::getFindOutMore($exampleFile) . "\n"
            : self::getFindOutMoreHtml($exampleFile);
    }

    /**
     * Read the list of hardware profiles from the 'hardware' element data.
     *
     * An empty list is returned when the resource key has no access to the
     * hardware aspect at all, because reading a property that is not there
     * raises an exception rather than returning an empty list.
     *
     * @param mixed $hardware The 'hardware' element data
     * @return array<int, mixed>
     */
    public static function getProfiles($hardware)
    {
        try {
            $profiles = $hardware->profiles;
        } catch (\Exception $e) {
            return [];
        }

        return is_array($profiles) ? $profiles : [];
    }

    /**
     * The line printed when a lookup returned no device profiles at all.
     *
     * @return string
     */
    public static function getNoProfilesMessage()
    {
        return "\tNo device profiles were returned. The current resource " .
            'key does not include the hardware properties this example ' .
            'needs. See https://51degrees.com/pricing?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-cloud-classes-exampleutils.php&utm_term=no-profiles';
    }

    /**
     * Read a single AspectPropertyValue out of an element data instance or
     * an array of values, returning null when it is not present rather than
     * raising a warning.
     *
     * @param mixed $device An AspectDataDictionary or an array of values
     * @param string $name The property name
     * @return null|AspectPropertyValue
     */
    private static function getPropertyValue($device, $name)
    {
        $value = null;

        if (is_array($device)) {
            $value = $device[$name] ?? null;
        } elseif (is_object($device)) {
            try {
                $value = $device->{$name};
            } catch (\Exception $e) {
                return null;
            }
        }

        return $value instanceof AspectPropertyValue ? $value : null;
    }

    /**
     * The example's path from the package's folder, in lower case with a
     * hyphen for each folder separator, such as
     * examples-cloud-gettingstartedconsole.php.
     *
     * @param string $exampleFile The example's file
     * @return string
     */
    private static function getContentSlug($exampleFile)
    {
        $path = str_replace('\\', '/', $exampleFile);
        $at = strrpos($path, '/examples/');
        $path = $at === false ? basename($path) : substr($path, $at + 1);

        return strtolower(str_replace('/', '-', $path));
    }
}
