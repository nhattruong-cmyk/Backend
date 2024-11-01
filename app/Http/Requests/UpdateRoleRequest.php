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

        return [
            'name' => 'sometimes|required|unique:roles,name,',
            'description' => 'nullable',
            'permissions' => 'array', // permissions là một mảng chứa ID của các quyền
            'permissions.*' => 'integer|exists:permissions,id',
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'Tên vai trò là bắt buộc.',
            'name.unique' => 'Tên vai trò đã tồn tại, vui lòng chọn tên khác.',
            'description.nullable' => 'Mô tả có thể để trống.',
            'permissions.array' => 'Quyền phải là một mảng.',
            'permissions.*.integer' => 'Mỗi phần tử trong quyền phải là một số nguyên.',
            'permissions.*.exists' => 'Một trong các quyền không tồn tại.',
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
