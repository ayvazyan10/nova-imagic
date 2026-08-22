<?php

namespace Ayvazyan10\Imagic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
