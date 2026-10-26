<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Development users documented in the README.
     */
    public function run(): void
    {
        $users = [
            ['username' => 'lohgarra', 'password' => 'rwaaaaawr', 'author_pseudonym' => 'Lohgarra'],
            ['username' => 'chewbacca', 'password' => 'rrwwwgggh', 'author_pseudonym' => 'Chewbacca'],
            ['username' => 'darth_vader', 'password' => 'iwanttopublish', 'author_pseudonym' => 'Darth Vader'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                ['password' => $user['password'], 'author_pseudonym' => $user['author_pseudonym']],
            );
        }
    }
}
