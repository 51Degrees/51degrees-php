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

namespace fiftyone\pipeline\ipintelligence\tests;

use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use PHPUnit\Framework\TestCase;

/**
 * How the engine picks the client IP out of the evidence. The method is
 * static and private, so it is reached through reflection and needs no
 * native extension.
 */
class ClientIpTests extends TestCase
{
    /**
     * @param array<string, mixed> $evidence
     * @return array{0: ?string, 1: ?string}
     */
    private function clientIps(array $evidence): array
    {
        $method = new \ReflectionMethod(IpIntelligenceOnPremise::class, 'clientIpsFromEvidence');
        $method->setAccessible(true);

        return $method->invoke(null, $evidence);
    }

    public function testMappedAddressIsReportedAsItsIpv4Address(): void
    {
        $forms = ['::ffff:8.8.8.8', '::FFFF:8.8.8.8', '::ffff:808:808', '0:0:0:0:0:ffff:808:808'];
        foreach ($forms as $form) {
            $this->assertSame(['8.8.8.8', null], $this->clientIps(['query.client-ip' => $form]), $form);
        }
    }

    public function testOtherIpv6FormsStayIpv6(): void
    {
        foreach (['::8.8.8.8', '64:ff9b::8.8.8.8', '::1'] as $form) {
            $this->assertSame([null, $form], $this->clientIps(['query.client-ip' => $form]), $form);
        }
    }

    public function testFirstAddressOfEachFamilyWins(): void
    {
        $evidence = [
            'header.x-forwarded-for' => '9.9.9.9',
            'query.client-ip' => '::ffff:8.8.8.8',
            'server.client-ip' => '2001:4860:4860::8888',
        ];

        $this->assertSame(['8.8.8.8', '2001:4860:4860::8888'], $this->clientIps($evidence));
    }

    public function testNoAddressGivesNothing(): void
    {
        $this->assertSame([null, null], $this->clientIps(['query.client-ip' => 'not an address']));
        $this->assertSame([null, null], $this->clientIps([]));
    }

    /**
     * Evidence keys ignore letter case, and getAll() hands back each key in
     * the letters it was first set with, so the two keys that are looked for
     * by name are matched whatever those letters are.
     */
    public function testNamedKeysAreFoundInAnyLetterCase(): void
    {
        $spellings = [
            ['Query.Client-IP', 'Server.Client-IP'],
            ['QUERY.CLIENT-IP', 'SERVER.CLIENT-IP'],
            ['query.client-ip', 'server.client-ip'],
        ];

        foreach ($spellings as [$query, $server]) {
            $this->assertSame(
                ['8.8.8.8', null],
                $this->clientIps([$query => '8.8.8.8']),
                $query
            );
            $this->assertSame(
                ['9.9.9.9', null],
                $this->clientIps([$server => '9.9.9.9']),
                $server
            );
        }
    }

    /**
     * The two named keys are read before anything else whatever letters they
     * carry, so a header listed earlier does not take their place.
     */
    public function testNamedKeysKeepTheirPrecedenceInAnyLetterCase(): void
    {
        $evidence = [
            'header.x-forwarded-for' => '9.9.9.9',
            'Query.Client-IP' => '8.8.8.8',
        ];

        $this->assertSame(['8.8.8.8', null], $this->clientIps($evidence));
    }

    /**
     * A key given twice in other letters is one key holding its last value,
     * as Evidence holds it, so the earlier value is not read as an address.
     */
    public function testAKeyGivenTwiceInOtherLettersCountsOnce(): void
    {
        $evidence = [
            'Query.Client-IP' => '8.8.8.8',
            'query.client-ip' => '2001:db8::1',
        ];

        $this->assertSame([null, '2001:db8::1'], $this->clientIps($evidence));
    }
}
