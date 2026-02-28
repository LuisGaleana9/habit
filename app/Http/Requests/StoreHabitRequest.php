<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHabitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'      => 'required|string|max:255',
            'difficulty' => 'required|integer|in:1,2,3',
            'type'       => ['required', Rule::in(['daily', 'weekly'])],
            'is_active'  => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'difficulty.in' => 'Difficulty must be 1 (easy), 2 (medium) or 3 (hard).',
            'type.in'       => 'Type must be "daily" or "weekly".',
        ];
    }
}
