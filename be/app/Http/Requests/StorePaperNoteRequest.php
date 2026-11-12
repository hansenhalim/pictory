<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaperNoteRequest extends FormRequest
{
    public const int NOTE_MAX_LENGTH = 500;

    public const int SENDER_MAX_LENGTH = 60;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:'.self::NOTE_MAX_LENGTH],
            'sender' => ['required', 'string', 'max:'.self::SENDER_MAX_LENGTH],
        ];
    }
}
