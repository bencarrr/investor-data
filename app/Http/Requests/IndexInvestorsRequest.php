<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexInvestorsRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'between:1,1000'],
            'cursor' => ['sometimes', 'string'],
            'format' => ['sometimes', 'in:json,csv'],
        ];
    }

    /**
     * Whether the client asked for CSV, via ?format=csv or an Accept: text/csv header.
     */
    public function wantsCsv(): bool
    {
        return $this->query('format') === 'csv' || ($this->accepts('text/csv') && ! $this->accepts('application/json'));
    }
}
