<?php

namespace Ayvazyan10\Imagic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'folder_id' => ['nullable', 'string', 'max:36'],
            'folder' => ['nullable', 'string', 'max:36'],
            'mime_type' => ['nullable', 'string', 'max:127'],
            'sort' => ['nullable', Rule::in(['name', 'size', 'created_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('imagic.media_library.max_per_page', 100)],
        ];
    }
}
