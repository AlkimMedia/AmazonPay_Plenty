<?php

namespace AmazonLoginAndPay\Providers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use AmazonLoginAndPay\Helpers\PaymentMethodHelper;
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
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Webshop\Consent\Contracts\ConsentRepositoryContract;

class AmzServiceProvider extends ServiceProvider
{
    public function boot(
        PaymentMethodHelper $paymentMethodHelper,
        Dispatcher $eventDispatcher,
        EventProceduresService $eventProceduresService,
        PaymentRepositoryContract $paymentRepository,
        PaymentMethodContainer $payContainer,
        Request $request
    ) {

        if (strpos($request->getUri(), '/logout') !== false) {
            pluginApp(AlkimAmazonLoginAndPayHelper::class)->setToSession('amazonLogout', 1);
        }

        $paymentMethodHelper->createMopIfNotExistsAndReturnId();
        $payContainer->register('alkim_amazonpay::AMAZONPAY', AmzPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);

        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function (ExecutePayment $event) use ($paymentMethodHelper, $paymentRepository) {
                /** @var AlkimAmazonLoginAndPayHelper $helper */
                $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
                $helper->log(__CLASS__, __METHOD__, 'execute payment event', $event);
                if ($event->getMop() == $paymentMethodHelper->createMopIfNotExistsAndReturnId()) {
                    $orderId = $event->getOrderId();
                    $helper->log(__CLASS__, __METHOD__, 'execute payment - auth id', $helper->getFromSession('amazonAuthId'));
                    /** @var AmzTransactionHelper $transactionHelper */
                    $transactionHelper = pluginApp(AmzTransactionHelper::class);
                    $orderReference = null;
                    if ($amazonAuthId = $helper->getFromSession('amazonAuthId')) {
                        if ($transaction = $transactionHelper->getTransactionFromAmzId($amazonAuthId)) {
                            if ($transaction->paymentId) {
                                if ($payment = $paymentRepository->getPaymentById($transaction->paymentId)) {
                                    $helper->assignPlentyPaymentToPlentyOrder($payment, $orderId);
                                    $helper->setOrderIdToAmazonTransactions($transactionHelper->getOrderRefFromAmzId($amazonAuthId), $orderId);
                                    $helper->log(__CLASS__, __METHOD__, 'assign payment to order', [$payment, $orderId]);
                                    $helper->setToSession('amazonAuthId', '');
                                    if ($transaction->status === 'Open' && $helper->getFromConfig('authorizedStatus')) {
                                        $helper->setOrderStatusAuthorized($orderId);
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
                    if (!empty($orderReference)) {
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
        /** @var ConsentRepositoryContract $consentRepository */
        $consentRepository = pluginApp(ConsentRepositoryContract::class);

        /** @var ConfigRepository $config */
        $config = pluginApp(ConfigRepository::class);

        $this->getApplication()->register(AmzRouteServiceProvider::class);
        $this->getApplication()->bind(AmzTransactionRepositoryContract::class, AmzTransactionRepository::class);
        $this->getApplication()->bind(AmzCaptureProcedure::class);
        $consentRepository->registerConsent(
            'amazonPay',
            'AmazonLoginAndPay::AmazonPay.consentLabel',
            [
                'description' => 'AmazonLoginAndPay::AmazonPay.consentDescription',
                'provider' => 'AmazonLoginAndPay::AmazonPay.consentProvider',
                'lifespan' => 'AmazonLoginAndPay::AmazonPay.consentLifespan',
                'policyUrl' => 'https://pay.amazon.de/help/201212490',
                'group' => $config->get('AmazonLoginAndPay.consentGroup', 'payment'),
                'necessary' => $config->get('AmazonLoginAndPay.consentNecessary') === 'true',
                'isOptOut' => $config->get('AmazonLoginAndPay.consentOptOut') === 'true',
                'cookieNames' => [
                    'amazon-pay-abtesting-apa-migration',
                    'amazon-pay-abtesting-new-widgets',
                    'amazon-pay-connectedAuth',
                    'apay-session-set',
                    'language',
                    'amazon_Login_state_cache',
                    'amazon_Login_accessToken',
                    'apayLoginState',
                    'amzLoginType',
                    'amzDummy'
                ]
            ]
        );
    }
}
