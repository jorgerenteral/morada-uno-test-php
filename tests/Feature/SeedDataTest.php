<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * @return list<int>
     */
    private function ids(string $uri, array $headers = []): array
    {
        return array_column($this->getJson($uri, $headers)->assertOk()->json(), 'id');
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, User::count());
        $this->assertSame([1, 2, 5], $this->ids('/books'));
    }

    public function test_public_catalog_matches_the_documented_expectations(): void
    {
        $this->assertSame([1, 2, 5], $this->ids('/books'));
        $this->assertSame([1, 2], $this->ids('/books?author=lohgarra'));
        $this->assertSame([2], $this->ids('/books?search=wookie'));
    }

    public function test_seeded_users_can_log_in_and_see_their_books(): void
    {
        $token = $this->postJson('/auth/token', ['username' => 'lohgarra', 'password' => 'rwaaaaawr'])
            ->assertOk()
            ->json('access_token');

        $this->assertSame([1, 2, 3], $this->ids('/me/books', ['Authorization' => "Bearer {$token}"]));

        $this->postJson('/auth/token', ['username' => 'chewbacca', 'password' => 'rrwwwgggh'])->assertOk();
        $this->postJson('/auth/token', ['username' => 'darth_vader', 'password' => 'iwanttopublish'])->assertOk();
    }
}
