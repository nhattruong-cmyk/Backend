<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class UpdateTaskRequest extends FormRequest
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
            'task_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|integer|in:1,2,3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_id' => 'sometimes|required|exists:projects,id',
            'department_id' => 'sometimes|integer', // Kiểm tra bằng tay ở Controller
            'files.*' => 'nullable|file|mimes:jpg,png,pdf,doc,docx,zip|max:20480',
            'delete_file_ids' => 'nullable|array',
            'delete_file_ids.*' => 'exists:files,id',
        ];
    }

    public function messages()
    {
        return [
            'task_name.required' => 'Task name is required.',
            'status.in' => 'Status must be one of the following: 1, 2, or 3.',
            'project_id.exists' => 'The selected project does not exist.',
            'delete_file_ids.*.exists' => 'One or more file IDs do not exist.',
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
