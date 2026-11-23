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

namespace fiftyone\pipeline\devicedetection\tests\classes;

/**
 * The check the live tests in ExampleTests make of what the TAC and native
 * model lookup examples print, kept apart so that HardwareLookupOutputTests
 * can test it with no key.
 */
class HardwareLookupOutput
{
    // What the examples print when the resource key lacks the hardware
    // lookup.
    public const NO_HARDWARE_LOOKUP = 'does not include HardwarePlatform.Profiles';

    // How the examples print a property with no value, before its reason.
    private const NO_VALUE = 'Unknown';

    /**
     * Why the output fails the live test, or null when it passes. It fails
     * when the example said the resource key lacks the hardware lookup, and
     * otherwise when any value it looks up lists no device with a vendor
     * and a model under its heading.
     *
     * @param string $output Everything the example wrote
     * @param string $lookup The lookup, such as 'TAC lookup'
     * @param string $subject What each value is, as the heading names it
     * @param string[] $values The values the example looks up
     */
    public static function failure(
        string $output,
        string $lookup,
        string $subject,
        array $values
    ): ?string {
        if (strpos($output, self::NO_HARDWARE_LOOKUP) !== false) {
            return 'The resource key in ' . Constants::RESOURCE_ENV_VAR .
                ' does not include HardwarePlatform.Profiles, which the ' .
                "{$lookup} needs.\n{$output}";
        }

        $lines = explode("\n", $output);
        foreach ($values as $value) {
            $heading = array_search(
                "Which devices are associated with the {$subject} '{$value}'?",
                $lines,
                true
            );
            // The device lines are the tab indented lines under the heading.
            $listed = false;
            $at = $heading === false ? count($lines) : $heading + 1;
            while (isset($lines[$at]) && strpos($lines[$at], "\t") === 0) {
                if (self::hasVendorAndModel(substr($lines[$at], 1))) {
                    $listed = true;
                }
                $at++;
            }
            if (!$listed) {
                return 'No device with a vendor and a model was listed for ' .
                    "the {$subject} '{$value}'.\n{$output}";
            }
        }

        return null;
    }

    /**
     * Whether a device line, '<vendor> <name> (<model>)' without its tab,
     * has a vendor and a model. The vendor is the text before the first
     * space and the model the text inside the final parentheses, and
     * neither may be empty or a property with no value, which the examples
     * print as 'Unknown' and its reason.
     */
    public static function hasVendorAndModel(string $line): bool
    {
        $line = rtrim($line);
        $space = strpos($line, ' ');
        if ($space === false || substr($line, -1) !== ')') {
            return false;
        }

        // Find the parenthesis that opens the final one, as a model that
        // has no value holds its reason in parentheses of its own.
        $depth = 0;
        for ($open = strlen($line) - 1; $open >= 0; $open--) {
            if ($line[$open] === ')') {
                $depth++;
            } elseif ($line[$open] === '(' && --$depth === 0) {
                break;
            }
        }
        if ($open < 0) {
            return false;
        }

        $vendor = substr($line, 0, $space);
        $model = trim(substr($line, $open + 1, -1));

        foreach ([$vendor, $model] as $part) {
            if ($part === '' || strpos($part, self::NO_VALUE) === 0) {
                return false;
            }
        }

        return true;
    }
}
