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

namespace fiftyone\pipeline\translation;

/**
 * How a translation engine behaves when there is no translation for a
 * given value.
 */
class MissingTranslationBehavior
{
    // Use the original value if there is no translation for it. Default.
    public const ORIGINAL = 'Original';

    // Use an empty string if there is no translation for the value.
    public const EMPTY_STRING = 'EmptyString';

    // Add a flow error if there is no translation for the value.
    public const FLOW_ERROR = 'FlowError';

    /**
     * The behavior a configuration file names, matched ignoring case.
     *
     * @throws \InvalidArgumentException when the name is not one of the
     *   three behaviors
     */
    public static function fromName(string $name): string
    {
        foreach ([self::ORIGINAL, self::EMPTY_STRING, self::FLOW_ERROR] as $behavior) {
            if (strcasecmp($name, $behavior) === 0) {
                return $behavior;
            }
        }

        throw new \InvalidArgumentException(sprintf(
            "'%s' is not a missing translation behavior. Use %s, %s or %s.",
            $name,
            self::ORIGINAL,
            self::EMPTY_STRING,
            self::FLOW_ERROR
        ));
    }
}
