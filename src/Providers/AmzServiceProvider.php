<?php

namespace AmazonLoginAndPay\Providers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzCheckoutHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use AmazonLoginAndPay\Procedures\AmzCaptureProcedure;
use AmazonLoginAndPay\Repositories\AmzTransactionRepository;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider;

class AmzServiceProvider extends ServiceProvider
{
    public $transactionHelper;

    public function boot(AlkimAmazonLoginAndPayHelper $paymentHelper,
                         AmzCheckoutHelper $checkoutHelper,
                         PaymentMethodContainer $payContainer,
                         Dispatcher $eventDispatcher,
                         AmzTransactionHelper $transactionHelper,
                         EventProceduresService $eventProceduresService,
                         PaymentRepositoryContract $paymentRepository)
    {

        $this->transactionHelper = $transactionHelper;
        // Create the ID of the payment method if it doesn't exist yet
        $paymentHelper->createMopIfNotExistsAndReturnId();

        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function (ExecutePayment $event) use ($paymentHelper, $transactionHelper, $paymentRepository, $checkoutHelper) {
                $orderId = $event->getOrderId();
                if ($paymentHelper->getFromConfig('submitOrderIds') == 'true') {
                    $amount = $paymentHelper->getOrderTotal($orderId);
                    $return = $checkoutHelper->doCheckoutActions($amount, $orderId);
                    if (!empty($return["redirect"]) && $return["redirect"] != 'place-order') {
                        $event->setType('redirectUrl');
                        $event->setValue($return["redirect"]);
                    }
                } else {
                    if ($amazonAuthId = $paymentHelper->getFromSession('amazonAuthId')) {
                        if ($transaction = $transactionHelper->getTransactionFromAmzId($amazonAuthId)) {
                            if ($transaction->paymentId) {
                                if ($payment = $paymentRepository->getPaymentById($transaction->paymentId)) {
                                    $paymentHelper->assignPlentyPaymentToPlentyOrder($payment, $orderId);
                                    $paymentHelper->setOrderIdToAmazonTransactions($transactionHelper->getOrderRefFromAmzId($amazonAuthId), $orderId);
                                    $paymentHelper->log(__CLASS__, __METHOD__, 'assign payment to order', [$payment, $orderId]);
                                    $paymentHelper->setToSession('amazonAuthId', '');
                                }
                            }
                        }
                    } elseif ($orderReference = $paymentHelper->getFromSession('amzCheckoutOrderReference')) {
                        $paymentHelper->setOrderIdToAmazonTransactions($orderReference, $orderId);
                    }
                    $paymentHelper->log(__CLASS__, __METHOD__, 'execute payment', ['auth' => $amazonAuthId, 'transaction' => $transaction, 'orderId' => $orderId, 'payment' => $payment]);
                }


                /*$transactionHelper->setOrderReferenceDetails($paymentHelper->getFromSession('amzOrderReference'), $amount, $event->getOrderId());
                $transactionHelper->confirmOrderReference($paymentHelper->getFromSession('amzOrderReference'), true, $event->getOrderId());
                $paymentHelper->log(__CLASS__, __METHOD__, 'checkout auth mode', $paymentHelper->getFromConfig('authorization_mode'));
                if ($paymentHelper->getFromConfig('authorization_mode') != 'manually') {

                    $paymentHelper->log(__CLASS__, __METHOD__, 'try to authorize', $paymentHelper->getFromSession('amzOrderReference'));
                    $response = $transactionHelper->authorize($paymentHelper->getFromSession('amzOrderReference'), $amount, 0);
                    $paymentHelper->log(__CLASS__, __METHOD__, 'amazonCheckoutAuthorizeResult', $response);
                    if (is_array($response)) {
                        $details = $response["AuthorizeResult"]["AuthorizationDetails"];
                        $status = $details["AuthorizationStatus"]["State"];
                        $paymentHelper->log(__CLASS__, __METHOD__, 'status', ['status' => $status]);
                        if ($status == "Declined") {
                            $reason = $details["AuthorizationStatus"]["ReasonCode"];
                            if ($reason == 'TransactionTimedOut') {

                                $transactionHelper->authorize($paymentHelper->getFromSession('amzOrderReference'), $amount);
                            } elseif ($reason == 'InvalidPaymentMethod') {

                            } else {
                                $transactionHelper->cancelOrder($paymentHelper->getFromSession('amzOrderReference'));
                                $event->setType('redirectUrl');
                                $event->setValue('/basket/');
                                //$this->response->redirectTo('basket');
                            }

                        } else {
                            $event->setType('success');
                            $event->setValue('The payment has been executed successfully!');
                            if ($paymentHelper->getFromConfig('captureMode') == 'after_auth' && $status == 'Open') {
                                $transactionHelper->capture($details["AmazonAuthorizationId"], $amount);
                            }
                        }

                    } else {

                    }


                }*/

            }
        );

        $eventProceduresService->registerProcedure(
            'alkim_amazonpay',
            ProcedureEntry::PROCEDURE_GROUP_ORDER,
            [
                'de' => 'Vollständiger Einzug der Amazon Pay Zahlung',
                'en' => 'Complete capture with Amazon Pay'
            ],
            '\AmazonLoginAndPay\Procedures\AmzCaptureProcedure@run'
        );

        $eventProceduresService->registerProcedure(
            'alkim_amazonpay',
            ProcedureEntry::PROCEDURE_GROUP_ORDER,
            [
                'de' => 'Erstattung der Amazon Pay Zahlung',
                'en' => 'Refund with Amazon Pay'
            ],
            '\AmazonLoginAndPay\Procedures\AmzRefundProcedure@run'
        );

        $eventProceduresService->registerProcedure(
            'alkim_amazonpay',
            ProcedureEntry::PROCEDURE_GROUP_ORDER,
            [
                'de' => 'Amazon Pay Zahlung autorisieren',
                'en' => 'Auithorize with Amazon Pay'
            ],
            '\AmazonLoginAndPay\Procedures\AmzAuthorizeProcedure@run'
        );

        $eventProceduresService->registerProcedure(
            'alkim_amazonpay',
            ProcedureEntry::PROCEDURE_GROUP_ORDER,
            [
                'de' => 'Amazon Pay Vorgang schließen',
                'en' => 'Close order with Amazon Pay'
            ],
            '\AmazonLoginAndPay\Procedures\AmzCloseOrderProcedure@run'
        );
    }

    /**
     * Register the service provider.
     */

    public function register()
    {
        $this->getApplication()->register(AmzRouteServiceProvider::class);
        $this->getApplication()->bind(AmzTransactionRepositoryContract::class, AmzTransactionRepository::class);
        $this->getApplication()->bind(AmzCaptureProcedure::class);
    }
}

