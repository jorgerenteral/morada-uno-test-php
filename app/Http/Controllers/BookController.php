<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicBookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $books = Book::query()
            ->published()
            ->with('author')
            ->when($request->query('search'), fn ($query, $term) => $query->search((string) $term))
            ->when($request->query('author'), fn ($query, $name) => $query->byAuthorPseudonym((string) $name))
            ->orderBy('id')
            ->get();

        return PublicBookResource::collection($books);
    }

    public function show(int $id): PublicBookResource
    {
        $book = Book::query()->published()->with('author')->findOrFail($id);

        return new PublicBookResource($book);
    }
}
