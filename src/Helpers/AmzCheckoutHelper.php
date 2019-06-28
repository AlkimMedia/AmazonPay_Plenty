<?php

namespace AmazonLoginAndPay\Helpers;

use AmazonLoginAndPay\Services\AmzBasketService;
use AmazonLoginAndPay\Services\AmzCheckoutService;
use AmazonLoginAndPay\Services\AmzCustomerService;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Address\Models\AddressRelationType;
use Plenty\Modules\Account\Contact\Contracts\ContactAddressRepositoryContract;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;

class AmzCheckoutHelper
{
    public $checkoutService;
    public $basketService;
    public $checkout;
    public $helper;
    public $transactionHelper;
    public $basketItemRepository;
    public $orderRepository;

    public function __construct(OrderRepositoryContract $orderRepository, BasketItemRepositoryContract $basketItemRepository, AlkimAmazonLoginAndPayHelper $helper, AmzBasketService $basketService, AmzCheckoutService $checkoutService, Checkout $checkout, AmzTransactionHelper $transactionHelper)
    {
        $this->helper               = $helper;
        $this->transactionHelper    = $transactionHelper;
        $this->checkoutService      = $checkoutService;
        $this->basketService        = $basketService;
        $this->checkout             = $checkout;
        $this->basketItemRepository = $basketItemRepository;
        $this->orderRepository      = $orderRepository;
    }

    public function getShippingOptionsList()
    {
        $checkoutData = $this->getCheckoutData();

        return $checkoutData["shippingProfileList"];
    }

    public function getCheckoutData()
    {
        return $this->checkoutService->getCheckout();
    }

    public function getBasketItems()
    {

        $return = [];
        try {
            $return = $this->basketService->getBasketItemsForTemplate();
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'getBasketItems failed', [$e, $e->getMessage()], true);
        }
        $this->helper->log(__CLASS__, __METHOD__, 'getBasketItems return', [$return]);

