<?php

namespace AmazonLoginAndPay\Repositories;

use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Exceptions\ValidationException;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Models\AmzTransaction;
use AmazonLoginAndPAy\Validators\AmzTransactionValidator;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Modules\Frontend\Services\AccountService;


class AmzTransactionRepository implements AmzTransactionRepositoryContract
{

    public $helper;
    private $accountService;

    public function __construct(AccountService $accountService, AlkimAmazonLoginAndPayHelper $helper)
    {
        $this->accountService = $accountService;
        $this->helper = $helper;
    }

    /**
     * Add a new item to the To Do list
     *
     * @param array $data
     * @return AmzTransaction
     * @throws ValidationException
     */
    public function createTransaction(array $data)
    {
        /*try {
            AmzTransactionValidator::validateOrFail($data);
        } catch (ValidationException $e) {
            throw $e;
        }*/

        /**
         * @var DataBase $database
         */


        $database = pluginApp(DataBase::class);

        $transaction = pluginApp(AmzTransaction::class);
        $transaction->orderReference = (string)$data["orderReference"];
        $transaction->type = (string)$data["type"];
        $transaction->status = (string)$data["status"];
        $transaction->reference = (string)$data["reference"];
        $transaction->expiration = (string)$data["expiration"];
        $transaction->time = (string)$data["time"];
        $transaction->amzId = (string)$data["amzId"];
        $transaction->lastChange = (string)$data["lastChange"];
        $transaction->lastUpdate = (string)$data["lastUpdate"];
        $transaction->customerInformed = (bool)$data["customerInformed"];
        $transaction->adminInformed = (bool)$data["adminInformed"];
        $transaction->merchantId = (string)$data["merchantId"];
        $transaction->mode = (string)$data["mode"];
        $transaction->amount = (float)$data["amount"];
        $transaction->amountRefunded = (float)$data["amountRefunded"];
        $transaction->order = (string)$data["order"];
        $transaction->paymentId = (int)$data["paymentId"];
        $this->helper->log(__CLASS__, __METHOD__, 'create transaction - before save', ['data' => $data, 'input' => $transaction]);
        try {
            $response = $database->save($transaction);
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'create transaction - exception', [$e, $e->getMessage()], true);
        }
        $this->helper->log(__CLASS__, __METHOD__, 'create transaction - after save', ['data' => $data, 'input' => $transaction, 'output' => $response]);

        return $response;
    }


    /**
     * Get the current contact ID
     * @return int
     */
    public function getCurrentContactId()
    {
        return $this->accountService->getAccountContactId();
    }

    /**
     * List all items of the To Do list
     *
     * @return AmzTransaction[]
     */
    public function getTransactions($criteria)
    {
        $database = pluginApp(DataBase::class);
        $stmt = $database->query(AmzTransaction::class);
        foreach ($criteria as $c) {
            $stmt->where($c[0], $c[1], $c[2]);
        }
        $transactions = $stmt->get();
        $this->helper->log(__CLASS__, __METHOD__, 'get transactions', [$transactions]);
        return $transactions;
    }


    public function updateTransaction(AmzTransaction $transaction)
    {
        /**
         * @var DataBase $database
         */
        $database = pluginApp(DataBase::class);
        $transaction->lastUpdate = date('Y-m-d H:i:s');
        try {
            $response = $database->save($transaction);
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'update transaction - exception', [$e, $e->getMessage()], true);
        }
        $this->helper->log(__CLASS__, __METHOD__, 'update transaction - after save', ['input' => $transaction, 'output' => $response]);
        return $response;
    }

}