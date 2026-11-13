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

namespace fiftyone\pipeline\derivedproperty\tests\classes;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;

/**
 * A source element for the tests, which gives fixed values under a data key
 * as the PHP engines do, keyed by lower case property names.
 */
class StubSourceElement extends FlowElement
{
    /**
     * @var null|array<string, mixed>
     */
    private ?array $values;

    /**
     * @param array<string, mixed> $values each property's value as the
     *     element data holds it, or null for an element that writes no
     *     element data at all
     * @param array<string> $names the properties the element says it
     *     gives, which may be more than it has values for
     */
    public function __construct(string $dataKey, ?array $values, array $names = [])
    {
        parent::__construct();
        $this->dataKey = $dataKey;
        $this->values = $values === null ? null : array_change_key_case($values);
        foreach (array_merge($names, array_keys($values ?? [])) as $name) {
            $this->properties[strtolower((string) $name)] = [
                'name' => (string) $name,
                'type' => 'String',
            ];
        }
    }

    /**
     * A value with no no value message.
     *
     * @param mixed $value
     */
    public static function value($value): AspectPropertyValue
    {
        $result = new AspectPropertyValue();
        $result->value = $value;

        return $result;
    }

    public function processInternal(FlowData $flowData): void
    {
        if ($this->values !== null) {
            $flowData->setElementData(new ElementDataDictionary($this, $this->values));
        }
    }
}
