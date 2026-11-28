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

namespace fiftyone\pipeline\cloudrequestengine;

/**
 * Issues a new Human Confidence transaction for each page and keeps it on
 * the server until the Cloud Request Engine redeems the page's sealed
 * result with it. A kept transaction is taken once, so it never serves two
 * requests.
 *
 * The transaction is kept under a page id issued with it, which the page
 * sends back as the Evidence query.humanconfidencepage. Each page has its
 * own, so two pages open in one session each redeem their own sealed
 * result.
 *
 * PHP keeps nothing in memory from one request to the next, so each
 * transaction is kept in a file of its own in a folder that every request
 * of the site can read and write. The folder is the 51degrees-human-confidence
 * folder in the system's temporary folder unless another is given. A site
 * served by more than one machine gives a folder they share.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#the-transaction
 */
final class HumanConfidenceTransactions
{
    /** How long a page's transaction is kept, in seconds. */
    public const LIFETIME_SECONDS = 600;

    /**
     * The most pages kept at once. Issuing one more drops the oldest, so a
     * flood of pages cannot fill the disk.
     */
    public const MAX_PAGES = 10000;

    /** The folder in the system's temporary folder used by default. */
    public const DEFAULT_FOLDER_NAME = '51degrees-human-confidence';

    // The bytes of randomness in a transaction.
    private const TRANSACTION_BYTES = 32;

    // The bytes of randomness in a page id, enough that one page can't
    // guess another's.
    private const PAGE_ID_BYTES = 16;

    // The file name ending of a kept transaction.
    private const EXTENSION = '.transaction';

    private string $folder;

    private int $lifetimeSeconds;

    /**
     * @param null|string $folder The folder the transactions are kept in,
     *   or null for the default
     * @param int $lifetimeSeconds How long a transaction is kept
     * @throws \InvalidArgumentException for a lifetime that is not greater
     *   than 0
     */
    public function __construct(?string $folder = null, int $lifetimeSeconds = self::LIFETIME_SECONDS)
    {
        if ($lifetimeSeconds <= 0) {
            throw new \InvalidArgumentException(
                'The lifetime of a Human Confidence transaction must be greater than 0 seconds.'
            );
        }
        $this->folder = rtrim(
            $folder ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::DEFAULT_FOLDER_NAME,
            '/\\'
        );
        $this->lifetimeSeconds = $lifetimeSeconds;
    }

    /**
     * A new page with a new page id and a new transaction, kept under the
     * page id until the page's sealed result is redeemed or the lifetime
     * ends.
     *
     * @throws \RuntimeException where the transaction cannot be kept, as
     *   where the folder can't be trusted
     */
    public function newPage(): HumanConfidencePage
    {
        $this->ensureFolder();
        $this->removeOld();

        $pageId = self::randomText(self::PAGE_ID_BYTES);
        $transaction = self::randomText(self::TRANSACTION_BYTES);
        $file = $this->file($pageId);
        $written = self::quietly(fn () => file_put_contents($file, $transaction, LOCK_EX));
        if ($written !== strlen($transaction)) {
            throw new \RuntimeException('The Human Confidence transaction could not be kept.');
        }

        return new HumanConfidencePage($pageId, $transaction);
    }

    /**
     * The transaction kept for the page id, which is no longer kept once
     * taken, or null where none is kept for it, as when it was taken
     * before or its lifetime has ended.
     *
     * Private, so a consumer has no accessor for a transaction. Only the
     * Cloud Request Engine takes one, to redeem with, by calling this in
     * the scope of this class.
     */
    // @phpstan-ignore-next-line The Cloud Request Engine calls it in this class's scope.
    private function take(?string $pageId): ?string
    {
        if ($pageId === null || $pageId === '') {
            return null;
        }
        $file = $this->file($pageId);
        // Nothing is taken from a folder that can't be trusted, so the
        // sealed result isn't redeemed and reads Medium.
        if (!is_file($file) || $this->folderRefusal() !== null) {
            return null;
        }

        // Renaming is atomic, so of two requests for the same page only one
        // gets the transaction.
        $taken = $file . '.' . bin2hex(random_bytes(8));
        if (self::quietly(fn () => rename($file, $taken)) !== true) {
            return null;
        }
        $modified = self::quietly(fn () => filemtime($taken));
        $transaction = self::quietly(fn () => file_get_contents($taken));
        self::quietly(fn () => unlink($taken));

        if (
            !is_int($modified) ||
            !is_string($transaction) ||
            $transaction === '' ||
            time() - $modified > $this->lifetimeSeconds
        ) {
            return null;
        }

        return $transaction;
    }

