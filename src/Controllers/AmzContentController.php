<?php

namespace AmazonLoginAndPay\Controllers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzCheckoutHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
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

    public function __construct(Response $response, AlkimAmazonLoginAndPayHelper $helper, AmzTransactionHelper $transactionHelper, AmzCheckoutHelper $checkoutHelper)
    {
        $this->response = $response;
        $this->helper = $helper;
        $this->transactionHelper = $transactionHelper;
        $this->checkoutHelper = $checkoutHelper;
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


    public function amazonLoginProcessingAction(Twig $twig, AmzTransactionRepositoryContract $amzTransactionRepository, Request $request, ConfigRepository $configRepository)
    {
        $this->configRepo = $configRepository;
        //$newTransaction = $amzTransactionRepository->createTransaction(['orderReference' => 'test']);
        //$callResult = AlkimAmazonLoginAndPayHelper::call('GetOrderReferenceDetails', ['amazon_order_reference_id' => 'whatever'], $libCall, $this->configRepo);
        $userData = $this->transactionHelper->call('GetUserInfo', ['access_token' => $request->get('access_token')]);
        $this->helper->setToSession('amzUserData', $userData);
        $this->helper->setToSession('amzUserToken', $request->get('access_token'));
        /*TODO: decide where to go*/
        return $this->response->redirectTo('amazon-checkout');
    }

    public function amazonCheckoutProceedAction(Twig $twig)
    {
        if ($this->helper->getFromConfig('submitOrderIds') != 'true') {
            $return = $this->checkoutHelper->doCheckoutActions();
            if (!empty($return["redirect"])) {
                return $this->response->redirectTo($return["redirect"]);
            }
        }
        return $this->response->redirectTo('place-order');
    }

    public function amazonCronAction()
    {
        return '';
    }


}
