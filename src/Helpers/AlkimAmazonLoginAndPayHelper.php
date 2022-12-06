<?php

namespace AmazonLoginAndPay\Helpers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use IO\Services\SessionStorageService;
use IO\Services\UrlBuilder\UrlQuery;
use IO\Services\WebstoreConfigurationService;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Property\Contracts\OrderPropertyRepositoryContract;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Translation\Translator;

class AlkimAmazonLoginAndPayHelper
{
    public static $config;
    public $pluginVersion = '1.6.5';
    public $session;
    public $configRepo;
    public $paymentMethodRepository;
    public $paymentRepository;
    public $orderRepository;
    public $paymentOrderRelationRepository;
    public $statusMap;
    public $webstoreHelper;
    public $translator;
    private $paymentMethodHelper;

    use Loggable;

    public function __construct(Translator $translator, WebstoreHelper $webstoreHelper, PaymentOrderRelationRepositoryContract $paymentOrderRelationRepository, OrderRepositoryContract $orderRepository, PaymentRepositoryContract $paymentRepository, FrontendSessionStorageFactoryContract $session, ConfigRepository $configRepository, PaymentMethodHelper $paymentMethodHelper)
    {
        $this->paymentRepository              = $paymentRepository;
        $this->orderRepository                = $orderRepository;
        $this->paymentOrderRelationRepository = $paymentOrderRelationRepository;
        $this->session                        = $session;
        $this->configRepo                     = $configRepository;
        $this->webstoreHelper                 = $webstoreHelper;
        $this->translator                     = $translator;
        $this->paymentMethodHelper            = $paymentMethodHelper;
    }

    public function getWebstoreName()
    {
        $this->log(__CLASS__, __METHOD__, 'webstore helper', $this->webstoreHelper);
        $webstoreConfig = $this->webstoreHelper->getCurrentWebstoreConfiguration();
        $this->log(__CLASS__, __METHOD__, 'webstore config', $webstoreConfig);

        return $webstoreConfig->name;
    }

    public function log($class, $method, $msg, $arg, $error = false)
    {
        $logger = $this->getLogger($class . '_' . $method);
        if ($error) {
            $logger->error($msg, $arg);
        } else {
            if (!is_array($arg)) {
                $arg = [$arg];
            }
            $arg[] = $msg;
            $logger->info('AmazonLoginAndPay::Logger.infoCaption', $arg);
        }
    }

    public function getCallConfig()
    {
        return [
            'sandbox'             => ((string)$this->getFromConfig('sandbox') == 'true'),
            'merchant_id'         => $this->getFromConfig('merchantId'),
            'access_key'          => $this->getFromConfig('mwsAccessKey'),
            'secret_key'          => $this->getFromConfig('mwsSecretAccessKey'),
            'client_id'           => $this->getFromConfig('loginClientId'),
            'application_name'    => 'plentymarkets-alkim-amazon-pay',
            'application_version' => $this->pluginVersion,
            'region'              => 'de'
        ];
    }

    public function getFromConfig($key)
    {
        return $this->configRepo->get('AmazonLoginAndPay.' . $key);
    }

    public function getTransactionMode()
    {
        return ($this->getFromConfig('sandbox') == 'true' ? 'Sandbox' : 'Live');
    }

