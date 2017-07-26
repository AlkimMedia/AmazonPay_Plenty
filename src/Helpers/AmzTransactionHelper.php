<?php
namespace AmazonLoginAndPay\Helpers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Models\AmzTransaction;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\ConfigRepository;

class AmzTransactionHelper
{
    public $paymentMethodRepository;
    public $callLib;
    public $helper;
    public $amzTransactionRepository;

    public function __construct(AmzTransactionRepositoryContract $amzTransactionRepository, PaymentMethodRepositoryContract $paymentMethodRepository, LibraryCallContract $libCall, AlkimAmazonLoginAndPayHelper $helper)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->callLib = $libCall;
        $this->helper = $helper;
        $this->amzTransactionRepository = $amzTransactionRepository;
    }


    public function setOrderReferenceDetails($orderRef, $amount, $orderId)
    {
        $requestParameters = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $requestParameters['amount'] = $amount;
        $requestParameters['currency_code'] = 'EUR';
        $requestParameters['platform_id'] = 'A1SGXK19QKIYNB';
        $requestParameters['merchant_id'] = $this->helper->getFromConfig('merchantId');
        $requestParameters['store_name'] = $this->helper->getWebstoreName();
        if (!empty($orderId)) {
            $requestParameters['seller_order_id'] = $orderId;
        }
        $requestParameters['seller_note'] = '';
        $response = $this->call('SetOrderReferenceDetails', $requestParameters);
        return $response;
    }

    public function call($action, $parameters)
    {
        $result = $this->callLib->call(
            'AmazonLoginAndPay::amz_client_call',
            [
                'config' => $this->helper->getCallConfig(),
                'action' => $action,
                'parameters' => $parameters
            ]
        );
        $this->helper->log(__CLASS__, __METHOD__, 'call result ' . $action, ['config' => $this->helper->getCallConfig(), 'action' => $action, 'parameters' => $parameters, 'result' => $result]);
        return $result;
    }

    public function confirmOrderReference($orderRef, $saveTransaction = true, $orderId = '')
    {
        $requestParameters = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $response = $this->call('confirmOrderReference', $requestParameters);
        if ($saveTransaction) {
            $details = $this->getOrderReferenceDetails($orderRef);
            $details = $details["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"];

            $data = [
                'orderReference' => $orderRef,
                'type' => 'order_ref',
                'status' => $details["OrderReferenceStatus"]["State"],
                'reference' => $orderRef,
                'expiration' => $details["ExpirationTimestamp"],
                'time' => date('Y-m-d H:i:s'),
                'amzId' => $orderRef,
                'lastChange' => date('Y-m-d H:i:s'),
                'lastUpdate' => date('Y-m-d H:i:s'),
                'customerInformed' => false,
                'adminInformed' => false,
                'merchantId' => $this->helper->getFromConfig('merchantId'),
                'mode' => $this->helper->getTransactionMode(),
                'amount' => $details["OrderTotal"]["Amount"],
                'amountRefunded' => 0,
                'order' => $orderId
            ];
            $this->amzTransactionRepository->createTransaction($data);
        }

        return $response;
    }

    public function getOrderReferenceDetails($orderRef, $token = '')
    {
        $requestParameters = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $requestParameters['address_consent_token'] = $token;
        $response = $this->call('GetOrderReferenceDetails', $requestParameters);
        return $response;
    }

    public function authorize($orderRef, $amount, $timeout = 1440, $comment = '')
    {

        $requestParameters = [];
        $requestParameters['authorization_amount'] = $amount;
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $requestParameters['transaction_timeout'] = $timeout;
        $requestParameters['currency_code'] = 'EUR';
        $requestParameters['merchant_id'] = $this->helper->getFromConfig('merchantId');
        $requestParameters['soft_descriptor'] = $comment;
        $requestParameters['authorization_reference_id'] = 'plenty_auth_ref_' . time() . '_' . rand(10000, 99999);
        /*
        if (MODULE_PAYMENT_AM_APA_PROVOCATION == 'hard_decline' && MODULE_PAYMENT_AM_APA_MODE == 'sandbox') {
            $requestParameters['seller_authorization_note'] = '{"SandboxSimulation": {"State":"Declined", "ReasonCode":"AmazonRejected"}}';
        }

        if (MODULE_PAYMENT_AM_APA_PROVOCATION == 'soft_decline' && MODULE_PAYMENT_AM_APA_MODE == 'sandbox') {
            $requestParameters['seller_authorization_note'] = '{"SandboxSimulation": {"State":"Declined", "ReasonCode":"InvalidPaymentMethod", "PaymentMethodUpdateTimeInMins":1}}';
        }*/

        $response = $this->call('authorize', $requestParameters);
        $details = $response["AuthorizeResult"]["AuthorizationDetails"];
        $authId = $details["AmazonAuthorizationId"];
        if ($authId != '') {
            $data = [
                'orderReference' => $orderRef,
                'type' => 'auth',
                'status' => $details["AuthorizationStatus"]["State"],
                'reference' => $details["AuthorizationReferenceId"],
                'expiration' => $details["ExpirationTimestamp"],
                'time' => date('Y-m-d H:i:s'),
                'amzId' => $authId,
                'lastChange' => date('Y-m-d H:i:s'),
                'lastUpdate' => date('Y-m-d H:i:s'),
                'customerInformed' => false,
                'adminInformed' => false,
                'merchantId' => $this->helper->getFromConfig('merchantId'),
                'mode' => $this->helper->getTransactionMode(),
                'amount' => $amount,
                'amountRefunded' => 0
            ];
            $this->helper->log(__CLASS__, __METHOD__, 'try to create payment', ['payment' => $data]);
            try {
                $plentyPayment = $this->helper->createPlentyPayment(($data["status"] == 'Declined' ? 0 : $amount), ($data["status"] == 'Open' ? 'approved' : ($data["status"] == 'Pending' ? 'awaiting_approval' : 'refused')), date('Y-m-d H-i-s'), 'Autorisierung: ' . $data["amzId"] . "\n" . 'Betrag: ' . $amount . "\n" . 'Status: ' . $data["status"], $data["amzId"]);
            } catch (\Exception $e) {
                $this->helper->log(__CLASS__, __METHOD__, 'plenty payment creation failed', [$e, $e->getMessage()], true);
            }
            $this->helper->log(__CLASS__, __METHOD__, 'payment created', ['payment' => $plentyPayment]);
            $orderId = $this->getOrderIdFromOrderRef($orderRef);
            $this->helper->log(__CLASS__, __METHOD__, 'orderid', [$orderId]);
            if ($plentyPayment instanceof Payment && !empty($orderId)) {
                $this->helper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
                $this->helper->log(__CLASS__, __METHOD__, 'assign payment to order', [$plentyPayment, $orderId]);
            }
            $data["paymentId"] = $plentyPayment->id;
            $data["order"] = $orderId;
            $this->amzTransactionRepository->createTransaction($data);
        }

        return $response;
    }

    public function getOrderIdFromOrderRef($orderRef)
    {
        $transactions = $this->amzTransactionRepository->getTransactions([['orderReference', '=', $orderRef], ['type', '=', 'order_ref']]);
        $this->helper->log(__CLASS__, __METHOD__, 'get order id transaction', [$transactions[0]]);
        return $transactions[0]->order;
    }

    public function refund($captureId, $amount, $creditNoteId = null)
    {
        if ($captureId) {
            $orderRef = $this->getOrderRefFromAmzId($captureId);
            $requestParameters = [];
            $requestParameters['amazon_capture_id'] = $captureId;
            $requestParameters['refund_amount'] = $amount;
            $requestParameters['currency_code'] = 'EUR';
            $requestParameters['refund_reference_id'] = 'plenty_ref_r_' . time() . '_' . rand(10000, 99999);

            $response = $this->call('refund', $requestParameters);

            $details = $response["RefundResult"]["RefundDetails"];
            $refundId = $details["AmazonRefundId"];
            $data = [
                'orderReference' => $orderRef,
                'type' => 'refund',
                'status' => $details["RefundStatus"]["State"],
                'reference' => $details["RefundReferenceId"],
                'expiration' => '',
                'time' => date('Y-m-d H:i:s'),
                'amzId' => $refundId,
                'lastChange' => date('Y-m-d H:i:s'),
                'lastUpdate' => date('Y-m-d H:i:s'),
                'customerInformed' => false,
                'adminInformed' => false,
                'merchantId' => $this->helper->getFromConfig('merchantId'),
                'mode' => ($this->helper->getFromConfig('sandbox') == 'true' ? 'Sandbox' : 'Live'),
                'amount' => $amount,
                'amountRefunded' => 0
            ];

            try {
                $plentyRefund = $this->helper->createPlentyPayment($amount, 'refunded', date('Y-m-d H-i-s'), 'Rueckzahlung: ' . $data["amzId"] . "\n" . 'Betrag: ' . $amount . "\n" . 'Status: ' . $data["status"], $data["amzId"], 'debit');
            } catch (\Exception $e) {
                $this->helper->log(__CLASS__, __METHOD__, 'plenty refund creation failed', [$e, $e->getMessage()], true);
            }

            $this->helper->log(__CLASS__, __METHOD__, 'refund created', ['payment' => $plentyRefund]);
            $orderId = $this->getOrderIdFromOrderRef($orderRef);

            if (!empty($creditNoteId)) {
                $docId = $creditNoteId;
            } else {
                $docId = $orderId;
            }

            if ($plentyRefund instanceof Payment) {
                $this->helper->assignPlentyPaymentToPlentyOrder($plentyRefund, $docId);
            }
            $data["paymentId"] = $plentyRefund->id;
            $data["order"] = $orderId;

            $this->amzTransactionRepository->createTransaction($data);
            /*
                        $transaction = $this->getTransactionFromAmzId($captureId);
                        $this->helper->log(__CLASS__, __METHOD__, 'transaction for payment status update', [$transaction]);
                        if ($transaction) {
                            $paymentId = $transaction->paymentId;
                            $this->helper->log(__CLASS__, __METHOD__, 'try to update payment', [$paymentId]);
                            $this->helper->updatePlentyPayment($paymentId, 'captured');
                        }
            */
            return $response;
        } else {
            return false;
        }

    }

    public function getOrderRefFromAmzId($amzId)
    {
        if (preg_match('/([0-9A-Z]+\-[0-9]+\-[0-9]+)\-[0-9A-Z]+/', $amzId, $matches)) {
            $this->helper->log(__CLASS__, __METHOD__, 'or matches', [$matches]);
            return $matches[1];
        } else {
            return '';
        }
    }

    public function intelligentRefresh(AmzTransaction $transaction)
    {
        switch ($transaction->type) {
            case 'refund':
                //$this->refreshRefund($transaction);
                break;
            case 'capture':
                //$this->refreshCapture($transaction);
                break;
            case 'auth':
                $this->refreshAuthorization($transaction);
                break;
            case 'order_ref':
                $this->refreshOrderReference($transaction);
                break;

        }
    }

    public function refreshAuthorization(AmzTransaction $transaction)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'refresh authorization', [$transaction]);
        $details = $this->call('getAuthorizationDetails', ['amazon_authorization_id' => $transaction->amzId]);
        $details = $details["GetAuthorizationDetailsResult"]["AuthorizationDetails"];
        $this->helper->log(__CLASS__, __METHOD__, 'refresh authorization details', [$details]);

        // call might take a while - therefore get current transaction row //
        $transactions = $this->amzTransactionRepository->getTransactions([['id', '=', $transaction->id]]);
        $transaction = $transactions[0];
        $transaction->status = (string)$details["AuthorizationStatus"]["State"];
        $transaction->lastChange = (string)$details["AuthorizationStatus"]["LastUpdateTimestamp"];
        $this->amzTransactionRepository->updateTransaction($transaction);
        if ($transaction->status == 'Open') {
            if ($this->helper->getFromConfig('captureMode') == 'after_auth') {
                $this->capture($transaction->amzId, $transaction->amount);
            }
            /*
            $q = "SELECT amz_tx_admin_informed FROM amz_transactions WHERE amz_tx_amz_id = '".xtc_db_input($authId)."'";
            $rs = xtc_db_query($q);
            $r = xtc_db_fetch_array($rs);
            if ($r["amz_tx_admin_informed"]==0) {
                AlkimAmazonHandler::handleOpenAuth($authId);
            }


            $q = "UPDATE amz_transactions SET amz_tx_admin_informed = 1 WHERE amz_tx_amz_id = '".xtc_db_input($authId)."'";
            xtc_db_query($q);*/
        }

        if ($transaction->status == 'Declined') {
            $reason = (string)$details["AuthorizationStatus"]["ReasonCode"];
            if ($reason == 'AmazonRejected') {
                $this->cancelOrder($transaction->orderReference);
            }
            $this->helper->updatePlentyPayment($transaction->paymentId, 'refused', 'comment - test', 0);
            //AlkimAmazonHandler::authorizationDeclinedAction($authId, $reason);
        }
    }

    public function capture($authId, $amount)
    {
        if ($authId) {
            $orderRef = $this->getOrderRefFromAmzId($authId);

            $requestParameters = [];
            $requestParameters['merchant_id'] = $this->helper->getFromConfig('merchantId');
            $requestParameters['amazon_order_reference_id'] = $orderRef;
            $requestParameters['amazon_authorization_id'] = $authId;
            $requestParameters['capture_amount'] = $amount;
            $requestParameters['currency_code'] = 'EUR';
            $requestParameters['capture_reference_id'] = 'plenty_cpt_r_' . time() . '_' . rand(10000, 99999);
            /*
                        if (MODULE_PAYMENT_AM_APA_PROVOCATION == 'capture_decline' && MODULE_PAYMENT_AM_APA_MODE == 'sandbox') {
                            $requestParameters['seller_capture_note'] = '{"SandboxSimulation":{"State":"Declined", "ReasonCode":"AmazonRejected"}}';
                        }
            */
            $response = $this->call('capture', $requestParameters);

            $details = $response["CaptureResult"]["CaptureDetails"];
            $captureId = $details["AmazonCaptureId"];
            $data = [
                'orderReference' => $orderRef,
                'type' => 'capture',
                'status' => $details["CaptureStatus"]["State"],
                'reference' => $details["CaptureReferenceId"],
                'expiration' => '',
                'time' => date('Y-m-d H:i:s'),
                'amzId' => $captureId,
                'lastChange' => date('Y-m-d H:i:s'),
                'lastUpdate' => date('Y-m-d H:i:s'),
                'customerInformed' => false,
                'adminInformed' => false,
                'merchantId' => $this->helper->getFromConfig('merchantId'),
                'mode' => ($this->helper->getFromConfig('sandbox') == 'true' ? 'Sandbox' : 'Live'),
                'amount' => $amount,
                'amountRefunded' => 0
            ];

            $orderId = $this->getOrderIdFromOrderRef($orderRef);

            try {
                $plentyPayment = $this->helper->createPlentyPayment($amount, 'captured', date('Y-m-d H-i-s'), 'Zahlungseinzug: ' . $data["amzId"] . "\n" . 'Betrag: ' . $amount . "\n" . 'Status: ' . $data["status"], $data["amzId"]);
            } catch (\Exception $e) {
                $this->helper->log(__CLASS__, __METHOD__, 'plenty payment creation failed', [$e, $e->getMessage()], true);
            }
            $this->helper->log(__CLASS__, __METHOD__, 'payment created', ['payment' => $plentyPayment]);

            if ($plentyPayment instanceof Payment && !empty($orderId)) {
                $this->helper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
            }
            $data["paymentId"] = $plentyPayment->id;
            $data["order"] = $orderId;
            $this->helper->log(__CLASS__, __METHOD__, 'before create capture transaction', []);
            $this->amzTransactionRepository->createTransaction($data);
            $this->helper->log(__CLASS__, __METHOD__, 'after create capture transaction', []);
            /*
            $authTransaction = $this->getTransactionFromAmzId($authId);
            $this->helper->log(__CLASS__, __METHOD__, 'transaction for payment status update', [$authTransaction]);
            if ($authTransaction) {
                $paymentId = $authTransaction->paymentId;
                $this->helper->log(__CLASS__, __METHOD__, 'try to update payment', [$paymentId]);
                $this->helper->updatePlentyPayment($paymentId, 'captured', null, $amount, $orderId);
            }
            */
            $this->helper->log(__CLASS__, __METHOD__, 'before doCloseOrderService', $orderRef);
            $this->doCloseOrderService($orderRef);

            return $response;
        } else {
            return false;
        }

    }

    public function doCloseOrderService($orderRef)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'closeOrderServiceConfig', $this->helper->getFromConfig('closeOroOnCompleteCapture'));
        if ($this->helper->getFromConfig('closeOroOnCompleteCapture') == 'true') {
            $captures = $this->amzTransactionRepository->getTransactions([
                ['status', '=', 'Completed'],
                ['orderReference', '=', $orderRef],
                ['type', '=', 'capture']
            ]);
            $capturedSum = 0;
            foreach ($captures as $capture) {
                $capturedSum += $capture->amount;
            }
            $this->helper->log(__CLASS__, __METHOD__, 'closeOrderServiceCaptures', ['captures' => $captures, 'sum' => $capturedSum]);
            $orderRefTransaction = $this->getTransactionFromAmzId($orderRef);
            $this->helper->log(__CLASS__, __METHOD__, 'closeOrderServiceCaptures', ['captures' => $captures, 'sum' => $capturedSum, 'orderSum' => $orderRefTransaction->amount]);
            if ($capturedSum >= $orderRefTransaction->amount) {
                $this->closeOrder($orderRef);
            }
        }
    }

    public function getTransactionFromAmzId($amzId)
    {
        $transactions = $this->amzTransactionRepository->getTransactions([['amzId', '=', $amzId]]);
        if (!empty($transactions) && is_array($transactions)) {
            return $transactions[0];
        } else {
            return null;
        }
    }

    public function closeOrder($orderRef)
    {
        $requestParameters = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $response = $this->call('closeOrderReference', $requestParameters);
        return $response;
    }

    public function cancelOrder($orderRef)
    {
        $requestParameters = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $response = $this->call('cancelOrderReference', $requestParameters);
        return $response;
    }

    public function refreshOrderReference(AmzTransaction $transaction)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'refresh oro', [$transaction]);
        $details = $this->getOrderReferenceDetails($transaction->orderReference);
        $details = $details["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"];
        $this->helper->log(__CLASS__, __METHOD__, 'refresh oro details', [$details]);
        $transaction->status = (string)$details["OrderReferenceStatus"]["State"];
        $transaction->lastChange = (string)$details["OrderReferenceStatus"]["LastUpdateTimestamp"];
        $this->amzTransactionRepository->updateTransaction($transaction);
        if ($transaction->status == 'Open') {
            //TODO: AlkimAmazonTransactions::doAuthorizationAfterDecline($orderRef);
        }
    }

    public function getAmountFromOrderRef($orderReferenceId)
    {
        $oroArr = $this->amzTransactionRepository->getTransactions([
            ['orderReference', '=', $orderReferenceId],
            ['type', '=', 'order_ref']
        ]);
        $oro = $oroArr[0];
        return $oro->amount;
    }

    public function getCaptureTransactionsFromOrderRef($orderReferenceId)
    {
        $transactions = $this->amzTransactionRepository->getTransactions([
            ['orderReference', '=', $orderReferenceId],
            ['type', '=', 'capture']
        ]);
        return $transactions;
    }
}