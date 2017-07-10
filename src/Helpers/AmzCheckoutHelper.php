<?php
/** @noinspection PhpUndefinedNamespaceInspection */
namespace AmazonLoginAndPay\Helpers;

use AmazonLoginAndPay\Services\BasketService;
use AmazonLoginAndPay\Services\CheckoutService;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Frontend\Contracts\Checkout;

class AmzCheckoutHelper
{
    public static $config;
    public $checkoutService;
    public $basketService;
    public $checkout;
    public $helper;
    public $transactionHelper;
    public $basketItemRepository;

    public function __construct(BasketItemRepositoryContract $basketItemRepository, AlkimAmazonLoginAndPayHelper $helper, BasketService $basketService, CheckoutService $checkoutService, Checkout $checkout, AmzTransactionHelper $transactionHelper)
    {
        $this->helper = $helper;
        $this->transactionHelper = $transactionHelper;
        $this->checkoutService = $checkoutService;
        $this->basketService = $basketService;
        $this->checkout = $checkout;
        $this->basketItemRepository = $basketItemRepository;
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
        $basketItems = $this->basketService->getBasketItems();
        $return = [];
        foreach ($basketItems as $basketItem) {
            $item = [];
            $item["name"] = $basketItem["name"];
            $item["price"] = $basketItem["price"];
            $item["quantity"] = $basketItem["quantity"];
            $item["final_price"] = $item["price"] * $item["quantity"];
            $item["image"] = '';
            $return[] = $item;

        }
        $this->helper->log(__CLASS__, __METHOD__, 'basket items', [$item, $basketItems]);
        return $return;
    }

    public function setAddresses($orderReferenceDetails = null)
    {
        if ($orderReferenceDetails === null) {
            $orderReferenceDetails = $this->transactionHelper->getOrderReferenceDetails($this->helper->getFromSession('amzOrderReference'), $this->helper->getFromSession('amzUserToken'));
        }
        $this->helper->log(__CLASS__, __METHOD__, 'set addresses', $orderReferenceDetails);
        $this->setShippingAddress($orderReferenceDetails);
        $this->setInvoiceAddress($orderReferenceDetails);
    }

    public function setShippingAddress($orderReferenceDetails = null)
    {
        if ($orderReferenceDetails === null) {
            $orderReferenceDetails = $this->transactionHelper->getOrderReferenceDetails($this->helper->getFromSession('amzOrderReference'), $this->helper->getFromSession('amzUserToken'));
        }

        $shippingAddress = $orderReferenceDetails["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Destination"]["PhysicalDestination"];
        $formattedShippingAddress = $this->helper->reformatAmazonAddress($shippingAddress);
        $shippingAddressObject = $this->helper->createAddress($formattedShippingAddress);
        $this->checkout->setCustomerShippingAddressId($shippingAddressObject->id);

        $this->helper->log(__CLASS__, __METHOD__, 'shipping address', [
            'shippingAddressArray' => $formattedShippingAddress,
            'shippingAddress' => $shippingAddressObject,
            'checkout' => $this->checkout
        ]);
    }

