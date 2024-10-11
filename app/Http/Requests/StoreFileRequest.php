<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class StoreFileRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:jpg,png,pdf,doc,docx,zip|max:20480', // Quy tắc xác thực cho file
        ];
    }
    public function messages(): array
    {
        return [
            'file.required' => 'Bạn phải chọn một tệp để tải lên.',
            'file.mimes' => 'Tệp phải có định dạng: jpg, png, pdf, doc, docx, hoặc zip.',
            'file.max' => 'Kích thước tệp tối đa là 20MB.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422));
    }
}
