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

namespace fiftyone\pipeline\derivedproperty;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ElementDataDictionary;

/**
 * The derived properties of one request, under the element data key
 * `derived`. Each is an AspectPropertyValue, and one a script could not
 * produce has no value and a message naming every source property that was
 * missing. Names are matched without regard to case, and the keys of
 * asDictionary are lower case, as for the other elements.
 */
class DerivedPropertyData extends ElementDataDictionary
{
    /**
     * The name of each property as its script writes it, by its lower case
     * name.
     *
     * @var array<string, string>
     */
    private array $names = [];

    public function __construct(DerivedPropertyElement $flowElement)
    {
        parent::__construct($flowElement, []);
    }

    public function setValue(string $name, AspectPropertyValue $value): void
    {
        $key = strtolower($name);
        $this->contents[$key] = $value;
        $this->names[$key] = $name;
    }

    /**
     * @return AspectPropertyValue
     * @throws \Exception the element produces no property of that name
     */
    public function getInternal(string $key)
    {
        $lower = strtolower($key);
        if (array_key_exists($lower, $this->contents)) {
            return $this->contents[$lower];
        }

        throw new \Exception(sprintf(
            "There is no derived property '%s'. The derived properties are %s.",
            $key,
            $this->names ? implode(', ', $this->names) : '(none)'
        ));
    }
}
