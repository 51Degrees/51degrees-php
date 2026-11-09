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

use fiftyone\pipeline\core\CallbackUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the callback URL against the cases every implementation of the
 * JavaScript builder shares, in the same order, so that each one refuses and
 * writes the same URLs.
 */
class CallbackUrlTests extends TestCase
{
    private const ENDPOINT = '/51dpipeline/json';

    private static function url(string $protocol, string $host): ?CallbackUrl
    {
        return CallbackUrl::of($protocol, $host, self::ENDPOINT);
    }

    private function assertValid(
        string $expected,
        ?CallbackUrl $url,
        string $given
    ): void {
        $this->assertNotNull($url, $given . ' gave no URL');
        $this->assertTrue($url->valid, $given . ' was refused as ' . $url->url);
        $this->assertSame($expected, $url->url, $given);
    }

    private function assertInvalid(?CallbackUrl $url, string $given): void
    {
        $this->assertNotNull($url, $given . ' gave no URL');
        $this->assertFalse(
            $url->valid,
            $given . ' was accepted as ' . $url->url
        );
    }

    public function testAddsASingleSlash(): void
    {
        $this->assertValid(
            'https://example.com/51dpipeline/json',
            CallbackUrl::of('https', 'example.com', '51dpipeline/json'),
            'an endpoint without a slash'
        );
    }

    public function testKeepsASingleSlashOnTheEndpoint(): void
    {
        $this->assertValid(
            'https://example.com/51dpipeline/json',
            CallbackUrl::of('https', 'example.com', '/51dpipeline/json'),
            'an endpoint with a slash'
        );
    }

    public function testCollapsesADoubleSlash(): void
    {
        $this->assertValid(
            'http://example.com/json',
            CallbackUrl::of('http', 'example.com/', '/json'),
            'a slash on both'
        );
    }

    public function testMissingWhenAPartIsEmpty(): void
    {
        $this->assertNull(CallbackUrl::of('https', '', '/json'));
        $this->assertNull(CallbackUrl::of('', 'example.com', '/json'));
        $this->assertNull(CallbackUrl::of('https', 'example.com', ''));
        $this->assertNull(CallbackUrl::of('https', '  ', '/json'));
    }

    public static function provider_acceptedHosts(): array
    {
        return [
            ['EXAMPLE.com', 'https://example.com/51dpipeline/json'],
            ['example.com:8080', 'https://example.com:8080/51dpipeline/json'],
            ['example.com:', 'https://example.com:/51dpipeline/json'],
            ['example.com:65535', 'https://example.com:65535/51dpipeline/json'],
            ['a_b.com', 'https://a_b.com/51dpipeline/json'],
            ['-ab.com', 'https://-ab.com/51dpipeline/json'],
            ['127.0.0.1:80', 'https://127.0.0.1:80/51dpipeline/json'],
            ['1.2.3.4', 'https://1.2.3.4/51dpipeline/json'],
            ['1.2.3.4:8080', 'https://1.2.3.4:8080/51dpipeline/json'],
            ['1.2.3.4/path', 'https://1.2.3.4/path/51dpipeline/json'],
            ['0.0.0.0', 'https://0.0.0.0/51dpipeline/json'],
            ['101.2.3.4', 'https://101.2.3.4/51dpipeline/json'],
            ['example.co1', 'https://example.co1/51dpipeline/json'],
            ['123.example.com', 'https://123.example.com/51dpipeline/json'],
            ['0x7f.example.com', 'https://0x7f.example.com/51dpipeline/json'],
            ['[::1]', 'https://[::1]/51dpipeline/json'],
            ['[::1]:8080', 'https://[::1]:8080/51dpipeline/json'],
            ['example.com.', 'https://example.com./51dpipeline/json'],
            ['example.com/path', 'https://example.com/path/51dpipeline/json'],
            ['example.com/a b', 'https://example.com/a%20b/51dpipeline/json'],
            ['example.com/a%zzb', 'https://example.com/a%25zzb/51dpipeline/json'],
            ['example.com/a%41b', 'https://example.com/a%41b/51dpipeline/json'],
            ["example.com/\u{fc}", 'https://example.com/%C3%BC/51dpipeline/json'],
            ["example.com/a\nb", 'https://example.com/a%0Ab/51dpipeline/json'],
            [
                "example.com/a\u{2028}b",
                'https://example.com/a%E2%80%A8b/51dpipeline/json'
            ]
        ];
    }

