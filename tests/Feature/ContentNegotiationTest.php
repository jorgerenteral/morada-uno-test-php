<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentNegotiationTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $author = User::factory()->create(['author_pseudonym' => 'Lohgarra']);
        $this->book = Book::factory()->for($author, 'author')->published()->create(['title' => 'Tom & Jerry', 'price' => 9.99]);
    }

    public function test_json_is_the_default_format(): void
    {
        $this->get('/books')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_accept_json_returns_json(): void
    {
        $this->get('/books', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('0.title', 'Tom & Jerry');
    }

    public function test_accept_xml_returns_xml_for_lists(): void
    {
        $response = $this->get('/books', ['Accept' => 'application/xml'])->assertOk();

        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertSame((string) $this->book->id, (string) $xml->item[0]->id);
        $this->assertSame('Tom & Jerry', (string) $xml->item[0]->title);
        $this->assertSame('Lohgarra', (string) $xml->item[0]->author_pseudonym);
        $this->assertSame('9.99', (string) $xml->item[0]->price);
        $this->assertSame('true', (string) $xml->item[0]->cover_image_url['nil']);
    }

    public function test_accept_xml_returns_xml_for_a_single_book(): void
    {
        $response = $this->get("/books/{$this->book->id}", ['Accept' => 'application/xml'])->assertOk();

        $this->assertSame('Tom & Jerry', (string) simplexml_load_string($response->getContent())->title);
    }

    public function test_xml_is_available_on_authenticated_endpoints(): void
    {
        $token = app(JwtService::class)->issue($this->book->author);

        $response = $this->get('/me/books', ['Accept' => 'application/xml', 'Authorization' => "Bearer {$token}"])
            ->assertOk();

        $this->assertNotNull(simplexml_load_string($response->getContent())->item[0]->deleted_at);
    }

    public function test_xml_is_available_on_the_token_endpoint(): void
    {
        User::factory()->create(['username' => 'lohgarra', 'password' => 'rwaaaaawr']);

        $response = $this->post('/auth/token', ['username' => 'lohgarra', 'password' => 'rwaaaaawr'], ['Accept' => 'application/xml'])
            ->assertOk();

        $xml = simplexml_load_string($response->getContent());
        $this->assertSame('bearer', (string) $xml->token_type);
        $this->assertNotEmpty((string) $xml->access_token);
    }

    public function test_unsupported_format_returns_406_as_json(): void
    {
        $this->get('/books', ['Accept' => 'text/html'])
            ->assertStatus(406)
            ->assertJsonPath('error', 'not_acceptable');

        $this->get('/books', ['Accept' => 'application/yaml'])->assertStatus(406);
    }

    public function test_unsupported_format_is_rejected_before_authentication(): void
    {
        $this->get('/me/books', ['Accept' => 'text/csv'])->assertStatus(406);
    }

    public function test_wildcards_resolve_to_json(): void
    {
        $this->get('/books', ['Accept' => '*/*'])->assertHeader('Content-Type', 'application/json');
        $this->get('/books', ['Accept' => 'text/html, */*;q=0.8'])->assertHeader('Content-Type', 'application/json');
    }

    public function test_browser_style_accept_headers_get_json(): void
    {
        $this->get('/books', ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'])
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_quality_values_decide_between_supported_formats(): void
    {
        $xml = $this->get('/books', ['Accept' => 'application/json;q=0.5, application/xml;q=0.9'])->headers->get('Content-Type');
        $json = $this->get('/books', ['Accept' => 'application/xml;q=0.2, application/json'])->headers->get('Content-Type');

        $this->assertStringStartsWith('application/xml', $xml);
        $this->assertStringStartsWith('application/json', $json);
    }

    public function test_errors_stay_json_even_when_xml_is_requested(): void
    {
        $this->get('/books/999', ['Accept' => 'application/xml'])
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');
    }

    public function test_no_content_responses_stay_empty(): void
    {
        $token = app(JwtService::class)->issue($this->book->author);

        $this->delete("/me/books/{$this->book->id}", [], ['Accept' => 'application/xml', 'Authorization' => "Bearer {$token}"])
            ->assertNoContent();
    }
}
