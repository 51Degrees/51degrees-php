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
     * The old name of the environment variable CLOUD_ENDPOINT_VARIABLE
     * names. It is no longer read.
     *
     * @deprecated use {@see Constants::CLOUD_ENDPOINT_VARIABLE}
     */
    public const FOD_CLOUD_API_URL = 'FOD_CLOUD_API_URL';

    // Default base url
    public const BASE_URL_DEFAULT = 'https://cloud.51degrees.com/api/v4/';

    // The Cloud Request Engine's Data Key.
    public const DATA_KEY = 'cloud-response';

    // The Data Key the Cloud Request Engine had before DATA_KEY, which still
    // finds the engine and its data and is logged as deprecated.
    public const DEPRECATED_DATA_KEY = 'cloud';

    // The one Property of the Cloud Request Engine's Element Data, the raw
    // JSON response body.
    public const JSON_RESPONSE_PROPERTY = 'json-response';

    // The name the Property had before JSON_RESPONSE_PROPERTY, which still
    // gives the response and is logged as deprecated.
    public const DEPRECATED_JSON_RESPONSE_PROPERTY = 'cloud';

    // Logged once by each engine whose Element Data is read by the
    // Property's deprecated name, which is named first, then the new one.
    public const MESSAGE_PROPERTY_DEPRECATED =
        "The Cloud Request Engine's Property '%s' is deprecated and will be " .
        "removed in a later release. Use '%s', which gives the same raw " .
        'JSON response.';

    // No Data in response message to be set in exception when cloud neither
    // return any data nor any error messages
    public const MESSAGE_NO_DATA_IN_RESPONSE = "No data in response from cloud service at '%s'";

    // Message when multiple errors are returned from cloud service
    public const EXCEPTION_CLOUD_ERRORS_MULTIPLE =
            'Multiple errors returned from 51Degrees cloud service. See inner ' .
            'exceptions for details.';

    // Message when single error is returned from cloud service
    public const EXCEPTION_CLOUD_ERROR =
            "Error returned from 51Degrees cloud service: '%s'";

    // Logged for each entry of the warnings array in a response.
    public const MESSAGE_CLOUD_WARNING =
            "Warning returned from 51Degrees cloud service: '%s'";

    // Logged when a discovery request made as the engine is built fails
    // with anything but a 4xx answer. The first placeholder is what was
    // asked for, and the second the failure.
    public const MESSAGE_DISCOVERY_DEFERRED =
        'CloudRequestEngine could not get the %s from the cloud service ' .
        'when it was built, and will ask again when it is first used. %s';

    // Refused when the discoveryResults setting is not what
    // CloudRequestEngine::getDiscoveryResults() gives.
    public const MESSAGE_DISCOVERY_RESULTS_NOT_A_COPY =
        'The discoveryResults setting is not a copy of the discovery ' .
        'results that CloudRequestEngine::getDiscoveryResults() gives. ' .
        'Leave the setting out so that the engine asks the cloud service, ' .
        'then keep what getDiscoveryResults() gives once it is built.';

    // Refused when the discoveryResults setting was made for other keys or
    // another cloud endpoint.
    public const MESSAGE_DISCOVERY_RESULTS_OTHER_CREDENTIALS =
        'The discoveryResults setting holds the discovery results for other ' .
        'keys or another cloud endpoint, and the results depend on both. ' .
        'Leave the setting out so that the engine asks the cloud service ' .
        'for these keys, then keep what getDiscoveryResults() gives once ' .
        'it is built.';

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

    // The timeout of a request to the cloud service unless the
    // timeoutSeconds setting gives one.
    public const TIMEOUT_SECONDS_DEFAULT = 2.0;

    // Refused as a timeout, with the value given, by the engine when it is
    // built and by HttpClient::setTimeoutSeconds.
    public const MESSAGE_TIMEOUT_NOT_POSITIVE =
        'The timeout of requests to the cloud service is %s, and it must ' .
        'be a number of seconds greater than 0, because a request with no ' .
        "timeout can wait forever. Leave CloudRequestEngine's " .
        'timeoutSeconds setting out for the default of 2 seconds.';
}