    public function createPlentyPayment($amount, $status, $dateTime, $comment, $transactionId, $type = 'credit', $transactionType = 2, $currency = 'EUR')
    {
        /** @var Payment $payment */
        $payment                   = pluginApp(Payment::class);
        $payment->mopId            = (int)$this->paymentMethodHelper->createMopIfNotExistsAndReturnId();
        $payment->transactionType  = $transactionType;
        $payment->type             = $type;
        $payment->status           = $this->mapStatus($status);
        $payment->currency         = $currency;
        $payment->isSystemCurrency = ($currency === 'EUR' ? true : false);
        $payment->amount           = $amount;
        $payment->receivedAt       = $dateTime;
        if ($status != 'captured' && $status != 'refunded') {
            $payment->unaccountable = 1;
        } else {
            $payment->unaccountable = 0;
        }
        //TODO: remove after plenty fix
        $payment->hash = $dateTime . '_' . rand(100000, 999999);

        $paymentProperties   = [];
        $paymentProperties[] = $this->getPaymentProperty(PaymentProperty::TYPE_BOOKING_TEXT, $comment);
        if (!empty($transactionId)) {
            $paymentProperties[] = $this->getPaymentProperty(PaymentProperty::TYPE_TRANSACTION_ID, $transactionId);
        }

        $payment->properties = $paymentProperties;
        $payment             = $this->paymentRepository->createPayment($payment);

        return $payment;
    }

    public function mapStatus(string $status)
    {
        if (!is_array($this->statusMap) || count($this->statusMap) <= 0) {
            $statusConstants = $this->paymentRepository->getStatusConstants();
            if (!is_null($statusConstants) && is_array($statusConstants)) {
                $this->statusMap['captured']           = $statusConstants['captured'];
                $this->statusMap['approved']           = $statusConstants['approved'];
                $this->statusMap['refused']            = $statusConstants['refused'];
                $this->statusMap['partially_captured'] = $statusConstants['partially_captured'];
                $this->statusMap['captured']           = $statusConstants['captured'];
                $this->statusMap['awaiting_approval']  = $statusConstants['awaiting_approval'];
                $this->statusMap['refunded']           = $statusConstants['refunded'];
            }
        }

        return (int)$this->statusMap[$status];
    }

    private function getPaymentProperty($typeId, $value)
    {
        /** @var PaymentProperty $paymentProperty */
        $paymentProperty         = pluginApp(PaymentProperty::class);
        $paymentProperty->typeId = $typeId;
        $paymentProperty->value  = $value;

        return $paymentProperty;
    }

