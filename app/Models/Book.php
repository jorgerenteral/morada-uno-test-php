<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'description', 'cover_image_url', 'price', 'published'])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Books visible on the public catalog (soft deleted ones are already excluded).
     *
     * @param  Builder<Book>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('published', true);
    }

    /**
     * Case-insensitive substring match against title or description.
     *
     * @param  Builder<Book>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $like = '%'.addcslashes(mb_strtolower($term), '\\%_').'%';

        $query->where(function (Builder $q) use ($like) {
            $q->whereRaw("LOWER(title) LIKE ? ESCAPE '\\'", [$like])
                ->orWhereRaw("LOWER(description) LIKE ? ESCAPE '\\'", [$like]);
        });
    }

    /**
     * Case-insensitive exact match against the author's pseudonym.
     *
     * @param  Builder<Book>  $query
     */
    public function scopeByAuthorPseudonym(Builder $query, string $pseudonym): void
    {
        $query->whereHas('author', function (Builder $q) use ($pseudonym) {
            $q->whereRaw('LOWER(author_pseudonym) = ?', [mb_strtolower($pseudonym)]);
        });
    }
}
