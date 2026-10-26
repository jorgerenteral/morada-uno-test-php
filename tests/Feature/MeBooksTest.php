<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeBooksTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['username' => 'lohgarra', 'author_pseudonym' => 'Lohgarra']);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(?User $user = null): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->issue($user ?? $this->author)];
    }

    public function test_listing_requires_authentication(): void
    {
        $this->getJson('/me/books')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'unauthorized');
    }

    public function test_unauthenticated_requests_never_redirect_even_without_json_accept_header(): void
    {
        $this->get('/me/books')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'unauthorized');
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->getJson('/me/books', ['Authorization' => 'Bearer not-a-valid-token'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'unauthorized');
    }

    public function test_token_of_a_removed_user_returns_401(): void
    {
        $headers = $this->authHeaders();
        $this->author->delete();

        $this->getJson('/me/books', $headers)->assertUnauthorized();
    }

    public function test_listing_returns_own_drafts_and_published_books_but_not_deleted_ones(): void
    {
        $published = Book::factory()->for($this->author, 'author')->published()->create();
        $draft = Book::factory()->for($this->author, 'author')->create();
        Book::factory()->for($this->author, 'author')->published()->trashed()->create();
        Book::factory()->published()->create();

        $this->getJson('/me/books', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $published->id)
            ->assertJsonPath('1.id', $draft->id)
            ->assertJsonPath('1.published', false)
            ->assertJsonPath('1.deleted_at', null);
    }

    public function test_create_stores_a_draft_owned_by_the_authenticated_user(): void
    {
        $this->postJson('/me/books', [
            'title' => 'Adventures on Kashyyyk',
            'description' => 'A thrilling tale of...',
            'price' => '9.99',
            'cover_image_url' => 'https://example.com/cover.jpg',
        ], $this->authHeaders())
            ->assertCreated()
            ->assertJsonPath('title', 'Adventures on Kashyyyk')
            ->assertJsonPath('author_pseudonym', 'Lohgarra')
            ->assertJsonPath('price', '9.99')
            ->assertJsonPath('published', false)
            ->assertJsonPath('deleted_at', null);

        $this->assertDatabaseHas('books', ['title' => 'Adventures on Kashyyyk', 'author_id' => $this->author->id, 'published' => false]);
    }

    public function test_create_without_cover_image_is_allowed(): void
    {
        $this->postJson('/me/books', [
            'title' => 'No cover',
            'description' => 'Plain',
            'price' => 5,
        ], $this->authHeaders())
            ->assertCreated()
            ->assertJsonPath('cover_image_url', null)
            ->assertJsonPath('price', '5.00');
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/me/books', ['title' => 'x', 'description' => 'y', 'price' => 1])->assertUnauthorized();
    }

    public function test_create_with_missing_title_returns_a_clear_400(): void
    {
        $this->postJson('/me/books', ['description' => 'y', 'price' => '1.00'], $this->authHeaders())
            ->assertStatus(400)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonPath('details.field', 'title')
            ->assertJsonPath('details.code', 'required')
            ->assertJsonPath('message', 'The title field is required.');
    }

    public function test_create_with_missing_description_or_price_returns_400(): void
    {
        $headers = $this->authHeaders();

        $this->postJson('/me/books', ['title' => 'x', 'price' => '1.00'], $headers)
            ->assertStatus(400)->assertJsonPath('details.field', 'description');

        $this->postJson('/me/books', ['title' => 'x', 'description' => 'y'], $headers)
            ->assertStatus(400)->assertJsonPath('details.field', 'price');
    }

    public function test_create_rejects_invalid_price_and_cover_url(): void
    {
        $headers = $this->authHeaders();
        $payload = ['title' => 'x', 'description' => 'y'];

        $this->postJson('/me/books', $payload + ['price' => 'abc'], $headers)
            ->assertStatus(400)->assertJsonPath('details.field', 'price');
        $this->postJson('/me/books', $payload + ['price' => '-1'], $headers)
            ->assertStatus(400)->assertJsonPath('details.field', 'price');
        $this->postJson('/me/books', $payload + ['price' => '1.999'], $headers)
            ->assertStatus(400)->assertJsonPath('details.field', 'price');
        $this->postJson('/me/books', $payload + ['price' => '1', 'cover_image_url' => 'not a url'], $headers)
            ->assertStatus(400)->assertJsonPath('details.field', 'cover_image_url');
    }

    public function test_create_with_published_true_returns_400(): void
    {
        $this->postJson('/me/books', [
            'title' => 'x',
            'description' => 'y',
            'price' => '1.00',
            'published' => true,
        ], $this->authHeaders())
            ->assertStatus(400)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonPath('message', 'Cannot publish on create. Use PATCH to publish.')
            ->assertJsonPath('details.field', 'published');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_show_returns_own_book_including_drafts(): void
    {
        $draft = Book::factory()->for($this->author, 'author')->create();

        $this->getJson("/me/books/{$draft->id}", $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('id', $draft->id)
            ->assertJsonPath('published', false);
    }

    public function test_show_of_someone_elses_book_returns_403(): void
    {
        $other = Book::factory()->published()->create();

        $this->getJson("/me/books/{$other->id}", $this->authHeaders())
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_patch_updates_fields_and_can_publish(): void
    {
        $book = Book::factory()->for($this->author, 'author')->create(['title' => 'Old', 'price' => 5]);

        $this->patchJson("/me/books/{$book->id}", ['title' => 'New', 'price' => '12.50', 'published' => true], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('id', $book->id)
            ->assertJsonPath('title', 'New')
            ->assertJsonPath('price', '12.50')
            ->assertJsonPath('published', true);

        $this->getJson("/books/{$book->id}")->assertOk();
    }

    public function test_patch_can_unpublish_and_clear_the_cover(): void
    {
        $book = Book::factory()->for($this->author, 'author')->published()->create(['cover_image_url' => 'https://example.com/c.jpg']);

        $this->patchJson("/me/books/{$book->id}", ['published' => false, 'cover_image_url' => null], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('published', false)
            ->assertJsonPath('cover_image_url', null);

        $this->getJson("/books/{$book->id}")->assertNotFound();
    }

    public function test_patch_ignores_attempts_to_change_the_author(): void
    {
        $book = Book::factory()->for($this->author, 'author')->create();
        $other = User::factory()->create();

        $this->patchJson("/me/books/{$book->id}", ['author_id' => $other->id, 'title' => 'Same owner'], $this->authHeaders())
            ->assertOk();

        $this->assertSame($this->author->id, $book->fresh()->author_id);
    }

    public function test_patch_with_invalid_data_returns_400(): void
    {
        $book = Book::factory()->for($this->author, 'author')->create();
        $headers = $this->authHeaders();

        $this->patchJson("/me/books/{$book->id}", ['title' => ''], $headers)
            ->assertStatus(400)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonPath('details.field', 'title');

        $this->patchJson("/me/books/{$book->id}", ['published' => 'maybe'], $headers)
            ->assertStatus(400)
            ->assertJsonPath('details.field', 'published');
    }

    public function test_patch_requires_authentication(): void
    {
        $book = Book::factory()->for($this->author, 'author')->create();

        $this->patchJson("/me/books/{$book->id}", ['title' => 'x'])->assertUnauthorized();
    }

    public function test_patch_on_someone_elses_book_returns_403_and_changes_nothing(): void
    {
        $other = Book::factory()->create(['title' => 'Original']);

        $this->patchJson("/me/books/{$other->id}", ['title' => 'Hijacked'], $this->authHeaders())
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');

        $this->assertSame('Original', $other->fresh()->title);
    }

    public function test_forbidden_takes_precedence_over_validation_for_strangers(): void
    {
        $other = Book::factory()->create();

        $this->patchJson("/me/books/{$other->id}", ['title' => ''], $this->authHeaders())->assertForbidden();
    }

    public function test_patch_on_unknown_or_deleted_book_returns_404(): void
    {
        $deleted = Book::factory()->for($this->author, 'author')->trashed()->create();
        $headers = $this->authHeaders();

        $this->patchJson('/me/books/999', ['title' => 'x'], $headers)
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');
        $this->patchJson("/me/books/{$deleted->id}", ['title' => 'x'], $headers)->assertNotFound();
    }

    public function test_delete_soft_deletes_the_book_and_hides_it_everywhere(): void
    {
        $book = Book::factory()->for($this->author, 'author')->published()->create();
        $headers = $this->authHeaders();

        $this->deleteJson("/me/books/{$book->id}", [], $headers)
            ->assertNoContent();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertNotNull(Book::withTrashed()->find($book->id)->deleted_at);

        $this->getJson("/books/{$book->id}")->assertNotFound();
        $this->getJson('/books')->assertJsonCount(0);
        $this->getJson('/me/books', $headers)->assertJsonCount(0);
        $this->getJson("/me/books/{$book->id}", $headers)->assertNotFound();
    }

    public function test_delete_on_someone_elses_book_returns_403_and_keeps_it(): void
    {
        $other = Book::factory()->published()->create();

        $this->deleteJson("/me/books/{$other->id}", [], $this->authHeaders())
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');

        $this->assertNull($other->fresh()->deleted_at);
    }

    public function test_delete_requires_authentication(): void
    {
        $book = Book::factory()->for($this->author, 'author')->create();

        $this->deleteJson("/me/books/{$book->id}")->assertUnauthorized();
    }

    public function test_delete_unknown_or_already_deleted_book_returns_404(): void
    {
        $deleted = Book::factory()->for($this->author, 'author')->trashed()->create();
        $headers = $this->authHeaders();

        $this->deleteJson('/me/books/999', [], $headers)->assertNotFound();
        $this->deleteJson("/me/books/{$deleted->id}", [], $headers)->assertNotFound();
    }
}
