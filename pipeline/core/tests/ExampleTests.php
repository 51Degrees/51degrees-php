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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

class ExampleTests extends TestCase
{
    public function testCustomFlowElementExample()
    {
        ob_start(); // hide output
        include __DIR__ . '/../examples/CustomFlowElement.php';
        ob_end_clean(); // discard output

        $this->assertTrue(strpos($output, 'Starsign') !== false);
    }

    public function testPipelineExample()
    {
        ob_start(); // hide output
        include __DIR__ . '/../examples/Pipeline.php';
        ob_end_clean(); // discard output

        $this->assertTrue($flowData->example1->exampleProperty1 === 5);
    }

    /**
     * The page, rendered in its own PHP for a request for the home page,
     * ends with the links to find out more.
     */
    public function testCustomFlowElementEndsWithFindOutMore()
    {
        $run = $this->runExample('CustomFlowElement.php', ExampleRun::WEB_REQUEST);
        $run->assertPageEndsWithFindOutMore();
    }

    /**
     * The example, run in its own PHP, ends with the links to find out more.
     */
    public function testPipelineEndsWithFindOutMore()
    {
        $run = $this->runExample('Pipeline.php', []);
        $run->assertEndsWithFindOutMore();
    }

    /**
     * Runs an example with the package's autoloader, which the examples
     * expect to be loaded already.
     *
     * @param array<string, string> $set Environment variables to add
     */
    private function runExample(string $script, array $set): ExampleRun
    {
        $run = ExampleRun::run(
            __DIR__ . "/../examples/{$script}",
            ['-d', 'auto_prepend_file=' . realpath(__DIR__ . '/../vendor/autoload.php')],
            [],
            $set
        );
        $this->assertSame(0, $run->status, $run->printed());

        return $run;
    }
}
