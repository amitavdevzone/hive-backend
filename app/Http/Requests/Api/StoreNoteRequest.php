<?php

namespace App\Http\Requests\Api;

use App\Enums\NoteFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim and lowercase tag names so uniqueness and reuse are case-insensitive.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->tags)) {
            $this->merge([
                'tags' => array_map(
                    fn (mixed $tag): mixed => is_string($tag) ? mb_strtolower(trim($tag)) : $tag,
                    $this->tags,
                ),
            ]);
        }
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
            'text' => ['required', 'string'],
            'format' => ['required', Rule::enum(NoteFormat::class)],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct'],
        ];
    }
}
