<?php

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
class PublicBookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'author_pseudonym' => $this->author->author_pseudonym,
            'cover_image_url' => $this->cover_image_url,
            'price' => $this->price,
            'published' => $this->published,
        ];
    }
}
