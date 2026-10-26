<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Resources\BookResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
