<?php

namespace AmazonLoginAndPay\Controllers;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzCheckoutHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use AmazonLoginAndPay\Services\AmzBasketService;
use Plenty\Plugin\ConfigRepository;
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

    public function __construct(Response $response, AlkimAmazonLoginAndPayHelper $helper, AmzTransactionHelper $transactionHelper, AmzCheckoutHelper $checkoutHelper, AmzBasketService $basketService)
    {
        $this->response = $response;
        $this->helper = $helper;
        $this->transactionHelper = $transactionHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->basketService = $basketService;
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


    public function amazonLoginProcessingAction(Request $request, ConfigRepository $configRepository)
    {
        $this->configRepo = $configRepository;
        $userData = $this->transactionHelper->call('GetUserInfo', ['access_token' => $request->get('access_token')]);
        $this->helper->setToSession('amzUserData', $userData);
        $this->helper->setToSession('amzUserToken', $request->get('access_token'));
        /*TODO: decide where to go*/
        return $this->response->redirectTo('amazon-checkout');
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
