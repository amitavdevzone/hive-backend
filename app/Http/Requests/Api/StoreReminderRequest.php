<?php

namespace App\Http\Requests\Api;

use App\Enums\ReminderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(ReminderType::class)],
            'remind_at' => ['required_if:type,once', 'prohibited_if:type,recurring', 'date'],
            'interval_minutes' => ['required_if:type,recurring', 'prohibited_if:type,once', 'integer', 'min:1', 'max:10080'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
