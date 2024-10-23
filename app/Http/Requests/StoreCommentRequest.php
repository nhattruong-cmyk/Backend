<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class StoreCommentRequest extends FormRequest
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
            'task_id' => 'required|exists:tasks,id',
            'comment' => 'required|string|max:500',  // Giới hạn độ dài bình luận
            'parent_id' => 'nullable|exists:comments,id',
            'files.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048', // Bổ sung validate cho file
        ];
    }
    public function messages(): array
    {
        return [
            'task_id.required' => 'ID công việc là bắt buộc.',
            'task_id.exists' => 'Công việc được chọn không tồn tại.',
            'comment.required' => 'Vui lòng nhập nội dung bình luận.',
            'comment.max' => 'Bình luận không được vượt quá 500 ký tự.',
            'parent_id.exists' => 'Bình luận cha không tồn tại.',
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
