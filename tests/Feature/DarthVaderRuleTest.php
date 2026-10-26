<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarthVaderRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $vader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vader = User::factory()->create(['username' => 'darth_vader', 'author_pseudonym' => 'Darth Vader']);
    }

    /**
     * @return array<string, string>
     */
    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->issue($user)];
    }

    public function test_darth_vader_cannot_publish_a_book(): void
    {
        $book = Book::factory()->for($this->vader, 'author')->create();

        $this->patchJson("/me/books/{$book->id}", ['published' => true], $this->headers($this->vader))
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'forbidden',
                'message' => 'Darth Vader is not allowed to publish on Wookie Books',
            ]);

        $this->assertFalse($book->fresh()->published);
    }

    public function test_darth_vader_can_still_manage_his_drafts(): void
    {
        $book = Book::factory()->for($this->vader, 'author')->create();
        $headers = $this->headers($this->vader);

        $this->postJson('/me/books', ['title' => 'Dark Side', 'description' => 'Join us', 'price' => '6.66'], $headers)
            ->assertCreated()
            ->assertJsonPath('published', false);

        $this->patchJson("/me/books/{$book->id}", ['title' => 'Renamed', 'published' => false], $headers)
            ->assertOk()
            ->assertJsonPath('title', 'Renamed');

        $this->deleteJson("/me/books/{$book->id}", [], $headers)->assertNoContent();
    }

    public function test_other_authors_can_publish(): void
    {
        $chewbacca = User::factory()->create(['username' => 'chewbacca']);
        $book = Book::factory()->for($chewbacca, 'author')->create();

        $this->patchJson("/me/books/{$book->id}", ['published' => true], $this->headers($chewbacca))
            ->assertOk()
            ->assertJsonPath('published', true);
    }

    public function test_ownership_is_checked_before_the_publish_rule(): void
    {
        $other = Book::factory()->create();

        $this->patchJson("/me/books/{$other->id}", ['published' => true], $this->headers($this->vader))
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }
}
