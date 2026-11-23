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

namespace fiftyone\pipeline\core\tests\classes;

use fiftyone\pipeline\core\Pipeline;

/**
 * The Data Keys of the web integration elements a PipelineBuilder adds
 * around the elements it is given, as web-integration.md in the Pipeline
 * specification requires.
 */
class WebIntegrationKeys
{
    public const KEYS = [
        'sequence',
        'jsonbundler',
        'javascriptbuilder',
        'set-headers'
    ];

    /**
     * The Data Keys of a Pipeline's elements in order, leaving out the web
     * integration elements, so a test sees the elements it gave.
     *
     * @return string[]
     */
    public static function without(Pipeline $pipeline): array
    {
        return array_values(array_diff(
            array_keys($pipeline->flowElementsList),
            self::KEYS
        ));
    }
}
