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

namespace fiftyone\pipeline\cloudrequestengine;

class Constants
{
    // Environment variable to set cloud endpoint
    public const CLOUD_ENDPOINT_VARIABLE = '_51DEGREES_CLOUD_ENDPOINT';

    /**
     * Environment variable read for the cloud endpoint when
     * CLOUD_ENDPOINT_VARIABLE is not set or is empty.
     *
     * @deprecated use {@see Constants::CLOUD_ENDPOINT_VARIABLE}
     */
    public const FOD_CLOUD_API_URL = 'FOD_CLOUD_API_URL';

    // Logged once by each engine whose cloud endpoint came from the
    // deprecated environment variable, which is named first, then the
    // one that replaces it.
    public const MESSAGE_ENDPOINT_VARIABLE_DEPRECATED =
        'The cloud endpoint was read from the environment variable %s, ' .
        'which is deprecated. %s replaces it, so set that to the same ' .
        'value.';

    // Default base url
    public const BASE_URL_DEFAULT = 'https://cloud.51degrees.com/api/v4/';

    // No Data in response message to be set in exception when cloud neither
    // return any data nor any error messages
    public const MESSAGE_NO_DATA_IN_RESPONSE = 'No data in response from cloud service at %s';

    // Message when multiple errors are returned from cloud service
    public const EXCEPTION_CLOUD_ERRORS_MULTIPLE =
            'Multiple errors returned from 51Degrees cloud service. See inner ' .
            'exceptions for details.';

    // Message when single error is returned from cloud service
    public const EXCEPTION_CLOUD_ERROR =
            "Error returned from 51Degrees cloud service: '%s'";

    // Error message when non-success status is returned.
    public const MESSAGE_ERROR_CODE_RETURNED = "Cloud service at '%s' returned status code '%s' with content %s";

    // Message when the request never reached the cloud service, for
    // example where the name does not resolve, the connection is refused,
    // the certificate cannot be checked or the request times out.
    public const MESSAGE_REQUEST_FAILED = "The request to the cloud service at '%s' could not be made: %s";

    // The names the cloud service reads the keys and the requested
    // properties from, in the query string of a GET and the body of a POST.
    public const RESOURCE_PARAMETER = 'resource';
    public const LICENSE_PARAMETER = 'license';
    public const VALUES_PARAMETER = 'values';

    // The path below the base URL of the data route that takes no resource
    // key, used when the engine authenticates on a license key alone.
    public const LICENSE_DATA_PATH = 'json';

    // Refused when the engine is built, each saying why the cloud service
    // would refuse every request.
    public const MESSAGE_NO_CREDENTIAL =
        'CloudRequestEngine needs a resourceKey or a licenseKey to ' .
        'authenticate with, as the cloud service answers 401 to a request ' .
        'that carries neither. Create a resource key for free at ' .
        'https://51degrees.com/configure?utm_source=code&utm_medium=comment&utm_campaign=51degrees-php&utm_content=src-constants.php&utm_term=no-credential';
    public const MESSAGE_LICENSE_KEY_NEEDS_PROPERTIES =
        'CloudRequestEngine has a licenseKey and no resourceKey or ' .
        'requestedProperties. A license key names no properties of its own, ' .
        'so the cloud service answers 400 to every request made with one ' .
        'that does not list the properties it wants. Set ' .
        'requestedProperties to the fully qualified names wanted, for ' .
        "example ['device.ismobile', 'device.iscrawler'].";

    // Logged once when an answer leaves out requested properties.
    public const MESSAGE_PROPERTIES_NOT_COVERED =
        'The cloud service did not return the requested properties %s. ' .
        'They are not covered by the keys in use, so they will be missing ' .
        'from every result. The other requested properties came back and ' .
        'are usable.';

    // Logged once when an answer to a resource key with no license key
    // leaves out requested properties.
    public const MESSAGE_NOT_PROPERTIES =
        'The cloud service did not return the requested properties %s. ' .
        'They are not properties the cloud service knows, so they will be ' .
        'missing from every result. Check each name against the property ' .
        'dictionary. The other requested properties came back and are ' .
        'usable.';

    // Evidence key seperator
    public const EVIDENCE_SEPERATOR = '.';

    // Used to prefix evidence that is obtained from HTTP headers
    public const EVIDENCE_HTTPHEADER_PREFIX = 'header';

    // Used to prefix evidence that is obtained from HTTP bookies
    public const EVIDENCE_COOKIE_PREFIX = 'cookie';

    // Used to prefix evidence that is obtained from an HTTP request's
    // query string or is passed into the pipeline for off-line
    // processing.
    public const EVIDENCE_QUERY_PREFIX = 'query';

    // The prefixes after query, header and cookie, highest precedence
    // first, as evidence.md lists them. Any other prefix ranks after these,
    // alphabetically.
    public const OTHER_PREFIX_PRECEDENCE = ['server', 'fiftyone', 'location'];

    // other evidence constant
    public const EVIDENCE_OTHER = 'other';

    // Names the engine sends from its own settings, which Evidence must
    // not supply.
    public const RESERVED_EVIDENCE_SUFFIXES = [
        'resource', 'license', 'licence', 'values',
    ];

    // warning message to be shown for conflicted evidences
    public const WARNING_MESSAGE = "'%s=>%s' evidence conflicts with ";

    // The suffix that is used to identify a TAC in evidence.
    // https://en.wikipedia.org/wiki/Type_Allocation_Code
    public const EVIDENCE_TAC_SUFFIX = 'tac';

    // The complete key for supplying a TAC as evidence.
    public const EVIDENCE_QUERY_TAC_KEY =
        Constants::EVIDENCE_QUERY_PREFIX .
        Constants::EVIDENCE_SEPERATOR .
        Constants::EVIDENCE_TAC_SUFFIX;

    // The suffix that is used to identify a native model name in evidence.
    // This is the text returned by
    // https://developer.android.com/reference/android/os/Build#MODEL
    // for Android devices and by
    // https://gist.github.com/soapyigu/c99e1f45553070726f14c1bb0a54053b#file-machinename-swift
    // for iOS devices.
    public const EVIDENCE_NATIVE_MODEL_SUFFIX = 'nativemodel';

    // The complete key for supplying a native model name as evidence.
    public const EVIDENCE_QUERY_NATIVE_MODEL_KEY =
        Constants::EVIDENCE_QUERY_PREFIX .
        Constants::EVIDENCE_SEPERATOR .
        Constants::EVIDENCE_NATIVE_MODEL_SUFFIX;

    public const CLOUDREQUESTENGINE_EXCEPTION_MESSAGE =
        'CloudRequestEngine needs to be placed before cloud elements in Pipeline';
}
