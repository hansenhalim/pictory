<?php

namespace App\Http\Requests;

use App\Models\ShortCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class StorePaperRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ref' => ['required', 'uuid'],
            'code' => ['nullable', 'string', 'size:'.ShortCode::LENGTH],
            'paper' => ['required', 'image', 'mimes:png', 'max:20480'],
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg', 'max:10240'],
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        if ($this->filled('ref')) {
            $this->merge(['ref' => strtolower($this->string('ref'))]);
        }

        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper($this->string('code'))]);
        }
    }
}
