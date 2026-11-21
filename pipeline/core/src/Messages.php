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

namespace fiftyone\pipeline\core;

/**
 * Constants used for messages returned to the user. These can be error
 * messages, or otherwise. Messages which require formatting will contain
 * format characters e.g. %s.
 */
class Messages
{
    /**
     * Written to PHP's error log, with the entry, when the Logger cannot
     * write to its file. The placeholder is the file.
     */
    public const LOG_FILE_NOT_WRITTEN = "The pipeline log entry could not be written to '%s', so it is written here:";

    /**
     * Error message thrown when there is no matching element in the FlowData.
     */
    public const NO_ELEMENT_DATA = "There is no element data for '%s' against this flow data. Available element data keys are: '%s'";
    public const NO_ELEMENT_DATA_NULL = "There is no element data for '%s' against this flow data.'";

    /**
     * Logged once when a deprecated Data Key is used. This takes the
     * deprecated key, then the key that replaces it.
     */
    public const DATA_KEY_DEPRECATED = "The Data Key '%s' is deprecated and will be removed in a later release. Use '%s', which gives the same element and data.";
    public const PASS_KEY_VALUE = 'Must pass key and value';
    public const FLOW_DATA_PROCESSED = 'FlowData already processed';

    /**
     * Thrown when a property of a Pipeline other than a setting, such as
     * flowElements, is set. This takes the name of the property as a format
     * argument.
     */
    public const PIPELINE_IMMUTABLE = "A Pipeline cannot be changed once it is built, so '%s' cannot be set. Build a new Pipeline with the FlowElements it needs instead.";

    /**
     * Thrown when a setting of a Pipeline, such as logger, is set. This
     * takes the name of the setting as a format argument.
     */
    public const PIPELINE_SETTING_IMMUTABLE = "A Pipeline cannot be changed once it is built, so '%s' cannot be set. Give it in the settings when the Pipeline is built instead.";

    /**
     * Thrown when a Pipeline has no element with the key asked for. This
     * takes the key, then the Data Keys of the Pipeline's elements.
     */
    public const NO_ELEMENT = "There is no element for '%s' in this Pipeline. Available element keys are: '%s'";

    /**
     * A client-side response was asked for with a FlowData that has been
     * processed, so the Evidence the request supplied cannot be told from
     * the Evidence processing added.
     */
    public const CLIENT_SIDE_PROCESSED = 'Pass the FlowData for a client-side response before it is processed, because its ETag is calculated from the Evidence the request supplied.';

    /**
     * The JSON Bundler found no sequence number from a Sequence Element.
     */
    public const NO_SEQUENCE = 'The JSON Bundler needs a sequence number. Place a Sequence Element before the JSON Bundler in the Pipeline.';

    /**
     * Property does not start with SetHeader. This takes the name of a property as a format argument.
     */
    public const PROPERTY_NOT_SET_HEADER = "Property Name '%s' does not start with 'SetHeader'.";

    /**
     * Property Name is not in the valid format. This takes the property name
     * as format argument.
     */
    public const WRONG_PROPERTY_FORMAT = "Property Name '%s' is not in the expected format i.e. SetHeader[Component][HeaderName]. ";

    /**
     * Element not found in flowData. This takes the element datakey
     * as format argument.
     */
    public const ELEMENT_NOT_FOUND = "Element '%s' is not present in the FlowData. ";

    /**
     * Property not found in flowData. This takes the element dataKey
     * and property names as format arguments.
     */
    public const PROPERTY_NOT_FOUND = "Property '%s' is not present in the FlowData against '%s' ElementData. ";

    /**
     * A typed getter was asked for a Property whose value is not a single
     * value, such as a list. This takes the property name, then the type
     * asked for, as format arguments.
     */
    public const PROPERTY_NOT_SCALAR = "The value of property '%s' is not a single value, so it cannot be read as %s.";
}
