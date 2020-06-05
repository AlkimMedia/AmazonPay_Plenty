<?php

namespace AmazonLoginAndPay\Procedures;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use Exception;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\Order;

class AmzRefundProcedure
{

    public function run(EventProceduresTriggered $eventTriggered, AmzTransactionHelper $transactionHelper, AlkimAmazonLoginAndPayHelper $helper)
    {
        try {
            /** @var Order $order */
            $procedureOrderObject = $eventTriggered->getOrder();
            $helper->log(__CLASS__, __METHOD__, 'refundProcedure', $procedureOrderObject);
            $orderId      = 0;
            $amount       = 0;
            switch ($procedureOrderObject->typeId) {
                case 4: //credit note
                    $parentOrder  = $procedureOrderObject->parentOrder;
                    $amount       = $procedureOrderObject->amounts[0]->invoiceTotal;
                    $helper->log(__CLASS__, __METHOD__, 'refundProcedure first note', ['orderReferences' => $procedureOrderObject->orderReferences, 'isObject' => is_object($procedureOrderObject->orderReferences), 'isArray' => is_array($procedureOrderObject->orderReferences)]);
                    if (isset($procedureOrderObject->orderReferences)) {
                        foreach ($procedureOrderObject->orderReferences as $reference) {
                            $helper->log(__CLASS__, __METHOD__, 'refundProcedure note', ['reference' => $reference, 'isObject' => is_object($reference), 'amount' => $amount]);
                            if ($reference->referenceType == 'parent') {
                                $orderId = $reference->originOrderId;
                            }
                        }
                    }

                    if (empty($orderId) && $parentOrder instanceof Order && $parentOrder->typeId == 1) {
                        $orderId = $parentOrder->id;
                    }
                    break;
                case 1: //sales order
                    $orderId = $procedureOrderObject->id;
                    $amount  = $procedureOrderObject->amounts[0]->invoiceTotal;
                    break;
            }
            $helper->log(__CLASS__, __METHOD__, 'refundProcedure infos', ['orderId' => $orderId, 'procedureOrderObjectId' => $procedureOrderObject->id, 'amount' => $amount]);
            if (empty($orderId)) {
                throw new Exception('Amazon Pay Refund failed! The given order is invalid!');
            }

            $captures = $transactionHelper->amzTransactionRepository->getTransactions([
                ['order', '=', $orderId],
                ['type', '=', 'capture'],
                ['amount', '=', $amount]
            ]);
            $helper->log(__CLASS__, __METHOD__, 'refundProcedure captures', $captures);

            if (!is_array($captures) || count($captures) == 0) {
                $captures = $transactionHelper->amzTransactionRepository->getTransactions([
                    ['order', '=', $orderId],
                    ['type', '=', 'capture'],
                    ['amount', '>=', $amount]
                ]);
                $helper->log(__CLASS__, __METHOD__, 'refundProcedure captures - 2nd try', $captures);
            }
            if (is_array($captures) && isset($captures[0]) && !empty($procedureOrderObject->id)) {
                $capture = $captures[0];
                $transactionHelper->refund($capture->amzId, $amount, $procedureOrderObject->id);
            }
        } catch (Exception $e) {
            $helper->log(__CLASS__, __METHOD__, 'plenty refund failed', [$e, $e->getMessage()], true);
        }

    }
}