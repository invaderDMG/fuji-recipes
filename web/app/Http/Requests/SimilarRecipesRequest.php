<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimilarRecipesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'n' => ['integer', 'min:1', 'max:50'],
            'sensor' => ['nullable', 'string', 'exists:sensors,slug'],
            'same_sim' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(QueryBooleans::normalize($this, ['same_sim']));
    }
}