    /**
     * The file a page's transaction is kept in. The page id comes back
     * from the request, so the name is its hash, which holds nothing that
     * could name another folder.
     */
    private function file(string $pageId): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . hash('sha256', $pageId) . self::EXTENSION;
    }

    /**
     * Random bytes as text that is safe in an address without encoding.
     */
    private static function randomText(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /**
     * @throws \RuntimeException where the folder cannot be made, or is
     *   there already and can't be trusted
     */
    private function ensureFolder(): void
    {
        if (!is_dir($this->folder)) {
            // Another request may make it at the same moment.
            self::quietly(fn () => mkdir($this->folder, 0700, true));
            if (!is_dir($this->folder)) {
                throw new \RuntimeException('The folder for Human Confidence transactions could not be made.');
            }
        }
        $refusal = $this->folderRefusal();
        if ($refusal !== null) {
            throw new \RuntimeException($refusal);
        }
    }

    /**
     * Why the folder can't be trusted to keep transactions, or null where
     * it can. Another local user could make the folder first, as the
     * default is a fixed name in the shared temporary folder, and then
     * read or plant transactions. So a folder that is a link is refused
     * everywhere. On POSIX systems, a folder that the user the site runs
     * as doesn't own, or that its group or anyone else can use at all, is
     * refused too. On Windows, PHP can't read a folder's owner or access
     * list, so only the link is checked there. The default folder is then
     * in the user's own temporary folder, which other users can't open.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#the-transaction
     */
    private function folderRefusal(): ?string
    {
        clearstatcache(true, $this->folder);
        $posix = DIRECTORY_SEPARATOR === '/';

        return self::refusal(
            $this->folder,
            is_link($this->folder),
            $posix,
            self::quietly(fn () => fileperms($this->folder)),
            self::quietly(fn () => fileowner($this->folder)),
            $posix ? self::processUser() : null
        );
    }

    /**
     * Why a folder with these details can't be trusted, or null where it
     * can. The text names the folder and the rule, and nothing kept in it.
     *
     * @param false|int $permissions The folder's mode, as fileperms() gives
     * @param false|int $owner The folder's owner, as fileowner() gives
     * @param null|int $user The user the site runs as, or null where it
     *   can't be found
     */
    private static function refusal(
        string $folder,
        bool $isLink,
        bool $posix,
        $permissions,
        $owner,
        ?int $user
    ): ?string {
        $unsafe = 'The folder for Human Confidence transactions, ' . $folder .
            ', is not safe to keep them in. ';
        if ($isLink) {
            return $unsafe . 'It is a link. Give a folder that is not a link.';
        }
        if (!$posix) {
            return null;
        }
        if (!is_int($permissions) || ($permissions & 0077) !== 0) {
            return $unsafe . 'Its group or other users can use it. ' .
                'Make it open to its owner alone, with mode 0700.';
        }
        if ($user !== null && $owner !== $user) {
            return $unsafe . 'It belongs to another user. ' .
                'Give a folder that belongs to the user the site runs as.';
        }

        return null;
    }

    /**
     * The user the site runs as, from the posix extension where it's
     * there, and otherwise from the owner of a file the site makes, or
     * null where neither can be had.
     */
    private static function processUser(): ?int
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid();
        }
        $file = self::quietly(fn () => tempnam(sys_get_temp_dir(), '51d'));
        if (!is_string($file)) {
            return null;
        }
        $owner = self::quietly(fn () => fileowner($file));
        self::quietly(fn () => unlink($file));

        return is_int($owner) ? $owner : null;
    }

    /**
     * Removes the transactions whose lifetime has ended, and the oldest
     * where more than MAX_PAGES are kept.
     */
    private function removeOld(): void
    {
        $files = glob($this->folder . DIRECTORY_SEPARATOR . '*' . self::EXTENSION . '*');
        if ($files === false) {
            return;
        }
        $now = time();
        $kept = [];
        foreach ($files as $file) {
            $modified = self::quietly(fn () => filemtime($file));
            if (!is_int($modified) || $now - $modified > $this->lifetimeSeconds) {
                self::quietly(fn () => unlink($file));
            } else {
                $kept[$file] = $modified;
            }
        }
        if (count($kept) < self::MAX_PAGES) {
            return;
        }
        asort($kept);
        foreach (array_slice(array_keys($kept), 0, count($kept) - self::MAX_PAGES + 1) as $file) {
            self::quietly(fn () => unlink($file));
        }
    }

    /**
     * Runs a file function whose failure the caller checks for, without the
     * warning PHP raises for it, as another request may have changed the
     * file first.
     *
     * @return mixed
     */
    private static function quietly(callable $call)
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }
}
