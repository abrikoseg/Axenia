<?php

class BotDao extends AbstractDao
{

// region -------------------- Users

    public function getUserID($username)
    {
        $username = "'" . (isset($username) ? $this->escape_mimic($username) : '') . "'";
        $username = strtolower(str_ireplace("@", "", $username));
        $res = $this->select("SELECT id FROM Users WHERE lower(username)=$username");

        return (!$res[0]) ? false : $res[0];
    }

    public function insertOrUpdateUser($user)
    {
        $user_id = $user['id'];

        $username = Util::wrapQuotes(isset($user['username']) ? $this->escape_mimic($user['username']) : '');
        $firstname = Util::wrapQuotes(isset($user['first_name']) ? $this->escape_mimic($user['first_name']) : '');
        $lastname = Util::wrapQuotes(isset($user['last_name']) ? $this->escape_mimic($user['last_name']) : '');
        $query = "
            INSERT INTO Users (id, username, firstname, lastname, date_added) 
            VALUES ($user_id,$username,$firstname,$lastname, now()) 
            ON DUPLICATE KEY UPDATE username=$username, firstname=$firstname, lastname=$lastname, last_updated=now()
        ";
        
        return $this->insert($query);
    }


    public function getUser($id)
    {
        $res = $this->select("SELECT username,firstname,lastname FROM Users WHERE id=" . $id);
        return (!$res[0] && !$res[1] && !$res[2]) ? false : ['username'=> $res[0], 'first_name'=> $res[1],'last_name'=> $res[2]];
    }

    public function getUsersIDByUsername($username)
    {
        $username = "'" . (isset($username) ? $this->escape_mimic($username) : '') . "'";
        $username = strtolower(str_ireplace("@", "", $username));
        $res = $this->select("SELECT u.id, k.chat_id FROM Users u, Karma k WHERE k.user_id=u.id and lower(u.username)=$username");

        return (!$res[0]) ? false : $res;
    }

    public function getUserName($id)
    {
        $res = $this->select("SELECT username,firstname,lastname FROM Users WHERE id=" . $id);

        return (!$res[0]) ? $res[1] : $res[0];
    }

    public function getUsersByName($query, $limit = 0)
    {
        if ($query != '') {
            $query = "'" . strtolower("%" . $query . "%") . "'";
            $res = $this->select(
                "SELECT id,firstname,lastname,username
                  FROM Users 
                  WHERE concat(username,firstname,lastname) LIKE $query LIMIT ".$limit
            );

            return $res;
        }

        return false;
    }

    public function getRights($user_id){
        $q="select * from Rights where user_id=".$user_id;
        $res = $this->select($q);
        return $res;
    }


    public function isHidden($user_id){
        $res = $this->select("SELECT hidden FROM Users WHERE id=" . $user_id);
        return (is_null($res[0])) ? null : $res[0];
    }

    public function setHidden($user_id,$value){
        return $this->update("UPDATE Users SET hidden = ".$value." WHERE id = ".$user_id);
    }
//endregion

// region -------------------- Lang

    public function getChatLang($chat_id)
    {
        $res = $this->select("SELECT lang FROM Chats WHERE id = " . $chat_id, true);
        if (isset($res[0])) {
            return !($res[0]) ? false : $res[0];
        }

        return false;
    }


    public function getSilentMode($chat_id)
    {
        $res = $this->select("SELECT silent_mode FROM Chats WHERE id = " . $chat_id, true);
        if (isset($res[0])) {
            return ($res[0] == 1) ? true : false;
        } else return false;
    }

    public function setSilentMode($chat_id, $mode)
    {
        return $this->update("UPDATE Chats SET silent_mode = " . (($mode) ? 1 : 0) . " WHERE id=" . $chat_id);
    }

    public function getUserLang($user_id)
    {
        $res = $this->select("SELECT lang FROM Users WHERE id = " . $user_id);
        return !($res[0]) ? false : $res[0];
    }

    public function setChatLang($chat_id, $lang)
    {
        return $this->update("UPDATE Chats SET lang = '" . $lang . "' WHERE id = " . $chat_id);
    }

    public function setUserLang($user_id, $lang)
    {
        return $this->update("UPDATE Users SET lang = '" . $lang . "' WHERE id = " . $user_id);
    }


//endregion

// region -------------------- Chats

