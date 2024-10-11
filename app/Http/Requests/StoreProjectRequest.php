<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
class StoreProjectRequest extends FormRequest
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
    public function rules()
    {
        return [
            'project_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'project_name')->ignore($this->route('id')), // Đảm bảo tên không trùng, bỏ qua project hiện tại
            ],
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|integer|in:0,1,2',
            'user_id' => 'required|exists:users,id',
        ];
    }

    public function messages()
    {
        return [
            'project_name.required' => 'Tên dự án là bắt buộc.',
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'status.in' => 'Trạng thái phải là một trong các giá trị: 0, 1, 2.',
            'user_id.exists' => 'Người dùng này không tồn tại.',
            'project_name.unique' => 'The project name has already been taken. Please choose a different name.',
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
