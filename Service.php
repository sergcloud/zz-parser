<?php
class Service
{
    public function getPosts($start)
    {
        $sql = "SELECT tid, title_seo FROM forums_topics WHERE tid > $start ORDER BY tid LIMIT 500";

        try {
            $db = new DB();
            $conn = $db->get();

            $urls = array();
            // Если пользователь есть в бд, то выходим
            foreach ($conn->query($sql) as $row) {
                $urls[] = "https://www.zonazakona.ru/forum/topic/{$row['tid']}-{$row['title_seo']}/";
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

        if (!isset($urls)) {
            exit("Ошибка 21\r\n");
        }

        $mh = curl_multi_init();

        $requests = array();

        $options = array(
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_HEADER         => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true
        );

        foreach ($urls as $key => $url) {
            $requests[$key] = curl_init($url);

            curl_setopt_array($requests[$key], $options);

            curl_multi_add_handle($mh, $requests[$key]);
        }

        do {
            curl_multi_exec($mh, $active);
        } while ($active > 0);

        $pages = array();
        foreach ($requests as $key => $request) {
            $pages[$key] = curl_multi_getcontent($request);
            curl_multi_remove_handle($mh, $request);
            curl_close($request);
        }

        curl_multi_close($mh);

        if (!isset($pages)) {
            exit("Ошибка 22\r\n");
        }

        foreach ($pages as $page) {
            $posts = array();
            preg_match_all("#<article.+?</article>#s", $page, $matches);

            if (!isset($matches[0])) {
                exit("Ошибка 23\r\n");
            }

            for ($i = 0; $i < count($matches[0]); $i++) {
                $post = new Post();

                if ($i == 0) {
                    $post->newTopic = 1;
                }

                preg_match("#<div id='comment-([0-9]+)_wrap#", $matches[0][$i], $match);

                if (!isset($match[1])) {
                    exit("Ошибка 24\r\n");
                }

                $post->pid = $match[1];

                preg_match("#<div data-role='commentContent'.*?>(.+?)<div data-controller='core.front.core.reputation'#s", $matches[0][$i], $match);

                if (!isset($match[1])) {
                    exit("Ошибка 25\r\n");
                }

                $match[1] = trim($match[1]);

                preg_match("#(.+?)</div>$#s", $match[1], $match);

                if (!isset($match[1])) {
                    exit("Ошибка 26\r\n");
                }

                $post->post = $match[1];

                preg_match("#<a href='https://www.zonazakona.ru/forum/profile/([0-9]+)-(.+?)/'#s", $matches[0][$i], $match);

                if (!isset($match[1]) || !isset($match[2])) {
                    exit("Ошибка 27\r\n");
                }

                $post->authorId = $match[1];
                $post->authorName = $match[2];

                $author = new User();
                $author->memberId = $post->authorId;
                $author->membersSeoName = $post->authorName;
                $author->name = $post->authorName;
                User::db($author);

                preg_match("#<a href='https://www.zonazakona.ru/forum/topic/([0-9]+)#s", $matches[0][$i], $match);

                if (!isset($match[1])) {
                    exit("Ошибка 28\r\n");
                }

                $post->topicId = $match[1];

                preg_match("#<time.+?title='(.+?)'#s", $matches[0][$i], $match);

                if (!isset($match[1])) {
                    exit("Ошибка 29\r\n");
                }

                $post->postDate = Service::dtStrToArr(trim($match[1]));

                $posts[] = $post;

                $a = 1;
            }

            foreach ($posts as $post) {
                Post::db($post);
            }

            $a = 1;
        }
    }

    public function getTopicNames($pageLink, $endPage, $forumId)
    {
        $pageTopicLinks = $this->getPageTopicLinks($pageLink, $endPage);

        foreach ($pageTopicLinks as $value) {
            preg_match_all("#<li class=\"ipsDataItem.+?</ul>.*?<ul class='ipsDataItem_lastPoster.+?</ul>#s", $value, $matches);

            if (!isset($matches[0])) {
                exit("Ошибка 2. Class Servive\r\n");
            }

            $htmlTopicLinks = $matches[0];

            $topics = array();
            foreach ($htmlTopicLinks as $htmlTopic) {
                $topic = new Topic();

                preg_match("#<a href='https://www.zonazakona.ru/forum/topic/([0-9]+)-(.+?)/'#", $htmlTopic, $match);

                if (!$match[1] || !$match[2]) {
                    exit("Ошибка 3\r\n");
                }

                $topic->tid = $match[1];
                $topic->titleSeo = $match[2];

                preg_match("#<span itemprop=\"name headline\">(.+?)</span>#s", $htmlTopic, $match);

                if (!$match[1]) {
                    exit("Ошибка 4\r\n");
                }

                $topic->title = trim($match[1]);

                preg_match("#<span itemprop='name'>.*?<a.+?</span>#s", $htmlTopic, $match);
                if (!isset($match[0])) {
                    // значит гость
                } else {
                    // значит зарегистрированный пользователь
                    preg_match("#<span itemprop='name'>.*?<a href='https://www.zonazakona.ru/forum/profile/([0-9]+)-(.+?)/'.+?>(.+?)</a>.*?</span>#s", $htmlTopic, $match);

                    if (!isset($match[1]) || !isset($match[2]) || !isset($match[3])) {
                        print_r($topic);
                        exit("Ошибка 5\r\n");
                    }

                    $author = new User();
                    $author->memberId = $match[1];
                    $author->membersSeoName = $match[2];
                    $author->name = $match[3];

                    $topic->author = $author;
                }

                preg_match_all("#<time.+?title='(.+?)'.*?#s", $htmlTopic, $matches);

                if (!isset($matches[1][0]) || !isset($matches[1][1])) {
                    exit("Ошибка 6\r\n");
                }

                $topic->startDate = Service::dtStrToArr(trim($matches[1][0]));
                $topic->lastRealPost = Service::dtStrToArr(trim($matches[1][1]));

                preg_match_all("#<span class='ipsDataItem_stats_number'.*?>(.*?)</span>#s", $htmlTopic, $matches);

                if (!isset($matches[1][0]) || !isset($matches[1][1])) {
                    exit("Ошибка 7\r\n");
                }

                $topic->posts = $matches[1][0];
                $topic->views = str_replace(" ", "", $matches[1][1]);

                preg_match("#<ul class='ipsDataItem_lastPoster.+?<a.+?<a href='https://www.zonazakona.ru/forum/profile/([0-9]+)-(.+?)/.*?>(.+?)</a>#s", $htmlTopic, $match);

                if (!isset($match[1]) || !isset($match[2]) || !isset($match[3])) {
                    exit("Ошибка 8\r\n");
                }

                $lastPoster = new User();
                $lastPoster->memberId = $match[1];
                $lastPoster->membersSeoName = $match[2];
                $lastPoster->name = $match[3];
                $topic->lastPoster = $lastPoster;

                $topics[] = $topic;
            }

            if (!isset($topics)) {
                exit("Ошибка 9\r\n");
            }

            foreach ($topics as $topic) {
                User::db($topic->author);
                User::db($topic->lastPoster);
                $topic->toDB($forumId);
            }

            $a = 1;
        }

        $a = 1;
    }

    private function getPageTopicLinks($pageLink, $endPage)
    {
        $urls = array();
        for ($i = 1; $i <= $endPage; $i++) {
            if ($i == 1) {
                $urls[$i] = $pageLink;
            } else {
                $urls[$i] = $pageLink . "?page=$i";
            }
        }

        $mh = curl_multi_init();

        $requests = array();

        $options = array(
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_HEADER         => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true
        );

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

        if (!isset($returned)) {
            exit("Ошибка 1. Class Service. Method getPageTopicLinks");
        }

        echo "Получено страниц: " . count($returned) . "\r\n";

        return $returned;
    }

    public static function dtStrToArr($stStr)
    {
        $stStr = trim($stStr);
        $arrDatetime = explode(" ", $stStr);

        if (count($arrDatetime != 2)) {

        }

        $date = $arrDatetime[0];
        $time = $arrDatetime[1];

        $arrDate = explode(".", $date);

        if (count($arrDate) != 3) {

        }

        $arrReturn = array();

        $arrReturn['day'] = $arrDate[0];
        $arrReturn['month'] = $arrDate[1];
        $arrReturn['year'] = $arrDate[2];

        $arrTime = explode(":", $time);

        if (count($arrTime != 2)) {

        }

        $arrReturn['hour'] = $arrTime[0];
        $arrReturn['minute'] = $arrTime[1];

        return mktime($arrReturn['hour'], $arrReturn['minute'], 0, $arrReturn['month'], $arrReturn['day'], $arrReturn['year']);
    }
}
