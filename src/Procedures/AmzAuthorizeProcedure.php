<?php

namespace AmazonLoginAndPay\Procedures;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;

class AmzAuthorizeProcedure
{

    public function run(EventProceduresTriggered $eventTriggered, AmzTransactionHelper $transactionHelper, AlkimAmazonLoginAndPayHelper $helper)
    {
        $order = $eventTriggered->getOrder();
        $helper->log(__CLASS__, __METHOD__, 'authorizeProcedure', $order);
        // only sales orders and credit notes are allowed order types to refund
        switch ($order->typeId) {
            case 1: //sales order
                $orderId = $order->id;
                break;
        }
        if (empty($orderId)) {
            throw new \Exception('Amazon Pay Authorization failed! The given order is invalid!');
        }

        $oroArr = $transactionHelper->amzTransactionRepository->getTransactions([
            ['order', '=', $orderId],
            ['type', '=', 'order_ref']
        ]);
        $oro    = $oroArr[0];
        $amount = $oro->amount;
        $transactionHelper->authorize($oro->orderReference, $amount);
    }
}