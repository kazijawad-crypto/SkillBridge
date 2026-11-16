<?php

namespace App\Http\Requests;

use App\Models\NotificationPreference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_fill_keys(array_keys(NotificationPreference::TYPES), ['required', 'boolean']);
    }
}
