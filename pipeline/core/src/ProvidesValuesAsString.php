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

namespace fiftyone\pipeline\core;

/**
 * Implemented by Element Data that holds its Property values as strings and
 * parses them into the type each Property declares. It gives the string as
 * the data holds it, beside the parsed value that get() gives. For example,
 * a Boolean Property whose data holds "Unknown" is false through get(), and
 * "Unknown" through getValueAsString().
 *
 * Not every Element Data offers this, so test for it before using it:
 *
 *     if ($data instanceof ProvidesValuesAsString) {
 *         $stored = $data->getValueAsString('javascriptgethighentropyvalues');
 *     }
 *
 * See "Values stored as strings" in the pipeline specification's
 * features/value-types.md.
 */
interface ProvidesValuesAsString
{
    /**
     * Get the string the data holds for a Property, never one composed from
     * the parsed value. A Property with no value gives an
     * AspectPropertyValue with no value and the reason, and a Property the
     * data does not hold is reported in the same way get() reports it.
     *
     * @param string $key Property name, in any letter case
     */
    public function getValueAsString(string $key): AspectPropertyValue;
}
