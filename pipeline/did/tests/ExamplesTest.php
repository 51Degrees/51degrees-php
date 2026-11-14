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

namespace fiftyone\pipeline\did\tests;

use PHPUnit\Framework\TestCase;

/**
 * Runs the examples as someone runs them from a fresh clone, with no
 * resource key set, each in its own PHP. Each must end with the Find out
 * more section, with every 51degrees.com link it prints tagged.
 */
class ExamplesTest extends TestCase
{
    // The environment variables the creator context server reads the
    // resource key from.
    private const RESOURCE_KEY_VARIABLES = ['_51DEGREES_RESOURCE_KEY', 'RESOURCE_KEY'];

    /**
     * The offline reader example needs no key and no cloud service.
     */
    public function testFodIdExample(): void
    {
        $run = ExampleRun::run(__DIR__ . '/../examples/fodid_example.php');
        $this->assertSame(0, $run->status, $run->printed());
        $this->assertStringContainsString('Match key', $run->output);
        $run->assertEndsWithFindOutMore();
    }

    /**
     * With no resource key the creator context server says where to create
     * one, with a tagged link to the configuration page, then gives the
     * links to find out more. What status it answers with is not checked
     * here.
     */
    public function testCreatorContextServerWithoutKey(): void
    {
        $run = ExampleRun::run(
            __DIR__ . '/../examples/creator-context-web/server.php',
            [],
            self::RESOURCE_KEY_VARIABLES,
            ExampleRun::WEB_REQUEST
        );
        $this->assertStringContainsString(
            'https://51degrees.com/configure',
            html_entity_decode($run->output),
            $run->printed()
        );
        $run->assertPageEndsWithFindOutMore();
    }
}
