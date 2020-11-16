<?php


namespace App\Model;

use Michelf\MarkdownExtra;


class PostManager extends AbstractManager
{
    const TABLE = 'post';

    /**
     *  Initializes this class.
     */
    public function __construct()
    {
        parent::__construct(self::TABLE);
    }

    private function cleanPosts($posts)
    {
        $cleanPost = [];
        foreach ($posts as $post) {
            $post['content'] = MarkdownExtra::defaultTransform($post['content']);
            $cleanPost[] = $post;
        }
        return $cleanPost;
    }

    public function selectAllWithLanguage(): array
    {
        $posts = $this->pdo->query('SELECT *, post.id as post_unique_id, (post.nbOfLikes - post.nbOfDislikes) as popularity FROM ' . $this->table . ' LEFT JOIN language ON post.language_id = language.id;')->fetchAll();
        return $this->cleanPosts($posts);
    }

    public function selectPostsOrderedBy($orderedBy): array
    {
        $posts = $this->pdo->query('SELECT *, post.id as post_unique_id, (post.nbOfLikes - post.nbOfDislikes) as popularity FROM ' . $this->table . ' LEFT JOIN language ON post.language_id = language.id ORDER BY ' . $orderedBy . ' DESC;')->fetchAll();
        return $this->cleanPosts($posts);
    }

    public function postByLanguage($id): array
    {
        $posts = $this->pdo->query('SELECT *, post.id as post_unique_id,(post.nbOfLikes - post.nbOfDislikes) as popularity FROM ' . $this->table . ' LEFT JOIN language ON post.language_id = language.id WHERE language.id=' . $id . ';')->fetchAll();
        return $this->cleanPosts($posts);
    }

    public function selectAllMyFavorites($user): array
    {
        $posts = $this->pdo->query('SELECT *, (post.nbOfLikes - post.nbOfDislikes) as popularity FROM ' . $this->table . ' LEFT JOIN favorite ON post.id = favorite.post_id WHERE favorite.user_id=' . $user . ';')->fetchAll();
        return $this->cleanPosts($posts);
    }

    public function selectAllMyPosts($user): array
    {
        $posts = $this->pdo->query('SELECT *, (post.nbOflikes - post. nbOfdislikes) as popularity FROM ' . $this->table . ' WHERE post.user_id=' . $user . ';')->fetchAll();
        return $this->cleanPosts($posts);
    }

    public function createPost($user, $title, $content, $language): void
    {
        $statement = $this->pdo->prepare("INSERT INTO " . self::TABLE . " (user_id, title, content, language_id, creation_at) VALUES (:user, :title, :content, :language, now())");
        $statement->bindValue('user', $user, \PDO::PARAM_INT);
        $statement->bindValue('title', $title, \PDO::PARAM_STR);
        $statement->bindValue('content', $content, \PDO::PARAM_STR);
        $statement->bindValue('language', $language, \PDO::PARAM_INT);
        $statement->execute();
    }