    public function updatePlentyPayment($id, $status, $comment = null, $amount = null, $orderId = null)
    {

        try {
            if (!empty($id)) {
                $payment = $this->paymentRepository->getPaymentById($id);
                if ($payment) {
                    $payment->status = $this->mapStatus($status);

                    if ($amount !== null) {
                        $payment->amount = (float)$amount;
                    }

                    if (($status != 'approved' && $status != 'captured') || $amount == 0) {
                        $payment->unaccountable = 1;
                    } else {
                        $payment->unaccountable = 0;
                    }
                    $payment->updateOrderPaymentStatus = true;
                    $this->log(__CLASS__, __METHOD__, 'before updatePayment', ['payment' => $payment, 'orderId' => $orderId, 'comment' => $comment]);
                    $result = $this->paymentRepository->updatePayment($payment);
                    $this->log(__CLASS__, __METHOD__, 'after updatePayment', ['result' => $result, 'orderId' => $orderId]);
                    if ($orderId !== null) {
                        $this->recalculateOrderPayment($payment, $orderId);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'plenty payment update failed', [$e, $e->getMessage()], true);
        }
    }

    public function recalculateOrderPayment(Payment $payment, int $orderId)
    {
        $order = $this->orderRepository->findOrderById($orderId);
        $this->log(__CLASS__, __METHOD__, 'recalculate order payment start', ['order' => $order, 'payment' => $payment]);
        if (!is_null($order) && $order instanceof Order) {
            $this->paymentOrderRelationRepository->deleteOrderRelation($payment);
            $this->paymentOrderRelationRepository->createOrderRelation($payment, $order);
            $this->log(__CLASS__, __METHOD__, 'recalculate order payment end', ['order' => $order, 'payment' => $payment]);
        }
    }

    public function assignPlentyPaymentToPlentyOrder(Payment $payment, int $orderId)
    {
        try {
            $this->log(__CLASS__, __METHOD__, 'start assign plenty payment to order', ['orderId' => $orderId, 'payment' => $payment]);
            /** @var AuthHelper $authHelper */
            $authHelper = pluginApp(AuthHelper::class);
            $orderRepo  = $this->orderRepository;
            $order      = $authHelper->processUnguarded(
                function () use ($orderRepo, $orderId) {
                    return $orderRepo->findOrderById($orderId);
                }
            );
            $this->log(__CLASS__, __METHOD__, 'assign plenty payment to order', ['order' => $order, 'payment' => $payment]);
            if (!is_null($order) && $order instanceof Order) {
                $paymentOrderRepo = $this->paymentOrderRelationRepository;
                $return           = $authHelper->processUnguarded(
                    function () use ($paymentOrderRepo, $payment, $order) {
                        return $paymentOrderRepo->createOrderRelation($payment, $order);
                    }
                );
                $this->log(__CLASS__, __METHOD__, 'assign plenty payment to order - result', $return);
            }
        } catch (\Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'assign plenty payment to order failed', [$e, $e->getMessage()], true);

            return false;
        }

        return true;
    }

    public function getOrderTotalAndCurrency(int $orderId)
    {
        $order = $this->orderRepository->findOrderById($orderId);
        $this->log(__CLASS__, __METHOD__, 'get order amount', ['order' => $order, 'amounts' => $order->amounts, 'amount' => $order->amounts[0], 'amount 1' => $order->amounts[0]->grossTotal, 'amount 2' => $order->amounts[0]["grossTotal"]]);
        $amount = (isset($order->amounts[1]) ? $order->amounts[1] : $order->amounts[0]);

        return [
            'total'    => ($amount->isNet ? $amount->netTotal : $amount->grossTotal),
            'currency' => $amount->currency
        ];
    }

    public function reformatAmazonAddress($address, $emailAddress = null)
    {
        $finalAddress = [
            'options' => []
        ];

        $name        = $address["Name"];
        $t           = explode(' ', $name);
        $lastNameKey = count($t) - 1;
        $lastName    = $t[$lastNameKey];
        unset($t[$lastNameKey]);
        $firstName = implode(' ', $t);
        if (empty($address["AddressLine1"])) { // sometimes AddressLine1 is array(0){}
            $address["AddressLine1"] = '';
        }
        if ((string)$address["AddressLine3"] != '') {
            $street  = trim($address["AddressLine3"]);
            $company = trim($address["AddressLine1"] . ' ' . $address["AddressLine2"]);
        } elseif ((string)$address["AddressLine2"] != '') {
            $street  = trim($address["AddressLine2"]);
            $company = trim($address["AddressLine1"]);
        } else {
            $company = '';
            $street  = trim($address["AddressLine1"]);
        }
        $houseNo     = '';
        $streetParts = explode(' ', $street); //TODO: replace with preg_split('/[\s]+/', $street);
        if (count($streetParts) > 1) {
            $houseNoKey = max(array_keys($streetParts));
            if (strlen($streetParts[$houseNoKey]) <= 5) {
                $houseNo = $streetParts[$houseNoKey];
                unset($streetParts[$houseNoKey]);
                $street = implode(' ', $streetParts);
            }
        }
        $city        = $address["City"];
        $postcode    = $address["PostalCode"];
        $countryCode = $address["CountryCode"];
        $phone       = $address["Phone"];

        $finalAddress["name1"]    = $company;
        $finalAddress["name2"]    = $firstName;
        $finalAddress["name3"]    = $lastName;
        $finalAddress["address1"] = $street;

        if (!empty($houseNo)) {
            $finalAddress["address2"] = $houseNo;
        }

        $finalAddress["postalCode"] = $postcode;
        $finalAddress["town"]       = $city;
        $finalAddress["countryId"]  = $this->getCountryId($countryCode);
        if (!empty($phone)) {
            $finalAddress["phone"]     = $phone;
            $finalAddress["options"][] = [
                'typeId' => 4,
                'value'  => $phone
            ];

        }
        if (!empty($emailAddress)) {
            $finalAddress["email"]     = $emailAddress;
            $finalAddress["options"][] = [
                'typeId' => 5,
                'value'  => $emailAddress
            ];
        }
        $this->log(__CLASS__, __METHOD__, 'formatted address', [$finalAddress]);

        return $finalAddress;
    }

    public function getCountryId($countryIso2)
    {
        /** @var CountryRepositoryContract $countryContract */
        $countryContract = pluginApp(CountryRepositoryContract::class);
        $country         = $countryContract->getCountryByIso($countryIso2, 'isoCode2');
        $this->log(__CLASS__, __METHOD__, 'get country id', [$countryIso2, $country]);

        return (!empty($country) ? $country->id : 1);
    }

    public function setOrderStatusAuthorized($orderId)
    {
        $newOrderStatus = $this->getFromConfig('authorizedStatus');
        if ($newOrderStatus === '4/5') {
            try {
            $this->log(__CLASS__, __METHOD__, 'start intelligent stock', ['order' => $orderId]);
            $orderRepo = $this->orderRepository;
            /** @var AuthHelper $authHelper */
            $authHelper = pluginApp(AuthHelper::class);
            $authHelper->processUnguarded(
                function () use ($orderRepo, $orderId) {
                    return $orderRepo->setOrderStatus45((int)$orderId);
                }
            );
            } catch (\Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'set intelligent stock order status failed', [$e, $e->getMessage()], true);
            }
        } else {
            $this->setOrderStatus($orderId, $newOrderStatus);
        }
    }

