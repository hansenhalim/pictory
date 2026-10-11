<?php

namespace App\Http\Requests;

use App\Models\QrCard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaperCardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'card' => ['present', 'nullable', 'string', 'regex:'.QrCard::LABEL_PATTERN],
        ];
    }
}
