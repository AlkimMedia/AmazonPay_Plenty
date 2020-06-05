<?php

namespace AmazonLoginAndPay\Procedures;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use Exception;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;

class AmzCaptureProcedure
{

    public function run(EventProceduresTriggered $eventTriggered, AmzTransactionHelper $transactionHelper, AlkimAmazonLoginAndPayHelper $helper)
    {
        $order = $eventTriggered->getOrder();
        $helper->log(__CLASS__, __METHOD__, 'captureProcedure', $order);
        // only sales orders and credit notes are allowed order types to refund
        switch ($order->typeId) {
            case 1: //sales order
                $orderId = $order->id;
                break;
        }
        if (empty($orderId)) {
            throw new Exception('Amazon Pay Capture failed! The given order is invalid!');
        }

        $openAuths = $transactionHelper->amzTransactionRepository->getTransactions([
            ['order', '=', $orderId],
            ['type', '=', 'auth'],
            ['status', '=', 'Open']
        ]);

        foreach ($openAuths as $openAuth) {
            $transactionHelper->refreshAuthorization($openAuth);
        }

        $openAuths = $transactionHelper->amzTransactionRepository->getTransactions([
            ['order', '=', $orderId],
            ['type', '=', 'auth'],
            ['status', '=', 'Open']
        ]);

        if (count($openAuths) === 0) {
            $oroArr = $transactionHelper->amzTransactionRepository->getTransactions([
                ['order', '=', $orderId],
                ['type', '=', 'order_ref']
            ]);
            $oro    = $oroArr[0];
            $amount = $oro->amount;
            $transactionHelper->authorize($oro->orderReference, $amount, 0);

            $openAuths = $transactionHelper->amzTransactionRepository->getTransactions([
                ['order', '=', $orderId],
                ['type', '=', 'auth'],
                ['status', '=', 'Open']
            ]);

        }

        foreach ($openAuths as $openAuth) {
            $helper->log(__CLASS__, __METHOD__, 'amounts', [$openAuth->amount, $order->amount->invoiceTotal]);
            $amountToCapture = min($openAuth->amount, $order->amount->invoiceTotal);
            $transactionHelper->capture($openAuth->amzId, $amountToCapture);
        }

    }
}