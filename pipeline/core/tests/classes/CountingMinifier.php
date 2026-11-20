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

namespace JShrink;

/**
 * Stands in for JShrink's Minifier, counting the scripts it is given and
 * returning each as it is, so that a test can tell how often the JavaScript
 * Builder minifies. The real Minifier has the same name, so only a test run
 * in its own process can load this file, before anything loads the real one.
 */
class Minifier
{
    /**
     * The scripts given to minify since the counting started.
     */
    public static int $scripts = 0;

    /**
     * @param string $js
     * @param array<string, mixed> $options
     */
    public static function minify($js, $options = []): string
    {
        self::$scripts++;

        return $js;
    }
}
