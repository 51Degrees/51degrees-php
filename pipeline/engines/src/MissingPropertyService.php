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
    private bool $cloudRequestFailed;

    /**
     * @param bool $cloudRequestFailed true for the Element Data of a cloud
     *   engine whose cloud request for the FlowData failed, so that every
     *   property gives the CloudRequestFailed reason
     */
    public function __construct(bool $cloudRequestFailed = false)
    {
        $this->cloudRequestFailed = $cloudRequestFailed;
    }

    /**
     * @throws \Exception
     * @return mixed
     */
    public function check(string $propertyName, Engine $flowElement)
    {
        throw new \Exception($this->getMessage($propertyName, $flowElement));
    }

    /**
     * Get the message to go with the exception, deciding the reason in the
     * order the pipeline specification gives, at
     * https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/properties.md#deciding-the-reason
     */
    private function getMessage(string $propertyName, Engine $flowElement): string
    {
        $reason = MissingPropertyReason::Unknown;
        $properties = $flowElement->getProperties();
        $property = null;
        $isCloud = $flowElement instanceof CloudEngineBase;

        foreach ($properties as $currentProperty) {
            if (isset($currentProperty['name'])) {
                if (strcasecmp($currentProperty['name'], $propertyName) == 0) {
                    $property = $currentProperty;
                    break;
                }
            }
        }

        // A cloud engine has no data file, so only an on-premise engine
        // compares its data tier, and only where the metadata gives the
        // tiers the property is present in.
        $tiers = $property !== null ? self::dataTiers($property) : null;
        if ($this->cloudRequestFailed) {
            $reason = MissingPropertyReason::CloudRequestFailed;
        } elseif ($property !== null && !$isCloud && is_array($tiers) &&
            !self::containsTier($tiers, $flowElement->getDataSourceTier())) {
            $reason = MissingPropertyReason::DataFileUpgradeRequired;
        } elseif ($property !== null && ($property['available'] ?? true) === false) {
            $reason = MissingPropertyReason::PropertyExcludedFromEngineConfiguration;
        } elseif ($flowElement instanceof CloudEngineBase && $flowElement->propertyMetadataLoaded) {
            $reason = count($properties) === 0
                ? MissingPropertyReason::ProductNotAccessibleWithResourceKey
                : MissingPropertyReason::PropertyNotAccessibleWithResourceKey;
        } elseif ($flowElement instanceof CloudEngineBase && $flowElement->propertyListRequestFailed) {
            // The metadata was not loaded because the request for the
            // property list failed. Not loaded for any other reason, the
            // reason is unknown.
            $reason = MissingPropertyReason::CloudRequestFailed;
        }

        // Build the message string to return to the caller.
        $message = sprintf(MissingPropertyMessages::PREFIX, $propertyName, $flowElement->dataKey);

        switch ($reason) {
            case MissingPropertyReason::DataFileUpgradeRequired:
                $message .= count($tiers) === 0
                    ? MissingPropertyMessages::DATA_UPGRADE_REQUIRED_TIERS_NOT_KNOWN
                    : sprintf(
                        MissingPropertyMessages::DATA_UPGRADE_REQUIRED,
                        join(',', $tiers),
                        get_class($flowElement)
                    );
                break;
            case MissingPropertyReason::PropertyExcludedFromEngineConfiguration:
                $message .= MissingPropertyMessages::PROPERTY_EXCLUDED;
                break;
            case MissingPropertyReason::ProductNotAccessibleWithResourceKey:
                $message .= sprintf(
                    MissingPropertyMessages::PRODUCT_NOT_IN_CLOUD_RESOURCE,
                    $flowElement->dataKey
                );
                break;
            case MissingPropertyReason::PropertyNotAccessibleWithResourceKey:
                $message .= sprintf(
                    MissingPropertyMessages::PROPERTY_NOT_IN_CLOUD_RESOURCE,
                    $flowElement->dataKey,
                    join(',', $this->getPropertyNames($properties))
                );
                break;
            case MissingPropertyReason::CloudRequestFailed:
                $message .= MissingPropertyMessages::CLOUD_REQUEST_FAILED;
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
     * The data tiers a property's metadata says it is present in, or null
     * where the metadata does not say. The engines name the list
     * differently, Device Detection dataFiles and IP Intelligence datafiles,
     * so the key is found ignoring case, under either name or under
     * dataTiersWherePresent.
     *
     * @param array<string, mixed> $property
     * @return null|array<string>
     */
    private static function dataTiers(array $property): ?array
    {
        foreach ($property as $key => $value) {
            $lower = strtolower((string) $key);
            if (($lower === 'datatierswherepresent' || $lower === 'datafiles') &&
                is_array($value)) {
                return array_values(array_map('strval', $value));
            }
        }

        return null;
    }

    /**
     * Whether a data tier is in a list, ignoring case.
     *
     * @param array<string> $tiers
     */
    private static function containsTier(array $tiers, string $tier): bool
    {
        foreach ($tiers as $each) {
            if (strcasecmp($each, $tier) === 0) {
                return true;
            }
        }

        return false;
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
