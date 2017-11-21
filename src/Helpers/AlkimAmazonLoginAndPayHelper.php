<?php
namespace AmazonLoginAndPay\Helpers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

class AlkimAmazonLoginAndPayHelper
{
    public static $config;
    public $session;
    public $configRepo;
    public $paymentMethodRepository;
    public $paymentRepository;
    public $orderRepository;
    public $paymentOrderRelationRepository;
    public $statusMap;
    public $webstoreHelper;
    use Loggable;

    public function __construct(WebstoreHelper $webstoreHelper, PaymentOrderRelationRepositoryContract $paymentOrderRelationRepository, OrderRepositoryContract $orderRepository, PaymentRepositoryContract $paymentRepository, PaymentMethodRepositoryContract $paymentMethodRepository, FrontendSessionStorageFactoryContract $session, ConfigRepository $configRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentRepository = $paymentRepository;
        $this->orderRepository = $orderRepository;
        $this->paymentOrderRelationRepository = $paymentOrderRelationRepository;
        $this->session = $session;
        $this->configRepo = $configRepository;
        $this->webstoreHelper = $webstoreHelper;
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
            $logger->error('ERROR: ' . $msg, $arg);
        } else {
            $logger->error('INFO: ' . $msg, $arg);
        }

    }

    public function getCallConfig()
    {
        return [
            'sandbox' => ((string)$this->getFromConfig('sandbox') == 'true'),
            'merchant_id' => $this->getFromConfig('merchantId'),
            'access_key' => $this->getFromConfig('mwsAccessKey'),
            'secret_key' => $this->getFromConfig('mwsSecretAccessKey'),
            'client_id' => $this->getFromConfig('loginClientId'),
            'application_name' => 'plentymarkets-alkim-amazon-pay',
            'application_version' => '0.1.0',
            'region' => 'de'
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

    public function createPlentyPayment($amount, $status, $dateTime, $comment, $transactionId, $type = 'credit', $transactionType = 2)
    {
        /** @var Payment $payment */
        $payment = pluginApp(Payment::class);
        $payment->mopId = (int)$this->createMopIfNotExistsAndReturnId();
        $payment->transactionType = $transactionType;
        $payment->type = $type;
        $payment->status = $this->mapStatus($status);
        $payment->currency = 'EUR';
        $payment->amount = $amount;
        $payment->receivedAt = $dateTime;
        if ($status != 'captured' && $status != 'refunded') {
            $payment->unaccountable = 1;
        } else {
            $payment->unaccountable = 0;
        }
        //TODO: remove after plenty fix
        $payment->hash = $dateTime . '_' . rand(100000, 999999);

        $paymentProperties = [];
        $paymentProperties[] = $this->getPaymentProperty(PaymentProperty::TYPE_BOOKING_TEXT, $comment);
        $paymentProperties[] = $this->getPaymentProperty(PaymentProperty::TYPE_TRANSACTION_ID, $transactionId);


        $payment->properties = $paymentProperties;
        $payment = $this->paymentRepository->createPayment($payment);
        return $payment;
    }

    public function createMopIfNotExistsAndReturnId()
    {
        $paymentMethodId = $this->getPaymentMethod();
        if ($paymentMethodId === false) {
            $paymentMethodData = array('pluginKey' => 'alkim_amazonpay',
                'paymentKey' => 'AMAZONPAY',
                'name' => 'Amazon Pay');

            $this->paymentMethodRepository->createPaymentMethod($paymentMethodData);
            $paymentMethodId = $this->getPaymentMethod();
        }
        return $paymentMethodId;
    }

    /**
     * Load the ID of the payment method for the given plugin key
     * Return the ID for the payment method
     *
     * @return string|int
     */
    public function getPaymentMethod()
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('alkim_amazonpay');
        if (!is_null($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                if ($paymentMethod->paymentKey == 'AMAZONPAY') {
                    return $paymentMethod->id;
                }
            }
        }

        return false;
    }

    public function mapStatus(string $status)
    {
        if (!is_array($this->statusMap) || count($this->statusMap) <= 0) {
            $statusConstants = $this->paymentRepository->getStatusConstants();
            if (!is_null($statusConstants) && is_array($statusConstants)) {
                $this->statusMap['captured'] = $statusConstants['captured'];
                $this->statusMap['approved'] = $statusConstants['approved'];
                $this->statusMap['refused'] = $statusConstants['refused'];
                $this->statusMap['partially_captured'] = $statusConstants['partially_captured'];
                $this->statusMap['captured'] = $statusConstants['captured'];
                $this->statusMap['awaiting_approval'] = $statusConstants['awaiting_approval'];
                $this->statusMap['refunded'] = $statusConstants['refunded'];
            }
        }
        return (int)$this->statusMap[$status];
    }

    private function getPaymentProperty($typeId, $value)
    {
        /** @var PaymentProperty $paymentProperty */
        /** @noinspection PhpUndefinedNamespaceInspection */
        $paymentProperty = pluginApp(PaymentProperty::class);
        $paymentProperty->typeId = $typeId;
        $paymentProperty->value = $value;
        return $paymentProperty;
    }

    public function updatePlentyPayment($id, $status, $comment = null, $amount = null, $orderId = null)
    {

        try {
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
        $order = $this->orderRepository->findOrderById($orderId);
        $this->log(__CLASS__, __METHOD__, 'assign plenty payment to order', ['order' => $order, 'payment' => $payment]);
        if (!is_null($order) && $order instanceof Order) {
            $this->paymentOrderRelationRepository->createOrderRelation($payment, $order);
        }
    }

    public function getOrderTotal(int $orderId)
    {
        $order = $this->orderRepository->findOrderById($orderId);
        $this->log(__CLASS__, __METHOD__, 'get order amount', ['order' => $order, 'amounts' => $order->amounts, 'amount' => $order->amounts[0], 'amount 1' => $order->amounts[0]->grossTotal, 'amount 2' => $order->amounts[0]["grossTotal"]]);
        return $order->amounts[0]->grossTotal;
    }


    public function reformatAmazonAddress($address)
    {
        $finalAddress = [];

        $name = $address["Name"];
        $t = explode(' ', $name);
        $lastNameKey = count($t) - 1;
        $lastName = $t[$lastNameKey];
        unset($t[$lastNameKey]);
        $firstName = implode(' ', $t);
        if (empty($address["AddressLine1"])) { // sometimes AddressLine1 is array(0){}
            $address["AddressLine1"] = '';
        }
        if ((string)$address["AddressLine3"] != '') {
            $street = trim($address["AddressLine3"]);
            $company = trim($address["AddressLine1"] . ' ' . $address["AddressLine2"]);
        } elseif ((string)$address["AddressLine2"] != '') {
            $street = trim($address["AddressLine2"]);
            $company = trim($address["AddressLine1"]);
        } else {
            $company = '';
            $street = trim($address["AddressLine1"]);
        }


        $city = $address["City"];
        $postcode = $address["PostalCode"];
        $countryCode = $address["CountryCode"];
        $phone = $address["Phone"];


        $finalAddress["name1"] = $company;
        $finalAddress["name2"] = $firstName;
        $finalAddress["name3"] = $lastName;
        $finalAddress["address1"] = $street;
        $finalAddress["postalCode"] = $postcode;
        $finalAddress["town"] = $city;
        $finalAddress["countryId"] = $this->getCountryId($countryCode);
        $finalAddress["phone"] = $phone;
        $this->log(__CLASS__, __METHOD__, 'formatted address', [$finalAddress]);
        return $finalAddress;
    }

    public function getCountryId($countryIso2)
    {
        /** @var CountryRepositoryContract $countryContract */
        $countryContract = pluginApp(CountryRepositoryContract::class);
        $country = $countryContract->getCountryByIso($countryIso2, 'isoCode2');
        $this->log(__CLASS__, __METHOD__, 'get country id', [$countryIso2, $country]);
        return (!empty($country) ? $country->id : 1);
    }

    public function setOrderStatus($orderId, $status)
    {
        $this->log(__CLASS__, __METHOD__, 'try to set order status', ['order' => $orderId, 'status' => $status]);
        $order = ['statusId' => (float)$status];
        $response = '';
        try {
            $orderRepo = $this->orderRepository;
            /** @var AuthHelper $authHelper */
            $authHelper = pluginApp(AuthHelper::class);
            $response = $authHelper->processUnguarded(
                function () use ($orderRepo, $order, $orderId) {
                    return $orderRepo->updateOrder($order, (int)$orderId);
                }
            );
        } catch (\Exception $e) {
            $this->log(__CLASS__, __METHOD__, 'set order status failed', [$e, $e->getMessage()], true);
        }

        $this->log(__CLASS__, __METHOD__, 'finished set order status', ['order' => $response, 'status' => $status]);
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

    public function getFromSession($key)
    {
        return $this->session->getPlugin()->getValue($key);
    }

    public function setOrderIdToAmazonTransactions($orderReference, $orderId)
    {
        /** @var AmzTransactionRepositoryContract $amzTransactionRepository */
        $amzTransactionRepository = pluginApp(AmzTransactionRepositoryContract::class);
        $transactions = $amzTransactionRepository->getTransactions([
            ['orderReference', '=', $orderReference]
        ]);
        $this->log(__CLASS__, __METHOD__, 'transactions with order ref for setting order id', [
            'orderRef' => $orderReference,
            'transactions' => $transactions
        ]);
        foreach ($transactions as $_transaction) {
            $_transaction->order = $orderId;
            $amzTransactionRepository->updateTransaction($_transaction);
            $this->log(__CLASS__, __METHOD__, 'set order id to transaction', $_transaction);
        }
    }


}