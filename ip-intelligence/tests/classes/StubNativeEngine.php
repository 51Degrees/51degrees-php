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
 * holds, and records the calls made into it, so that a test can see what
 * crosses into native code without the extension.
 */
class StubNativeEngine
{
    /**
     * How many times getKeys was called.
     */
    public int $keyReads = 0;

    /**
     * How many times getMetaData was called.
     */
    public int $metaDataReads = 0;

    /**
     * Each name looked up in the property metadata, in call order.
     *
     * @var array<string>
     */
    public array $propertyLookups = [];

    /**
     * Each result process gave, in call order.
     *
     * @var array<StubSwigResult>
     */
    public array $results = [];

    /**
     * @var array<string>
     */
    private array $keys;

    /**
     * The type of each property, keyed by its name.
     *
     * @var array<string, string>
     */
    private array $types;

    /**
     * @var array<string, StubSwigValue>
     */
    private array $values;

    /**
     * @var null|array<string>
     */
    private ?array $refreshedKeys;

    /**
     * @param array<string> $keys the accepted Evidence keys, as getKeys
     *   gives them
     * @param array<string, string> $types the type of each property, keyed
     *   by its name, in the order the engine was built with them
     * @param array<string, StubSwigValue> $values keyed by property name
     * @param null|array<string> $refreshedKeys the keys getKeys gives after
     *   refreshData, or null to keep them
     */
    public function __construct(
        array $keys,
        array $types,
        array $values,
        ?array $refreshedKeys = null
    ) {
        $this->keys = $keys;
        $this->types = $types;
        $this->values = $values;
        $this->refreshedKeys = $refreshedKeys;
    }

    public function getKeys(): StubSwigList
    {
        ++$this->keyReads;

        return new StubSwigList($this->keys);
    }

    public function refreshData(?string $fileName = null): void
    {
        if ($this->refreshedKeys !== null) {
            $this->keys = $this->refreshedKeys;
        }
    }

    public function process(string $ipAddress): StubSwigResult
    {
        $result = new StubSwigResult($this->values, array_keys($this->types));
        $this->results[] = $result;

        return $result;
    }

    public function getRequiredProperties(): StubSwigList
    {
        return new StubSwigList(array_keys($this->types));
    }

    /**
     * The metadata, of which only the lookup of one property by name is
     * given, so that a read of the table of every property fails.
     */
    public function getMetaData(): object
    {
        ++$this->metaDataReads;

        return new class ($this) {
            private StubNativeEngine $engine;

            public function __construct(StubNativeEngine $engine)
            {
                $this->engine = $engine;
            }

            public function getProperties(): object
            {
                return new class ($this->engine) {
                    private StubNativeEngine $engine;

                    public function __construct(StubNativeEngine $engine)
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
     * A property's metadata, as the native property collection gives it,
     * or null when there is no property of that name.
     */
    public function property(string $name): ?object
    {
        $this->propertyLookups[] = $name;
        if (!isset($this->types[$name])) {
            return null;
        }

        return new class ($name, $this->types[$name]) {
            private string $name;

            private string $type;

            public function __construct(string $name, string $type)
            {
                $this->name = $name;
                $this->type = $type;
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
                return true;
            }
        };
    }
}
