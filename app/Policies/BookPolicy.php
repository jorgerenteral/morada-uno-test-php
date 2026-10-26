<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function view(User $user, Book $book): bool
    {
        return $this->owns($user, $book);
    }

    public function update(User $user, Book $book): bool
    {
        return $this->owns($user, $book);
    }

    public function delete(User $user, Book $book): bool
    {
        return $this->owns($user, $book);
    }

    private function owns(User $user, Book $book): bool
    {
        return $book->author_id === $user->id;
    }
}
