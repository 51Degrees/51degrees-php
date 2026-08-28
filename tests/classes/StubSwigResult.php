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

namespace fiftyone\pipeline\ipintelligence\tests\classes;

/**
 * Stands in for a SWIG wrapped ResultsIpi. Every accessor returns the
 * StubSwigValue registered under the requested property name, and records
 * which accessor family was used so a test can assert that a metadata type
 * routed to the intended one.
 */
class StubSwigResult
{
    /**
     * Property names read through one of the weighted list accessors.
     *
     * @var array<string>
     */
    public array $weightedRequests = [];

    /**
     * Property names read through one of the single value accessors.
     *
     * @var array<string>
     */
    public array $scalarRequests = [];

    /**
     * @var array<string, StubSwigValue>
     */
    private array $values;

    /**
     * @param array<string, StubSwigValue> $values keyed by property name
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function getValuesAsWeightedStringList(string $name): StubSwigValue
    {
        return $this->weighted($name);
    }

    public function getValuesAsWeightedIntegerList(string $name): StubSwigValue
    {
        return $this->weighted($name);
    }

    public function getValuesAsWeightedDoubleList(string $name): StubSwigValue
    {
        return $this->weighted($name);
    }

    public function getValuesAsWeightedBoolList(string $name): StubSwigValue
    {
        return $this->weighted($name);
    }

    public function getValueAsString(string $name): StubSwigValue
    {
        return $this->scalar($name);
    }

    public function getValueAsInteger(string $name): StubSwigValue
    {
        return $this->scalar($name);
    }

    public function getValueAsDouble(string $name): StubSwigValue
    {
        return $this->scalar($name);
    }

    public function getValueAsBool(string $name): StubSwigValue
    {
        return $this->scalar($name);
    }

    public function getValues(string $name): StubSwigValue
    {
        return $this->scalar($name);
    }

    private function weighted(string $name): StubSwigValue
    {
        $this->weightedRequests[] = $name;

        return $this->valueOf($name);
    }

    private function scalar(string $name): StubSwigValue
    {
        $this->scalarRequests[] = $name;

        return $this->valueOf($name);
    }

    private function valueOf(string $name): StubSwigValue
    {
        return $this->values[$name] ?? new StubSwigValue(false, null);
    }
}
