<?php

namespace AmazonLoginAndPay\Controllers;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzCheckoutHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use AmazonLoginAndPay\Services\AmzBasketService;
use AmazonLoginAndPay\Services\AmzCustomerService;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Templates\Twig;


class AmzContentController extends Controller{
    public $configRepo;
    public $response;
    public $helper;
    public $transactionHelper;
    public $checkoutHelper;
    public $basketService;
    public $customerService;

    public function __construct(Response $response, AlkimAmazonLoginAndPayHelper $helper, AmzTransactionHelper $transactionHelper, AmzCheckoutHelper $checkoutHelper, AmzBasketService $basketService, AmzCustomerService $customerService)
    {
        $this->response = $response;
        $this->helper = $helper;
        $this->transactionHelper = $transactionHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->basketService = $basketService;
        $this->customerService = $customerService;
    }

    public function amazonCheckoutAction(Twig $twig)
    {
        $this->checkoutHelper->setPaymentMethod();
        $userData = $this->helper->getFromSession('amzUserData');
        $templateData = ['userData' => $userData, 'error' => $this->helper->getFromSession('amazonCheckoutError')];
        return $twig->render('AmazonLoginAndPay::content.amazon-checkout', $templateData);
    }

    public function amazonCheckoutWalletAction(Twig $twig)
    {
        $this->checkoutHelper->setPaymentMethod();
        $userData = $this->helper->getFromSession('amzUserData');
        $templateData = ['userData' => $userData, 'walletOnly' => 1];
        return $twig->render('AmazonLoginAndPay::content.amazon-checkout', $templateData);
    }


    public function amazonLoginProcessingAction(Twig $twig)
    {
        return $twig->render('AmazonLoginAndPay::content.amazon-login-processing', []);
        /*
        //TODO: decide whether to login or not
        $loginInfo = $this->customerService->loginWithAmazonUserData($userData);
        if (!empty($loginInfo["redirect"])) {
            return $this->response->redirectTo($loginInfo["redirect"]);
        } else {
            //TODO: decide where to go
            return $this->response->redirectTo('amazon-checkout');
        }
        */
    }

    public function amazonConnectAccountsAction(Request $request, Twig $twig)
    {
        $userData = $this->helper->getFromSession('amzUserData');
        if (($email = $request->get('email')) && ($password = $request->get('password'))) {
            $connectInfo = $this->customerService->connectAccounts($userData, $email, $password);
            if ($connectInfo["success"]) {
                return $this->response->redirectTo('amazon-checkout');
            }
        }
        $templateData = [
            'email' => $userData["email"]
        ];
        return $twig->render('AmazonLoginAndPay::content.amazon-connect-accounts', $templateData);
    }

    public function amazonCheckoutProceedAction()
    {
        $orderReferenceId = $this->helper->getFromSession('amzOrderReference');
        $walletOnly = $this->helper->getFromSession('amzInvalidPaymentOrderReference') == $orderReferenceId;
        $this->helper->log(__CLASS__, __METHOD__, 'is wallet only?', $walletOnly);
        if ($this->helper->getFromConfig('submitOrderIds') != 'true' || $walletOnly) {
            $amount = null;
            if ($walletOnly) {
                $amount = $this->transactionHelper->getAmountFromOrderRef($orderReferenceId);
            }
            $return = $this->checkoutHelper->doCheckoutActions($amount, 0, $walletOnly);
            $this->helper->log(__CLASS__, __METHOD__, 'checkout actions response', $return);
            if (!empty($return["redirect"])) {
                return $this->response->redirectTo($return["redirect"]);
            }
        } else {
            $basket = $this->checkoutHelper->getBasketData();
            $amount = $basket["basketAmount"];
            $orderReferenceId = $this->helper->getFromSession('amzOrderReference');
            $setOrderReferenceDetailsResponse = $this->transactionHelper->setOrderReferenceDetails($orderReferenceId, $amount, null);
            $constraints = $setOrderReferenceDetailsResponse["SetOrderReferenceDetailsResult"]["OrderReferenceDetails"]["Constraints"];
            $constraint = $constraints["Constraint"]["ConstraintID"];
            if (!empty($constraint)) {
                $this->helper->setToSession('amazonCheckoutError', 'InvalidPaymentMethod');
                return $this->response->redirectTo('amazon-checkout');
            }

            $basketItems = $this->basketService->getBasketItems();
            $this->helper->log(__CLASS__, __METHOD__, 'set basket items to session', $basketItems);
            $this->helper->setToSession('amzCheckoutBasket', $basketItems);
            $this->helper->log(__CLASS__, __METHOD__, 'set basket items to session - done', $basketItems);
        }
        return $this->response->redirectTo('place-order');
    }

    public function amazonCronAction()
    {
        return '';
    }


}