        return $return;
    }

    public function setAddresses($orderReferenceDetails = null)
    {
        if ($orderReferenceDetails === null) {
            $orderReferenceDetails = $this->transactionHelper->getOrderReferenceDetails($this->helper->getFromSession('amzOrderReference'), $this->helper->getAccessToken());
            if(!empty($orderReferenceDetails["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Constraints"])){
                $this->transactionHelper->setOrderReferenceDetailsAuto();
                $orderReferenceDetails = $this->transactionHelper->getOrderReferenceDetails($this->helper->getFromSession('amzOrderReference'), $this->helper->getAccessToken());
            }
        }
        $this->helper->log(__CLASS__, __METHOD__, 'set addresses', $orderReferenceDetails);
        $this->setShippingAddress($orderReferenceDetails);
        $this->setInvoiceAddress($orderReferenceDetails);
    }

    public function setShippingAddress($orderReferenceDetails = null)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'set shipping address - start', []);
        if ($orderReferenceDetails === null) {
            $orderReferenceDetails = $this->transactionHelper->getOrderReferenceDetails($this->helper->getFromSession('amzOrderReference'), $this->helper->getAccessToken());
        }
        $formattedShippingAddress = null;
        $shippingAddressObject    = null;
        try {
            $shippingAddress          = $orderReferenceDetails["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Destination"]["PhysicalDestination"];
            $email = null;
            if($this->helper->getFromConfig('useEmailInShippingAddress') == 'true'){
                $email          = $orderReferenceDetails["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Buyer"]["Email"];
                if (empty($email)) {
                    $userData = $this->transactionHelper->call('GetUserInfo', ['access_token' => $this->helper->getAccessToken()]);
                    $email    = $userData["email"];
                }
            }
            $formattedShippingAddress = $this->helper->reformatAmazonAddress($shippingAddress, $email);

            /** @var AmzCustomerService $customerService */
            $customerService = pluginApp(AmzCustomerService::class);
            $contactId       = $customerService->getContactId();

            if ($contactId) {
                $shippingAddressObject = $this->createContactAddress($contactId, $formattedShippingAddress);
            } else {
                $shippingAddressObject = $this->createAddress($formattedShippingAddress);
            }

            $this->checkout->setCustomerShippingAddressId($shippingAddressObject->id);
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'set shipping address failed', [$e, $e->getMessage()], true);
        }

        $this->helper->log(__CLASS__, __METHOD__, 'shipping address', [
            'shippingAddressArray' => $formattedShippingAddress,
            'shippingAddress'      => $shippingAddressObject,
            'checkout'             => $this->checkout
        ]);
    }

    public function createContactAddress($contactId, $data, $type = 'delivery')
    {
        /** @var ContactAddressRepositoryContract $contactAddressRepo */
        $contactAddressRepo = pluginApp(ContactAddressRepositoryContract::class);
        $addressObj         = null;
        try {
            $addressObj = $contactAddressRepo->createAddress($data, $contactId, ($type === 'delivery' ? AddressRelationType::DELIVERY_ADDRESS : AddressRelationType::BILLING_ADDRESS));
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'contact address creation failed', [$e, $e->getMessage()], true);
        }
        $this->helper->log(__CLASS__, __METHOD__, 'contact address created', [$data, $addressObj]);

        return $addressObj;
    }

    public function createAddress($data)
    {
        /** @var AddressRepositoryContract $addressRepo */
        $addressRepo = pluginApp(AddressRepositoryContract::class);
        $addressObj  = null;
        try {
            $addressObj = $addressRepo->createAddress($data);
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'address creation failed', [$e, $e->getMessage()], true);
        }
        $this->helper->log(__CLASS__, __METHOD__, 'address created', [$data, $addressObj]);

        return $addressObj;
    }

    public function setInvoiceAddress($orderReferenceDetails = null, $fromShippingAddress = false)
    {
        if ($orderReferenceDetails === null) {
            $orderReferenceDetails = $this->transactionHelper->getOrderReferenceDetails($this->helper->getFromSession('amzOrderReference'), $this->helper->getAccessToken());
        }
        $formattedInvoiceAddress = null;
        $invoiceAddressObject    = null;

        try {
            if($fromShippingAddress){
                $invoiceAddress = $orderReferenceDetails["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Destination"]["PhysicalDestination"];
            }else {
                $invoiceAddress = $orderReferenceDetails["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["BillingAddress"]["PhysicalAddress"];
            }
            $email          = $orderReferenceDetails["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Buyer"]["Email"];
            if (empty($email)) {
                $userData = $this->transactionHelper->call('GetUserInfo', ['access_token' => $this->helper->getAccessToken()]);
                $email    = $userData["email"];
            }
            $formattedInvoiceAddress = $this->helper->reformatAmazonAddress($invoiceAddress, $email);
            /** @var AmzCustomerService $customerService */
            $customerService = pluginApp(AmzCustomerService::class);
            $contactId       = $customerService->getContactId();

            if ($contactId) {
                $invoiceAddressObject = $this->createContactAddress($contactId, $formattedInvoiceAddress, 'invoice');
            } else {
                $invoiceAddressObject = $this->createAddress($formattedInvoiceAddress);
            }
            $this->checkout->setCustomerInvoiceAddressId($invoiceAddressObject->id);
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'set invoice address failed', [$e, $e->getMessage(), $fromShippingAddress], true);
            if(!$fromShippingAddress){
                $this->setInvoiceAddress($orderReferenceDetails, true);
            }
        }

        $this->helper->log(__CLASS__, __METHOD__, 'invoice address', [
            'invoiceAddressArray' => $formattedInvoiceAddress,
            'invoiceAddress'      => $invoiceAddressObject,
            'checkout'            => $this->checkout,
            'fromShippingAddress' => $fromShippingAddress
        ]);
    }

    public function setShippingProfile($id)
    {
        $this->checkout->setShippingProfileId($id);
    }

    public function doCheckoutActions($amount = null, $orderId = 0, $walletOnly = false)
    {
        $successTarget = 'place-order';
        if (!$walletOnly) {
            $this->setPaymentMethod();
        }
        $orderReferenceId = $this->helper->getFromSession('amzOrderReference');

        if ($this->helper->getFromConfig('authorizationMode') != 'manually') {
            if (empty($amount)) {
                $basket = $this->getBasketData();
                $amount = $basket["basketAmount"];
            }
            $response = $this->transactionHelper->authorize($orderReferenceId, $amount, 0);
            $this->helper->log(__CLASS__, __METHOD__, 'amazonCheckoutAuthorizeResult', $response);
            if (is_array($response) && !empty($response["AuthorizeResult"])) {
                $details = $response["AuthorizeResult"]["AuthorizationDetails"];
                $status  = $details["AuthorizationStatus"]["State"];
                if ($status == "Declined") {
                    $reason = $details["AuthorizationStatus"]["ReasonCode"];
                    if ($reason == 'TransactionTimedOut') {
                        if ($this->helper->getFromConfig('authorizationMode') == 'fast_auth') {
                            $this->transactionHelper->cancelOrder($orderReferenceId);
                            if (!empty($orderId)) {
                                $this->cancelOrder($orderId);
                                $this->restoreBasket();
                            }
                            $this->helper->resetSession();
                            $this->helper->setToSession('amazonCheckoutError', 'AmazonRejected');
                            $this->helper->scheduleNotification($this->helper->translate('AmazonLoginAndPay::AmazonPay.paymentDeclinedInfo'));
                            $return["redirect"] = 'basket';

                            return $return;
                        } else {
                            $response = $this->transactionHelper->authorize($orderReferenceId, $amount);
                            $details  = $response["AuthorizeResult"]["AuthorizationDetails"];
                            $this->helper->setToSession('amazonAuthId', $details["AmazonAuthorizationId"]);
                            $this->helper->setToSession('paymentWarningTimeout', 1);
                        }
                    } elseif ($reason == 'InvalidPaymentMethod') {
                        $this->helper->setToSession('amzInvalidPaymentOrderReference', $orderReferenceId);
                        $return["redirect"] = 'amazon-checkout-wallet';

                        return $return;
                    } else {
                        //Hard Decline / AmazonRejected
                        $this->helper->log(__CLASS__, __METHOD__, 'AmazonRejected', ['orderID' => $orderId]);
                        if (!empty($orderId)) {
                            $this->cancelOrder($orderId);
                            $this->restoreBasket();
                        }
                        $this->helper->resetSession();
                        $this->helper->scheduleNotification($this->helper->translate('AmazonLoginAndPay::AmazonPay.paymentDeclinedInfo'));
                        $return["redirect"] = 'basket';

                        return $return;
                    }
                } else {
                    $this->helper->setToSession('amazonAuthId', $details["AmazonAuthorizationId"]);
                    if ($this->helper->getFromConfig('captureMode') == 'after_auth' && $status == 'Open') {
                        $this->transactionHelper->capture($details["AmazonAuthorizationId"], $amount);
                    }
                }
            } else {
                if (!empty($orderId)) {
                    $this->cancelOrder($orderId);
                    $this->restoreBasket();
                }
                $this->helper->setToSession('amazonCheckoutError', 'UnknownError');
                $this->helper->scheduleNotification($this->helper->translate('AmazonLoginAndPay::AmazonPay.paymentDeclinedInfo'));
                $return["redirect"] = 'basket';

                return $return;
            }
        }
        $this->helper->setToSession('amzCheckoutOrderReference', $orderReferenceId);
        $return["redirect"] = $successTarget;

        return $return;
    }

    public function setPaymentMethod()
    {
        $this->helper->log(__CLASS__, __METHOD__, 'try to set payment method', []);
        try {
            $paymentMethodId = $this->helper->createMopIfNotExistsAndReturnId();
            $this->checkout->setPaymentMethodId($paymentMethodId);
            $this->helper->log(__CLASS__, __METHOD__, 'paymentMethodId', ['id' => $paymentMethodId]);
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'set payment method failed', [$e, $e->getMessage()], true);
        }
    }

    public function getBasketData()
    {
        $returnBasket = [];
        try {
            $basket = $this->basketService->getBasket();
            $this->helper->log(__CLASS__, __METHOD__, 'basket', [$basket]);
            $returnBasket = $basket->toArray();
            if ($this->helper->isNet()) {
                $returnBasket["itemSum"]        = $returnBasket["itemSumNet"];
                $returnBasket["basketAmount"]   = $returnBasket["basketAmountNet"];
                $returnBasket["shippingAmount"] = $returnBasket["shippingAmountNet"];
            }
            $basketAmount        = $returnBasket["basketAmount"];
            $basketNetAmount     = $returnBasket["basketAmountNet"];
            $returnBasket["vat"] = $basketAmount - $basketNetAmount;
            $this->helper->log(__CLASS__, __METHOD__, '$returnBasket', [$returnBasket]);
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'getBasketData failed', [$e, $e->getMessage()], true);
        }

        return $returnBasket;

        /*
         * "id": 37,
    "sessionId": "7e6ad5c459065d346d0d310ff432a8685403c677",
    "orderId": ,
    "customerId": ,
    "customerShippingAddressId": 35,
    "currency": "EUR",
    "referrerId": 1,
    "shippingCountryId": 1,
    "methodOfPaymentId": 0,
    "shippingProviderId": 101,
    "shippingProfileId": 6,
    "itemSum": 415.31,
    "itemSumNet": 349,
    "basketAmount": 415.31,
    "basketAmountNet": 349,
    "shippingAmount": 0,
    "shippingAmountNet": 0,
    "paymentAmount": 0,
    "couponCode": "",
    "couponDiscount": 0,
    "shippingDeleteByCoupon": false,
    "basketRebate": 0,
    "maxFsk": 0,
    "orderTimestamp": ,
    "createdAt": "2017-03-10T17:11:51+01:00",
    "updatedAt": "2017-03-10T19:57:39+01:00",
    "basketRebateType": 0,

         */
    }

    public function cancelOrder($orderId)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'cancel order', $orderId);
        $orderRepo = $this->orderRepository;
        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);
        $response   = $authHelper->processUnguarded(
            function () use ($orderRepo, $orderId) {
                return $orderRepo->cancelOrder($orderId, ['message' => 'Amazon Pay']);
            }
        );
        $this->helper->log(__CLASS__, __METHOD__, 'cancelled order', $response);
    }

    public function restoreBasket()
    {
        $basketItems = $this->helper->getFromSession('amzCheckoutBasket');
        $this->helper->log(__CLASS__, __METHOD__, 'restore basket items', $basketItems);
        if (!empty($basketItems)) {
            foreach ($basketItems as $item) {
                $basketItem = $this->basketItemRepository->addBasketItem([
                    'variationId' => $item["variationId"],
                    'quantity'    => $item["quantity"]
                ]);
                $this->helper->log(__CLASS__, __METHOD__, 'added basket item', $basketItem);
            }
            $this->helper->setToSession('amzCheckoutBasket', []);
        }
    }

    public function confirmOrderReference($orderId = 0)
    {
        $return = [
            'redirect' => ''
        ];

        $basket   = $this->getBasketData();
        $amount   = $basket["basketAmount"];
        $currency = $basket["currency"];

        $orderReferenceId = $this->helper->getFromSession('amzOrderReference');

        $setOrderReferenceDetailsResponse = $this->transactionHelper->setOrderReferenceDetails($orderReferenceId, $amount, $orderId, $currency);
        $constraints                      = $setOrderReferenceDetailsResponse["SetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Constraints"];
        $constraint                       = $constraints["Constraint"]["ConstraintID"];
        if (!empty($constraint)) {
            if (!empty($orderId)) {
                $this->cancelOrder($orderId);
                $this->restoreBasket();
            }
            $this->helper->setToSession('amazonCheckoutError', 'InvalidPaymentMethod');
            $return["redirect"] = 'amazon-checkout';

            return $return;
        }

        $this->transactionHelper->confirmOrderReference($orderReferenceId, true, $orderId);

        return $return;
    }

}