    /**
     * @dataProvider provider_acceptedHosts
     */
    #[DataProvider('provider_acceptedHosts')]
    public function testAcceptsTheHost(string $host, string $expected): void
    {
        $this->assertValid($expected, self::url('https', $host), json_encode($host));
    }

    public static function provider_refusedHosts(): array
    {
        return [
            ['example.com:65536'],
            ['example.com:99999'],
            ['example.com:abc'],
            ['exa mple.com'],
            ["example.com');alert(1);//"],
            ['a"b.com'],
            ["a'b.com"],
            ['a<b.com'],
            ['::1'],
            ['[::1'],
            ['[v1.x]'],
            ['[1.2.3.4]'],
            ['a..b.com'],
            ['%41.com'],
            ['a\\b.com'],
            ['a`b.com'],
            ['a{b}.com'],
            ['a|b.com'],
            ['a^b.com'],
            ['a(b).com'],
            ['a;b.com'],
            ['a,b.com'],
            ['a!b.com'],
            ['a*b.com'],
            ['a+b.com'],
            ['a=b.com'],
            ['a$b.com'],
            ['a&b.com'],
            ['a~b.com'],
            ["a\tb.com"],
            ["a\nb.com"],
            [' example.com'],
            ['example.com '],
            ["example.com:8080');alert(1);//"]
        ];
    }

    /**
     * @dataProvider provider_refusedHosts
     */
    #[DataProvider('provider_refusedHosts')]
    public function testRefusesTheHost(string $host): void
    {
        $this->assertInvalid(self::url('https', $host), json_encode($host));
    }

    public static function provider_refusedZonesAndTextAfterTheBracket(): array
    {
        return [
            ['[fe80::1%25]'],
            ["[fe80::1%25'+alert(1)+']"],
            ['[fe80::1%eth0]'],
            ['[fe80::1%25eth%0]'],
            ['[::1]x'],
            ['[::1]80'],
            ['[::1]:80x'],
            ['[::1]]']
        ];
    }

    /**
     * Inside the brackets only an IPv6 address, optionally followed by '%25'
     * and a zone of unreserved characters, and after them only a port. The
     * zone check is what keeps a quote out of the script for a bracketed
     * host.
     *
     * @dataProvider provider_refusedZonesAndTextAfterTheBracket
     */
    #[DataProvider('provider_refusedZonesAndTextAfterTheBracket')]
    public function testRefusesTheZoneOrTheTextAfterTheBracket(
        string $host
    ): void {
        $this->assertInvalid(self::url('https', $host), json_encode($host));
    }

    /**
     * A port goes by its value, so leading zeros are allowed, and a port too
     * long to read is refused rather than read as small enough.
     */
    public function testChecksThePortByItsValue(): void
    {
        $this->assertValid(
            'https://example.com:00080/51dpipeline/json',
            self::url('https', 'example.com:00080'),
            'example.com:00080'
        );
        $this->assertValid(
            'https://example.com:0065535/51dpipeline/json',
            self::url('https', 'example.com:0065535'),
            'example.com:0065535'
        );
        $refused = [
            'example.com:0065536',
            'example.com:123456',
            'example.com:4294967296',
            'example.com:99999999999999999999'
        ];
        foreach ($refused as $host) {
            $this->assertInvalid(self::url('https', $host), $host);
        }
    }

    /**
     * Each character rule 8 lists, after the host and in the endpoint, and a
     * '%' without two hexadecimal digits after it, even at the end.
     */
    public function testEncodesTheCharactersRule8Lists(): void
    {
        $this->assertValid(
            'https://example.com/%22%5E%60%7B%7C%7D/j%22%5E%60%7B%7C%7D',
            CallbackUrl::of('https', 'example.com/"^`{|}', '/j"^`{|}'),
            'rule 8 characters'
        );
        $cases = [
            'example.com/a%' => 'https://example.com/a%25/51dpipeline/json',
            'example.com/a%4' => 'https://example.com/a%254/51dpipeline/json',
            'example.com/a%41' => 'https://example.com/a%41/51dpipeline/json'
        ];
        foreach ($cases as $host => $expected) {
            $this->assertValid($expected, self::url('https', $host), $host);
        }
        $this->assertValid(
            'https://example.com/json%25',
            CallbackUrl::of('https', 'example.com', '/json%'),
            '/json%'
        );
    }

