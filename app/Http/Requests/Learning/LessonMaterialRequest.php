<?php

namespace App\Http\Requests\Learning;

use App\Services\Learning\LearningFiles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A file upload or an external link. Files are checked by extension and detected MIME type.
 */
class LessonMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('courses.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extensions = implode(',', array_keys((array) config('lms.extensions')));
        $maxKilobytes = app(LearningFiles::class)->maxUploadKilobytes();

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_downloadable' => ['boolean'],
            'url' => ['required_without:file', 'nullable', 'url:https,http', 'max:2048'],
            'file' => ['required_without:url', 'nullable', 'file', "max:{$maxKilobytes}", "extensions:{$extensions}", "mimes:{$extensions}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => __('This file type is not allowed.'),
            'file.mimes' => __('The file’s contents do not match an allowed type.'),
            'file.max' => __('The file is larger than the school’s upload limit.'),
            'url.required_without' => __('Upload a file or add a link.'),
        ];
    }
}
