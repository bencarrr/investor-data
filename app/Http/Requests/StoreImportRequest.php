<?php

namespace App\Http\Requests;

use App\Services\InvestorImportService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use SplFileObject;

class StoreImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv', 'max:20480'],
        ];
    }

    /**
     * Reject files whose header row does not match the expected columns.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if ($validator->errors()->has('file') || ! $file instanceof UploadedFile) {
                    return;
                }

                $header = (new SplFileObject($file->getRealPath()))->fgetcsv(escape: '');

                if (! InvestorImportService::hasValidHeader($header)) {
                    $validator->errors()->add('file', 'The file header must be: '.implode(',', InvestorImportService::HEADER).'.');
                }
            },
        ];
    }
}
