<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
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
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'context_type' => ['sometimes', 'in:project,task,proposal,review'],
            'context_id' => ['required_if:context_type,task,proposal,review', 'nullable', 'integer', 'min:1'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
