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

namespace fiftyone\pipeline\devicedetection\tests;

use PHPUnit\Framework\TestCase;

/**
 * The web integration specification says "Every web integration example,
 * and the documentation of every web integration, MUST say that a Flow
 * Data cannot be used by two threads at once, and MUST show it." The
 * thread safety specification says code comments "MUST say that a Flow
 * Data is not safe to use from several threads at once, and that the
 * caller is responsible for any locking."
 *
 * The Getting Started web example says both in the comment just above the
 * line that creates the Flow Data of a page request.
 */
class WebExampleFlowDataTests extends TestCase
{
    private const EXAMPLE = __DIR__ .
        '/../examples/cloud/classes/GettingStartedWeb.php';

    public function testTheExampleSaysAFlowDataIsUsedByOneThreadAndWhoLocks(): void
    {
        $comment = strtolower($this->commentAbove('= Utils::processWebRequest('));

        $this->assertStringContainsString(
            'must not be used by two threads at once',
            $comment
        );
        $this->assertStringContainsString(
            'the caller is responsible for any locking',
            $comment
        );
    }

    /**
     * The comment lines just above the first line that holds the text, as
     * one line of single spaces.
     */
    private function commentAbove(string $text): string
    {
        $lines = explode(
            "\n",
            str_replace("\r", '', (string)file_get_contents(self::EXAMPLE))
        );
        $comment = [];
        foreach ($lines as $line) {
            if (strpos($line, $text) !== false) {
                return (string)preg_replace(
                    '/\s+/',
                    ' ',
                    implode(' ', $comment)
                );
            }
            $comment = strpos(ltrim($line), '//') === 0
                ? array_merge($comment, [ltrim($line, " /")])
                : [];
        }

        $this->fail('The example has a line that holds ' . $text);
    }
}
