<?php

namespace AmazonLoginAndPay\Contracts;

use AmazonLoginAndPay\Models\AmzTransaction;

/**
 * Class AmzTransactionRepositoryContract
 * @package AmazonLoginAndPay\Contracts
 */
interface AmzTransactionRepositoryContract
{
    /**
     * Add a new transaction
     *
     * @param array $data
     *
     * @return AmzTransaction
     */
    public function createTransaction(array $data);

    /**
     * List all transactions
     *
     * @param array $criteria
     *
     * @return AmzTransaction[]
     */
    public function getTransactions($criteria);

    /**
     * Update transaction
     *
     * @param AmzTransaction $transaction
     *
     * @return AmzTransaction
     */
    public function updateTransaction(AmzTransaction $transaction);

    /**
     * Save transaction
     *
     * @param AmzTransaction $transaction
     *
     * @return AmzTransaction
     */
    public function saveTransaction(AmzTransaction $transaction);

}