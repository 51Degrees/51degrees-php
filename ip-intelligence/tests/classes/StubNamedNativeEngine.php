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
 * Stands in for the SWIG wrapped native engine that IpIntelligenceOnPremise
 * holds, created with names, and records what a read asks of it. As the
 * native engine does, its results keep the names it was created with through
 * refreshData, so each position names the same property after a refresh.
 * After refreshData, getRequiredProperties gives the later data set's names,
 * which the native engine never does, so that positions taken again from
 * them read the wrong property.
 */
class StubNamedNativeEngine
{
    public const KEYS = ['query.client-ip', 'server.client-ip'];

    /**
     * How many times getRequiredProperties was called.
     */
    public int $requiredReads = 0;

    /**
     * Each result process gave, in call order.
     *
     * @var array<StubSwigResult>
     */
    public array $results = [];

    /**
     * The names the engine was created with, in order.
     *
     * @var array<string>
     */
    private array $createdNames;

    /**
     * The type of each property of the data set in use, keyed by its name,
     * in that data set's order.
     *
     * @var array<string, string>
     */
    private array $types;

    /**
     * @var array<string, string>
     */
    private array $laterTypes;

    /**
     * @var array<string, StubSwigValue>
     */
    private array $values;

    /**
     * @param array<string, string> $types the type of each property the
     *   engine is created with, keyed by its name, in the order it is
     *   created with them
     * @param array<string, StubSwigValue> $values keyed by property name
     * @param array<string, string> $laterTypes the type of each property of
     *   the data set refreshData moves to, keyed by its name, in that data
     *   set's order
     */
    public function __construct(array $types, array $values, array $laterTypes)
    {
        $this->createdNames = array_keys($types);
        $this->types = $types;
        $this->values = $values;
        $this->laterTypes = $laterTypes;
    }

    public function getKeys(): StubSwigList
    {
        return new StubSwigList(self::KEYS);
    }

    public function getRequiredProperties(): StubSwigList
    {
        ++$this->requiredReads;

        return new StubSwigList(array_keys($this->types));
    }

    public function refreshData(?string $fileName = null): void
    {
        $this->types = $this->laterTypes;
    }

    public function process(string $ipAddress): StubSwigResult
    {
        $result = new StubSwigResult($this->values, $this->createdNames);
        $this->results[] = $result;

        return $result;
    }

    /**
     * The metadata, of which only the lookup of one property by name is
     * given.
     */
    public function getMetaData(): object
    {
        return new class ($this) {
            private StubNamedNativeEngine $engine;

            public function __construct(StubNamedNativeEngine $engine)
            {
                $this->engine = $engine;
            }

            public function getProperties(): object
            {
                return new class ($this->engine) {
                    private StubNamedNativeEngine $engine;

                    public function __construct(StubNamedNativeEngine $engine)
                    {
                        $this->engine = $engine;
                    }

                    public function getByKey(string $name): ?object
                    {
                        return $this->engine->property($name);
                    }
                };
            }
        };
    }

    /**
     * A property of the data set in use, as the native property collection
     * gives it, or null when the data set has no property of that name. As
     * in the native code, it is available only when the engine was created
     * with its name.
     */
    public function property(string $name): ?object
    {
        if (!isset($this->types[$name])) {
            return null;
        }

        return new class (
            $name,
            $this->types[$name],
            in_array($name, $this->createdNames, true)
        ) {
            private string $name;

            private string $type;

            private bool $available;

            public function __construct(string $name, string $type, bool $available)
            {
                $this->name = $name;
                $this->type = $type;
                $this->available = $available;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getType(): string
            {
                return $this->type;
            }

            public function getCategory(): string
            {
                return 'Network';
            }

            public function getAvailable(): bool
            {
                return $this->available;
            }
        };
    }
}
