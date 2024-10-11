<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class StoreDepartmentRequest extends FormRequest
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
            'department_name' => 'required|string|max:255|unique:departments,department_name',
            'description' => 'nullable|string',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'exists:users,id',  // Kiểm tra từng ID người dùng có tồn tại trong bảng users không
        ];
    }
    public function messages(): array
    {
        return [
            'department_name.required' => 'Tên phòng ban là bắt buộc.',
            'department_name.unique' => 'Tên phòng ban đã tồn tại, vui lòng chọn tên khác.',
            'user_ids.*.exists' => 'Một hoặc nhiều ID người dùng không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
