<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateBookRequest extends FormRequest
{
    /**
     * Ownership is checked before validating so that strangers get a 403
     * instead of learning about the shape of someone else's book. Publishing
     * has its own rule, so it is checked right after ownership.
     */
    public function authorize(): bool
    {
        $book = $this->route('book');

        Gate::authorize('update', $book);

        if ($this->boolean('published')) {
            Gate::authorize('publish', $book);
        }

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'cover_image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'published' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