    public function getChatsIds()
    {
        $res = $this->select("SELECT id FROM Chats");
        return (!$res) ? false : $res;
    }

    public function getShowcase()
    {
        $res = $this->select("SELECT title,price,censor FROM Showcase");
        return (!$res) ? false : $res;
    }

    public function getCooldown($chat_id)
    {
        $res = $this->select(
            "SELECT cooldown
            FROM Chats 
            WHERE id=" . $chat_id
        );
        return ($res[0]===NULL) ? DEFAULT_COOLDOWN : $res[0];
    }

    public function getGrowth($chat_id)
    {
        $res = $this->select(
            "SELECT ariphmeticGrowth
            FROM Chats 
            WHERE id=" . $chat_id
        );
        return ($res[0]===NULL) ? 0 : $res[0];
    }

    public function getAccess($chat_id)
    {
        $res = $this->select(
            "SELECT forAdmin
            FROM Chats 
            WHERE id=" . $chat_id
        );
        return ($res[0]===NULL) ? 0 : $res[0];
    }

    public function getShowcaseStatus($chat_id)
    {
        $res = $this->select(
            "SELECT showcase
            FROM Chats 
            WHERE id=" . $chat_id
        );
        return ($res[0]===NULL) ? 1 : $res[0];
    }

    public function setGrowth($chat_id, $value)
    {
        return $this->update("UPDATE Chats SET ariphmeticGrowth = " . $value . " WHERE id = " . $chat_id);
    }

    public function setAccess($chat_id, $value)
    {
        return $this->update("UPDATE Chats SET forAdmin = " . $value . " WHERE id = " . $chat_id);
    }

    public function setShowcase($chat_id, $value)
    {
        return $this->update("UPDATE Chats SET showcase = " . $value . " WHERE id = " . $chat_id);
    }

    public function setCooldown($chat_id,$cooldown)
    {
        return $this->update("UPDATE Chats SET cooldown = " . $cooldown . " WHERE id = " . $chat_id);
    }


    public function insertOrUpdateChat($chat_id, $title, $username)
    {
        $title = $this->clearForInsert($title);
        $username = $this->clearForInsert($username);

        $query = "
            INSERT INTO Chats(id, title,username, date_add, date_remove) 
            VALUES($chat_id, $title,$username, now(), null) 
            ON DUPLICATE KEY UPDATE title = $title,username=$username, date_remove=null
        ";

        return $this->insert($query);
    }

    public function deleteChat($chat_id)
    {
        $query = "DELETE FROM Chats WHERE id = " . $chat_id;

        return $this->delete($query);
    }

    public function getUserGroups($user_id)
    {
        $res = $this->select(
            "SELECT c.title, c.username, c.id 
            FROM Chats c, Karma k 
            WHERE k.chat_id=c.id AND k.user_id=" . $user_id . " and c.isPresented=1
            ORDER BY c.title"
        );
        return (!$res[2]) ? false : $res;
    }

    public function getMembersCount($chat_id)
    {
        $res = $this->select(
            "SELECT count(1) 
            FROM Karma k 
            WHERE k.chat_id=" . $chat_id
        );

        return (!$res[0]) ? 0 : $res[0];
    }

    public function getGroupName($chat_id)
    {
        $res = $this->select("SELECT title FROM Chats WHERE id = " . intval($chat_id));

        return (!$res[0]) ? false : htmlspecialchars($res[0]);
    }

    public function getGroupsByName($query, $limit = 0)
    {
        if ($query != '') {
            $query = "'" . strtolower("%" . $query . "%") . "'";
            $res = $this->select(
                "SELECT id,title
                  FROM Chats 
                  WHERE title LIKE $query LIMIT ".$limit
            );

            return $res;
        }

        return false;
    }

    public function getGroupsMistakes()
    {
        $res = $this->select(
            "SELECT DISTINCT k.chat_id 
            FROM Karma k 
            WHERE NOT(k.last_updated IS NULL) AND k.chat_id NOT IN (SELECT id FROM Chats)"
        );
        $temp = $this->select(
            "SELECT DISTINCT k.chat_id, c.title 
            FROM Chats c,Karma k 
            WHERE NOT(k.last_updated IS NULL) AND k.chat_id=c.id AND (c.title='')"
        );
        $res = array_merge($res, $temp);

        return (!$res) ? false : $res;
    }

