<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class UpdateFileRequest extends FormRequest
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
            'files' => 'required|array', // Kiểm tra 'files' là một mảng
            'files.*' => 'required|file|mimes:jpg,jpeg,png,pdf,docx|max:20480', // Kiểm tra từng file trong mảng

        ];
    }
    public function messages(): array
    {
        return [
            'files.required' => 'Bạn phải tải lên ít nhất một file.',
            'files.*.required' => 'Mỗi file trong danh sách file là bắt buộc.',
            'files.*.mimes' => 'Định dạng file không hợp lệ. Chỉ hỗ trợ jpg, jpeg, png, pdf, docx.',
            'files.*.max' => 'Kích thước file không được vượt quá 20MB.',
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
