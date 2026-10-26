<?php

namespace Tests\Unit;

use App\Support\XmlEncoder;
use PHPUnit\Framework\TestCase;

class XmlEncoderTest extends TestCase
{
    private function parse(string $xml): \SimpleXMLElement
    {
        return simplexml_load_string($xml) ?: $this->fail('Invalid XML produced');
    }

    public function test_encodes_associative_arrays_as_child_elements(): void
    {
        $xml = $this->parse(XmlEncoder::encode(['id' => 1, 'title' => 'Adventures', 'published' => true]));

        $this->assertSame('response', $xml->getName());
        $this->assertSame('1', (string) $xml->id);
        $this->assertSame('Adventures', (string) $xml->title);
        $this->assertSame('true', (string) $xml->published);
    }

    public function test_encodes_lists_as_repeated_items(): void
    {
        $xml = $this->parse(XmlEncoder::encode([['id' => 1], ['id' => 2]]));

        $this->assertCount(2, $xml->item);
        $this->assertSame('2', (string) $xml->item[1]->id);
    }

    public function test_null_is_flagged_as_nil(): void
    {
        $xml = $this->parse(XmlEncoder::encode(['cover_image_url' => null]));

        $this->assertSame('true', (string) $xml->cover_image_url['nil']);
        $this->assertSame('', (string) $xml->cover_image_url);
    }

    public function test_special_characters_are_escaped(): void
    {
        $xml = $this->parse(XmlEncoder::encode(['title' => 'Tom & <Jerry>']));

        $this->assertSame('Tom & <Jerry>', (string) $xml->title);
    }

    public function test_invalid_element_names_fall_back_to_item(): void
    {
        $xml = $this->parse(XmlEncoder::encode(['1' => 'a', 'b' => 'c']));

        $this->assertSame('a', (string) $xml->item);
        $this->assertSame('c', (string) $xml->b);
    }
}
