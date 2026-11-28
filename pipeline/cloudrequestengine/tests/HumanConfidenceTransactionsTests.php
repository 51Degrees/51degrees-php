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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\HumanConfidencePage;
use fiftyone\pipeline\cloudrequestengine\HumanConfidenceTransactions;
use PHPUnit\Framework\TestCase;

/**
 * The package gives each page a new transaction, keeps it on the server,
 * and gives it up once. The consumer never sees it.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#the-transaction
 */
class HumanConfidenceTransactionsTests extends TestCase
{
    private string $folder = '';

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'hc-transactions-tests-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->folder)) {
            rmdir($this->folder);
        }
    }

    /**
     * Takes a kept transaction as the Cloud Request Engine does, in the
     * store's own scope, as take() is private.
     */
    public static function take(HumanConfidenceTransactions $store, ?string $pageId): ?string
    {
        $take = \Closure::bind(
            static fn (HumanConfidenceTransactions $store, ?string $id): ?string => $store->take($id),
            null,
            HumanConfidenceTransactions::class
        );

        return $take($store, $pageId);
    }

    private static function transactionOf(HumanConfidencePage $page): string
    {
        parse_str((string) parse_url($page->scriptUrl('https://cloud.example/api/v4/', 'key'), PHP_URL_QUERY), $query);

        return (string) $query['transaction'];
    }

    public function testEachPageHasANewTransactionAndANewPageId(): void
    {
        $transactions = new HumanConfidenceTransactions($this->folder);
        $kept = [];
        $ids = [];
        for ($i = 0; $i < 20; ++$i) {
            $page = $transactions->newPage();
            $kept[] = self::transactionOf($page);
            $ids[] = $page->pageId();
        }
        $this->assertCount(20, array_unique($kept));
        $this->assertCount(20, array_unique($ids));
        foreach ($ids as $i => $id) {
            $this->assertStringNotContainsString($kept[$i], $id);
            // The page sends it back as a form field or in an address.
            $this->assertSame(rawurlencode($id), $id);
        }
    }

    public function testAKeptTransactionIsTakenOnce(): void
    {
        $transactions = new HumanConfidenceTransactions($this->folder);
        $page = $transactions->newPage();
        $this->assertSame(self::transactionOf($page), self::take($transactions, $page->pageId()));
        $this->assertNull(self::take($transactions, $page->pageId()));
    }

    public function testATransactionIsKeptUnderItsOwnPageIdOnly(): void
    {
        $transactions = new HumanConfidenceTransactions($this->folder);
        $one = $transactions->newPage();
        $two = $transactions->newPage();
        $this->assertNull(self::take($transactions, 'unknown-page'));
        $this->assertSame(self::transactionOf($two), self::take($transactions, $two->pageId()));
        $this->assertSame(self::transactionOf($one), self::take($transactions, $one->pageId()));
    }

    public function testTheTransactionIsKeptForEveryRequestOfTheSite(): void
    {
        // Another instance stands for another request of the same site.
        $page = (new HumanConfidenceTransactions($this->folder))->newPage();
        $this->assertSame(
            self::transactionOf($page),
            self::take(new HumanConfidenceTransactions($this->folder), $page->pageId())
        );
    }

    /**
     * A folder another user made first, open to its group and everyone
     * else, is refused. No page is kept in it, and no transaction planted
     * in it is taken, so the sealed result reads Medium. Only on POSIX
     * systems, as Windows gives PHP no folder mode to check.
     */
    public function testAFolderMadeBeforehandThatOthersCanUseIsRefused(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $this->markTestSkipped('PHP reads no folder mode or owner on Windows.');
        }
        mkdir($this->folder, 0700);
        chmod($this->folder, 0777);
        $planted = 'planted-page';
        file_put_contents(
            $this->folder . DIRECTORY_SEPARATOR . hash('sha256', $planted) . '.transaction',
            'PLANTED-TRANSACTION'
        );
        $transactions = new HumanConfidenceTransactions($this->folder);

        try {
            $transactions->newPage();
            $this->fail('A page was kept in a folder others can use.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($this->folder, $e->getMessage());
            $this->assertStringContainsString('0700', $e->getMessage());
            $this->assertStringNotContainsString('PLANTED', $e->getMessage());
        }
        $this->assertCount(1, glob($this->folder . DIRECTORY_SEPARATOR . '*') ?: []);
        $this->assertNull(self::take($transactions, $planted));

        // Made private again, the same folder is used.
        chmod($this->folder, 0700);
        $page = $transactions->newPage();
        $this->assertSame(self::transactionOf($page), self::take($transactions, $page->pageId()));
    }

    /**
     * The rules for a folder that's there already, checked with each
     * detail given, as a test can't make a folder another user owns, and
     * on Windows can't give one a POSIX mode. A link is refused anywhere.
     * On POSIX systems a folder any group or other user can use, or one
     * another user owns, is refused. The reason names the folder and the
     * rule only.
     */
    public function testAFolderThatIsALinkOpenToOthersOrAnotherUsersIsRefused(): void
    {
        $refusal = \Closure::bind(
            static fn (...$details): ?string => HumanConfidenceTransactions::refusal(...$details),
            null,
            HumanConfidenceTransactions::class
        );
        $folder = '/tmp/51degrees-human-confidence';
        $cases = [
            'a link' => [true, true, 040700, 1000, 1000, 'link'],
            'a link on Windows' => [true, false, 040777, 0, null, 'link'],
            'open to the group' => [false, true, 040750, 1000, 1000, '0700'],
            'open to others' => [false, true, 040701, 1000, 1000, '0700'],
            'open to all' => [false, true, 040777, 1000, 1000, '0700'],
            'mode unknown' => [false, true, false, 1000, 1000, '0700'],
            'another owner' => [false, true, 040700, 1001, 1000, 'another user'],
            'owner unknown' => [false, true, 040700, false, 1000, 'another user'],
            'the site user\'s own' => [false, true, 040700, 1000, 1000, null],
            'stricter than 0700' => [false, true, 040500, 1000, 1000, null],
            'site user unknown' => [false, true, 040700, 1001, null, null],
            'Windows' => [false, false, 040777, 0, null, null],
        ];
        foreach ($cases as $name => [$isLink, $posix, $mode, $owner, $user, $expected]) {
            $reason = $refusal($folder, $isLink, $posix, $mode, $owner, $user);
            if ($expected === null) {
                $this->assertNull($reason, $name);

                continue;
            }
            $this->assertIsString($reason, $name);
            $this->assertStringContainsString($folder, $reason, $name);
            $this->assertStringContainsString($expected, $reason, $name);
        }
    }

    public function testATransactionPastItsLifetimeIsNotGiven(): void
    {
        $transactions = new HumanConfidenceTransactions($this->folder, 1);
        $page = $transactions->newPage();
        $files = glob($this->folder . DIRECTORY_SEPARATOR . '*.transaction') ?: [];
        $this->assertCount(1, $files);
        touch($files[0], time() - 5);
        $this->assertNull(self::take($transactions, $page->pageId()));
        $this->assertFileDoesNotExist($files[0]);
    }

    public function testAPageIdFromTheRequestNeverNamesAFile(): void
    {
        $transactions = new HumanConfidenceTransactions($this->folder);
        $page = $transactions->newPage();
        $this->assertNull(self::take($transactions, '..' . DIRECTORY_SEPARATOR . 'outside'));
        $files = glob($this->folder . DIRECTORY_SEPARATOR . '*') ?: [];
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString($page->pageId(), $files[0]);
        foreach ([null, ''] as $none) {
            $this->assertNull(self::take($transactions, $none));
        }
        $this->assertSame(self::transactionOf($page), self::take($transactions, $page->pageId()));
    }

    public function testThePageOffersItsPageIdButNoTransaction(): void
    {
        $page = (new HumanConfidenceTransactions($this->folder))->newPage();
        $transaction = self::transactionOf($page);
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass($page))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        sort($methods);
        $this->assertSame(['__construct', '__debugInfo', 'pageId', 'scriptUrl'], $methods);
        $this->assertStringNotContainsString($transaction, print_r($page, true));
        $this->assertStringNotContainsString($transaction, $page->pageId());
        $this->assertSame('humanconfidencepage', HumanConfidencePage::PARAMETER);
    }

    /**
     * The store offers only new pages. Taking a kept transaction is for the
     * Cloud Request Engine alone, so no public method gives one, and the
     * engine doesn't hand the store out.
     */
    public function testTheConsumerHasNoAccessorForATransaction(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(HumanConfidenceTransactions::class))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        sort($methods);
        $this->assertSame(['__construct', 'newPage'], $methods);

        $engine = new \ReflectionClass(CloudRequestEngine::class);
        foreach ($engine->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            $this->assertFalse(
                $type instanceof \ReflectionNamedType && $type->getName() === HumanConfidenceTransactions::class,
                $property->getName()
            );
        }
        foreach ($engine->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            $this->assertFalse(
                $type instanceof \ReflectionNamedType && $type->getName() === HumanConfidenceTransactions::class,
                $method->getName()
            );
        }
    }

    public function testTheScriptAddressCarriesTheTransaction(): void
    {
        $page = (new HumanConfidenceTransactions($this->folder))->newPage();
        $this->assertSame(
            'https://cloud.example/api/v4/my%2Fkey.js?transaction=' . self::transactionOf($page),
            $page->scriptUrl('https://cloud.example/api/v4/', 'my/key')
        );
        $this->assertSame(
            $page->scriptUrl('https://cloud.example/api/v4/', 'k'),
            $page->scriptUrl('https://cloud.example/api/v4', 'k')
        );
    }

    public function testALifetimeOfZeroIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HumanConfidenceTransactions($this->folder, 0);
    }
}
