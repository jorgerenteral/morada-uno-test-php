<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MeBookController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $books = $request->user()->books()->with('author')->orderBy('id')->get();

        return BookResource::collection($books);
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = $request->user()->books()->create([
            ...$request->safe()->except('published'),
            'published' => false,
        ]);

        return (new BookResource($book->load('author')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Book $book): BookResource
    {
        Gate::authorize('view', $book);

        return new BookResource($book->load('author'));
    }

    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $book->update($request->validated());

        return new BookResource($book->load('author'));
    }

    public function destroy(Book $book): Response
    {
        Gate::authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }
}
