<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteHabitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership check is done in the controller
    }

    public function rules(): array
    {
        return [
            'timezone' => 'sometimes|timezone',
        ];
    }

    /**
     * Resolve the current date based on the client's timezone.
     */
    public function resolvedDate(): string
    {
        $tz = $this->input('timezone', 'UTC');

        return now($tz)->toDateString();
    }
}
