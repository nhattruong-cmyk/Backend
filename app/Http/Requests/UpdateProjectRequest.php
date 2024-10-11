<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
            'description' => 'sometimes|nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'status' => 'sometimes|required|integer|in:0,1,2',
            'user_id' => 'sometimes|required|exists:users,id',
        ];
    }

    public function messages()
    {
        return [
            'project_name.required' => 'The project name is required.',
            'project_name.max' => 'The project name may not be greater than 255 characters.',
            'start_date.required' => 'The start date is required.',
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
            'status.in' => 'The status must be one of the following values: 0, 1, or 2.',
            'user_id.exists' => 'The user ID must exist in the users table.',
            'project_name.unique' => 'The project name has already been taken. Please choose a different name.',

        ];
    }
}
