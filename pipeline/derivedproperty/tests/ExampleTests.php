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

namespace fiftyone\pipeline\derivedproperty\tests;

use fiftyone\pipeline\derivedproperty\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * Runs the example as someone runs it from a fresh clone, in its own PHP
 * with no settings, and checks it shows each of the four things the
 * specification asks the Derived Property Element example to show.
 */
class ExampleTests extends TestCase
{
    // The heading of the part built with the builder in code.
    private const FROM_CODE = "1. Built with the builder in code\n";

    // The heading of the part built from the configuration file.
    private const FROM_FILE = "2. Built from derived-property-options.json\n";

    // The value the script in ReaderEngagement.yaml gives when every test
    // passes.
    private const FILE_SCRIPT_VALUE = "  derived.ReaderEngagement is High.\n";

    // The value the script held as text in the example gives when every
    // test passes.
    private const TEXT_SCRIPT_VALUE = "  derived.ReaderEngagementIsHigh is true.\n";

    // What the caller sees when a source property is not available, being
    // no value and a message naming the property and what its element said.
    private const NO_VALUE = "  derived.ReaderEngagement has no value. " .
        "Derived property 'ReaderEngagement' has no value because 1 source " .
        "property was not available. 'session.PointerMoved' (element " .
        "'session' has no value for 'PointerMoved': The request did not say " .
        'whether the pointer moved.).';

    // What reading the value raises where there is none.
    private const NO_VALUE_RAISES = '  Reading the value of ' .
        'derived.ReaderEngagement raised an exception with the same message.';

    public function testDerivedPropertyExample(): void
    {
        $run = ExampleRun::run(
            __DIR__ . '/../examples/DerivedProperty.php',
            [],
            ExampleRun::RESOURCE_KEY_VARIABLES
        );
        $this->assertSame(0, $run->status, $run->printed());

        $fromCode = strpos($run->output, self::FROM_CODE);
        $fromFile = strpos($run->output, self::FROM_FILE);
        $this->assertNotFalse($fromCode, $run->printed());
        $this->assertNotFalse($fromFile, $run->printed());
        $this->assertGreaterThan($fromCode, $fromFile, $run->printed());
        $code = substr($run->output, $fromCode, $fromFile - $fromCode);
        $file = substr($run->output, $fromFile);

        // A script held in the example as text, and a script read from the
        // file beside the example, each giving a value.
        $this->assertStringContainsString(self::TEXT_SCRIPT_VALUE, $code, $run->printed());
        $this->assertStringContainsString(self::FILE_SCRIPT_VALUE, $code, $run->printed());

        // The pipeline built from the configuration file, which names the
        // script file and cannot hold script text.
        $this->assertStringContainsString(self::FILE_SCRIPT_VALUE, $file, $run->printed());
        $this->assertStringNotContainsString('ReaderEngagementIsHigh', $file, $run->printed());

        // A source property that is not available, shown in both.
        $this->assertStringContainsString(self::NO_VALUE, $code, $run->printed());
        $this->assertStringContainsString(self::NO_VALUE_RAISES, $code, $run->printed());
        $this->assertStringContainsString(self::NO_VALUE, $file, $run->printed());

        $run->assertEndsWithFindOutMore();
    }
}
