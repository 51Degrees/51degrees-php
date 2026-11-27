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

use fiftyone\pipeline\did\DidClient;
use PHPUnit\Framework\TestCase;

/**
 * A stream that PHP opens with a warning naming the URL and then answers,
 * as a request can that is made but warns on the way.
 */
class WarningThenAnswerStream
{
    /** @var resource|null */
    public $context;

    private bool $read = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool
    {
        // A real warning whose text holds the URL, as PHP's own does.
        $missing = sys_get_temp_dir() . '/no-such-folder-' . bin2hex(random_bytes(8));
        fopen($missing . '/' . $path, 'r');
        return true;
    }

    public function stream_read(int $count): string
    {
        if ($this->read) {
            return '';
        }
        $this->read = true;
        return '{}';
    }

    public function stream_eof(): bool
    {
        return $this->read;
    }

    /** @return array<mixed> */
    public function stream_stat(): array
    {
        return [];
    }
}

/**
 * The warning the default transport gives back for a request that was
 * answered is raised with the licence key masked.
 */
class MaskedWarningTest extends TestCase
{
    private const SCHEME = 'warning-then-answer';

    // A made up key, never a real one.
    private const KEY = 'MADEUPKEY0000TEST1234';

    public function testTheWarningRaisedAgainHasTheKeyMasked(): void
    {
        $this->assertTrue(stream_wrapper_register(self::SCHEME, WarningThenAnswerStream::class));
        $raised = [];
        set_error_handler(
            static function (int $level, string $message) use (&$raised): bool {
                $raised[] = $message;
                return true;
            },
            E_USER_WARNING
        );
        try {
            $client = new DidClient(
                'made-up-resource-key',
                self::KEY,
                self::SCHEME . '://cloud.example/'
            );
            $request = new \ReflectionMethod(DidClient::class, 'request');
            $answer = $request->invoke($client, 'GET', 'id/key/x', ['license' => self::KEY]);
        } finally {
            restore_error_handler();
            stream_wrapper_unregister(self::SCHEME);
        }

        $this->assertSame('{}', $answer['body']);
        $this->assertCount(1, $raised);
        $this->assertStringNotContainsString(self::KEY, $raised[0]);
        $this->assertStringContainsString('license=*', $raised[0]);
    }
}
