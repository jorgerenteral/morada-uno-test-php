<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Database\Factories\BookFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBooksTest extends TestCase
{
    use RefreshDatabase;

    private User $lohgarra;

    private User $chewbacca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lohgarra = User::factory()->create(['username' => 'lohgarra', 'author_pseudonym' => 'Lohgarra']);
        $this->chewbacca = User::factory()->create(['username' => 'chewbacca', 'author_pseudonym' => 'Chewbacca']);
    }

    private function book(User $author, string $title, string $description = 'A story.'): BookFactory
    {
        return Book::factory()->for($author, 'author')->state(compact('title', 'description'));
    }

    public function test_index_returns_only_published_and_not_deleted_books(): void
    {
        $visible = $this->book($this->lohgarra, 'Adventures on Kashyyyk')->published()->create();
        $this->book($this->lohgarra, 'My Draft Manuscript')->create();
        $this->book($this->lohgarra, 'Deleted Memories')->published()->trashed()->create();
        $other = $this->book($this->chewbacca, "Smuggler's Run")->published()->create();

        $this->getJson('/books')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $visible->id)
            ->assertJsonPath('1.id', $other->id);
    }

    public function test_book_payload_matches_the_public_schema(): void
    {
        $book = $this->book($this->lohgarra, 'Adventures on Kashyyyk')
            ->published()
            ->state(['price' => 9.9, 'cover_image_url' => 'https://example.com/cover.jpg'])
            ->create();

        $this->getJson("/books/{$book->id}")
            ->assertOk()
            ->assertExactJson([
                'id' => $book->id,
                'title' => 'Adventures on Kashyyyk',
                'description' => 'A story.',
                'author_pseudonym' => 'Lohgarra',
                'cover_image_url' => 'https://example.com/cover.jpg',
                'price' => '9.90',
                'published' => true,
            ]);
    }

    public function test_cover_image_url_is_null_when_missing(): void
    {
        $book = $this->book($this->lohgarra, 'No cover')->published()->create();

        $this->getJson("/books/{$book->id}")->assertJsonPath('cover_image_url', null);
    }

    public function test_unpublished_book_returns_404(): void
    {
        $draft = $this->book($this->lohgarra, 'My Draft Manuscript')->create();

        $this->getJson("/books/{$draft->id}")
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');
    }

    public function test_deleted_book_returns_404(): void
    {
        $deleted = $this->book($this->lohgarra, 'Deleted Memories')->published()->trashed()->create();

        $this->getJson("/books/{$deleted->id}")->assertNotFound();
    }

    public function test_unknown_book_returns_404(): void
    {
        $this->getJson('/books/999')->assertNotFound();
    }

    public function test_search_matches_title_or_description_case_insensitively(): void
    {
        $byTitle = $this->book($this->lohgarra, 'Adventures on Kashyyyk')->published()->create();
        $byDescription = $this->book($this->lohgarra, 'Life Debt', 'A great ADVENTURE awaits')->published()->create();
        $this->book($this->chewbacca, "Smuggler's Run", 'Fast ships')->published()->create();
        $this->book($this->lohgarra, 'Draft adventure')->create();
        $this->book($this->lohgarra, 'Deleted adventure')->published()->trashed()->create();

        $this->getJson('/books?search=adventure')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $byTitle->id)
            ->assertJsonPath('1.id', $byDescription->id);
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $this->book($this->lohgarra, '100% Wookie')->published()->create();
        $this->book($this->lohgarra, 'Other')->published()->create();

        $this->getJson('/books?search=%25')->assertJsonCount(1);
    }

    public function test_author_filter_is_an_exact_case_insensitive_match(): void
    {
        $mine = $this->book($this->lohgarra, 'Adventures on Kashyyyk')->published()->create();
        $this->book($this->chewbacca, "Smuggler's Run")->published()->create();

        $this->getJson('/books?author=LOHGARRA')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mine->id);

        $this->getJson('/books?author=lohgar')->assertOk()->assertJsonCount(0);
    }

    public function test_search_and_author_filters_are_combined(): void
    {
        $match = $this->book($this->lohgarra, 'Life Debt: A Wookie Story')->published()->create();
        $this->book($this->lohgarra, 'Adventures on Kashyyyk')->published()->create();
        $this->book($this->chewbacca, 'Wookie smuggler')->published()->create();

        $this->getJson('/books?search=wookie&author=lohgarra')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $match->id);
    }

    public function test_write_methods_are_not_allowed(): void
    {
        $this->postJson('/books', [])->assertStatus(405)->assertJsonPath('error', 'method_not_allowed');
    }
}
