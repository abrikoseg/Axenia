<?php
require_once('configs/axenia/config.php');
require_once('Telegram.php');
/**
 * Page for payment. Used for Qiwi
 */
$paid = $_REQUEST;
$bot_id = BOT_TOKEN;
$log_chat_id = LOG_CHAT_ID;

if ($paid['status'] == 'paid') {
    $telegram = new Telegram($bot_id);
    $redis = new Redis();
    $bill_id = $paid['bill_id'];
    $amount = $paid['amount'];
    $user = $paid['user'];


    function redis_error($error)
    {
        throw new error($error);
    }

    $redis->connect('127.0.0.1', 6379);
    if ($redis->hExists('bills', $bill_id . "_u")) {
        $payer = $redis->hGet('bills', $bill_id . "_u");
        $donate = $redis->hGet('bills', $bill_id . "_n");
        $balance = $redis->hGet('cookies', $payer);
        $balance += $donate;
        $telegram->sendMessage([
            'chat_id' => $log_chat_id,
            'text' => "💰 For " . $donate . " 🍪 user " . $payer . " pay " . $amount . " RUB. User's balance is " . $balance
        ]);
        $telegram->sendMessage([
            'chat_id' => $payer,
            'text' => "Спасибо за поддержку. С меня " .  $donate . " 🍪."
        ]);
        $redis->hSet('cookies', $payer, $balance);
        $redis->hDel('bills', $bill_id . "_u");
        $redis->hDel('bills', $bill_id . "_n");
    } else {
        $telegram->sendMessage([
            'chat_id' => $log_chat_id,
            'text' => "💰 Something went wrong. " . $user . " paid " . $amount . " RUB, but I can't found the bill. Bill " . $bill_id
        ]);
    }
    $redis->close();
}

header('Content-Type: text/xml');
$xmlres = <<<XML
<?xml version="1.0"?>
<result>
<result_code>0</result_code>
</result>
XML;
echo $xmlres;
