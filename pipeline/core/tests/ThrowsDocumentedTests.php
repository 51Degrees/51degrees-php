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

use PHPUnit\Framework\TestCase;

/**
 * Each public method of the package that throws in its own body says so in
 * an @throws tag of its doc comment, which is how PHP lets an IDE and a
 * reader know what a method can throw.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/exception-handling.md#overview
 */
class ThrowsDocumentedTests extends TestCase
{
    public function testEachPublicMethodThatThrowsSaysSo(): void
    {
        $missing = [];
        $files = glob(dirname(__DIR__) . '/src/*.php') ?: [];
        $read = 0;
        foreach ($files as $file) {
            $code = (string) file_get_contents($file);
            if (!preg_match('/^namespace\s+([^;]+);/m', $code, $namespace) ||
                !preg_match('/^(?:abstract |final )?class\s+(\w+)/m', $code, $name)) {
                continue;
            }

            // The line of each throw in the file.
            $throws = [];
            foreach (token_get_all($code) as $token) {
                if (is_array($token) && $token[0] === T_THROW) {
                    $throws[] = $token[2];
                }
            }

            $class = new \ReflectionClass($namespace[1] . '\\' . $name[1]);
            ++$read;
            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }

                $throwsHere = false;
                foreach ($throws as $line) {
                    if ($line >= $method->getStartLine() && $line <= $method->getEndLine()) {
                        $throwsHere = true;
                    }
                }

                if ($throwsHere &&
                    strpos((string) $method->getDocComment(), '@throws') === false) {
                    $missing[] = $class->getShortName() . '::' . $method->getName();
                }
            }
        }

        $this->assertNotSame([], $files, 'No source file was read from src.');
        $this->assertGreaterThan(0, $read, 'No class was read from src.');

        $this->assertSame([], $missing, 'Give each of these an @throws tag.');
    }
}
