<?php


namespace App\Model;


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

    public function selectAllWithLanguage(): array
    {
        return $this->pdo->query('SELECT *, (post.like - post. dislike) as popularity FROM ' . $this->table . ' LEFT JOIN language ON post.language_id = language.id;')->fetchAll();
    }

    public function selectPostsOrderedBy($orderedBy): array
    {
        return $this->pdo->query('SELECT *, (post.like - post. dislike) as popularity FROM ' . $this->table . ' LEFT JOIN language ON post.language_id = language.id ORDER BY ' . $orderedBy . ' DESC;')->fetchAll();
    }

    public function postByLanguage($id): array
    {
        return $this->pdo->query('SELECT *, (post.like - post. dislike) as popularity FROM ' . $this->table . ' LEFT JOIN language ON post.language_id = language.id WHERE language.id=' . $id . ';')->fetchAll();
    }

    public function selectAllMyFavoris($user): array
    {
        return $this->pdo->query('SELECT *, (post.like - post. dislike) as popularity FROM ' . $this->table . ' LEFT JOIN favorite ON post.id = favorite.post_id WHERE favorite.user_id=' . $user . ';')->fetchAll();
    }

    public function selectAllMyPosts($user): array
    {
        return $this->pdo->query('SELECT *, (post.like - post. dislike) as popularity FROM ' . $this->table . ' WHERE post.user_id=' . $user . ';')->fetchAll();
    }

}