    public static function provider_zoneIdentifiers(): array
    {
        return [
            ['[fe80::1%25eth0]'],
            ['[fe80::1%25eth0]:8080'],
            ['[FE80::1%25ETH0]'],
            ['[fe80::1%251]'],
            ['[::1%25lo]'],
            ['[fe80::1%]'],
            ['[fe80::1%25eth0]/path']
        ];
    }

    /**
     * Rule 4 refuses an IPv6 zone identifier.
     *
     * @dataProvider provider_zoneIdentifiers
     */
    #[DataProvider('provider_zoneIdentifiers')]
    public function testRefusesTheZoneIdentifier(string $host): void
    {
        $this->assertInvalid(self::url('https', $host), json_encode($host));
    }

    public static function provider_userInformation(): array
    {
        return [
            ['user@example.com'],
            ['user:pw@example.com'],
            ['@example.com'],
            [':@example.com'],
            ['user@example.com:8080'],
            ['user@[::1]'],
            ['user@example.com/path'],
            ['u%41@example.com'],
            ['u x@example.com'],
            ["u\tx@example.com"],
            ['u"x@example.com'],
            ["u'x@example.com"],
            ['u\\x@example.com'],
            ["u\nx@example.com"],
            ["u\u{1}x@example.com"],
            ['a@b@example.com'],
            ['example.com/a@b'],
            ['exa mple.com/a@b'],
            ['user:12/ss@example.com'],
            ['user:pa/ss@example.com'],
            ['user:pa?ss@example.com']
        ];
    }

    /**
     * Rule 3 refuses an '@' anywhere in the host value.
     *
     * @dataProvider provider_userInformation
     */
    #[DataProvider('provider_userInformation')]
    public function testRefusesTheUserInformation(string $host): void
    {
        $this->assertInvalid(self::url('https', $host), json_encode($host));
    }

    public static function provider_queryAndFragment(): array
    {
        return [
            ['example.com?x'],
            ['example.com#x'],
            ['example.com?'],
            ['example.com#'],
            ['example.com:8080?x'],
            ['[::1]#x'],
            ['example.com/path?x'],
            ['example.com/path#x'],
            ["example.com?');alert(1);//"],
            ["example.com#');alert(1);//"]
        ];
    }

    /**
     * Rule 2 refuses a '?' or '#' anywhere in the host value.
     *
     * @dataProvider provider_queryAndFragment
     */
    #[DataProvider('provider_queryAndFragment')]
    public function testRefusesTheQueryOrFragment(string $host): void
    {
        $this->assertInvalid(self::url('https', $host), json_encode($host));
    }

    public static function provider_namesEndingInANumber(): array
    {
        return [
            ['example.123'],
            ['example.0x1f'],
            ['example.0X'],
            ['1.2.3.999'],
            ['1.2.3'],
            ['127.1'],
            ['0x7f.0.0.1'],
            ['01.2.3.4'],
            ['00.2.3.4'],
            ['example.123.'],
            ['1.2.3.4.'],
            ['1.2.3.4.5'],
            ['1'],
            ['300.1.1.1'],
            ['0x7f.1'],
            ['1.2.3.4.:8080']
        ];
    }

    /**
     * Rule 4 reads a name whose last label is a number as an IPv4 address
     * written as four decimal numbers, each starting with 0 only when it is
     * 0 alone.
     *
     * @dataProvider provider_namesEndingInANumber
     */
    #[DataProvider('provider_namesEndingInANumber')]
    public function testRefusesANameEndingInANumber(string $host): void
    {
        $this->assertInvalid(self::url('https', $host), json_encode($host));
    }

    public static function provider_loggedUrls(): array
    {
        return [
            [
                'user:secret@example.com',
                'https://***@example.com/51dpipeline/json'
            ],
            ['a@b@example.com', 'https://***@example.com/51dpipeline/json'],
            ['u@[::1]:80x', 'https://***@[::1]:80x/51dpipeline/json'],
            [
                'user:pa/ss@example.com',
                'https://***@example.com/51dpipeline/json'
            ],
            [
                'user:pa?ss@example.com',
                'https://***@example.com/51dpipeline/json'
            ],
            ['exa mple.com/a@b', 'https://***@b/51dpipeline/json']
        ];
    }

