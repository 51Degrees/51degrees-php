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

use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use JShrink\Minifier;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * JShrink takes tens of milliseconds for the whole script, so a minifying
 * builder minifies its template once for each set of the template's choices
 * and writes each request's values into the minified text, rather than
 * running JShrink on every request. The test runs in its own process, where
 * a stand-in for JShrink's Minifier counts the scripts it is given.
 */
class JavaScriptBuilderMinifyOnceTests extends TestCase
{
    private const REQUESTS = 10;

    /**
     * A Pipeline kept between requests runs JShrink in its first request and
     * in the first request with each other set of choices, and not again for
     * a set it has met, though every request carries its own session id.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAKeptPipelineMinifiesOnceForEachSetOfChoices()
    {
        $this->assertFalse(
            class_exists(Minifier::class, false),
            'JShrink was loaded before the counting stand-in.'
        );
        require_once __DIR__ . '/classes/CountingMinifier.php';

        $pipeline = (new PipelineBuilder([
            'javascriptBuilderSettings' => ['minify' => true]
        ]))->build();

        for ($request = 0; $request < self::REQUESTS; $request++) {
            $this->assertStringContainsString(
                "cookies-on-$request",
                self::script($pipeline, "cookies-on-$request", true)
            );
            $this->assertSame(
                1,
                Minifier::$scripts,
                sprintf(
                    'Scripts given to JShrink after %d requests with the ' .
                    'same choices.',
                    $request + 1
                )
            );
        }

        // Cookies off is the second set of choices, met first here, then
        // the two sets take turns.
        for ($request = 0; $request < self::REQUESTS; $request++) {
            $cookies = $request % 2 === 1;
            $sessionId = ($cookies ? 'cookies-on-' : 'cookies-off-') .
                (self::REQUESTS + $request);
            $this->assertStringContainsString(
                $sessionId,
                self::script($pipeline, $sessionId, $cookies)
            );
            $this->assertSame(
                2,
                Minifier::$scripts,
                sprintf(
                    'Scripts given to JShrink after %d more requests with ' .
                    'two sets of choices.',
                    $request + 1
                )
            );
        }
    }

    private static function script(
        Pipeline $pipeline,
        string $sessionId,
        bool $cookies
    ): string {
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.session-id', $sessionId);
        $flowData->evidence->set(
            'query.fod-js-enable-cookies',
            $cookies ? 'true' : 'false'
        );
        $flowData->process();

        return $flowData->javascriptbuilder->javascript;
    }
}
