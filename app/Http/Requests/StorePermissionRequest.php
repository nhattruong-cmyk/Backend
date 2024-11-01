<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePermissionRequest extends FormRequest
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
            'name' => 'required|unique:roles,name',
            'description' => 'nullable',
            'permissions' => 'array', // permissions là một mảng chứa ID của các quyền
            'permissions.*' => 'integer|exists:permissions,id', // Mỗi phần tử phải là ID hợp lệ của Permission
            'parent_id' => 'nullable|exists:permissions,id', // Kiểm tra nếu parent_id tồn tại trong bảng permissions

        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'Tên quyền là bắt buộc.',
            'name.unique' => 'Tên quyền đã tồn tại, vui lòng chọn tên khác.',
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
