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
}
