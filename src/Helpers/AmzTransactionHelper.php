<?php

namespace AmazonLoginAndPay\Helpers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Models\AmzTransaction;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;

class AmzTransactionHelper
{
    public $paymentMethodRepository;
    public $callLib;
    public $helper;
    public $amzTransactionRepository;
    public $orderReferenceDetailsIsSet = false;

    public function __construct(AmzTransactionRepositoryContract $amzTransactionRepository, PaymentMethodRepositoryContract $paymentMethodRepository, LibraryCallContract $libCall, AlkimAmazonLoginAndPayHelper $helper)
    {
        $this->paymentMethodRepository  = $paymentMethodRepository;
        $this->callLib                  = $libCall;
        $this->helper                   = $helper;
        $this->amzTransactionRepository = $amzTransactionRepository;
    }

    public function setOrderReferenceDetailsAuto()
    {
        if (!$this->orderReferenceDetailsIsSet) {
            /** @var AmzCheckoutHelper $checkoutHelper */
            $checkoutHelper   = pluginApp(AmzCheckoutHelper::class);
            $basket           = $checkoutHelper->getBasketData();
            $amount           = $basket["basketAmount"];
            $currency         = $basket["currency"];
            $orderReferenceId = $this->helper->getFromSession('amzOrderReference');
            $this->setOrderReferenceDetails($orderReferenceId, $amount, null, $currency);
            $this->orderReferenceDetailsIsSet = true;
        }
    }

    public function setOrderReferenceDetails($orderRef, $amount, $orderId, $currency = 'EUR')
    {
        $requestParameters                              = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $requestParameters['amount']                    = $amount;
        $requestParameters['currency_code']             = $currency;
        $requestParameters['platform_id']               = 'A1SGXK19QKIYNB';
        $requestParameters['merchant_id']               = $this->helper->getFromConfig('merchantId');
        $requestParameters['store_name']                = $this->helper->getWebstoreName();
        $requestParameters['custom_information']        = 'created by Alkim Media, Plentymarkets, V' . $this->helper->getCallConfig()['application_version'];
        if (!empty($orderId)) {
            $requestParameters['seller_order_id'] = $orderId;
        }
        $requestParameters['seller_note'] = '';
        $response                         = $this->call('SetOrderReferenceDetails', $requestParameters);

        return $response;
    }

    public function call($action, $parameters)
    {
        $startTime = microtime(true);
        $result    = $this->callLib->call(
            'AmazonLoginAndPay::amz_client_call',
            [
                'config'     => $this->helper->getCallConfig(),
                'action'     => $action,
                'parameters' => $parameters
            ]
        );
        $endTime   = microtime(true);
        $duration  = $endTime - $startTime;
        $this->helper->log(__CLASS__, __METHOD__, 'call result ' . $action, ['startTime' => $startTime, 'endTime' => $endTime, 'duration' => $duration, 'config' => $this->helper->getCallConfig(), 'action' => $action, 'parameters' => $parameters, 'result' => $result]);

        return $result;
    }

    public function setOrderId($orderRef, $orderId)
    {
        $requestParameters                              = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $requestParameters['seller_order_id']           = $orderId;
        $response                                       = $this->call('SetOrderAttributes', $requestParameters);
        $this->helper->log(__CLASS__, __METHOD__, 'set order id result', [$requestParameters, $response]);
        $this->helper->setOrderExternalId($orderId, $orderRef);

        return $response;
    }

    public function confirmOrderReference($orderRef, $saveTransaction = true, $orderId = '')
    {
        $requestParameters                              = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $requestParameters['success_url']               = $this->helper->getUrl('amazon-checkout-proceed');
        $requestParameters['failure_url']               = $this->helper->getUrl('amazon-checkout');
        $response                                       = $this->call('confirmOrderReference', $requestParameters);
        if ($saveTransaction) {
            $details                     = $this->getOrderReferenceDetails($orderRef);
            $details                     = $details["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"];
            $transaction                 = pluginApp(AmzTransaction::class);
            $transaction->order          = $orderId;
            $transaction->orderReference = $orderRef;
            $transaction->type           = 'order_ref';
            $this->assignOrderReferenceDetails($transaction, $details);
            $this->amzTransactionRepository->saveTransaction($transaction);
        }

        return $response;
    }