    public function setOrderStatus($orderId, $status)
    {
        $this->log(__CLASS__, __METHOD__, 'try to set order status', ['order' => $orderId, 'status' => $status]);
        if (!empty($status)) {
            $order    = ['statusId' => (float)$status];
            $response = '';
            try {
                $orderRepo = $this->orderRepository;
                /** @var AuthHelper $authHelper */
                $authHelper = pluginApp(AuthHelper::class);
                $response   = $authHelper->processUnguarded(
                    function () use ($orderRepo, $order, $orderId) {
                        return $orderRepo->updateOrder($order, (int)$orderId);
                    }
                );
            } catch (\Exception $e) {
                $this->log(__CLASS__, __METHOD__, 'set order status failed', [$e, $e->getMessage()], true);
            }
            $this->log(__CLASS__, __METHOD__, 'finished set order status', ['order' => $response, 'status' => $status]);
        } else {
            $this->log(__CLASS__, __METHOD__, 'set order status cancelled because of empty status', null);
        }

    }

    public function resetSession($keepBasket = false)
    {
        $this->setToSession('amazonCheckoutError', '');
        $this->setToSession('amzOrderReference', '');
        $this->setToSession('amazonAuthId', '');
        $this->setToSession('amzCheckoutOrderReference', '');
        $this->setToSession('amzInvalidPaymentOrderReference', '');
        if (!$keepBasket) {
            $this->setToSession('amzCheckoutBasket', '');
        }
    }

    public function setToSession($key, $value)
    {
        $this->session->getPlugin()->setValue($key, $value);
    }

    public function getAccessToken()
    {
        $token = $this->getFromSession('amzUserToken');
        if (empty($token)) {
            /** @var Request $request */
            $request = pluginApp(Request::class);
            $header  = $request->header();
            $cookie  = $header["cookie"];
            if (is_array($cookie)) {
                $cookie = implode(' ', $cookie);
            }
            $this->log(__CLASS__, __METHOD__, 'no user token in session - tried from cookie', [$header, explode('; ', $cookie)]);
            foreach (explode('; ', $cookie) as $cookiePart) {
                if (substr($cookiePart, 0, 25) === 'amazon_Login_accessToken=') {
                    $token = urldecode(substr($cookiePart, 25));
                    $this->log(__CLASS__, __METHOD__, 'token from cookie', $token);
                }
            }
        }

        return $token;
    }

