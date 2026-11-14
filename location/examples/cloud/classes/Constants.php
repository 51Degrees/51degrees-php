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



namespace fiftyone\pipeline\geolocation\examples\cloud\classes;

class Constants
{
    // The environment variable the examples read the resource key from.
    const RESOURCE_KEY_VARIABLE = '_51DEGREES_RESOURCE_KEY';

    /**
     * The environment variable read for the resource key when
     * RESOURCE_KEY_VARIABLE is not set or is empty.
     *
     * @deprecated use {@see Constants::RESOURCE_KEY_VARIABLE}
     */
    const RESOURCE_KEY_ENV_VAR = 'resource_key';

    // The repository every example lists under "Find out more".
    private const FIND_OUT_MORE_REPOSITORIES = [
        'This example' =>
            'https://github.com/51Degrees/51degrees-php/tree/main/location'
    ];

    // The 51degrees.com pages every example lists under "Find out more",
    // each with the utm_term its link carries.
    private const FIND_OUT_MORE_PAGES = [
        'Location' => ['geolocation', 'find-out-more-location'],
        'Resource keys' => [
            'documentation/_services__cloud__resource_keys.html',
            'find-out-more-resource-keys'
        ],
        'Pricing' => ['pricing', 'find-out-more-pricing'],
        'Contact us' => ['contact-us', 'find-out-more-contact']
    ];

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
            "    <h3>Find out more</h3>\n" .
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
     * The example's path from the package's folder, in lower case with a
     * hyphen for each folder separator, such as
     * examples-cloud-gettingstarted.php.
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