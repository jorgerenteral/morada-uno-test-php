<?php

namespace App\Http\Requests;

use App\Rules\NotPublishedOnCreate;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'cover_image_url' => ['nullable', 'url', 'max:2048'],
            'published' => [new NotPublishedOnCreate],
        ];
    }
}
