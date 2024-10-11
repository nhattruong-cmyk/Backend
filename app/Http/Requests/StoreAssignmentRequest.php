<?php

namespace App\Http\Requests;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
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
            'task_id' => 'required|exists:tasks,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
            'department_id' => 'required|exists:departments,id',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages()
    {
        return [
            'task_id.required' => 'The task ID is required.',
            'task_id.exists' => 'The selected task does not exist.',
            'user_ids.required' => 'You must provide at least one user.',
            'user_ids.*.integer' => 'Each user ID must be a valid integer.',
            'user_ids.*.exists' => 'One or more user IDs do not exist.',
            'department_id.required' => 'The department ID is required.',
            'department_id.exists' => 'The selected department does not exist.',
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
