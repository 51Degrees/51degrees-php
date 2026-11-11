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

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\engines\Engine;

/**
 * A stand-in for DeviceDetectionOnPremise carrying only the property
 * metadata and build-time indexes SwigData reads, so SwigData can be built
 * without the native extension.
 */
class StubSwigEngine extends Engine
{
    public string $dataKey = 'device';

    /**
     * The index of each build-time property name, keyed by the lowercase
     * name, as the real engine builds it from getRequiredProperties().
     *
     * @var array<string, int>
     */
    public array $propertyIndexes = [];

    /**
     * @param array<string, array<string, string>> $properties metadata keyed
     *   by lowercase property name, as the real engine builds it
     * @param null|array<string> $requiredProperties the build-time names in
     *   index order, defaulting to the metadata's own order
     */
    public function __construct(array $properties, ?array $requiredProperties = null)
    {
        $this->pipelines = [];
        $this->properties = $properties;

        $names = $requiredProperties ?? array_map(
            static fn (array $property): string => $property['name'],
            array_values($properties)
        );
        foreach ($names as $index => $name) {
            $this->propertyIndexes[strtolower($name)] = $index;
        }

        parent::__construct();
    }

    /**
     * The index of a build-time property name, or null when the engine was
     * not built with the name.
     */
    public function getPropertyIndex(string $name): ?int
    {
        return $this->propertyIndexes[strtolower($name)] ?? null;
    }

    public function processInternal(FlowData $flowData): void
    {
        // Never processed, as SwigData is built directly by the tests.
    }
}
