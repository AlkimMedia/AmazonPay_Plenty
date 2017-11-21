<?php

namespace AmazonLoginAndPay\Controllers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzCheckoutHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use AmazonLoginAndPay\Models\AmzTransaction;
use AmazonLoginAndPay\Services\AmzCustomerService;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Templates\Twig;

class AjaxController extends Controller
{
    public $response;
    public $request;
    public $helper;
    public $transactionHelper;
    public $checkoutHelper;
    public $amzTransactionRepo;
    public $customerService;

    public function __construct(AmzTransactionRepositoryContract $amzTransactionRepo, Response $response, AlkimAmazonLoginAndPayHelper $helper, AmzTransactionHelper $transactionHelper, Request $request, AmzCheckoutHelper $checkoutHelper, AmzCustomerService $customerService)
    {
        $this->response = $response;
        $this->request = $request;
        $this->helper = $helper;
        $this->transactionHelper = $transactionHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->amzTransactionRepo = $amzTransactionRepo;
        $this->customerService = $customerService;
    }

    /**
     * @param Twig $twig
     * @return string
     */

    public function handle(Twig $twig)
    {
        $action = $this->request->get('action');
        $this->helper->log(__CLASS__, __METHOD__, 'ajax handle action', ['action' => $action, 'orderReference' => $this->helper->getFromSession('amzOrderReference')]);
        switch ($action) {
            case 'setAccessToken':
                $this->helper->setToSession('amzUserToken', $this->request->get('access_token'));
                $redirect = '/amazon-checkout';
                $cookieStr = '';
                $header = $this->request->header();
                if (isset($header['cookie']) && is_array($header['cookie'])) {
                    $cookieStr = implode('', $header['cookie']);
                    if (strpos($cookieStr, 'amzLoginType=Login') !== false) {
                        $redirect = '/my-account';
                    }
                }
                $userData = $this->transactionHelper->call('GetUserInfo', ['access_token' => $this->request->get('access_token')]);
                $this->helper->setToSession('amzUserData', $userData);
                $this->helper->log(__CLASS__, __METHOD__, 'cookie test', [$cookieStr, $header['cookie'][0], json_decode(json_encode($this->request->header()), true), $this->request->header(), $this->request->header()->cookie, $cookieStr]);
                if ($this->request->get('do_login')) {
                    $loginInfo = $this->customerService->loginWithAmazonUserData($userData);
                    if (!empty($loginInfo["redirect"])) {
                        $redirect = $loginInfo["redirect"];
                    } elseif (!$loginInfo["success"]) {
                        $redirect = '/?amz_login_error';
                    }
                } else {
                    $this->customerService->loginWithAmazonUserData($userData, null, null, true);
                }
                $this->helper->setToSession('amazonLogout', 2);
                return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => json_encode(['redirect' => $redirect])]);
                break;
            case 'setOrderReference':
                $this->helper->setToSession('amzOrderReference', $this->request->get('orderReference'));
                return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => $this->helper->getFromSession('amzOrderReference')]);
                break;
            case 'getShippingList':
                $this->checkoutHelper->setAddresses();
                $templateData = ['shippingOptions' => [], 'basket' => []];
                try {
                    $templateData['shippingOptions'] = $this->checkoutHelper->getShippingOptionsList();
                    $templateData['basket'] = $this->checkoutHelper->getBasketData();
                } catch (\Exception $e) {
                    $this->helper->log(__CLASS__, __METHOD__, 'getShippingList data fetch failed', [$e, $e->getMessage()], true);
                }
                $this->helper->log(__CLASS__, __METHOD__, 'shipping list template data', $templateData);
                return $twig->render('AmazonLoginAndPay::snippets.shipping-list', $templateData);
                break;
            case 'setShippingProfileId':
                $this->checkoutHelper->setShippingProfile($this->request->get('id'));
            case 'getOrderDetails':
                $this->checkoutHelper->setAddresses();
                $templateData = ['items' => [], 'basket' => []];
                try {
                    $templateData['items'] = $this->checkoutHelper->getBasketItems();
                    $templateData['basket'] = $this->checkoutHelper->getBasketData();
                } catch (\Exception $e) {
                    $this->helper->log(__CLASS__, __METHOD__, 'getOrderDetails data fetch failed', [$e, $e->getMessage()], true);
                }
                $this->helper->log(__CLASS__, __METHOD__, 'basket list template data', $templateData);
                return $twig->render('AmazonLoginAndPay::snippets.order-details', $templateData);

        }
        return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => 'No action found']);
    }

    public function dataTest(Twig $twig)
    {
        $action = $this->request->get('action');
        if ($action == 'insert') {
            $data = $this->request->get('data');
            //$data = ['orderReference' => 'TEST'];
            $tx = $this->amzTransactionRepo->createTransaction($data);
            $this->helper->log(__CLASS__, __METHOD__, 'insert transaction', [$data, $tx]);
        }

        return $twig->render('AmazonLoginAndPay::snippets.data-test', ['transactions' => json_decode(json_encode($this->amzTransactionRepo->getTransactions([['id', '>=', 130]])), true)]);
    }

    public function cron(Twig $twig)
    {

        $pendingTransactions = $this->amzTransactionRepo->getTransactions([
            ['status', '=', 'Pending'],
            ['mode', '=', $this->helper->getTransactionMode()]
        ]);
        $this->helper->log(__CLASS__, __METHOD__, 'cron pending transactions', [$pendingTransactions]);
        foreach ($pendingTransactions as $pendingTransaction) {
            $this->transactionHelper->intelligentRefresh($pendingTransaction);
            sleep(1);
        }

        $pendingTransactions = $this->amzTransactionRepo->getTransactions([
            ['type', '=', 'auth'],
            ['mode', '=', $this->helper->getTransactionMode()]
        ]);
        $this->helper->log(__CLASS__, __METHOD__, 'auth transactions', [$pendingTransactions, $this->helper->getTransactionMode()]);
        foreach ($pendingTransactions as $pendingTransaction) {
            $this->transactionHelper->intelligentRefresh($pendingTransaction);
            sleep(1);
        }

        $openTransactions = $this->amzTransactionRepo->getTransactions([
            ['status', '!=', 'Canceled'],
            ['status', '!=', 'Closed'],
            ['status', '!=', 'Declined'],
            ['status', '!=', 'Completed'],
            ['mode', '=', $this->helper->getTransactionMode()]
        ]);
        $this->helper->log(__CLASS__, __METHOD__, 'cron open transactions', [$openTransactions]);
        foreach ($openTransactions as $openTransaction) {
            $this->transactionHelper->intelligentRefresh($openTransaction);
            sleep(1);
        }

        /*$q = "SELECT * FROM amz_transactions WHERE amz_tx_status != 'Canceled' AND amz_tx_status != 'Closed' AND amz_tx_status != 'Declined' AND amz_tx_status != 'Completed' AND amz_tx_mode = '".xtc_db_input(MODULE_PAYMENT_AM_APA_MODE)."' ORDER BY amz_tx_last_update ASC LIMIT 40";
        $rs = xtc_db_query($q);
        while($r = xtc_db_fetch_array($rs)){
            AlkimAmazonTransactions::intelligentRefresh($r);
            sleep(1.5);
        }
        echo 'COMPLETED';
        return 'COMPLETED';*/
        return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => 'COMPLETED']);
    }

    public function ipn(Twig $twig, Migrate $migrate)
    {
        $key = $this->request->get('key');
        //TODO: Check key
        $requestBody = $this->request->getContent();

        $requestData = json_decode($requestBody);
        $message = json_decode($requestData->Message);


        $responseXml =  simplexml_load_string($message->NotificationData);
        $this->helper->log(__CLASS__, __METHOD__, 'ipn data', [$message, $responseXml]);
        switch ($message->NotificationType) {

            case 'PaymentAuthorize':
                $transactions = $this->amzTransactionRepo->getTransactions([['amzId', '=', $responseXml->AuthorizationDetails->AmazonAuthorizationId]]);
                $this->helper->log(__CLASS__, __METHOD__, 'ipn - auth', [$transactions]);
                if (!empty($transactions)) {
                    $transaction = $transactions[0];
                    $this->transactionHelper->refreshAuthorization($transaction);
                }
                break;
            case 'OrderReferenceNotification':
                $transactions = $this->amzTransactionRepo->getTransactions([['amzId', '=', $responseXml->OrderReference->AmazonOrderReferenceId], ['type', '=', 'order_ref']]);
                if (!empty($transactions)) {
                    $transaction = $transactions[0];
                    $this->transactionHelper->refreshOrderReference($transaction);
                }
                break;
        }

        try {
            if ($key == 'delete') {
                $this->helper->log(__CLASS__, __METHOD__, 'delete', []);
                $migrate->deleteTable(AmzTransaction::class);
                $this->helper->log(__CLASS__, __METHOD__, 'delete done', []);
            }
            if ($key == 'create') {
                $this->helper->log(__CLASS__, __METHOD__, 'create', []);
                $migrate->createTable(AmzTransaction::class);
                $this->helper->log(__CLASS__, __METHOD__, 'create done', []);
            }
        } catch (\Exception $e) {
            $this->helper->log(__CLASS__, __METHOD__, 'migration exception', [$e, $e->getMessage()]);
        }
        $output = 'done';
        return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => $output]);
    }

}