<?php

class BotService
{

    private $db;
    private $r;
    /**
     * Axenia constructor.
     * @param $db BotDao
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->r=new BotRedis();
    }

    /**
     * Handle exceptions
     * TODO optimized this, need not only message but inline and etc
     */
    public function handleException(Exception $e, $update)
    {
        $this->db->disconnect();
        if (defined('LOG_CHAT_ID')) {
            $message = $update["message"];
            $chat = $message['chat'];
            $from = $message['from'];
            $errorMsg = "<b>Caught Exception!</b>\n";
            $temp = "On message of user :uName [<i>:uid</i>] in group ':cName' [<i>:cid</i>]\n";
            $errorMsg .= Util::insert($temp,
                array('uid' => $from['id'],
                    'uName' => Util::getFullNameUser($from),
                    'cid' => $chat['id'],
                    'cName' => $this->isPrivate($chat) ? Util::getFullNameUser($chat) : $chat['title'])
            );
            $errorMsg .= Util::insert("<b>Error message:</b> <code>:0</code>\n<i>Error description:</i>\n<pre>:1</pre>", array($e->getMessage(), $e));
            Request::sendHtmlMessage(LOG_CHAT_ID, $errorMsg);
        } else {
            throw $e;
        }
    }


    public function debug($text)
    {
        if (defined('LOG_CHAT_ID')) {
            Request::sendMessage(LOG_CHAT_ID, print_r($text,true));
        }
    }

// region -------------------- Users

    public function getUserID($username)
    {
        $this->checkUsernames($username);
        return $this->db->getUserID($username);
    }



    //todo
    public function insertOrUpdateUser($user)
    {
        return $this->db->insertOrUpdateUser($user);
    }

    public function rememberUser($user)
    {
        return $this->db->insertOrUpdateUser($user);
    }

    public function getUserName($id)
    {
        return $this->db->getUserName($id);
    }

    public function getUser($user_id)
    {
        return $this->db->getUser($user_id);
    }

    public function getUserList($query)
    {
        $users = $this->db->getUsersByName($query, 10);
        if ($users != false) {
            $a = array_chunk($users, 4);
            $stack = array();
            foreach ($a as $user) {
                $userObj = ["id" => $user[0], "first_name" => $user[1],"last_name" => $user[2],"username" => $user[3]];
                $userTitle = Util::getFullNameUser($userObj);
                $text = Lang::message("user.stat.inline", array("user" => $userTitle));
                array_push($stack, array('type' => 'article', 'id' => uniqid(), 'title' => $text, 'message_text' => $this->getStats($userObj), 'parse_mode' => 'HTML', 'disable_web_page_preview' => true));
            }

            return $stack;
        }

        return false;
    }

    public function getStats($from, $chat_id = NULL)
    {
        $from_id = $from['id'];
        $res =
            Lang::message("user.stat.title") . "\r\n\r\n" .
            Lang::message("user.stat.name") . Util::getFullNameUser($from) . "\r\n" .
            ($chat_id == NULL ? "" : (Lang::message("user.stat.inchat") . $this->getUserLevel($from_id, $chat_id) . "\r\n")) .
            Lang::message("user.stat.sum") . round($this->db->SumKarma($from_id), 0) . "\r\n";
        if(!$this->db->isHidden($from_id)) $res.=Lang::message("user.stat.place") . $this->db->UsersPlace($from_id) . "\r\n";
        if(!$this->db->isHidden($from_id)) $res.=Lang::message("user.stat.membership") . implode(", ", $this->getUserGroup($from_id)) . "\r\n";
        $res.=Lang::message("user.stat.cookies") . $this->r->getDonates($from_id) . "\r\n";
        /*if ($a = $this->getAllUserRewards($from_id)) {
            $res .= Lang::message("user.stat.rewards") . implode(", ", $a);
        }*/

        return $res;
    }

    public function getUserGroup($id,$links=true)
    {

        if ($a = $this->db->getUserGroups($id)) {
            $a = array_chunk($a, 3);
            $stack = array();
            foreach ($a as $value) {
                if($links){
                    array_push($stack, (empty($value[1])) ? htmlspecialchars($value[0]) : "<a href='https://t.me/" . $value[1] . "'>" . htmlspecialchars($value[0]) . "</a>");
                }else{
                    array_push($stack, $value[2].":".htmlspecialchars($value[0]));
                }
            }

            return $stack;
        }

        return false;
    }

    public function isHidden($user_id){
        return $this->db->isHidden($user_id);
    }

    public function toggleHidden($user_id){
        $value=($this->db->isHidden($user_id))?0:1;
        return $this->db->setHidden($user_id,$value);
    }

    public function isUsernameEndBot($username)
    {
        return Util::endsWith(strtolower($username), 'bot');
    }

