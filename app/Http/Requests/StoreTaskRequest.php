<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class StoreTaskRequest extends FormRequest
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
            'task_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|integer|in:1,2,3,4',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_id' => 'required|exists:projects,id',
            'department_id' => 'required|integer',  // Không dùng exists để kiểm tra thủ công trong Controller
            'files.*' => 'nullable|file|mimes:jpg,png,pdf,doc,docx,zip|max:20480', // Kiểm tra định dạng và kích thước file
        ];
    }

    public function messages()
    {
        return [
            'task_name.required' => 'Tên nhiệm vụ là bắt buộc.',
            'task_name.max' => 'Tên nhiệm vụ không được vượt quá 255 ký tự.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.integer' => 'Trạng thái phải là một số nguyên.',
            'project_id.required' => 'Dự án là bắt buộc.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'department_id.required' => 'Phòng ban là bắt buộc.',
            'department_id.integer' => 'Phòng ban phải là số nguyên.',
            'files.*.mimes' => 'Định dạng file phải là jpg, png, pdf, doc, docx, zip.',
            'files.*.max' => 'File không được vượt quá 20MB.',
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