    public function getOrderReferenceDetails($orderRef, $token = '')
    {
        $requestParameters                              = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $requestParameters['address_consent_token']     = $token;
        $response                                       = $this->call('GetOrderReferenceDetails', $requestParameters);

        return $response;
    }

    private function assignOrderReferenceDetails(AmzTransaction $transaction, array $orderReferenceDetails)
    {
        $time                    = date('Y-m-d H:i:s');
        $transaction->reference  = $transaction->orderReference;
        $transaction->amzId      = $transaction->orderReference;
        $transaction->expiration = $orderReferenceDetails["ExpirationTimestamp"];
        $transaction->status     = (string)$orderReferenceDetails["OrderReferenceStatus"]["State"];
        $transaction->time       = $time;
        $transaction->lastChange = $time;
        $transaction->lastUpdate = $time;
        $transaction->merchantId = $this->helper->getFromConfig('merchantId');
        $transaction->mode       = $this->helper->getTransactionMode();
        $transaction->amount     = $orderReferenceDetails["OrderTotal"]["Amount"];
        $transaction->currency   = $orderReferenceDetails["OrderTotal"]["CurrencyCode"];
    }

    public function authorize($orderRef, $amount, $timeout = 1440, $comment = '')
    {

        $requestParameters                               = [];
        $requestParameters['authorization_amount']       = $amount;
        $requestParameters['amazon_order_reference_id']  = $orderRef;
        $requestParameters['transaction_timeout']        = $timeout;
        $requestParameters['currency_code']              = $this->getCurrencyFromOrderRef($orderRef);
        $requestParameters['merchant_id']                = $this->helper->getFromConfig('merchantId');
        $requestParameters['soft_descriptor']            = $comment;
        $requestParameters['authorization_reference_id'] = 'pm_auth_' . time() . '_' . rand(10000, 99999) . '_' . ($timeout == 0 ? 'sync' : 'async');
        $response                                        = $this->call('authorize', $requestParameters);

        $details = $response["AuthorizeResult"]["AuthorizationDetails"];
        $authId  = $details["AmazonAuthorizationId"];
        if ($authId != '') {
            /** @var AmzTransaction $transaction */
            $transaction        = pluginApp(AmzTransaction::class);
            $transaction->amzId = $authId;
            $transaction->type  = 'auth';
            $this->assignAuthorizationDetails($transaction, $details);
            $transaction = $this->amzTransactionRepository->saveTransaction($transaction);
            $this->doAuthorizationPaymentAction($transaction);
            $this->amzTransactionRepository->saveTransaction($transaction);
        }

        return $response;
    }

    public function getCurrencyFromOrderRef($orderReferenceId)
    {
        $orderRefrenceObject = $this->amzTransactionRepository->getTransactions([
            ['orderReference', '=', $orderReferenceId],
            ['type', '=', 'order_ref']
        ])[0];

        return ($orderRefrenceObject->currency ? $orderRefrenceObject->currency : 'EUR');
    }

