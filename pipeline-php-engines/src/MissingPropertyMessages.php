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
 * Messages which may be reused for various missing property exceptions.
 */
class MissingPropertyMessages
{
    /**
     * String to prefix all messages with. This takes the name of a property
     * and the name of the element as format arguments.
     */
    public const PREFIX = "Property '%s' not found in data for element '%s'. ";
    /**
     * A data upgrade is required (e.g. free to paid). This takes the data tier
     * name which contains the property and the name of the product as format
     * arguments.
     */
    public const DATA_UPGRADE_REQUIRED =
        'This is because your license and/or data file ' .
        'does not include this property. The property is available ' .
        'with the %s license/data for the %s';
    /**
     * The property was excluded from the engine's configuration. This takes no
     * format arguments.
     */
    public const PROPERTY_EXCLUDED = 'This is because the property has been excluded when configuring the engine.';
    /**
     * The engine was a cloud engine, and the resource key does not contain the
     * required product (e.g. device). This takes the name of the product as a
     * format argument.
     */
    public const PRODUCT_NOT_IN_CLOUD_RESOURCE =
        'This is because your resource key does not include access to ' .
        "any properties under '%s'. For more details on resource keys, " .
        'see our explainer: ' .
        'https://51degrees.com/documentation/_info__resource_keys.html?utm_source=code&utm_medium=comment&utm_campaign=pipeline-php-engines&utm_content=src-missingpropertymessages.php&utm_term=product_not_in_cloud_resource';
    /**
     * The engine was a cloud engine, and the resource key does not contain the
     * required property (e.g. hardwarename). This takes the name of the product
     * and the properties the resource contains as format arguments.
     */
    public const PROPERTY_NOT_IN_CLOUD_RESOURCE =
        'This is because your resource key does not include access to ' .
        'this property. Properties that are included for this key under ' .
        "'%s' are %s. For more details on resource keys, see our " .
        'explainer: ' .
        'https://51degrees.com/documentation/_info__resource_keys.html?utm_source=code&utm_medium=comment&utm_campaign=pipeline-php-engines&utm_content=src-missingpropertymessages.php&utm_term=property_not_in_cloud_resource';
    /**
     * The reason for the missing property is none of the above. This takes no
     * format arguments.
     */
    public const UNKNOWN =
        'The reason for this is unknown. Please check that the aspect ' .
        'and property name are correct.';
}
