<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class UpdateCommentRequest extends FormRequest
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
            'comment' => 'required|string|max:500',
            'task_id' => 'required|exists:tasks,id',
            'parent_id' => 'nullable|exists:comments,id', // Chỉ cần kiểm tra nếu có parent_id
            'files.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048', // Bổ sung validate cho file

        ];
    }
    public function messages(): array
    {
        return [
            'comment.required' => 'Nội dung bình luận là bắt buộc.',
            'comment.max' => 'Bình luận không được vượt quá 500 ký tự.',
            'task_id.required' => 'ID công việc là bắt buộc.',
            'task_id.exists' => 'Công việc được chọn không tồn tại.',
            'parent_id.exists' => 'ID phản hồi không tồn tại.',
            'files.*.mimes' => 'File phải là jpg, jpeg, png, pdf, doc hoặc docx.',
            'files.*.max' => 'Dung lượng file tối đa là 2MB.',
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
