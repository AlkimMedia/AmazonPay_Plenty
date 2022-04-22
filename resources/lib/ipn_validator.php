<?php
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;


$isValid = true;
$error = null;
try {
    $message = Message::fromJsonString(SdkRestApi::getParam('messageBody'));
    $validator = new MessageValidator();
    $validator->validate($message);
}catch (Exception $e){
    $isValid = false;
    $error = $e->getMessage();
}


return [
    'error'=>$error,
    'isValid'=>$isValid
];