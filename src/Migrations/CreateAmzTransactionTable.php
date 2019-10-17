<?php

namespace AmazonLoginAndPay\Migrations;

use AmazonLoginAndPay\Models\AmzTransaction;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

/**
 * Class CreateAmzTransactionTable
 */
class CreateAmzTransactionTable
{

    public function run(Migrate $migrate)
    {
        $migrate->createTable(AmzTransaction::class);
        $migrate->updateTable(AmzTransaction::class);
    }
}