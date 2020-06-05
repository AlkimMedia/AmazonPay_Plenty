<?php

namespace AmazonLoginAndPay\Procedures;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use Exception;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;

class AmzCloseOrderProcedure
{

    public function run(EventProceduresTriggered $eventTriggered, AmzTransactionHelper $transactionHelper, AlkimAmazonLoginAndPayHelper $helper)
    {
        $order = $eventTriggered->getOrder();
        $helper->log(__CLASS__, __METHOD__, 'closeOrderProcedure', $order);
        switch ($order->typeId) {
            case 1: //sales order
                $orderId = $order->id;
                break;
        }
        if (empty($orderId)) {
            throw new Exception('Amazon Pay Close Order failed! The given order is invalid!');
        }

        $oroArr = $transactionHelper->amzTransactionRepository->getTransactions([
            ['order', '=', $orderId],
            ['type', '=', 'order_ref']
        ]);
        $oro    = $oroArr[0];
        $transactionHelper->closeOrder($oro->orderReference);
    }
}