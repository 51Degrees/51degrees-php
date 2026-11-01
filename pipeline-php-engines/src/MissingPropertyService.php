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

namespace fiftyone\pipeline\engines;

/**
 * A missing property service runs when a property is not available in the
 * aspectData. It can be extended to provide a specific message for why the property
 * is not available.
 */
class MissingPropertyService
{
    /**
     * @throws \Exception
     * @return mixed
     */
    public function check(string $propertyName, Engine $flowElement)
    {
        throw new \Exception($this->getMessage($propertyName, $flowElement));
    }

    /**
     * Get the message to go with the exception.
     */
    private function getMessage(string $propertyName, Engine $flowElement): string
    {
        $reason = MissingPropertyReason::Unknown;
        $property = null;

        foreach ($flowElement->getProperties() as $currentProperty) {
            if (isset($currentProperty['name'])) {
                if (strcasecmp($currentProperty['name'], $propertyName) == 0) {
                    $property = $currentProperty;
                    break;
                }
            }
        }

        if ($property !== null) {
            // Check if the property is available in the data file that is
            // being used by the engine.
            $containsDataTier = false;
            if (isset($property['datatierswherepresent'])) {
                foreach ($property['datatierswherepresent'] as $tier) {
                    if ($tier === $flowElement->getDataSourceTier()) {
                        $containsDataTier = true;
                        break;
                    }
                }

                if ($containsDataTier === false) {
                    $reason = MissingPropertyReason::DataFileUpgradeRequired;
                }
                // Check if the property is excluded from the results.
                elseif ($property['available'] === false) {
                    $reason = MissingPropertyReason::PropertyExcludedFromEngineConfiguration;
                }
            }
        } else {
            if ($flowElement instanceof CloudEngineBase) {
                if (count($flowElement->getProperties()) === 0) {
                    $reason = MissingPropertyReason::ProductNotAccessibleWithResourceKey;
                } else {
                    $reason = MissingPropertyReason::PropertyNotAccessibleWithResourceKey;
                }
            }
        }

        // Build the message string to return to the caller.
        $message = sprintf(MissingPropertyMessages::PREFIX, $propertyName, $flowElement->dataKey);
        
        switch ($reason) {
            case MissingPropertyReason::DataFileUpgradeRequired:
                $message .= sprintf(
                    MissingPropertyMessages::DATA_UPGRADE_REQUIRED,
                    join(',', $property['datatierswherepresent']),
                    get_class($flowElement)
                );
                break;
            case MissingPropertyReason::PropertyExcludedFromEngineConfiguration:
                $message .= MissingPropertyMessages::PROPERTY_EXCLUDED;
                break;
            case MissingPropertyReason::ProductNotAccessibleWithResourceKey:
                $message .= sprintf(
                    MissingPropertyMessages::PRODUCT_NOT_IN_CLOUD_RESOURCE,
                    get_class($flowElement)
                );
                break;
            case MissingPropertyReason::PropertyNotAccessibleWithResourceKey:
                $available = $this->getPropertyNames($flowElement->getProperties());
                $message .= sprintf(
                    MissingPropertyMessages::PROPERTY_NOT_IN_CLOUD_RESOURCE,
                    $flowElement->dataKey,
                    join(', ', $available)
                );
                break;
            case MissingPropertyReason::Unknown:
                $message .= MissingPropertyMessages::UNKNOWN;
                break;
            default:
                break;
        }

        return $message;
    }

    /**
     * Get an array of property names from an array of properties.
     *
     * @param array<string, array<string, mixed>> $properties
     * @return array<int, mixed>
     */
    private function getPropertyNames(array $properties): array
    {
        $names = [];
        
        foreach ($properties as $property) {
            $names[] = $property['name'];
        }

        return $names;
    }
}
