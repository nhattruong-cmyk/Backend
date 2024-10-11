<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
        $roleId = $this->route('id'); // Lấy ID từ route {id}

        return [
            'name' => [
                'sometimes',  // Chỉ kiểm tra khi có dữ liệu
                'string',
                Rule::unique('roles', 'name')->ignore($roleId), // Kiểm tra unique nhưng bỏ qua role hiện tại
            ],
            'description' => 'nullable|string',
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'Bạn phải nhập tên vai trò.',
            'name.unique' => 'Tên vai trò này đã tồn tại, vui lòng nhập tên khác.',
            'description.nullable' => 'Mô tả không bắt buộc phải nhập.',
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