    public function getFromSession($key)
    {
        return $this->session->getPlugin()->getValue($key);
    }

    public function isNet()
    {
        return (bool)$this->session->getCustomer()->showNetPrice;
    }

    public function setOrderIdToAmazonTransactions($orderReference, $orderId)
    {
        /** @var AmzTransactionRepositoryContract $amzTransactionRepository */
        $amzTransactionRepository = pluginApp(AmzTransactionRepositoryContract::class);
        $transactions             = $amzTransactionRepository->getTransactions([
            ['orderReference', '=', $orderReference]
        ]);
        $this->log(__CLASS__, __METHOD__, 'transactions with order ref for setting order id', [
            'orderRef'     => $orderReference,
            'transactions' => $transactions
        ]);
        foreach ($transactions as $_transaction) {
            $_transaction->order = $orderId;
            $amzTransactionRepository->updateTransaction($_transaction);
            $this->log(__CLASS__, __METHOD__, 'set order id to transaction', $_transaction);
        }
        $this->setOrderExternalId($orderId, $orderReference);
    }

    public function setOrderExternalId($orderId, $externalId)
    {
        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);

        /** @var \Plenty\Modules\Order\Property\Contracts\OrderPropertyRepositoryContract $orderPropertyRepository */
        $orderPropertyRepository = pluginApp(OrderPropertyRepositoryContract::class);
        $helper                  = $this;
        $authHelper->processUnguarded(
            function () use ($orderPropertyRepository, $orderId, $externalId, $helper) {
                try {
                    /** @var \Plenty\Modules\Order\Property\Models\OrderProperty $existing */
                    $existing = $orderPropertyRepository->findByOrderId($orderId, OrderPropertyType::EXTERNAL_ORDER_ID);
                    $helper->log(__CLASS__, __METHOD__, 'existing external order id check', [$orderId, $externalId, $existing->toArray()]);
                    $existingArray = $existing->toArray();
                    if ($existing && !empty($existingArray)) {
                        $helper->log(__CLASS__, __METHOD__, 'existing external order id return', [$existingArray]);

                        return;
                    }
                    $orderProperty = $orderPropertyRepository->create([
                        'orderId' => $orderId,
                        'typeId'  => OrderPropertyType::EXTERNAL_ORDER_ID,
                        'value'   => $externalId
                    ]);
                    $helper->log(__CLASS__, __METHOD__, 'external order id set', [$orderProperty]);
                } catch (\Exception $e) {
                    $helper->log(__CLASS__, __METHOD__, 'setOrderExternalId error', [$e->getCode(), $e->getMessage(), $e->getLine()], true);
                }

            });
    }

    public function getUrl($path)
    {
        return $this->getAbsoluteUrl($path);
    }

    public function getAbsoluteUrl($path)
    {
        /** @var WebstoreConfigurationService $webstoreConfigurationService */
        $webstoreConfigurationService = pluginApp(WebstoreConfigurationService::class);
        /** @var SessionStorageService $sessionStorage */
        $sessionStorage  = pluginApp(SessionStorageService::class);
        $defaultLanguage = $webstoreConfigurationService->getDefaultLanguage();
        $lang            = $sessionStorage->getLang();

        $includeLanguage = $lang !== null && $lang !== $defaultLanguage;
        /** @var UrlQuery $urlQuery */
        $urlQuery = pluginApp(UrlQuery::class, ['path' => $path, 'lang' => $lang]);

        return $urlQuery->toAbsoluteUrl($includeLanguage);
    }

    public function scheduleNotification($message, $type = 'error')
    {
        $notification         = [
            'message'    => $message,
            'code'       => 0,
            'stackTrace' => []
        ];
        $notifications[$type] = $notification;
        $this->setToSession('notifications', json_encode($notifications));
    }

    public function translate($textId)
    {
        return $this->translator->trans($textId);
    }

}
