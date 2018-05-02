<?php

namespace AmazonLoginAndPay\Migrations;

use AmazonLoginAndPay\Models\AmzTransaction;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

/**
 * Class CreateAmzTransactionTable
 */
class UpdateAmzTransactionTable_AddCurrencyField
{

    public function run(Migrate $migrate)
    {
        $migrate->updateTable(AmzTransaction::class);
    }
}