    public function postByKeyword($keyword): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table . ' LEFT JOIN language ON post.language_id = language.id WHERE title LIKE :keyword ;');
        $statement->bindValue(':keyword', '%' . $keyword . '%', \PDO::PARAM_STR);
        $statement->execute();
        $posts = $statement->fetchAll();
        return $this->cleanPosts($posts);
    }

    public function addFavorite($postid, $userid)
    {
        $existOrNot = $this->pdo->prepare('SELECT * FROM favorite where post_id = :postid and user_id = :userid');
        $existOrNot->bindValue('postid', $postid, \PDO::PARAM_INT);
        $existOrNot->bindValue('userid', $userid, \PDO::PARAM_INT);
        $existOrNot->execute();
        $checkIfExist = $existOrNot->fetchAll();
        if (empty($checkIfExist)) {
                $statement = $this->pdo->prepare("INSERT INTO favorite (`post_id`, `user_id`) 
                VALUES (:postid, :userid)");
                $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
                $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
                $statement->execute();
                return (int)$this->pdo->lastInsertId();
        } else {
                $statement = $this->pdo->prepare("DELETE FROM favorite WHERE post_id = $postid AND user_id = $userid");
                $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
                $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
                $statement->execute();


        }
    }

    public function isInApproval($postid, $userid)
    {
        $statement = $this->pdo->prepare('SELECT * FROM approval where post_id = :postid and user_id = :userid');
        $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
        $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
        $statement->execute();
        $currentApproval = $statement->fetchAll();
        if (empty($currentApproval)) {
            $currentApproval = false;
        } else {
            $currentApproval = true;
        }
        return $currentApproval;
    }

    public function isLike($postid, $userid)
    {
        $statement = $this->pdo->prepare('SELECT up FROM approval where post_id = :postid and user_id = :userid');
        $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
        $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
        $statement->execute();
        $currentLike = $statement->fetch();
        return $currentLike['up'];
    }

    public function isDislike($postid, $userid)
    {
        $statement = $this->pdo->prepare('SELECT down FROM approval where post_id = :postid and user_id = :userid');
        $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
        $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
        $statement->execute();
        $currentDislike = $statement->fetch();
        return $currentDislike['down'];
    }

    public function changeLike($postid, $userid)
    {
        if (!$this->isInApproval($postid, $userid)) {
            $statement = $this->pdo->prepare("INSERT INTO approval (`user_id`, `post_id`, `up`,`down`) VALUES (:userid, :postid, true, false)");
            $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
            $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
            $statement->execute();
        } else {
            if ($this->isLike($postid, $userid) === '0') {
                $statement = $this->pdo->prepare("UPDATE approval SET up = true WHERE user_id=:userid and post_id = :postid");
                $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
                $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
                $statement->execute();
            } else {
                $statement = $this->pdo->prepare("UPDATE approval SET up = false WHERE user_id=:userid and post_id = :postid");
                $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
                $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
                $statement->execute();
            }
            if ($this->isDislike($postid, $userid) === '1') {
                $statement = $this->pdo->prepare("UPDATE approval SET down = false WHERE user_id=:userid and post_id = :postid");
                $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
                $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
                $statement->execute();
            }
        }
    }

    public function changeDislike($postid, $userid)
    {
        if (!$this->isInApproval($postid, $userid)) {
            $statement = $this->pdo->prepare("INSERT INTO approval (`user_id`, `post_id`, `up`,`down`) VALUES (:userid, :postid, false, true)");
            $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
            $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
            $statement->execute();
        } else {
            if ($this->isDislike($postid, $userid) === '0') {
                $statement = $this->pdo->prepare("UPDATE approval SET down = true WHERE user_id=:userid and post_id = :postid");
                $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
                $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
                $statement->execute();
            } else {
                $statement = $this->pdo->prepare("UPDATE approval SET down = false WHERE user_id=:userid and post_id = :postid");
                $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
                $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
                $statement->execute();
            }
            if ($this->isLike($postid, $userid) === '1') {
                $statement = $this->pdo->prepare("UPDATE approval SET up = false WHERE user_id=:userid and post_id = :postid");
                $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
                $statement->bindValue('postid', $postid, \PDO::PARAM_INT);
                $statement->execute();
            }
        }
    }

    public function selectAllLikesAndDislikesPerUser($userid)
    {
        $statement = $this->pdo->prepare("SELECT post_id, up, down FROM approval WHERE user_id = :userid;");
        $statement->bindValue('userid', $userid, \PDO::PARAM_INT);
        $statement->execute();
        $currentLikesAndDislikes = $statement->fetchAll();
        $CleanCurrentLikesAndDislikes = [];
        foreach ($currentLikesAndDislikes as $currentLikeAndDislike) {
            $CleanCurrentLikesAndDislikes[$currentLikeAndDislike['post_id']] =
            [$currentLikeAndDislike['up'], $currentLikeAndDislike['down']];
        };
        return $CleanCurrentLikesAndDislikes;
    }

    public function updateNbOfLikes($postid, $userid)
    {
        if ($this->isLike($postid, $userid) === '0') {
            $statement = $this->pdo->query("UPDATE post SET nbOfLikes = nbOfLikes + 1 WHERE id = $postid;");
            $statement->execute();
            if ($this->isDislike($postid, $userid) === '1') {
                $statement = $this->pdo->query("UPDATE post SET nbOfDislikes = nbOfDislikes - 1 WHERE id = $postid;");
                $statement->execute();
            }
        } else {
            $statement = $this->pdo->query("UPDATE post SET nbOfLikes = nbOfLikes - 1 WHERE id = $postid;");
            $statement->execute();
        }
    }


    public function updateNbOfDislikes($postid, $userid)
    {
        if ($this->isDislike($postid, $userid) === '0') {
            $statement = $this->pdo->query("UPDATE post SET nbOfDislikes = nbOfDislikes + 1 WHERE id = $postid;");
            $statement->execute();
            if ($this->isLike($postid, $userid) === '1') {
                $statement = $this->pdo->query("UPDATE post SET nbOfLikes = nbOfLikes - 1 WHERE id = $postid;");
                $statement->execute();
            }
        } else {
            $statement = $this->pdo->query("UPDATE post SET nbOfDislikes = nbOfDislikes - 1 WHERE id = $postid;");
            $statement->execute();
        }
    }

/* Ben
    public function getAllPopularities()
    {
        $statement = $this->pdo->prepare('SELECT post.id, (post.nbOfLikes - post.nbOfDislikes) as popularity 
        FROM ' . $this->table . ';');
        $statement->execute();
        $allPopularities = $statement->fetchAll();
        $CleanAllPopularities = [];
        foreach ($allPopularities as $popularity) {
            $CleanAllPopularities[$popularity['id']] = $popularity['popularity'];
        };
        return $CleanAllPopularities;
    }
*/
}