    private function assignAuthorizationDetails(AmzTransaction $transaction, array $authorizationDetails)
    {
        $orderReferenceId            = $this->getOrderRefFromAmzId($transaction->amzId);
        $time                        = date('Y-m-d H:i:s');
        $transaction->orderReference = $orderReferenceId;
        $transaction->status         = $authorizationDetails["AuthorizationStatus"]["State"];
        $transaction->reference      = $authorizationDetails["AuthorizationReferenceId"];
        $transaction->expiration     = $authorizationDetails["ExpirationTimestamp"];
        $transaction->time           = $time;
        $transaction->lastChange     = $time;
        $transaction->lastUpdate     = $time;
        $transaction->merchantId     = $this->helper->getFromConfig('merchantId');
        $transaction->mode           = $this->helper->getTransactionMode();
        $transaction->amount         = $authorizationDetails["AuthorizationAmount"]["Amount"];
        $transaction->currency       = $authorizationDetails["AuthorizationAmount"]["CurrencyCode"];
        if ($orderId = $this->getOrderIdFromOrderRef($transaction->orderReference)) {
            $transaction->order = $orderId;
        }
    }

    public function getOrderRefFromAmzId($amzId)
    {
        if (preg_match('/([0-9A-Z]+-[0-9]+-[0-9]+)-[0-9A-Z]+/', $amzId, $matches)) {
            $this->helper->log(__CLASS__, __METHOD__, 'or matches', [$matches]);

            return $matches[1];
        } else {
            return '';
        }
    }

    public function getOrderIdFromOrderRef($orderRef)
    {
        $transactions = $this->amzTransactionRepository->getTransactions([['orderReference', '=', $orderRef], ['type', '=', 'order_ref']]);

        return $transactions[0]->order;
    }

