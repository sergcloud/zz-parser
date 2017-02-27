<?php
class Post
{
    public $pid;
    public $authorId;
    public $authorName;
    public $ipAddress = "::1";
    public $postDate;
    public $post;
    public $topicId;
    public $newTopic = 0;

    public static function db($post)
    {
        $sql = "INSERT INTO forums_posts (pid, author_id, `author_name`, ip_address, post_date, post, topic_id, new_topic) VALUES (
          {$post->pid},
          '{$post->authorId}',
          '{$post->authorName}',
          '{$post->ipAddress}',
          '{$post->postDate}',
          '{$post->post}',
          '{$post->topicId}',
          '{$post->newTopic}'
        )";

        try {
            $db = new DB();
            $conn = $db->get();
            $statement = $conn->prepare($sql);
            $statement->execute();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    }
}