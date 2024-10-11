<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
class UpdateAssignmentRequest extends FormRequest
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
            'status' => 'sometimes|integer|in:0,1,2',
            'user_id' => 'sometimes|exists:users,id',
            'department_id' => 'sometimes|exists:departments,id',
        ];
    }

    public function messages()
    {
        return [
            'status.in' => 'Status must be one of the following values: 0, 1, or 2.',
            'user_id.exists' => 'The selected user does not exist.',
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
