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

use fiftyone\pipeline\core\JavascriptBuilderElement;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Each class configured with an array of settings gives the default of
 * every setting it reads in a DEFAULTS constant beside its constructor, and
 * uses those defaults, so that a default can be found where the setting is
 * given rather than in the code that reads it.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/reference-implementation-notes.md#builders
 */
class SettingDefaultsTests extends TestCase
{
    /**
     * The classes whose constructor reads an array of settings.
     */
    private const CONFIGURED = [
        PipelineBuilder::class,
        Pipeline::class,
        JavascriptBuilderElement::class,
    ];

    /**
     * The DEFAULTS constant of a class, or null when it has none.
     *
     * @return null|array<string, mixed>
     */
    private static function defaults(string $class): ?array
    {
        $constant = (new \ReflectionClass($class))->getReflectionConstant('DEFAULTS');
        if ($constant === false || !$constant->isPublic()) {
            return null;
        }

        return $constant->getValue();
    }

    public function testEachSettingTheConstructorReadsHasADefault(): void
    {
        $missing = [];
        foreach (self::CONFIGURED as $class) {
            $defaults = self::defaults($class);
            if ($defaults === null) {
                $missing[] = "{$class} has no public DEFAULTS constant";
                continue;
            }

            $constructor = (new \ReflectionClass($class))->getConstructor();
            $source = implode('', array_slice(
                (array) file((string) $constructor->getFileName()),
                $constructor->getStartLine() - 1,
                $constructor->getEndLine() - $constructor->getStartLine() + 1
            ));
            preg_match_all('/\$settings\[\s*\'(\w+)\'\s*\]/', $source, $read);
            foreach (array_unique($read[1]) as $name) {
                if (!array_key_exists($name, $defaults)) {
                    $missing[] = "{$class} reads '{$name}' with no default in DEFAULTS";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function testThePipelineBuilderUsesItsDefaults(): void
    {
        $defaults = self::defaults(PipelineBuilder::class) ?? [];
        $builder = new PipelineBuilder();

        $this->assertNotSame([], $defaults);
        foreach ($defaults as $name => $value) {
            $this->assertSame($value, $builder->{$name}, $name);
        }
    }

    public function testThePipelineUsesItsDefaults(): void
    {
        $defaults = self::defaults(Pipeline::class) ?? [];
        $pipeline = new Pipeline([], []);

        $this->assertNotSame([], $defaults);
        $this->assertSame(
            $defaults['suppressProcessExceptions'],
            $pipeline->suppressProcessExceptions
        );
        $this->assertSame($defaults['autoCloseElements'], $pipeline->autoCloseElements);
        $this->assertSame($defaults['metaDataStore'], $pipeline->metaDataStore);
        $this->assertNull($defaults['logger']);
        $this->assertInstanceOf(Logger::class, $pipeline->logger);
    }

    public function testTheJavaScriptBuilderUsesItsDefaults(): void
    {
        $defaults = self::defaults(JavascriptBuilderElement::class) ?? [];
        $element = new JavascriptBuilderElement();

        $this->assertNotSame([], $defaults);
        $this->assertSame($defaults['minify'], $element->minify);
        foreach (['objName', 'protocol', 'host', 'endpoint', 'enableCookies'] as $name) {
            $this->assertSame($defaults[$name], $element->settings['_' . $name], $name);
        }
    }

    /**
     * A Logger given no level, or one it does not know, logs at its
     * default level and above.
     */
    public function testTheLoggerUsesItsDefaultLevel(): void
    {
        $constant = (new \ReflectionClass(Logger::class))
            ->getReflectionConstant('DEFAULT_LEVEL');
        $this->assertNotFalse($constant, 'Logger has no DEFAULT_LEVEL constant');
        $this->assertSame('error', $constant->getValue());

        $file = (string) tempnam(sys_get_temp_dir(), 'pipeline-log');
        try {
            foreach ([null, 'unknown'] as $level) {
                $logger = new Logger($level, ['file' => $file]);
                $logger->log('warning', 'Below the default.');
                $logger->log('error', 'At the default.');
            }

            $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
            $this->assertCount(2, $lines);
            foreach ($lines as $line) {
                $this->assertStringEndsWith('At the default.', $line);
            }
        } finally {
            unlink($file);
        }
    }
}