    public function isUserBot($user)
    {
        return $user["is_bot"];
    }

    public function CheckRights($user_id,$type)
    {
        return $this->db->GetRights($user_id)[$type];
    }



    public function checkUsernames($username){
        foreach(array_chunk($this->db->getUsersIDByUsername($username),2) as $i){
            $ChatMember=Request::getChatMember($i[0],$i[1]);
            $this->db->insertOrUpdateUser($ChatMember['user']);
        }
    }
//endregion

// region -------------------- Admins
    public function isAdminInChat($user_id, $chat)
    {
        if ($this->isPrivate($chat)) return true;
        $admins = Request::getChatAdministrators($chat['id']);
        foreach ($admins as $admin) {
            if ($admin['user']['id'] == $user_id) return true;
        }

        return false;
    }

    public function isSilentMode($chat_id)
    {
        return $this->db->getSilentMode($chat_id);
    }

    public function toggleSilentMode($chat_id)
    {
        return $this->db->setSilentMode($chat_id, !$this->db->getSilentMode($chat_id));
    }


//endregion

// region -------------------- Lang

    /*
     * Type of chat, can be either “private”, “group”, “supergroup” or “channel”
     */
    public function getLang($chat)
    {
        $chat_id = $chat['id'];
        if ($this->isPrivate($chat)) {
            return $this->db->getUserLang($chat_id);
        } elseif ($this->isGroup($chat)) {
            return $this->db->getChatLang($chat_id);
        }

        return false;
    }

    public function setLang($chat, $lang)
    {
        $chat_id = $chat['id'];
        if ($this->isPrivate($chat)) {
            return $this->db->setUserLang($chat_id, $lang);
        } elseif ($this->isGroup($chat)) {
            return $this->db->setChatLang($chat_id, $lang);
        }

        return false;
    }


    public function initLang($chat)
    {
        $isNewChat = false;
        $lang = $this->getLang($chat);
        if ($lang === false) {
            $lang = Lang::defaultLangKey();
            $isNewChat = true;
        }
        Lang::init($lang);

        return $isNewChat;
    }

//endregion

// region -------------------- Chats
    public function isGroup($chat)
    {
        return Util::isInEnum("group,supergroup", $chat['type']);
    }

    public function getShowcase()
    {
        return $this->db->getShowcase();
    }

    public function isPrivate($chat)
    {
        return $chat['type'] == "private";
    }

    public function getCooldown($chat_id)
    {
        return $this->db->getCooldown($chat_id);
    }

    public function getGrowth($chat_id)
    {
        return $this->db->getGrowth($chat_id);
    }

    public function getAccess($chat_id)
    {
        return $this->db->getAccess($chat_id);
    }

    public function getShowcaseStatus($chat_id)
    {
        return $this->db->getShowcaseStatus($chat_id);
    }

    public function setCooldown($chat_id, $cooldown)
    {
        return $this->db->setCooldown($chat_id, $cooldown);
    }

    public function switchGrowth($chat_id)
    {
        return $this->db->setGrowth($chat_id, ($this->db->getGrowth($chat_id)+1)%2);
    }

    public function switchAccess($chat_id)
    {
        return $this->db->setAccess($chat_id, ($this->db->getAccess($chat_id)+1)%2);
    }

    public function switchShowcase($chat_id)
    {
        return $this->db->setShowcase($chat_id, ($this->db->getShowcaseStatus($chat_id)+1)%2);
    }

    public function getChatsIds()
    {
        return $this->db->getChatsIds();
    }

    public function rememberChat($chat, $adder_id = null)
    {
        if ($this->isGroup($chat)) {
            $chat_id = $chat['id'];
            $title = $chat['title'];
            $username = $chat['username'];
            $res = $this->db->insertOrUpdateChat($chat_id, $title, $username);
            if ($this->db->getChatLang($chat_id) === false) {
                $lang = Lang::defaultLangKey();
                if ($adder_id != null) {
                    $lang = $this->db->getUserLang($adder_id); //получение языка добавителя
                    if ($lang === false) {
                        $lang = Lang::defaultLangKey();
                    }
                }
                $this->db->setChatLang($chat_id, $lang);
                Lang::init($lang);
            }

            return $res;
        }

        return false;
    }

    public function getChatMembersCount($chat_id)
    {
        return $this->db->getMembersCount($chat_id);
    }

    public function deleteChat($chat_id)
    {
        if ($this->db->deleteChat($chat_id)) {
            if ($this->db->deleteAllKarmaInChat($chat_id)) {
                //if ($this->db->deleteAllRewardsInChat($chat_id)) {
                    return true;
                //}
            }
        }

        return false;
    }

