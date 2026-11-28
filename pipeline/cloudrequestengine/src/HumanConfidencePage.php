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
 * One page that asks for Human Confidence, made by
 * {@see HumanConfidenceTransactions::newPage()}. The page loads the
 * 51Degrees client script from scriptUrl(), which carries the page's
 * transaction to the remote server. It sends pageId() back with the sealed
 * result, as the parameter PARAMETER, which the web integration adds as
 * the Evidence query.humanconfidencepage, so the Pipeline redeems the
 * sealed result with the transaction it kept for that page. The
 * transaction itself has no accessor.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#the-transaction
 */
final class HumanConfidencePage
{
    /** The parameter the page sends its page id back as. */
    public const PARAMETER = 'humanconfidencepage';

    // The parameter the remote server reads the transaction from.
    private const TRANSACTION_PARAMETER = 'transaction';

    private string $pageId;

    private string $transaction;

    /**
     * @internal Made by HumanConfidenceTransactions, which keeps the
     *   transaction on the server under the page id.
     */
    public function __construct(string $pageId, string $transaction)
    {
        $this->pageId = $pageId;
        $this->transaction = $transaction;
    }

    /**
     * The page id, which the page sends back as PARAMETER with its sealed
     * result. It names the page's kept transaction, and is not the
     * transaction.
     */
    public function pageId(): string
    {
        return $this->pageId;
    }

    /**
     * The address of the 51Degrees client script for this page.
     *
     * @param string $endpoint The cloud service's address, ending with or
     *   without a slash, such as https://cloud.51degrees.com/api/v4/
     * @param string $resourceKey The Resource Key the page uses
     */
    public function scriptUrl(string $endpoint, string $resourceKey): string
    {
        return rtrim($endpoint, '/') . '/' . rawurlencode($resourceKey) . '.js?' .
            http_build_query([self::TRANSACTION_PARAMETER => $this->transaction]);
    }

    /**
     * The page id alone, so a dump of the page does not show the
     * transaction.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['pageId' => $this->pageId];
    }
}
