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

namespace fiftyone\pipeline\translation\tests;

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\translation\TranslationEngine;
use fiftyone\pipeline\translation\tests\classes\SourceElement;
use PHPUnit\Framework\TestCase;

/**
 * Evidence keys ignore letter case, as the Evidence specification requires,
 * so the engine resolves the locale from one of its evidence keys whatever
 * letters the key was set with.
 */
class TranslationEvidenceCaseTests extends TestCase
{
    /**
     * The value the engine reads to resolve the locale, from a pipeline
     * carrying the one evidence key given. The method is protected, so it is
     * reached through a closure bound to the engine.
     *
     * @param null|int|string $value
     */
    private function evidenceValue(string $key, $value): ?string
    {
        $engine = new TranslationEngine(['SourceElementDataKey' => 'ip']);
        $pipeline = (new PipelineBuilder())
            ->add(new SourceElement())
            ->add($engine)
            ->build();
        try {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set($key, $value);

            return (fn () => $this->getEvidenceValue($flowData))->call($engine);
        } finally {
            $pipeline->close();
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provider_keySpellings(): array
    {
        return [
            'query.translation lower' => ['query.translation'],
            'query.translation mixed' => ['Query.Translation'],
            'query.translation upper' => ['QUERY.TRANSLATION'],
            'query.accept-language lower' => ['query.accept-language'],
            'query.accept-language mixed' => ['Query.Accept-Language'],
            'header.accept-language lower' => ['header.accept-language'],
            'header.accept-language mixed' => ['Header.Accept-Language'],
            'header.accept-language upper' => ['HEADER.ACCEPT-LANGUAGE'],
        ];
    }

    /**
     * @dataProvider provider_keySpellings
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_keySpellings')]
    public function testEvidenceIsReadInAnyLetterCase(string $key): void
    {
        $this->assertSame('fr', $this->evidenceValue($key, 'fr'), $key);
    }

    /**
     * A key the engine does not read is still not read, whatever letters it
     * carries, so matching without regard to case has not widened the set of
     * keys the locale can come from.
     */
    public function testAnUnrelatedKeyIsStillNotRead(): void
    {
        $this->assertNull($this->evidenceValue('Header.Host', 'example.com'));
    }
}
