<?php

namespace App\Http\Requests\Api;

use App\Enums\ReminderType;
use App\Models\Reminder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Partial update: every field is optional, but the type-specific field is judged
     * against the effective type (requested, else stored) and is mandatory on a type change.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Reminder $reminder */
        $reminder = $this->route('reminder');

        $type = $this->enum('type', ReminderType::class) ?? $reminder->type;
        $presence = $type === $reminder->type ? 'sometimes' : 'required';

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'type' => ['sometimes', 'required', Rule::enum(ReminderType::class)],
            'remind_at' => $type === ReminderType::Once ? [$presence, 'date'] : ['prohibited'],
            'interval_minutes' => $type === ReminderType::Recurring ? [$presence, 'integer', 'min:1', 'max:10080'] : ['prohibited'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
