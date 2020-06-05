<?php

namespace AmazonLoginAndPay\Repositories;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Models\AmzTransaction;
use Exception;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;

class AmzTransactionRepository implements AmzTransactionRepositoryContract
{

    public $helper;

    public function __construct(AlkimAmazonLoginAndPayHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param array $data
     *
     * @return AmzTransaction
     */
    public function createTransaction(array $data)
    {
        $transaction                   = pluginApp(AmzTransaction::class);
        $transaction->orderReference   = (string)$data["orderReference"];
        $transaction->type             = (string)$data["type"];
        $transaction->status           = (string)$data["status"];
        $transaction->reference        = (string)$data["reference"];
        $transaction->expiration       = (string)$data["expiration"];
        $transaction->time             = (string)$data["time"];
        $transaction->amzId            = (string)$data["amzId"];
        $transaction->lastChange       = (string)$data["lastChange"];
        $transaction->lastUpdate       = (string)$data["lastUpdate"];
        $transaction->customerInformed = (bool)$data["customerInformed"];
        $transaction->adminInformed    = (bool)$data["adminInformed"];
        $transaction->merchantId       = (string)$data["merchantId"];
        $transaction->mode             = (string)$data["mode"];
        $transaction->amount           = (float)$data["amount"];
        $transaction->amountRefunded   = (float)$data["amountRefunded"];
        $transaction->order            = (string)$data["order"];
        $transaction->paymentId        = (int)$data["paymentId"];
        $transaction->currency         = (string)$data["currency"];

        return $this->saveTransaction($transaction);
    }

    /**
     * @param AmzTransaction $transaction
     *
     * @return AmzTransaction|\Plenty\Modules\Plugin\DataBase\Contracts\Model
     */
    public function saveTransaction(AmzTransaction $transaction)
    {
        /**
         * @var DataBase $database
         */
        $database = pluginApp(DataBase::class);
        $this->helper->log(__CLASS__, __METHOD__, 'save transaction - before save', ['input' => $transaction]);
        $response = null;
        try {
            /** @var AmzTransaction $response */
            $response = $database->save($transaction);
        } catch (Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'save transaction - exception', [$e, $e->getMessage()], true);
        }
        $this->helper->log(__CLASS__, __METHOD__, 'save transaction - after save', ['input' => $transaction, 'output' => $response]);

        return $response;
    }

    /**
     * @param array $criteria
     *
     * @return AmzTransaction[]
     */
    public function getTransactions($criteria)
    {
        /** @var DataBase $database */
        $database = pluginApp(DataBase::class);
        $stmt     = $database->query(AmzTransaction::class);
        foreach ($criteria as $c) {
            $stmt->where($c[0], $c[1], $c[2]);
        }
        $transactions = $stmt->get();
        $this->helper->log(__CLASS__, __METHOD__, 'get transactions', [$transactions]);

        return $transactions;
    }

    public function updateTransaction(AmzTransaction $transaction)
    {
        $transaction->lastUpdate = date('Y-m-d H:i:s');

        return $this->saveTransaction($transaction);
    }

}