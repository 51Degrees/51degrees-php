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

namespace fiftyone\pipeline\devicedetection\tests\classes;

/**
 * Stands in for a SWIG wrapped ResultsHash. Each accessor takes either a
 * property name or a required property index, as the native wrapper does,
 * and returns the StubSwigValue registered under the property name.
 *
 * An index is resolved through the required property names given at
 * construction, which stand for the list the engine was built with. A name
 * that list does not hold gives no value, which is what the native results
 * do for a property the loaded data set lacks.
 */
class StubSwigResult
{
    /**
     * Every argument an accessor was called with, in call order, so a test
     * can assert that reads went by index rather than by name.
     *
     * @var array<int|string>
     */
    public array $requestedKeys = [];

    /**
     * @var array<string, StubSwigValue>
     */
    private array $values;

    /**
     * The build-time required property names, in index order.
     *
     * @var array<string>
     */
    private array $requiredProperties;

    /**
     * @param array<string, StubSwigValue> $values keyed by property name
     * @param null|array<string> $requiredProperties build-time names in index
     *   order, defaulting to the value keys
     */
    public function __construct(array $values, ?array $requiredProperties = null)
    {
        $this->values = $values;
        $this->requiredProperties = $requiredProperties ?? array_keys($values);
    }

    /**
     * The name at a required property index, as the native wrapper gives it.
     */
    public function getPropertyName(int $requiredPropertyIndex): string
    {
        return $this->requiredProperties[$requiredPropertyIndex] ?? '';
    }

    /**
     * @param int|string $key property name or required property index
     */
    public function getValueAsString($key): StubSwigValue
    {
        return $this->read($key);
    }

    /**
     * @param int|string $key property name or required property index
     */
    public function getValueAsBool($key): StubSwigValue
    {
        return $this->read($key);
    }

    /**
     * @param int|string $key property name or required property index
     */
    public function getValueAsInteger($key): StubSwigValue
    {
        return $this->read($key);
    }

    /**
     * @param int|string $key property name or required property index
     */
    public function getValueAsDouble($key): StubSwigValue
    {
        return $this->read($key);
    }

    /**
     * @param int|string $key property name or required property index
     */
    public function getValues($key): StubSwigValue
    {
        return $this->read($key);
    }

    /**
     * The metric accessors, which take no property name because the data set
     * holds no property of that name.
     */
    public function getDifference(): int
    {
        return 0;
    }

    public function getMatchedNodes(): int
    {
        return 0;
    }

    public function getDrift(): int
    {
        return 0;
    }

    public function getIterations(): int
    {
        return 0;
    }

    public function getDeviceId(): string
    {
        return '0-0-0-0';
    }

    public function getUserAgents(): int
    {
        return 0;
    }

    /**
     * @param int|string $key property name or required property index
     */
    private function read($key): StubSwigValue
    {
        $this->requestedKeys[] = $key;

        $name = is_int($key) ? $this->getPropertyName($key) : $key;
        if ($name === '' || !in_array($name, $this->requiredProperties, true)) {
            return new StubSwigValue(false, null);
        }

        return $this->values[$name] ?? new StubSwigValue(false, null);
    }
}
