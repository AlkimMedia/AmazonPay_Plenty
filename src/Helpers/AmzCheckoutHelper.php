<?php
namespace AmazonLoginAndPay\Helpers;

use AmazonLoginAndPay\Services\AmzBasketService;
use AmazonLoginAndPay\Services\AmzCheckoutService;
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
        $this->helper = $helper;
        $this->transactionHelper = $transactionHelper;
        $this->checkoutService = $checkoutService;
        $this->basketService = $basketService;
        $this->checkout = $checkout;
        $this->basketItemRepository = $basketItemRepository;
        $this->orderRepository = $orderRepository;
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
            $item["variationId"] = $basketItem["variationId"];
            $return[] = $item;

        }
        $this->helper->log(__CLASS__, __METHOD__, 'basket items', [$return, $basketItems]);
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

    public function doCheckoutActions($amount = null, $orderId = 0, $walletOnly = false)
    {
        $return = [
            'redirect' => ''
        ];
        $successTarget = ($this->helper->getFromConfig('submitOrderIds') == 'true' ? 'confirmation' : 'place-order');
        if (!$walletOnly) {
            $this->setPaymentMethod();
            $successTarget = 'place-order';
        }
        if ($amount === null) {
            $basket = $this->getBasketData();
            $amount = $basket["basketAmount"];
        }
        $orderReferenceId = $this->helper->getFromSession('amzOrderReference');
        if (!$walletOnly) {
            $setOrderReferenceDetailsResponse = $this->transactionHelper->setOrderReferenceDetails($orderReferenceId, $amount, $orderId);
            $constraints = $setOrderReferenceDetailsResponse["SetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Constraints"];
            $constraint = $constraints["Constraint"]["ConstraintID"];
            if (!empty($constraint)) {
                if (!empty($orderId)) {
                    $this->cancelOrder($orderId);
                    $this->restoreBasket();
                }
                $this->helper->setToSession('amazonCheckoutError', 'InvalidPaymentMethod');
                $return["redirect"] = 'amazon-checkout';
                return $return;
            }
        }
        $this->transactionHelper->confirmOrderReference($orderReferenceId, true, $orderId);
        $this->helper->log(__CLASS__, __METHOD__, 'checkout auth mode', $this->helper->getFromConfig('authorizationMode'));
        if ($this->helper->getFromConfig('authorizationMode') != 'manually') {
            $this->helper->log(__CLASS__, __METHOD__, 'try to authorize', $orderReferenceId);
            $response = $this->transactionHelper->authorize($orderReferenceId, $amount, 0);
            $this->helper->log(__CLASS__, __METHOD__, 'amazonCheckoutAuthorizeResult', $response);
            if (is_array($response) && !empty($response["AuthorizeResult"])) {
                $details = $response["AuthorizeResult"]["AuthorizationDetails"];
                $status = $details["AuthorizationStatus"]["State"];
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
                            $return["redirect"] = 'basket';
                            return $return;
                        } else {
                            $response = $this->transactionHelper->authorize($orderReferenceId, $amount);
                            $details = $response["AuthorizeResult"]["AuthorizationDetails"];
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
                if (!empty($orderId)) {
                    $this->cancelOrder($orderId);
                    $this->restoreBasket();
                }
                $this->helper->setToSession('amazonCheckoutError', 'UnknownError');
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

    public function cancelOrder($orderId)
    {
        $this->helper->log(__CLASS__, __METHOD__, 'cancel order', $orderId);
        $orderRepo = $this->orderRepository;
        /** @var AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);
        $response = $authHelper->processUnguarded(
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
                    'quantity' => $item["quantity"]
                ]);
                $this->helper->log(__CLASS__, __METHOD__, 'added basket item', $basketItem);
            }
            $this->helper->setToSession('amzCheckoutBasket', []);
        }
    }


}