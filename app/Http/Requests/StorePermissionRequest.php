<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Permission;

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
            'name' => 'required|unique:permissions,name',
            'description' => 'nullable',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
            'parent_id' => [
                'nullable',
                'exists:permissions,id',
                function ($attribute, $value, $fail) {
                    // Kiểm tra nếu parent_id bị xóa mềm
                    if (Permission::onlyTrashed()->where('id', $value)->exists()) {
                        $fail('Parent permission đã bị xóa mềm và không thể được chọn.');
                    }
                },
            ],
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