    public function deleteUserDataInChat($user_id, $chat_id)
    {
        if ($this->db->deleteUserKarmaInChat($user_id, $chat_id)) {
            //if ($this->db->deleteUserRewardsInChat($user_id, $chat_id)) {
                return true;
            //}
        }

        return false;
    }


    public function getGroupName($chat_id)
    {
        return $this->db->getGroupName($chat_id);
    }

    public function getGroupsMistakes()
    {
        return $this->db->getGroupsMistakes();
    }

    public function setBotPresentedInChat($chat_id, $isPresented)
    {
        return $this->db->setPresented($chat_id, $isPresented);
    }

    public function migrateToNewChatId($newChatId, $oldChatId)
    {
        //$rewards = $this->db->changeChatIdInRewards($newChatId, $oldChatId);
        $karmas = $this->db->changeChatIdInKarma($newChatId, $oldChatId);
        $chats = $this->db->changeChatIdIn($newChatId, $oldChatId);

        return $chats && $karmas /*&& $rewards*/;
    }


//endregion

//region -------------------- Karma

    public function getTop($chat_id, $limit = 10)
    {
        $out = Lang::message('karma.top.title', ["chatName" => $this->db->getGroupName($chat_id)]);
        $top = $this->db->getTop($chat_id, $limit);
        $a = array_chunk($top, 4);
        $i = 0;
        foreach ($a as $value) {
            $username = ($value[0] == "") ? $value[1] . " " . $value[2] : "<a href='tg://resolve?domain=$value[0]'>$value[0]</a>";
            $out .= Lang::message('karma.top.'.($i ==0 ? "firstrow": "row"), ["username" => $username, "karma" => $value[3]]);
            $i++;
        }
        $out .= Lang::message('karma.top.footer', ["pathToSite" => PATH_TO_SITE, "chatId" => $chat_id]);

        return $out;
    }

    public function setLevelByUsername($username, $chat_id, $newLevel)
    {
        $user_id = $this->db->getUserID($username);
        if ($user_id !== false) {
            if ($this->db->setUserLevel($user_id, $chat_id, $newLevel)) {
                return Lang::message('karma.manualSet', [$username, $user_id, $chat_id, $newLevel]);
            }
        }

        return Lang::message('bot.error');
    }

    public function setLevel($user_id, $chat_id, $newLevel)
    {
        return $this->db->setUserLevel($user_id, $chat_id, $newLevel);
    }

    public function getUserLevel($from_id, $chat_id)
    {
        $fromLevelResult = $this->db->getUserLevel($from_id, $chat_id);
        if (!$fromLevelResult[0]) {
            $this->db->setUserLevel($from_id, $chat_id, 0);

            return 0;
        } else {
            return $fromLevelResult[0];
        }
    }

    public function getAllKarmaPair()
    {
        $pairs = $this->db->getAllKarmaPair();
        if ($pairs !== false) {
            return array_chunk($pairs, 2);
        }

        return false;
    }

    private function createHandleKarmaResult($good, $msg, $level)
    {
        return array('good' => $good, 'msg' => $msg, 'newLevel' => $level);
    }

    public function checkConditions($from_id, $chat){
        // TODO Время учитывается только если будет удачное голосование, если не удачное то кулдуан не срабаывает. НАдо подумать
        $chat_id = $chat['id'];
        if ($this->isGroup($chat)){
            if($this->getAccess($chat_id)==1 && !$this->isAdminInChat($from_id,$chat)){
                return false;
            }
            if($this->db->isCooldown($from_id, $chat_id)) {
                if(!$this->db->getTooFastShowed($from_id, $chat_id)) {
                    $this->initLang($chat);
                    Request::sendHtmlMessage($chat_id, Lang::message('karma.tooFast'));
                    $this->db->setTooFastShowed($from_id, $chat_id);
                }
                return false;
            }
        }
        return true;
    }

    public function handleKarma($isRise, $from, $to, $chat_id)
    {
        $newLevel = null;
        if ($from == $to) return $this->createHandleKarmaResult(true, Lang::message('karma.yourself'), $newLevel);

        $fromLevel = $this->getUserLevel($from, $chat_id);

        if ($fromLevel < 0) return $this->createHandleKarmaResult(true, Lang::message('karma.tooSmallKarma'), $newLevel);

        $userFrom = $this->getUserName($from);
        $fromLevelSqrt = $fromLevel == 0 ? 1 : ($this->db->getGrowth($chat_id)==1)?1:sqrt($fromLevel);
        $toLevel = $this->getUserLevel($to, $chat_id);

        $newLevel = number_format($toLevel + ($isRise ? $fromLevelSqrt : -$fromLevelSqrt),1, '.', '');

        $userTo = $this->getUserName($to);
        


        $res = $this->db->setUserLevel($to, $chat_id, $newLevel);
        if ($res) {
            $mod = $isRise ? 'karma.plus' : 'karma.minus';
            $msg = Lang::message($mod, array('from' => $userFrom, 'k1' => $fromLevel, 'to' => $userTo, 'k2' => $newLevel));
            $this->db->setLastTimeVote($from,$chat_id);
            return $this->createHandleKarmaResult(true, $msg, $newLevel);
        }

        return $this->createHandleKarmaResult(false, Lang::message('bot.error'), null);
    }


