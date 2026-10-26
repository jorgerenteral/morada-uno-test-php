<?php

namespace App\Support;

use XMLWriter;

/**
 * Minimal array to XML encoder for API payloads.
 *
 * Associative arrays become child elements named after their keys, lists
 * become repeated <item> elements, null becomes an empty element flagged with
 * nil="true" and booleans are written as true/false.
 */
class XmlEncoder
{
    public static function encode(mixed $data, string $root = 'response'): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        self::write($xml, $root, $data);

        $xml->endDocument();

        return $xml->outputMemory();
    }

    private static function write(XMLWriter $xml, string $name, mixed $value): void
    {
        $xml->startElement(self::elementName($name));

        if ($value === null) {
            $xml->writeAttribute('nil', 'true');
        } elseif (is_array($value)) {
            $isList = array_is_list($value);

            foreach ($value as $key => $child) {
                self::write($xml, $isList ? 'item' : (string) $key, $child);
            }
        } elseif (is_bool($value)) {
            $xml->text($value ? 'true' : 'false');
        } else {
            $xml->text((string) $value);
        }

        $xml->endElement();
    }

    /**
     * Keys that are not valid XML names (digits first, spaces...) fall back to <item>.
     */
    private static function elementName(string $name): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $name) ? $name : 'item';
    }
}
