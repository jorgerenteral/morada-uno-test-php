<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Development books with fixed ids, so reviewers can check the endpoints
     * against the expectations listed in the README.
     */
    public function run(): void
    {
        $lohgarra = User::where('username', 'lohgarra')->firstOrFail();
        $chewbacca = User::where('username', 'chewbacca')->firstOrFail();

        $books = [
            [
                'id' => 1,
                'author_id' => $lohgarra->id,
                'title' => 'Adventures on Kashyyyk',
                'description' => 'A thrilling tale of life among the wroshyr trees, told by someone who has seen it all.',
                'cover_image_url' => 'https://example.com/covers/adventures-on-kashyyyk.jpg',
                'price' => '9.99',
                'published' => true,
            ],
            [
                'id' => 2,
                'author_id' => $lohgarra->id,
                'title' => 'Life Debt: A Wookie Story',
                'description' => 'What does a Wookie owe the friend who saved them? A story about honor and loyalty.',
                'cover_image_url' => null,
                'price' => '12.50',
                'published' => true,
            ],
            [
                'id' => 3,
                'author_id' => $lohgarra->id,
                'title' => 'My Draft Manuscript',
                'description' => 'Still a work in progress.',
                'cover_image_url' => null,
                'price' => '5.00',
                'published' => false,
            ],
            [
                'id' => 4,
                'author_id' => $lohgarra->id,
                'title' => 'Deleted Memories',
                'description' => 'A book nobody should be able to find anymore.',
                'cover_image_url' => null,
                'price' => '7.25',
                'published' => true,
                'deleted_at' => now(),
            ],
            [
                'id' => 5,
                'author_id' => $chewbacca->id,
                'title' => "Smuggler's Run",
                'description' => 'Fast ships, bad odds and a very large co-pilot.',
                'cover_image_url' => 'https://example.com/covers/smugglers-run.jpg',
                'price' => '14.00',
                'published' => true,
            ],
        ];

        Book::unguarded(function () use ($books) {
            foreach ($books as $attributes) {
                Book::withTrashed()->updateOrCreate(['id' => $attributes['id']], $attributes + ['deleted_at' => null]);
            }
        });
    }
}
