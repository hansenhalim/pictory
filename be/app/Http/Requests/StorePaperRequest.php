<?php

namespace App\Http\Requests;

use App\Models\Frame;
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
            // Not checked against the frames table: a session can sit offline in a kiosk while its frame is deleted.
            'frame_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'size:'.ShortCode::LENGTH],
            'paper' => ['required', 'image', 'mimes:png', 'max:20480'],
            'photos' => ['required', 'array', 'min:1', 'max:'.Frame::MAX_SLOTS],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg', 'max:10240'],
            'clips' => ['nullable', 'array', 'max:'.Frame::MAX_SLOTS],
            'clips.*' => ['file', 'mimetypes:video/mp4', 'max:5120'],
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
