<?php

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;

/**
 * Book representation for its owner, which also exposes the soft delete marker.
 *
 * @mixin Book
 */
class BookResource extends PublicBookResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
