<?php
namespace AmazonLoginAndPay\Migrations;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Models\AmzTransaction;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

/**
 * Class CreateAmzTransactionTable
 */
class CreateAmzTransactionTable
{

    public function run(Migrate $migrate, AlkimAmazonLoginAndPayHelper $helper)
    {
        $migrate->createTable(AmzTransaction::class);

    }
}