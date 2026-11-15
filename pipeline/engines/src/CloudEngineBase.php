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
 * Intermediary between Engine and CloudEngine classes. This exists so that
 * the MissingPropertyService can check whether an engine is an instance of a
 * CloudEngine, and whether it has its property metadata.
 */
class CloudEngineBase extends Engine
{
    /**
     * Whether the engine has loaded its property metadata, being the
     * properties the Resource Key gives it, which is an empty list where the
     * key includes no product for the engine. An engine that has not yet
     * read the property list sets this false, so that a missing property is
     * not put down to the Resource Key.
     */
    public bool $propertyMetadataLoaded = true;

    /**
     * Whether the request for the property list failed, so that the engine
     * has not loaded its property metadata because of a failed request.
     */
    public bool $propertyListRequestFailed = false;
}