    public function setPresented($chat_id, $isPresented)
    {
        return $this->update("UPDATE Chats SET isPresented = " . (($isPresented) ? 1 : 0) . ", date_remove = ".(($isPresented) ? "null" : "now()")." WHERE id=" . $chat_id);
    }

    public function changeChatIdIn($newChatId, $oldChatId)
    {
        $query = "UPDATE Chats SET id=" . $newChatId . " WHERE id = " . $oldChatId;

        return $this->update($query);
    }

//endregion

// region -------------------- Admins

    //TODO прибрать


//endregion

//region -------------------- Karma

    public function getTop($chat_id, $limit = 5)
    {
        $query = "
        SELECT u . username, u . firstname, u . lastname, k . level 
        FROM Karma k, Users u 
        WHERE k . user_id = u . id AND k . level <> 0 AND k . chat_id = " . $chat_id . "
        ORDER BY level 
        DESC LIMIT " . $limit;

        return $this->select($query);
    }

    /**
     * получить уровень кармы пользователя из чата
     * @param $user_id
     * @param $chat_id
     * @return mixed
     */
    public function getUserLevel($user_id, $chat_id)
    {
        $query = "SELECT level FROM Karma WHERE user_id = " . $user_id . " AND chat_id = " . $chat_id;
        $res = $this->select($query);

        return $res;
    }

    /**
     * Добавляет запись с уровня кармы пользователя в чате.
     * Если пользователь уже имеется с каким то левелом то левел обновится из параметра $level
     * @param $user_id
     * @param $chat_id
     * @param $level
     * @return mixed
     */
    public function setUserLevel($user_id, $chat_id, $level)
    {
        //$query = "INSERT INTO `Karma` SET `user_id` = " . $user_id . ",`chat_id` = " . $chat_id . ",`level` = " . $level . " ON DUPLICATE KEY UPDATE `level` = " . $level . ", `last_updated`=now()";

        $user_id = $this->clearForInsert($user_id);
        $chat_id = $this->clearForInsert($chat_id);
        $level = $this->clearForInsert($level);

        $query = "
            INSERT INTO Karma (user_id, chat_id, level)
            VALUES ($user_id,$chat_id,$level)
            ON DUPLICATE KEY UPDATE level = " . $level . ", last_updated=now()
        ";

        return $this->insert($query);
    }

    public function setLastTimeVote($from_id,$chat_id){
        $query = "
            UPDATE Karma set last_time_voted=now(), toofast_showed=0
            WHERE user_id=" . $from_id . " and chat_id=".$chat_id;
        return $this->update($query);
    }

    public function setTooFastShowed($from_id,$chat_id){
        $query = "
            UPDATE Karma set toofast_showed=1
            WHERE user_id=" . $from_id . " and chat_id=".$chat_id;
        return $this->update($query);
    }

    public function getTooFastShowed($from_id,$chat_id){
        $res = $this->select("
            SELECT toofast_showed
            FROM Karma
            WHERE user_id=" . $from_id . " AND chat_id=" . $chat_id
        );
        return ($res[0] == null) ? false : $res[0];
    }

