<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Worktimes;


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
            'status' => 'sometimes|required|integer|in:1,2,3,4',
            'start_date' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    // Kiểm tra nếu có worktime_id
                    if ($this->input('worktime_id')) {
                        $worktime = Worktimes::find($this->input('worktime_id'));
    
                        if ($worktime) {
                            // Kiểm tra xem start_date có nằm trong khoảng thời gian của worktime không
                            if ($value < $worktime->start_date || $value > $worktime->end_date) {
                                $fail('Start date must be within the worktime period.');
                            }
                        }
                    }
                },
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
                function ($attribute, $value, $fail) {
                    // Kiểm tra nếu có worktime_id
                    if ($this->input('worktime_id')) {
                        $worktime = Worktimes::find($this->input('worktime_id'));
    
                        if ($worktime) {
                            // Kiểm tra xem end_date có nằm trong khoảng thời gian của worktime không
                            if ($value < $worktime->start_date || $value > $worktime->end_date) {
                                $fail('End date must be within the worktime period.');
                            }
                        }
                    }
                },
            ],
            'project_id' => 'sometimes|required|exists:projects,id',
            'department_id' => 'sometimes|integer',  // Kiểm tra bằng tay ở Controller
            'worktime_id' => 'sometimes|required|exists:worktimes,id', // Ràng buộc khóa ngoại tới worktimes
            'location_task' => 'sometimes|required|integer|in:0,1,2', // Giá trị vị trí 0,1,2
            'files.*' => 'nullable|file|mimes:jpg,png,pdf,doc,docx,zip|max:20480',
            'delete_file_ids' => 'nullable|array',
            'delete_file_ids.*' => 'exists:files,id',
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
            'worktime_id.required' => 'Worktime là bắt buộc.',
            'worktime_id.exists' => 'Worktime không tồn tại.',
            'location_task.required' => 'Vị trí nhiệm vụ là bắt buộc.',
            'location_task.integer' => 'Vị trí nhiệm vụ phải là một số nguyên.',
            'location_task.in' => 'Vị trí nhiệm vụ phải là một trong các giá trị: 0, 1, hoặc 2.',
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