    public function setInvoiceAddress($orderReferenceDetails = null)
    {
        if ($orderReferenceDetails === null) {
            $orderReferenceDetails = $this->transactionHelper->getOrderReferenceDetails($this->helper->getFromSession('amzOrderReference'), $this->helper->getFromSession('amzUserToken'));
        }
        $invoiceAddress = $orderReferenceDetails["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["BillingAddress"]["PhysicalAddress"];
        $formattedInvoiceAddress = $this->helper->reformatAmazonAddress($invoiceAddress);
        $formattedInvoiceAddress["email"] = $orderReferenceDetails["GetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Buyer"]["Email"];
        $invoiceAddressObject = $this->helper->createAddress($formattedInvoiceAddress);
        $this->checkout->setCustomerInvoiceAddressId($invoiceAddressObject->id);

        $this->helper->log(__CLASS__, __METHOD__, 'invoice address', [
            'invoiceAddressArray' => $formattedInvoiceAddress,
            'invoiceAddress' => $invoiceAddressObject,
            'checkout' => $this->checkout
        ]);
    }

    public function setShippingProfile($id)
    {
        $this->checkout->setShippingProfileId($id);
    }

    public function doCheckoutActions($amount = null, $orderId = 0)
    {
        $return = [
            'redirect' => ''
        ];
        $this->setPaymentMethod();
        if ($amount === null) {
            $basket = $this->getBasketData();
            $amount = $basket["basketAmount"];
        }
        $setOrderReferenceDetailsResponse = $this->transactionHelper->setOrderReferenceDetails($this->helper->getFromSession('amzOrderReference'), $amount, 0);
        $constraints = $setOrderReferenceDetailsResponse["SetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Constraints"];
        $constraint = $constraints["Constraint"]["ConstraintID"];
        if (!empty($constraint)) {
            $this->helper->setToSession('amazonCheckoutError', 'InvalidPaymentMethod');
            $return["redirect"] = 'amazon-checkout';
            return $return;
        }
        $this->transactionHelper->confirmOrderReference($this->helper->getFromSession('amzOrderReference'), true, $orderId);
        $this->helper->log(__CLASS__, __METHOD__, 'checkout auth mode', $this->helper->getFromConfig('authorizationMode'));
        if ($this->helper->getFromConfig('authorizationMode') != 'manually') {
            $this->helper->log(__CLASS__, __METHOD__, 'try to authorize', $this->helper->getFromSession('amzOrderReference'));
            $response = $this->transactionHelper->authorize($this->helper->getFromSession('amzOrderReference'), $amount, 0);
            $this->helper->log(__CLASS__, __METHOD__, 'amazonCheckoutAuthorizeResult', $response);
            if (is_array($response) && !empty($response["AuthorizeResult"])) {
                $details = $response["AuthorizeResult"]["AuthorizationDetails"];
                $status = $details["AuthorizationStatus"]["State"];
                if ($status == "Declined") {
                    $reason = $details["AuthorizationStatus"]["ReasonCode"];
                    if ($reason == 'TransactionTimedOut') {
                        if ($this->helper->getFromConfig('authorizationMode') == 'fast_auth') {
                            $this->transactionHelper->cancelOrder($this->helper->getFromSession('amzOrderReference'));
                            $this->helper->setToSession('amazonCheckoutError', 'AmazonRejected');
                            $return["redirect"] = 'basket';
                            return $return;
                        } else {
                            $response = $this->transactionHelper->authorize($this->helper->getFromSession('amzOrderReference'), $amount);
                            $details = $response["AuthorizeResult"]["AuthorizationDetails"];
                            $this->helper->setToSession('amazonAuthId', $details["AmazonAuthorizationId"]);
                            $this->helper->setToSession('paymentWarningTimeout', 1);
                        }
                    } elseif ($reason == 'InvalidPaymentMethod') {
                        $return["redirect"] = 'amazon-checkout-wallet';
                        return $return;
                    } else {
                        //Hard Decline / AmazonRejected
                        $this->helper->setToSession('amazonCheckoutError', 'AmazonRejected');
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
                $this->helper->setToSession('amazonCheckoutError', 'UnknownError');
                $return["redirect"] = 'basket';
                return $return;
            }
        }
        $this->helper->setToSession('amzCheckoutOrderReference', $this->helper->getFromSession('amzOrderReference'));
        $return["redirect"] = 'place-order';
        return $return;
    }

    public function setPaymentMethod()
    {
        $paymentMethodId = $this->helper->createMopIfNotExistsAndReturnId();
        $this->checkout->setPaymentMethodId($paymentMethodId);
        $this->helper->log(__CLASS__, __METHOD__, 'paymentMethodId', ['id' => $paymentMethodId]);
    }

    public function getBasketData()
    {
        $basket = $this->basketService->getBasket();
        $this->helper->log(__CLASS__, __METHOD__, 'basket', [$basket]);
        $returnBasket = $basket->toArray();
        $basketAmount = $returnBasket["basketAmount"];
        $basketNetAmount = $returnBasket["basketAmountNet"];
        $returnBasket["vat"] = $basketAmount - $basketNetAmount;
        $this->helper->log(__CLASS__, __METHOD__, '$returnBasket', [$returnBasket]);
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


}