    public function doAuthorizationPaymentAction(AmzTransaction $transaction, $setStatus = true)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'try to create payment', ['payment' => $transaction]);
        $plentyPayment = null;
        try {
            $plentyPayment = $this->helper->createPlentyPayment(($transaction->status == 'Declined' ? 0 : $transaction->amount), ($transaction->status == 'Open' || $transaction->status == 'Closed' ? 'approved' : ($transaction->status == 'Pending' ? 'awaiting_approval' : 'refused')), date('Y-m-d H-i-s'),
                'Autorisierung: ' . $transaction->amzId . "\n" . 'Betrag: ' . $transaction->amount . "\n" . 'Status: ' . $transaction->status,
                $transaction->amzId, 'credit', 2, $transaction->currency);
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'plenty payment creation failed', [$e, $e->getMessage()], true);
        }
        $this->helper->log(__CLASS__, __METHOD__, 'payment created', ['payment' => $plentyPayment]);
        $orderId = $transaction->order;
        $this->helper->log(__CLASS__, __METHOD__, 'orderid', [$orderId]);
        if ($plentyPayment instanceof Payment && !empty($orderId)) {
            $this->helper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
            $this->helper->log(__CLASS__, __METHOD__, 'assign payment to order', [$plentyPayment, $orderId]);
            if ($setStatus && $transaction->status === 'Open') {
                $this->helper->setOrderStatusAuthorized($orderId);
            }
        }
        $transaction->paymentId = $plentyPayment->id;
    }

    public function refund($captureId, $amount, $creditNoteId = null)
    {
        if ($captureId) {
            $orderRef                                 = $this->getOrderRefFromAmzId($captureId);
            $requestParameters                        = [];
            $requestParameters['amazon_capture_id']   = $captureId;
            $requestParameters['refund_amount']       = $amount;
            $requestParameters['currency_code']       = $this->getCurrencyFromOrderRef($orderRef);
            $requestParameters['refund_reference_id'] = 'plenty_ref_r_' . time() . '_' . rand(10000, 99999);

            $response = $this->call('refund', $requestParameters);

            $details      = $response["RefundResult"]["RefundDetails"];
            $refundId     = $details["AmazonRefundId"];
            $data         = [
                'orderReference'   => $orderRef,
                'type'             => 'refund',
                'status'           => $details["RefundStatus"]["State"],
                'reference'        => $details["RefundReferenceId"],
                'expiration'       => '',
                'time'             => date('Y-m-d H:i:s'),
                'amzId'            => $refundId,
                'lastChange'       => date('Y-m-d H:i:s'),
                'lastUpdate'       => date('Y-m-d H:i:s'),
                'customerInformed' => false,
                'adminInformed'    => false,
                'merchantId'       => $this->helper->getFromConfig('merchantId'),
                'mode'             => ($this->helper->getFromConfig('sandbox') == 'true' ? 'Sandbox' : 'Live'),
                'amount'           => $amount,
                'amountRefunded'   => 0,
                'currency'         => $requestParameters['currency_code']
            ];
            $plentyRefund = null;
            try {
                $plentyRefund = $this->helper->createPlentyPayment($amount, 'refunded', date('Y-m-d H-i-s'), 'Rueckzahlung: ' . $data["amzId"] . "\n" . 'Betrag: ' . $amount . "\n" . 'Status: ' . $data["status"], $data["amzId"], 'debit', 2, $requestParameters['currency_code']);
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
            $data["order"]     = $orderId;
            $this->amzTransactionRepository->createTransaction($data);

            return $response;
        } else {
            return false;
        }

    }

    public function intelligentRefresh(AmzTransaction $transaction)
    {
        switch ($transaction->type) {
            case 'refund':
                //$this->refreshRefund($transaction);
                break;
            case 'capture':
                $this->refreshCapture($transaction);
                break;
            case 'auth':
                $this->refreshAuthorization($transaction);
                break;
            case 'order_ref':
                $this->refreshOrderReference($transaction);
                break;

        }
    }

    public function refreshCapture(AmzTransaction $transaction, $isNew = null)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'refresh capture', ['transaction' => $transaction, 'isNew' => $isNew]);
        $details = $this->call('getCaptureDetails', ['amazon_capture_id' => $transaction->amzId]);
        $details = $details["GetCaptureDetailsResult"]["CaptureDetails"];
        $this->helper->log(__CLASS__, __METHOD__, 'refresh capture details', [$details]);
        $transactions = $this->amzTransactionRepository->getTransactions([['id', '=', $transaction->id]]);
        $transaction  = $transactions[0];
        $this->assignCaptureDetails($transaction, $details);
        $this->amzTransactionRepository->updateTransaction($transaction);
        if ($isNew) {
            $this->doCapturePaymentAction($transaction);
            $this->amzTransactionRepository->updateTransaction($transaction);
        }
    }

    private function assignCaptureDetails(AmzTransaction $transaction, array $captureDetails)
    {
        //TODO apply to ::capture()
        $orderReferenceId            = $this->getOrderRefFromAmzId($transaction->amzId);
        $time                        = date('Y-m-d H:i:s');
        $transaction->orderReference = $orderReferenceId;
        $transaction->status         = $captureDetails["CaptureStatus"]["State"];
        $transaction->reference      = $captureDetails["CaptureReferenceId"];
        $transaction->time           = $time;
        $transaction->lastChange     = $time;
        $transaction->lastUpdate     = $time;
        $transaction->merchantId     = $this->helper->getFromConfig('merchantId');
        $transaction->mode           = $this->helper->getTransactionMode();
        $transaction->amount         = $captureDetails["CaptureAmount"]["Amount"];
        $transaction->currency       = $captureDetails["CaptureAmount"]["CurrencyCode"];
        if ($orderId = $this->getOrderIdFromOrderRef($transaction->orderReference)) {
            $transaction->order = $orderId;
        }
    }

    public function doCapturePaymentAction(AmzTransaction $transaction)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'try to create capture payment', ['payment' => $transaction]);
        $plentyPayment = null;
        try {
            $plentyPayment = $this->helper->createPlentyPayment($transaction->amount, 'captured', date('Y-m-d H-i-s'), 'Zahlungseinzug: ' . $transaction->amzId . "\n" . 'Betrag: ' . $transaction->amount . "\n" . 'Status: ' . $transaction->status, $transaction->amzId, 'credit', 2, $transaction->currency);
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'plenty capture payment creation failed', [$e, $e->getMessage()], true);
        }
        $this->helper->log(__CLASS__, __METHOD__, 'capture payment created', ['payment' => $plentyPayment]);
        $orderId = $transaction->order;
        if ($plentyPayment instanceof Payment && !empty($orderId)) {
            $this->helper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
        }
        $transaction->paymentId = $plentyPayment->id;
    }

    public function refreshAuthorization(AmzTransaction $transaction, $complete = false)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'refresh authorization', [$transaction]);
        $details = $this->call('getAuthorizationDetails', ['amazon_authorization_id' => $transaction->amzId]);
        $details = $details["GetAuthorizationDetailsResult"]["AuthorizationDetails"];
        $this->helper->log(__CLASS__, __METHOD__, 'refresh authorization details', [$details]);
        $transactionBeforeRefresh = $transaction;
        // call might take a while - therefore get current transaction row //
        $transactions            = $this->amzTransactionRepository->getTransactions([['id', '=', $transaction->id]]);
        $transaction             = $transactions[0];
        $transaction->status     = (string)$details["AuthorizationStatus"]["State"];
        $transaction->lastChange = (string)$details["AuthorizationStatus"]["LastUpdateTimestamp"];

        if ($complete) {
            $this->assignAuthorizationDetails($transaction, $details);
            $transaction = $this->amzTransactionRepository->updateTransaction($transaction);
            if (empty($transaction->paymentId)) {
                $this->doAuthorizationPaymentAction($transaction, false);
            }
        }
        $this->amzTransactionRepository->updateTransaction($transaction);
        if ($transaction->status === 'Open') {
            if ($this->helper->getFromConfig('captureMode') == 'after_auth' && $transaction->order) {
                $this->capture($transaction->amzId, $transaction->amount);
            }
            $orderId = $this->getOrderIdFromOrderRef($transaction->orderReference);
            if ($orderId && $transactionBeforeRefresh->status !== 'Open') {
                $this->helper->setOrderStatusAuthorized($orderId);
            }
        }

        if ($transaction->status == 'Declined') {
            $reason = (string)$details["AuthorizationStatus"]["ReasonCode"];
            if (strpos($transaction->reference, '_async') !== false && $reason == 'TransactionTimedOut') {
                $reason = 'AmazonRejected';
            }
            if ($reason == 'AmazonRejected') {
                $this->cancelOrder($transaction->orderReference);
            }
            $this->helper->updatePlentyPayment($transaction->paymentId, 'refused', 'comment - test', 0);
            $this->authorizationDeclinedAction($transaction, $reason);
        }
    }

    public function capture($authId, $amount)
    {
        if ($authId) {
            $orderRef = $this->getOrderRefFromAmzId($authId);

            $requestParameters                              = [];
            $requestParameters['merchant_id']               = $this->helper->getFromConfig('merchantId');
            $requestParameters['amazon_order_reference_id'] = $orderRef;
            $requestParameters['amazon_authorization_id']   = $authId;
            $requestParameters['capture_amount']            = $amount;
            $requestParameters['currency_code']             = $this->getCurrencyFromOrderRef($orderRef);
            $requestParameters['capture_reference_id']      = 'plenty_cpt_r_' . time() . '_' . rand(10000, 99999);
            $response                                       = $this->call('capture', $requestParameters);
            $orderId                                        = $this->getOrderIdFromOrderRef($orderRef);

            if (!empty($response["Error"])) {
                if ($response["Error"]["Code"] === 'TransactionCountExceeded') {
                    //sometimes double capture attempts occur because of misconfiguration or slow execution - get out silently
                    return $response;
                } else {
                    $plentyPayment = null;
                    try {
                        $plentyPayment = $this->helper->createPlentyPayment($amount, 'refused', date('Y-m-d H-i-s'), 'Zahlungseinzug fehlgeschlagen: ' . $response["Error"]["Message"], '', 'credit', 2, $requestParameters['currency_code']);
                    } catch (\Exception $e) {
                        $this->helper->log(__CLASS__, __METHOD__, 'capture error notice - creation failed', [$e, $e->getMessage()], true);
                    }
                    $this->helper->log(__CLASS__, __METHOD__, 'capture error notice created', ['payment' => $plentyPayment]);
                    if ($plentyPayment instanceof Payment && !empty($orderId)) {
                        $this->helper->assignPlentyPaymentToPlentyOrder($plentyPayment, $orderId);
                    }
                }
            } else {
                $details            = $response["CaptureResult"]["CaptureDetails"];
                $captureId          = $details["AmazonCaptureId"];
                $transaction        = pluginApp(AmzTransaction::class);
                $transaction->type  = 'capture';
                $transaction->amzId = $captureId;
                $this->assignCaptureDetails($transaction, $details);
                $this->helper->log(__CLASS__, __METHOD__, 'before create capture transaction', [$transaction]);
                $transaction = $this->amzTransactionRepository->saveTransaction($transaction);
                $this->helper->log(__CLASS__, __METHOD__, 'after create capture transaction', [$transaction]);
                $this->doCapturePaymentAction($transaction);
                $this->amzTransactionRepository->saveTransaction($transaction);
                $this->helper->log(__CLASS__, __METHOD__, 'before doCloseOrderService', $orderRef);
                $this->doCloseOrderService($orderRef);
            }

            return $response;
        } else {
            return false;
        }

    }

    public function doCloseOrderService($orderRef)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'closeOrderServiceConfig', $this->helper->getFromConfig('closeOroOnCompleteCapture'));
        if ($this->helper->getFromConfig('closeOroOnCompleteCapture') == 'true') {
            $captures    = $this->amzTransactionRepository->getTransactions([
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
        $requestParameters                              = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $response                                       = $this->call('closeOrderReference', $requestParameters);

        return $response;
    }

    public function cancelOrder($orderRef)
    {
        $requestParameters                              = [];
        $requestParameters['amazon_order_reference_id'] = $orderRef;
        $response                                       = $this->call('cancelOrderReference', $requestParameters);

        return $response;
    }

    public function authorizationDeclinedAction(AmzTransaction $transaction, $reason)
    {
        if ($transaction->status == 'Declined') {
            $informed = false;
            if ($reason == 'InvalidPaymentMethod') {
                if (!$transaction->adminInformed) {
                    $this->helper->setOrderStatus($transaction->order, $this->helper->getFromConfig('softDeclineStatus'));
                }
                $informed = true;
            } elseif ($reason == 'AmazonRejected') {
                if (!$transaction->adminInformed) {
                    $this->helper->setOrderStatus($transaction->order, $this->helper->getFromConfig('hardDeclineStatus'));
                }
                $informed = true;
            }

            if ($informed) {
                $transaction->adminInformed = true;
                $this->amzTransactionRepository->updateTransaction($transaction);
            }
        }
    }

    public function refreshOrderReference(AmzTransaction $transaction, $complete = false)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'refresh oro', [$transaction]);
        $details = $this->getOrderReferenceDetails($transaction->orderReference);
        $details = $details["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"];
        $this->helper->log(__CLASS__, __METHOD__, 'refresh oro details', [$details]);
        $transaction->status     = (string)$details["OrderReferenceStatus"]["State"];
        $transaction->lastChange = (string)$details["OrderReferenceStatus"]["LastUpdateTimestamp"];
        if ($complete) {
            $this->assignOrderReferenceDetails($transaction, $details);
        }
        $this->amzTransactionRepository->updateTransaction($transaction);
    }

    public function getAmountFromOrderRef($orderReferenceId)
    {
        $oroArr = $this->amzTransactionRepository->getTransactions([
            ['orderReference', '=', $orderReferenceId],
            ['type', '=', 'order_ref']
        ]);
        $oro    = $oroArr[0];

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