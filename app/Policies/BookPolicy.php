<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    /**
     * Whether the user may leave the book published. Checked on top of `update`.
     */
    public function publish(User $user, Book $book): Response
    {
        return $user->isDarthVader()
            ? Response::deny('Darth Vader is not allowed to publish on Wookie Books')
            : Response::allow();
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
