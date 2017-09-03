<?php

namespace AmazonLoginAndPay\Providers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzCheckoutHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use AmazonLoginAndPay\Procedures\AmzCaptureProcedure;
use AmazonLoginAndPay\Repositories\AmzTransactionRepository;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\ServiceProvider;

class AmzServiceProvider extends ServiceProvider
{
    public $transactionHelper;

    public function boot(AlkimAmazonLoginAndPayHelper $helper,
                         AmzCheckoutHelper $checkoutHelper,
                         Dispatcher $eventDispatcher,
                         AmzTransactionHelper $transactionHelper,
                         EventProceduresService $eventProceduresService,
                         PaymentRepositoryContract $paymentRepository)
    {

        $this->transactionHelper = $transactionHelper;
        // Create the ID of the payment method if it doesn't exist yet
        $helper->createMopIfNotExistsAndReturnId();

        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function (ExecutePayment $event) use ($helper, $transactionHelper, $paymentRepository, $checkoutHelper) {
                $helper->log(__CLASS__, __METHOD__, 'execute payment event', $event);
                if ($event->getMop() == $helper->createMopIfNotExistsAndReturnId()) {
                    $orderId = $event->getOrderId();
                    $helper->log(__CLASS__, __METHOD__, 'execute payment - submit order id config value', $helper->getFromConfig('submitOrderIds'));
                    if ($helper->getFromConfig('submitOrderIds') == 'true') {
                        $helper->log(__CLASS__, __METHOD__, 'execute payment - with order id pre', '');
                        $amount = $helper->getOrderTotal($orderId);
                        $return = $checkoutHelper->doCheckoutActions($amount, $orderId);
                        $helper->log(__CLASS__, __METHOD__, 'execute payment - with order id', ['order' => $orderId, 'return' => $return]);
                        if (!empty($return["redirect"]) && $return["redirect"] != 'place-order') {
                            $event->setType('redirectUrl');
                            $event->setValue($return["redirect"]);
                        }
                    } else {
                        $helper->log(__CLASS__, __METHOD__, 'execute payment - auth id', $helper->getFromSession('amazonAuthId'));
                        if ($amazonAuthId = $helper->getFromSession('amazonAuthId')) {
                            if ($transaction = $transactionHelper->getTransactionFromAmzId($amazonAuthId)) {
                                if ($transaction->paymentId) {
                                    if ($payment = $paymentRepository->getPaymentById($transaction->paymentId)) {
                                        $helper->assignPlentyPaymentToPlentyOrder($payment, $orderId);
                                        $helper->setOrderIdToAmazonTransactions($transactionHelper->getOrderRefFromAmzId($amazonAuthId), $orderId);
                                        $helper->log(__CLASS__, __METHOD__, 'assign payment to order', [$payment, $orderId]);
                                        $helper->setToSession('amazonAuthId', '');
                                    }
                                }
                                $orderReference = $transactionHelper->getOrderRefFromAmzId($amazonAuthId);
                                if ($captures = $transactionHelper->getCaptureTransactionsFromOrderRef($orderReference)) {
                                    if (is_array($captures)) {
                                        foreach ($captures as $capture) {
                                            if ($payment = $paymentRepository->getPaymentById($capture->paymentId)) {
                                                $helper->assignPlentyPaymentToPlentyOrder($payment, $orderId);
                                                $helper->log(__CLASS__, __METHOD__, 'assign capture to order', [$payment, $orderId]);
                                            }
                                        }
                                    }
                                }
                            }
                        } elseif ($orderReference = $helper->getFromSession('amzCheckoutOrderReference')) {
                            $helper->setOrderIdToAmazonTransactions($orderReference, $orderId);
                        }
                    }
                }
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