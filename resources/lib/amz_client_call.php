<?php
use AmazonPay\Client;

$firstTime = microtime(true);
try {
    $config     = SdkRestApi::getParam('config');
    $action     = SdkRestApi::getParam('action');
    $parameters = SdkRestApi::getParam('parameters');
    $config     = json_decode(json_encode($config), true);
    $client     = new Client($config);
    $startTime  = microtime(true);
    if ($action == 'GetUserInfo') {
        $return = $client->getUserInfo($parameters["access_token"]);
    } else {
        $return = $client->{$action}($parameters)->toArray();
    }
    $endTime                   = microtime(true);
    $duration                  = $endTime - $startTime;
    $readable                  = print_r($return, true);
    $return["action"]          = $action;
    $return["call_duration"]   = $duration;
    $return["parameters"]      = print_r($parameters, true);
    $return["readable_result"] = $readable;
    /*$return = [
        'test' => 'test',
        'config' => $config
    ];*/
} catch (Exception $e) {
    $return = [
        'exception' => [
            'object'  => $e,
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile()
        ]
    ];
}
$return['start_time']  = $firstTime;
$return['return_time'] = microtime(true);

return $return;