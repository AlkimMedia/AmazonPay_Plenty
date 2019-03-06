<?php

namespace AmazonLoginAndPay\Controllers;

use AmazonLoginAndPay\Contracts\AmzTransactionRepositoryContract;
use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Helpers\AmzCheckoutHelper;
use AmazonLoginAndPay\Helpers\AmzTransactionHelper;
use AmazonLoginAndPay\Models\AmzTransaction;
use AmazonLoginAndPay\Services\AmzCustomerService;
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
            case 'setComment':
                $this->helper->setToSession('orderContactWish', $this->request->get('comment'));
                return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => 'success']);
                break;
            case 'setShippingProfileId':
                $this->checkoutHelper->setShippingProfile($this->request->get('id'));
            case 'getOrderDetails':
                $this->checkoutHelper->setAddresses();
                $templateData = ['items' => [], 'basket' => []];
                $this->helper->log(__CLASS__, __METHOD__, 'startGetOrderDetails', []);
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
        return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => 'COMPLETED']);
    }

    public function ipn(Twig $twig, AmzTransactionRepositoryContract $repository)
    {
        $key = $this->request->get('key');
        //TODO: Check key
        $requestBody = $this->request->getContent();

        $requestData = json_decode($requestBody);
        $message = json_decode($requestData->Message);

        $responseXml = simplexml_load_string($message->NotificationData);
        $this->helper->log(__CLASS__, __METHOD__, 'ipn data', [$message, $responseXml]);
        switch ($message->NotificationType) {
            case 'PaymentAuthorize':
                $transactions = $this->amzTransactionRepo->getTransactions([['amzId', '=', $responseXml->AuthorizationDetails->AmazonAuthorizationId]]);
                $this->helper->log(__CLASS__, __METHOD__, 'ipn - auth', [$transactions]);
                if (empty($transactions)) {
                    /** @var AmzTransaction $transaction */
                    $transaction = pluginApp(AmzTransaction::class);
                    $transaction->amzId = $responseXml->AuthorizationDetails->AmazonAuthorizationId;
                    $transaction->orderReference = $this->transactionHelper->getOrderRefFromAmzId($transaction->amzId);
                    $transaction->type = 'auth';
                    if($orderReferenceTransactions = $this->amzTransactionRepo->getTransactions([['amzId', '=', $transaction], ['type', '=', 'order_ref']])){
                        $orderReferenceTransaction = $orderReferenceTransactions[0];
                        if($orderReferenceTransaction->order){
                            $transaction->order = $orderReferenceTransaction->order;
                        }
                    }

                    $transaction = $repository->saveTransaction($transaction);
                } else {
                    $transaction = $transactions[0];
                }
                $this->transactionHelper->refreshAuthorization($transaction, empty($transaction->status));
                break;
            case 'OrderReferenceNotification':
                $transactions = $this->amzTransactionRepo->getTransactions([['amzId', '=', $responseXml->OrderReference->AmazonOrderReferenceId], ['type', '=', 'order_ref']]);
                $this->helper->log(__CLASS__, __METHOD__, 'ipn - order reference', [$transactions]);
                if (empty($transactions)) {
                    /** @var AmzTransaction $transaction */
                    $transaction = pluginApp(AmzTransaction::class);
                    $transaction->orderReference = $responseXml->OrderReference->AmazonOrderReferenceId;
                    $transaction->type = 'order_ref';
                    $transaction = $repository->saveTransaction($transaction);
                } else {
                    $transaction = $transactions[0];
                }

                $this->transactionHelper->refreshOrderReference($transaction, empty($transaction->status));
                break;
        }

        $output = 'done';
        return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => $output]);
    }

    public function shopwareConnect(Twig $twig, AmzTransactionRepositoryContract $repository)
    {
        $key = $this->request->get('key');
        $response = ['success' => true];
        $this->helper->log(__CLASS__, __METHOD__, 'shopware connect start', [$this->request->getContent(), $this->request->all()]);
        if (!empty($key) && $key === $this->helper->getFromConfig('amzShopwareConnectorKey')) {
            $orderId = $this->request->get('order_id');
            $orderReferenceId = $this->request->get('order_reference_id');
            if (!empty($orderId) && !empty($orderReferenceId)) {
                $transactions = $repository->getTransactions([['orderReference', '=', $orderReferenceId]]);
                $orderReferenceObjectExists = false;
                if (!empty($transactions)) {
                    foreach ($transactions as $transaction) {
                        if ($transaction->type === 'order_ref') {
                            $orderReferenceObjectExists = true;
                        }
                        if (empty($transaction->order)) {
                            $transaction->order = (int)$orderId;
                            if ($transaction->type === 'auth') {
                                if(empty($transaction->paymentId)) {
                                    $this->transactionHelper->doAuthorizationPaymentAction($transaction);
                                }else{
                                    $this->helper->log(__CLASS__, __METHOD__, 'shopware connector - detected payment', [$transaction->paymentId, $orderId]);
                                    if ($payment = $this->helper->paymentRepository->getPaymentById($transaction->paymentId)) {
                                        $this->helper->log(__CLASS__, __METHOD__, 'shopware connector - assign payment', [$payment, $orderId]);
                                        if(!$this->helper->assignPlentyPaymentToPlentyOrder($payment, $orderId)){
                                            $this->transactionHelper->doAuthorizationPaymentAction($transaction);
                                        }
                                    }
                                }
                            }
                            $repository->updateTransaction($transaction);
                        }
                    }
                }
                if(!$orderReferenceObjectExists){
                    /** @var AmzTransaction $transaction */
                    $transaction = pluginApp(AmzTransaction::class);
                    $transaction->order = (int)$orderId;
                    $transaction->orderReference = $orderReferenceId;
                    $transaction->type = 'order_ref';
                    $transaction = $repository->saveTransaction($transaction);
                    $this->transactionHelper->refreshOrderReference($transaction, true);
                }
            } else {
                $response = ['success' => false, 'error' => 'data missing'];
            }
        } else {
            $response = ['success' => false, 'error' => 'wrong key'];
        }
        $this->response->json($response, 200, ['Content-Type: application/json']);
        return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => json_encode($response)]);

    }

    public function getTable(Twig $twig, AmzTransactionRepositoryContract $repository)
    {
        $transactions = $repository->getTransactions([]);
        $html = '<table>';
        foreach ($transactions as $transaction) {
            $html .= '<tr>';
            foreach ($transaction as $k => $v) {
                $html .= '<td>' . $v . '</td>';
            }
            $html .= '</tr>';
        }
        return $twig->render('AmazonLoginAndPay::content.custom-output', ['output' => $html]);
    }

}