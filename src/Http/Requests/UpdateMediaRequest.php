<?php

namespace Ayvazyan10\Imagic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[^\/\\\\\x00-\x1F\x7F]+$/u'],
            'folder_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
