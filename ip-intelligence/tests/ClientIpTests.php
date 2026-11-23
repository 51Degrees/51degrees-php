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
     * The keys an engine accepts, in the order the native getKeys() gives
     * them, which takes each client IP header of the data file in priority
     * order and writes it under the query and then the server prefix.
     */
    private const KEYS = [
        'query.true-client-ip-51d',
        'server.true-client-ip-51d',
        'query.client-ip-51d',
        'server.client-ip-51d',
        'query.client-ip',
        'server.client-ip',
    ];

    /**
     * @param array<string, mixed> $evidence
     * @param array<string> $keys
     * @return array{0: ?string, 1: ?string}
     */
    private function clientIps(array $evidence, array $keys = self::KEYS): array
    {
        $method = new \ReflectionMethod(IpIntelligenceOnPremise::class, 'clientIpsFromEvidence');
        $method->setAccessible(true);

        return $method->invoke(null, $evidence, $keys);
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

    /**
     * One value is chosen, and only its address is reported, so an address
     * of the other family under a later key is not.
     */
    public function testOnlyTheChosenAddressIsReported(): void
    {
        $evidence = [
            'header.x-forwarded-for' => '9.9.9.9',
            'query.client-ip' => '::ffff:8.8.8.8',
            'server.client-ip' => '2001:4860:4860::8888',
        ];

        $this->assertSame(['8.8.8.8', null], $this->clientIps($evidence));
    }

    /**
     * Every query key comes before every server key, so a server key the
     * data file lists first does not take the place of a later query key.
     */
    public function testQueryKeysComeBeforeServerKeys(): void
    {
        $this->assertSame(
            ['8.8.8.8', null],
            $this->clientIps([
                'server.true-client-ip-51d' => '9.9.9.9',
                'server.client-ip' => '9.9.9.8',
                'query.client-ip-51d' => '8.8.8.8',
            ])
        );
    }

    /**
     * Under one prefix the keys are tried in the data file's order, which is
     * the order of the engine's keys and not the order of the Evidence.
     */
    public function testKeysUnderOnePrefixFollowTheDataFileOrder(): void
    {
        $evidence = [
            'query.client-ip' => '9.9.9.9',
            'query.client-ip-51d' => '9.9.9.8',
            'query.true-client-ip-51d' => '8.8.8.8',
        ];
        $this->assertSame(['8.8.8.8', null], $this->clientIps($evidence));

        $evidence = [
            'server.client-ip' => '9.9.9.9',
            'server.client-ip-51d' => '8.8.8.8',
        ];
        $this->assertSame(['8.8.8.8', null], $this->clientIps($evidence));

        // A data file that lists the headers the other way round.
        $this->assertSame(
            ['9.9.9.9', null],
            $this->clientIps(
                ['query.true-client-ip-51d' => '8.8.8.8', 'query.client-ip' => '9.9.9.9'],
                ['query.client-ip', 'server.client-ip', 'query.true-client-ip-51d', 'server.true-client-ip-51d']
            )
        );
    }

    /**
     * The same header under both prefixes gives the query value, and the
     * server value only when the query value is not read.
     */
    public function testTheSameKeyUnderBothPrefixes(): void
    {
        $this->assertSame(
            ['8.8.8.8', null],
            $this->clientIps(['server.client-ip' => '9.9.9.9', 'query.client-ip' => '8.8.8.8'])
        );
        $this->assertSame(
            ['9.9.9.9', null],
            $this->clientIps(['server.client-ip' => '9.9.9.9', 'query.client-ip' => 'unknown'])
        );
    }

    /**
     * The chosen value's address is looked up whatever its family, so an
     * IPv6 address under an earlier key wins over an IPv4 address under a
     * later one.
     */
    public function testTheFirstValueIsUsedWhateverItsFamily(): void
    {
        $this->assertSame(
            [null, '2001:db8::7'],
            $this->clientIps([
                'query.client-ip' => '8.8.8.8',
                'query.true-client-ip-51d' => '2001:db8::7',
            ])
        );
        $this->assertSame(
            [null, '2001:db8::7'],
            $this->clientIps([
                'server.true-client-ip-51d' => '8.8.8.8',
                'query.client-ip' => '2001:db8::7',
            ])
        );
    }

    /**
     * Only the keys the engine accepts are read, so an address in any other
     * Evidence value is not looked up.
     */
    public function testAValueTheEngineDoesNotAcceptIsNotRead(): void
    {
        $this->assertSame(
            [null, null],
            $this->clientIps([
                'header.x-forwarded-for' => '8.8.8.8',
                'query.ip' => '8.8.8.9',
                'cookie.client-ip' => '2001:db8::7',
            ])
        );
        $this->assertSame(
            [null, null],
            $this->clientIps(['query.client-ip' => '8.8.8.8'], ['query.client-ip-51d', 'server.client-ip-51d'])
        );
    }

    public function testNoAddressGivesNothing(): void
    {
        $this->assertSame([null, null], $this->clientIps(['query.client-ip' => 'not an address']));
        $this->assertSame([null, null], $this->clientIps([]));
    }

    /**
     * Each form the specification's "Reading the client IP value" section
     * accepts, with the address it gives. A port is dropped, and brackets
     * are not part of the address.
     */
    public function testEachAcceptedFormIsRead(): void
    {
        $forms = [
            '203.0.113.1' => ['203.0.113.1', null],
            '0.0.0.0' => ['0.0.0.0', null],
            '2001:db8::1' => [null, '2001:db8::1'],
            '2001:0DB8:0:0:0:0:0:1' => [null, '2001:0DB8:0:0:0:0:0:1'],
            '::ffff:203.0.113.1' => ['203.0.113.1', null],
            '203.0.113.1:8080' => ['203.0.113.1', null],
            '203.0.113.1:0' => ['203.0.113.1', null],
            '203.0.113.1:65535' => ['203.0.113.1', null],
            '[2001:db8::1]' => [null, '2001:db8::1'],
            '[2001:db8::1]:8080' => [null, '2001:db8::1'],
            '[::ffff:203.0.113.1]:8080' => ['203.0.113.1', null],
            // Without brackets the text after the last colon is part of the
            // address, so this is an address with no port.
            '2001:db8::1:80' => [null, '2001:db8::1:80'],
        ];
        foreach ($forms as $form => $expected) {
            $this->assertSame(
                $expected,
                $this->clientIps(['query.client-ip' => (string) $form]),
                (string) $form
            );
        }
    }

    /**
     * Every value in the specification's table of values that are not read,
     * and the zone index forms its "Zone indexes" section refuses. None of
     * them is read in part.
     */
    public function testEachRefusedValueIsNotRead(): void
    {
        $values = [
            '203.0.113.0/24',
            '203.0.113.1 x',
            '203.0.113.1, 198.51.100.7',
            ' 203.0.113.1',
            '203.0.113.1 ',
            "\t203.0.113.1",
            "203.0.113.1\t",
            "203.0.113.1\n",
            "203.0.113.1\r\n",
            ' 2001:db8::1',
            '[2001:db8::1]:80 ',
            'fe80::1%eth0',
            'fe80::1%1',
            '[fe80::1%eth0]',
            '[fe80::1%eth0]:80',
            '[fe80::1%25eth0]',
            '[203.0.113.1]',
            '[203.0.113.1]:80',
            '[2001:db8::1',
            '2001:db8::1]:80',
            '2001:db8::1]',
            '203.0.113.1:',
            '[2001:db8::1]:',
            '203.0.113.1:65536',
            '[2001:db8::1]:65536',
            '203.0.113.1:8o',
            '256.0.113.1',
            '01.2.3.4',
            '1.02.3.4',
            '1.2.3.04',
            '00.0.0.0',
            '203.0.113',
            '2001:db8::1::2',
            '2001:db8:1:2:3:4:5:6:7',
            '2001:db8::12345',
            'unknown',
            '',
        ];
        foreach ($values as $value) {
            $this->assertSame(
                [null, null],
                $this->clientIps(['query.client-ip' => $value]),
                json_encode($value)
            );
        }
    }

    /**
     * A value that is not read does not hide a value under a key that comes
     * later.
     */
    public function testARefusedValueDoesNotHideALaterKey(): void
    {
        $this->assertSame(
            ['198.51.100.7', null],
            $this->clientIps([
                'query.client-ip' => ' 203.0.113.1',
                'server.client-ip' => '198.51.100.7:443',
            ])
        );
        $this->assertSame(
            [null, '2001:db8::7'],
            $this->clientIps([
                'query.client-ip' => '[fe80::1%eth0]:80',
                'server.client-ip' => '[2001:db8::7]:443',
            ])
        );
    }

    /**
     * Evidence keys ignore letter case, and getAll() hands back each key in
     * the letters it was first set with, so a client IP key is matched
     * whatever those letters are.
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
     * A client IP key keeps its place in the order whatever letters it
     * carries, so neither a header listed earlier nor a server key takes it.
     */
    public function testNamedKeysKeepTheirPrecedenceInAnyLetterCase(): void
    {
        $evidence = [
            'header.x-forwarded-for' => '9.9.9.9',
            'SERVER.TRUE-CLIENT-IP-51D' => '9.9.9.8',
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
