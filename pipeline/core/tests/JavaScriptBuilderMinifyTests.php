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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\PipelineBuilder;
use JShrink\Minifier;
use PHPUnit\Framework\TestCase;

/**
 * The JavaScript Builder minifies with an existing third-party library,
 * JShrink, rather than with a process of its own.
 */
class JavaScriptBuilderMinifyTests extends TestCase
{
    public function testTheMinifiedScriptIsJShrinksOutput()
    {
        $plain = self::script(false);
        $minified = self::script(true);

        // The two differ, or the comparison below would prove nothing.
        $this->assertNotSame($plain, $minified);
        $this->assertSame(Minifier::minify($plain), $minified);
    }

    /**
     * The script for one request, with a fixed session id and sequence so
     * that both scripts carry the same values.
     */
    private static function script(bool $minify): string
    {
        $flowData = (new PipelineBuilder([
            'javascriptBuilderSettings' => ['minify' => $minify]
        ]))->build()->createFlowData();
        $flowData->evidence->set('query.session-id', 'minify-test');
        $flowData->evidence->set('query.sequence', 1);
        $flowData->process();

        return $flowData->javascriptbuilder->javascript;
    }
}
