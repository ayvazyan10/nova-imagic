<?php

namespace Ayvazyan10\Imagic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $extensions = implode(',', (array) config('imagic.uploads.allowed_extensions', []));
        $mimeTypes = implode(',', (array) config('imagic.uploads.allowed_mime_types', []));
        $maxWidth = (int) config('imagic.uploads.max_width', 12000);
        $maxHeight = (int) config('imagic.uploads.max_height', 12000);

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.(int) config('imagic.uploads.max_files', 20)],
            'files.*' => [
                'required',
                'file',
                'image',
                'max:'.(int) config('imagic.uploads.max_file_size', 12288),
                'mimes:'.$extensions,
                'mimetypes:'.$mimeTypes,
                "dimensions:max_width={$maxWidth},max_height={$maxHeight}",
            ],
            'folder_id' => ['nullable', 'uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $maxPixels = (int) config('imagic.uploads.max_pixels', 40_000_000);

            foreach ((array) $this->file('files', []) as $index => $file) {
                $dimensions = @getimagesize($file->getRealPath());

                if ($dimensions === false || ((int) $dimensions[0] * (int) $dimensions[1]) > $maxPixels) {
                    $validator->errors()->add("files.{$index}", 'The image dimensions are invalid or exceed the configured pixel limit.');
                }
            }
        });
    }
}
