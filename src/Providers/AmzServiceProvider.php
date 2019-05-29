<?php

namespace AmazonLoginAndPay\Providers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzCheckoutHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use AmazonLoginAndPay\Methods\AmzPaymentMethod;
use AmazonLoginAndPay\Procedures\AmzCaptureProcedure;
use AmazonLoginAndPay\Repositories\AmzTransactionRepository;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\ServiceProvider;

class AmzServiceProvider extends ServiceProvider
{
    public $transactionHelper;

    public function boot(
        AlkimAmazonLoginAndPayHelper $helper,
        AmzCheckoutHelper $checkoutHelper,
        Dispatcher $eventDispatcher,
        AmzTransactionHelper $transactionHelper,
        EventProceduresService $eventProceduresService,
        PaymentRepositoryContract $paymentRepository,
        PaymentMethodContainer $payContainer,
        Request $request
    ) {
        //$helper->log(__CLASS__, __METHOD__, 'request uri', $request->getUri());
        if (strpos($request->getUri(), '/logout') !== false) {
            $helper->setToSession('amazonLogout', 1);
        }

        $helper->createMopIfNotExistsAndReturnId();
        $payContainer->register('alkim_amazonpay::AMAZONPAY', AmzPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);

        $this->transactionHelper = $transactionHelper;
        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function (ExecutePayment $event) use ($helper, $transactionHelper, $paymentRepository, $checkoutHelper) {
                $helper->log(__CLASS__, __METHOD__, 'execute payment event', $event);
                if ($event->getMop() == $helper->createMopIfNotExistsAndReturnId()) {
                    $orderId = $event->getOrderId();
                    $helper->log(__CLASS__, __METHOD__, 'execute payment - auth id', $helper->getFromSession('amazonAuthId'));
                    if ($amazonAuthId = $helper->getFromSession('amazonAuthId')) {
                        if ($transaction = $transactionHelper->getTransactionFromAmzId($amazonAuthId)) {
                            if ($transaction->paymentId) {
                                if ($payment = $paymentRepository->getPaymentById($transaction->paymentId)) {
                                    $helper->assignPlentyPaymentToPlentyOrder($payment, $orderId);
                                    $helper->setOrderIdToAmazonTransactions($transactionHelper->getOrderRefFromAmzId($amazonAuthId), $orderId);
                                    $helper->log(__CLASS__, __METHOD__, 'assign payment to order', [$payment, $orderId]);
                                    $helper->setToSession('amazonAuthId', '');
                                    if ($transaction->status === 'Open' && $helper->getFromConfig('authorizedStatus')) {
                                        $helper->setOrderStatus($orderId, $helper->getFromConfig('authorizedStatus'));
                                    }
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
                    $helper->log(__CLASS__, __METHOD__, 'set order id', $orderReference);
                    if(!empty($orderReference)) {
                        $transactionHelper->setOrderId($orderReference, $orderId);
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

        $eventProceduresService->registerProcedure(
            'alkim_amazonpay',
            ProcedureEntry::PROCEDURE_GROUP_ORDER,
            [
                'de' => 'Amazon Pay Vorgang abbrechen',
                'en' => 'Cancel order with Amazon Pay'
            ],
            '\AmazonLoginAndPay\Procedures\AmzCancelOrderProcedure@run'
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