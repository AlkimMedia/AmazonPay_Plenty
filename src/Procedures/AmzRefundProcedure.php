<?php
namespace AmazonLoginAndPay\Procedures;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\Order;


class AmzRefundProcedure
{

    public function run(EventProceduresTriggered $eventTriggered,
                        AmzTransactionHelper $transactionHelper,
                        AlkimAmazonLoginAndPayHelper $helper
    )
    {
        try {
            /** @var Order $order */
            $creditNote = $eventTriggered->getOrder();
            $helper->log(__CLASS__, __METHOD__, 'refundProcedure', $creditNote);
            $orderId = 0;
            $amount = 0;
            $creditNoteId = 0;
            switch ($creditNote->typeId) {

                case 4: //credit note
                    $parentOrder = $creditNote->parentOrder;
                    $creditNoteId = $creditNote->id;
                    $amount = $creditNote->amounts[0]->invoiceTotal;
                    $helper->log(__CLASS__, __METHOD__, 'refundProcedure first note', ['orderReferences' => $creditNote->orderReferences, 'isObject' => is_object($creditNote->orderReferences), 'isArray' => is_array($creditNote->orderReferences)]);
                    if (isset($creditNote->orderReferences)) {
                        foreach ($creditNote->orderReferences as $reference) {
                            $helper->log(__CLASS__, __METHOD__, 'refundProcedure note', ['reference' => $reference, 'isObject' => is_object($reference), 'amount' => $amount]);
                            if ($reference->referenceType == 'parent') {
                                $orderId = $reference->originOrderId;
                            }
                        }
                    }

                    if (empty($orderId) && $parentOrder instanceof Order && $parentOrder->typeId == 1) {
                        $orderId = $parentOrder->id;

                        /*
                        else {
                            $parentParentOrder = $parentOrder->parentOrder;
                            if ($parentParentOrder instanceof Order) {
                                $orderId = $parentParentOrder->id;
                            }
                        }
                        */
                    }
                    break;
            }
            $helper->log(__CLASS__, __METHOD__, 'refundProcedure infos', ['orderId' => $orderId, 'creditNoteId' => $creditNoteId, 'amount' => $amount]);
            if (empty($orderId)) {
                throw new \Exception('Amazon Pay Refund failed! The given order is invalid!');
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
            if (is_array($captures) && isset($captures[0]) && !empty($creditNoteId)) {
                $capture = $captures[0];
                $transactionHelper->refund($capture->amzId, $amount, $creditNoteId);
            }
        } catch (\Exception $e) {
            $helper->log(__CLASS__, __METHOD__, 'plenty refund failed', [$e, $e->getMessage()], true);
        }

    }
}