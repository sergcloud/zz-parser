<?php
class User
{
    public $memberId;
    public $name;
    public $memberGroupId = 3;
    public $email = "undefined@qq.ru";
    public $ipAddress = "::1";
    public $membersSeoName;
    public $joined;
    public $lastVisit;
    public $lastActivity;

    static public function db($user)
    {
        $sql = "SELECT member_id FROM core_members WHERE member_id = {$user->memberId}";

        try {
            $db = new DB();
            $conn = $db->get();

            // Если пользователь есть в бд, то выходим
            foreach ($conn->query($sql) as $row) {
                if (isset($row)) {
                    return true;
                }
            }

            $page = file_get_contents("https://www.zonazakona.ru/forum/profile/{$user->memberId}-{$user->membersSeoName}/");

            if (!isset($page)) {
                exit("Ошибка 10\r\n");
            }

            preg_match("#<div id='elProfileStats'.+?<time datetime=.+?title='(.+?)'.*?<time datetime=.+?title='(.+?)'#s", $page, $match);

            if (!isset($match[1]) || !isset($match[2])) {
                exit("Ошибка 11\r\n");
            }

            $user->joined = Service::dtStrToArr(trim($match[1]));
            $user->lastVisit = Service::dtStrToArr(trim($match[2]));
            $user->lastActivity = $user->lastVisit;

            User::toDB($user);

            $a = 1;
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    }

    static public function toDB($user)
    {
        $sql = "INSERT INTO core_members (member_id, `name`, member_group_id, email, joined, ip_address, last_visit, last_activity, members_seo_name) VALUES (
          {$user->memberId},
          '{$user->name}',
          '{$user->memberGroupId}',
          '{$user->email}',
          '{$user->joined}',
          '{$user->ipAddress}',
          '{$user->lastVisit}',
          '{$user->lastActivity}',
          '{$user->membersSeoName}'
        )";

        try {
            $db = new DB();
            $conn = $db->get();
            $statement = $conn->prepare($sql);
            $statement->execute();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

        $a = 1;
    }

    public static function getAvatars($start, $interval)
    {
        $startTime = microtime(true);

        $sql = "SELECT member_id, members_seo_name FROM core_members ORDER BY member_id";

        $members = array();

        try {
            $db = new DB();
            $conn = $db->get();

            $b = 0;
            $i = 0;
            foreach ($conn->query($sql) as $row) {
                if ($i < 3) {
                    $i++;
                    continue;
                }

                $members[$i]['member_id'] = $row['member_id'];
                $members[$i]['members_seo_name'] = $row['members_seo_name'];

                if ($members[$i]['member_id'] == 12205) {
                    $b = $i;
                }

                $i++;
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

        $start = 21000;
        $interval = 1000;
        $fl = false;
        while (true) {
            if ($fl) {
                break;
            }

            $urls = array();
            for ($i = $start; $i < $start + $interval; $i++) {
                if (isset($members[$i])) {
                    $urls[$i] = "https://www.zonazakona.ru/forum/profile/{$members[$i]['member_id']}-{$members[$i]['members_seo_name']}/";
                }

                if ($i == count($members) - 1) {
                    $fl = true;
                    echo "ВСЕ ВЫКАЧАНО!!!\r\n";
                    break;
                }
            }
            $start += $interval;

            $mh = curl_multi_init();

            $requests = array();

            $options = array(
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_RETURNTRANSFER => true
            );

            $folder = 'content/';
            if (!file_exists($folder)) {
                mkdir($folder);
            }

            foreach ($urls as $key => $url) {
                $requests[$key] = curl_init($url);

                curl_setopt_array($requests[$key], $options);

                curl_multi_add_handle($mh, $requests[$key]);
            }

            do {
                curl_multi_exec($mh, $active);
            } while ($active > 0);

            $returned = array();
            foreach ($requests as $key => $request) {

                $returned[$key] = curl_multi_getcontent($request);
                curl_multi_remove_handle($mh, $request);
                curl_close($request);
            }

            curl_multi_close($mh);

            foreach ($returned as $key => $value) {
                preg_match("#<div.*?id='elProfilePhoto'>.*?<a href=\"https://www.zonazakona.ru/forum/(uploads/.+?)\".*?<img src='https://www.zonazakona.ru/forum/(uploads/.+?)'#s", $value, $match);

                if (!isset($match[1]) || !isset($match[2])) {
                    continue;
                }

                $members[$key]['pp_main_photo'] = $match[1];
                $members[$key]['pp_thumb_photo'] = $match[2];
            }

            foreach ($members as $key => $value) {
                if (!isset($value['pp_main_photo']) && !isset($value['pp_thumb_photo'])) {
                    continue;
                }

                $sql = "UPDATE `core_members` SET
              `pp_main_photo` = '{$value['pp_main_photo']}',
              `pp_thumb_photo` = '{$value['pp_thumb_photo']}',
              `pp_photo_type` = 'custom'
              WHERE member_id = {$value['member_id']} 
              ";

                try {
                    $db = new DB();
                    $conn = $db->get();
                    $statement = $conn->prepare($sql);
                    $statement->execute();
                } catch (PDOException $e) {
                    echo $e->getMessage();
                }

                preg_match("#uploads/(.+?)/(.+)#", $value['pp_main_photo'], $match);

                if (!file_exists("C:/xampp/htdocs/ipb4/uploads/{$match[1]}/")) {
                    mkdir("C:/xampp/htdocs/ipb4/uploads/{$match[1]}/");
                }

                file_put_contents("C:/xampp/htdocs/ipb4/uploads/{$match[1]}/{$match[2]}", file_get_contents("https://www.zonazakona.ru/forum/" . $value['pp_main_photo']));

                preg_match("#uploads/(.+?)/(.+)#", $value['pp_thumb_photo'], $match);

                if (!file_exists("C:/xampp/htdocs/ipb4/uploads/{$match[1]}/")) {
                    mkdir("C:/xampp/htdocs/ipb4/uploads/{$match[1]}/{$match[2]}");
                }

                file_put_contents("C:/xampp/htdocs/ipb4/uploads/{$match[1]}/{$match[2]}", file_get_contents("https://www.zonazakona.ru/forum/" . $value['pp_thumb_photo']));
            }

            break;
        }

        echo 'Время выполнения скрипта: ' . (microtime(true) - $startTime) . ' сек.';
    }
}