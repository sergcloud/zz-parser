<?php
class Topic
{
    public $tid;
    public $titleSeo;
    public $title;
    public $author;
    public $lastPoster;
    public $startDate;
    public $lastRealPost;
    public $posts;
    public $views;
    public $state = "open";

    public function toDB($forumId)
    {
        $sql = "INSERT INTO forums_topics (tid, title, state, posts, starter_id, start_date, last_poster_id, last_post, starter_name, last_poster_name, views, forum_id, approved, title_seo, last_real_post) VALUES (
          {$this->tid},
          '{$this->title}',
          '{$this->state}',
          '{$this->posts}',
          '{$this->author->memberId}',
          '{$this->startDate}',
          '{$this->lastPoster->memberId}',
          '0',
          '{$this->author->name}',
          '{$this->lastPoster->name}',
          '{$this->views}',
          '{$forumId}',
          1,
          '{$this->titleSeo}',
          '{$this->lastRealPost}'
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
}