    public function checkCooldown($from_id,$chat_id){
        $res = $this->select("select now()-last_time_voted from Karma
            WHERE user_id=" . $from_id . " and chat_id=".$chat_id);
        return (!$res[0]) ? false : $res[0];
    }

    public function isCooldown($from_id, $chat_id)    {
        $res = $this->select("
            SELECT if(last_time_voted IS NOT NULL, ((now()- last_time_voted) < (cooldown*60)), 0) isCoolDown 
            FROM Karma
            LEFT JOIN Chats ON Karma.chat_id = Chats.id
            WHERE user_id=" . $from_id . " AND chat_id=" . $chat_id
        );

        return (count($res) == 0 || $res[0] == null) ? false : $res[0];
    }

    public function SumKarma($user_id)
    {
        $res = $this->select("SELECT sum(level) FROM Karma WHERE user_id=" . $user_id);

        return (!$res[0]) ? 0 : $res[0];
    }

    public function UsersPlace($user_id)
    {
        $res = $this->select(
            "SELECT count(a.Sumlevel) 
              FROM 
              (   SELECT sum(level) AS SumLevel 
                  FROM Karma k 
                  GROUP BY k.user_id) a,
              (   SELECT sum(level) AS SumLevel 
                  FROM Karma 
                  WHERE user_id=" . $user_id . ") u 
                WHERE u.SumLevel<=a.SumLevel 
                ORDER BY a.SumLevel ASC;"
        );

        return (!$res[0]) ? false : $res[0];
    }

    public function getAllKarmaPair()
    {
        $res = $this->select("SELECT k.user_id, k.chat_id FROM Karma k");

        return (!$res[0]) ? false : $res;
    }

    public function deleteUserKarmaInChat($userId, $chatId)
    {
        $query = "DELETE FROM Karma WHERE user_id = " . $userId . " AND chat_id = " . $chatId;

        return $this->delete($query);
    }

    public function deleteAllKarmaInChat($chatId)
    {
        $query = "DELETE FROM Karma WHERE chat_id = " . $chatId;

        return $this->delete($query);
    }

    public function changeChatIdInKarma($newChatId, $oldChatId)
    {
        $query = "UPDATE Karma SET chat_id=" . $newChatId . " WHERE chat_id = " . $oldChatId;

        return $this->update($query);
    }


//endregion

//region -------------------- Rewards
/*
    public function getUserRewardIds($user_id, $chat_id)
    {
        $res = $this->select("SELECT type_id FROM Rewards WHERE user_id = " . $user_id . " AND group_id = " . $chat_id);
        return (!$res[0]) ? [] : $res;
    }

    public function getUserRewardsInChat($user_id, $chat_id)
    {
        $res = $this->select(
            "SELECT r.type_id AS type_id, rt.code AS code 
            FROM Rewards r 
            LEFT JOIN Reward_Type rt ON r.type_id=rt.id 
            WHERE r.user_id = " . $user_id . " AND r.group_id = " . $chat_id
        );
        if ($res !== false) {
            return $res;
        }

        return array();
    }

    public function getUserRewards($user_id)
    {
        $res = $this->select(
            "select rt.code,count(1) Count 
            from Rewards r, Reward_Type rt 
            where r.type_id=rt.id and r.user_id=$user_id 
            group by rt.code"
        );

        return $res;
    }

    public function getRewardTypes($types_array)
    {
        if (is_array($types_array) && count($types_array) > 0) {
            $types_array = join(',', $types_array);
            $res = $this->select("SELECT id, code, karma_min FROM Reward_Type WHERE id in ($types_array)", false);
            if ($res !== false) {
                return $res;
            }
        }

        return array();
    }

    public function insertReward($type_id, $desc, $user_id, $chat_id)
    {
        $user_id = $this->clearForInsert($user_id);
        $desc = $this->clearForInsert($desc);

        return $this->insert(
            "INSERT INTO Rewards(type_id, user_id, group_id, description) 
            VALUES($type_id, $user_id, $chat_id , $desc)"
        );
    }

    public function deleteUserRewardsInChat($userId, $chatId)
    {
        $query = "DELETE FROM Rewards WHERE user_id = " . $userId . " AND group_id = " . $chatId;

        return $this->delete($query);
    }

    public function deleteAllRewardsInChat($chatId)
    {
        $query = "DELETE FROM Rewards WHERE group_id = " . $chatId;

        return $this->delete($query);
    }

    public function changeChatIdInRewards($newChatId, $oldChatId)
    {
        $query = "UPDATE Rewards SET group_id=" . $newChatId . " WHERE group_id = " . $oldChatId;

        return $this->update($query);
    }
*/
//endregion

// region -------------------- Donate

    public function getDonateButtons()
    {
        $res = $this->select("SELECT id, nominal, price FROM Donates", false);
        if ($res !== false) {
            return $res;
        }
        return array();
    }

    public function getPrice($codename)
    {
        $res = $this->select("SELECT price FROM Prices where codename='".$codename."'");
        return (!$res[0]) ? 0 : $res[0];
    }

    
//endregion
}


?>