    public function handleKarmaFromBot($isRise, $user_id, $chat_id)
    {
        $user2 = $this->getUserName($user_id);

        if ($user2) {
            $toLevel = $this->getUserLevel($user_id, $chat_id);

            $newLevel = $isRise ? $toLevel + 1 : $toLevel - (($toLevel > 0 && $toLevel <= 1) ? 0.1 : 1);

            $res = $this->db->setUserLevel($user_id, $chat_id, $newLevel);
            if ($res) {
                $mod = $isRise ? 'karma.plus' : 'karma.minus';
                $msg = Lang::message($mod, array('from' => Lang::message('bot.name'), 'k1' => '∞', 'to' => $user2, 'k2' => $newLevel));

                return $this->createHandleKarmaResult(true, $msg, $newLevel);
            }

            return $this->createHandleKarmaResult(false, Lang::message('bot.error'), null);
        } else {
            return $this->createHandleKarmaResult(false, Lang::message('karma.unknownUser'), null);
        }
    }


//    public function deleteUserDataForChat($userId, $chatId)
//    {
//        if($this->db->deleteUserKarmaInChat($userId, $chatId)){
//            if($this->db->deleteUserRewardsInChat($userId, $chatId)){
//                return true;
//            }
//        }
//        return false;
//    }

//endregion

//region -------------------- Rewards
/*
    public function handleRewards($currentCarma, $chat_id, $user_id)
    {
        $out = [];
        $oldRewards = $this->db->getUserRewardIds($user_id, $chat_id);

        $newRewards = [];
        if ($currentCarma >= 200) {
            array_push($newRewards, 2);
        }
        if ($currentCarma >= 500) {
            array_push($newRewards, 3);
        }
        if ($currentCarma >= 1000) {
            array_push($newRewards, 4);
        }

        $newRewards = array_diff($newRewards, $oldRewards);

        if (count($newRewards) > 0) {
            $groupName = $this->getGroupName($chat_id);
            $rewardTypes = $this->db->getRewardTypes($newRewards);
            $username = $this->getUserName($user_id);
            foreach ($rewardTypes as $type) {
                $desc = Lang::messageRu('reward.type.karma.desc', [$groupName, $type['karma_min']]);
                //$this->r->insertReward($user_id,$type['id']);
                $insertRes = $this->db->insertReward($type['id'], $desc, $user_id, $chat_id);
                if ($insertRes !== false) {
                    $msg = Lang::message('reward.new', ['user' => $username, 'path' => PATH_TO_SITE, 'user_id' => $user_id, 'title' => Lang::message('reward.type.' . $type['code'])]);
                    array_push($out, $msg);
                }
            }
        }

        return $out;
    }
*/
    /**
     * Формирует сообщения для отпарки по команде rewards
     * @param $user_id
     * @param $chat_id
     */
   /* public function getUserRewards($user_id, $chat_id)
    {
        if ($user_id != $chat_id) {
            $res = $this->db->getUserRewardsInChat($user_id, $chat_id);

        } else {
            $res = $this->db->getUserRewards($user_id);
        }

        if (count($res) > 0) {
        } else {
        }

    }

    public function getAllUserRewards($user_id)
    {
        $res = $this->db->getUserRewards($user_id);
        if ($res) {
            $stack = array();
            foreach (array_chunk($res, 2) as $a) {
                $text = Lang::message("reward.type." . $a[0]);
                if ($a[1] > 1) $text .= "<b> x" . $a[1] . "</b>";
                array_push($stack, $text);
            }

            return $stack;
        }

        return false;

    }
*/
//endregion

// region -------------------- Donate
    public function showDonateMenu($from_id){
        $button_list=[];
        foreach($this->getDonateButtons() as $a){
            array_push($button_list, ['text' => Lang::message('donate.price', ['k' => $a['nominal'], 'r' => $a['price']]), 'callback_data' => 'donate_'.$a['id']]);
        }
        $text=Lang::message("donate.title");
        Request::sendHtmlMessage($from_id, $text, ["reply_markup" => ['inline_keyboard' => array_chunk($button_list,2)]]);
    }

    public function getDonateButtons()
    {
        return $this->db->getDonateButtons();
    }


    public function getPrice($codename)
    {
        return $this->db->getPrice($codename);
    }
//endregion
}

?>