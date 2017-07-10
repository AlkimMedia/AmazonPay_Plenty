<?php
namespace AmazonLoginAndPay\Procedures;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Modules\Order\Models\Order;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Modules\Payment\Models\Payment;


class AmzCaptureProcedure
{

    public function run(EventProceduresTriggered $eventTriggered,
                        AmzTransactionHelper $transactionHelper,
                        AlkimAmazonLoginAndPayHelper $helper
    )
    {
        /** @var Order $order */
        $order = $eventTriggered->getOrder();
        $helper->log(__CLASS__, __METHOD__, 'captureProcedure', $order);
        // only sales orders and credit notes are allowed order types to refund
        switch ($order->typeId) {
            case 1: //sales order
                $orderId = $order->id;
                break;
        }
        if (empty($orderId)) {
            throw new \Exception('Amazon Pay Capture failed! The given order is invalid!');
        }

        $openAuths = $transactionHelper->amzTransactionRepository->getTransactions([
            ['order', '=', $orderId],
            ['type', '=', 'auth'],
            ['status', '=', 'Open']
        ]);

        foreach ($openAuths as $openAuth) {
            $transactionHelper->capture($openAuth->amzId, $openAuth->amount);
        }

    }
}