    /**
     * Rule 6 shows *** in place of everything in the host before its last
     * '@', wherever in the host value that '@' is.
     *
     * @dataProvider provider_loggedUrls
     */
    #[DataProvider('provider_loggedUrls')]
    public function testTheLoggedUrlHoldsNoUserInformation(
        string $host,
        string $logged
    ): void {
        $url = self::url('https', $host);
        $this->assertInvalid($url, $host);
        $this->assertSame($logged, $url->url);
    }

    public function testTheInvalidUrlIsTheOneBuilt(): void
    {
        $url = self::url('https', 'exa mple.com');
        $this->assertInvalid($url, 'exa mple.com');
        $this->assertSame('https://exa mple.com/51dpipeline/json', $url->url);
    }

    /**
     * Only http and https, ignoring the case of ASCII letters only, so a
     * letter outside ASCII that a case-insensitive comparison can match to an
     * ASCII one, such as the long s, is refused.
     */
    public function testChecksTheScheme(): void
    {
        foreach (['https', 'http', 'HtTp'] as $protocol) {
            $url = self::url($protocol, 'example.com');
            $this->assertTrue($url->valid, $protocol);
        }
        $this->assertValid(
            'https://example.com/51dpipeline/json',
            self::url('HTTPS', 'example.com'),
            'HTTPS'
        );
        $refused = [
            'ftp',
            'h+t-p.s',
            'ws',
            'javascript',
            'httpx',
            'https ',
            '1http',
            'h t',
            "x');alert(1);//",
            'a://b',
            "http\u{017F}",
            "HTTP\u{017F}"
        ];
        foreach ($refused as $protocol) {
            $url = self::url($protocol, 'example.com');
            $this->assertInvalid($url, json_encode($protocol));
        }
    }

    public static function provider_hostsThatCouldEndTheString(): array
    {
        return [
            ["example.com/');alert(1);//"],
            ['example.com/a"b'],
            ['example.com/a\\b'],
            ['example.com/</script>']
        ];
    }

    /**
     * A quote, a backslash or a line break never reaches the script,
     * wherever in the URL it was given.
     *
     * @dataProvider provider_hostsThatCouldEndTheString
     */
    #[DataProvider('provider_hostsThatCouldEndTheString')]
    public function testNothingGivenCanEndTheString(string $host): void
    {
        $url = self::url('https', $host);
        $this->assertTrue($url->valid, $host);
        $ends = ["'", '"', '\\', '<', '>', "\n", "\r", "\u{2028}", "\u{2029}"];
        foreach ($ends as $c) {
            $this->assertStringNotContainsString($c, $url->url, $host);
        }
    }

    public function testTheEndpointIsEncoded(): void
    {
        $this->assertValid(
            'https://example.com/json%27x',
            CallbackUrl::of('https', 'example.com', "/json'x"),
            "the endpoint /json'x"
        );
    }

    public static function provider_hostsOutsideAscii(): array
    {
        return [
            ["b\u{fc}cher.de"],
            ["a\u{a0}b.com"],
            ["a\u{200b}b.com"],
            ["a\u{2028}b.com"]
        ];
    }

    /**
     * A host must be ASCII, so an internationalized name is given as
     * A-labels, and anything else outside ASCII is refused.
     *
     * @dataProvider provider_hostsOutsideAscii
     */
    #[DataProvider('provider_hostsOutsideAscii')]
    public function testRefusesTheHostOutsideAscii(string $host): void
    {
        $this->assertInvalid(self::url('https', $host), json_encode($host));
    }

    /**
     * Not one of the shared cases. A PHP string is bytes, so text that is
     * not UTF-8 is percent-encoded byte by byte.
     */
    public function testEncodesBytesThatAreNotUtf8(): void
    {
        $this->assertValid(
            'https://example.com/%FF/51dpipeline/json',
            self::url('https', "example.com/\xff"),
            'a byte that is not UTF-8'
        );
    }
}
