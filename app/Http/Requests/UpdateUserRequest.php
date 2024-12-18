<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class UpdateUserRequest extends FormRequest
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
        // Các quy tắc xác thực cho request
        $rules = [
            'fullname' => 'sometimes|string|max:255', // Chỉ xác thực nếu fullname được gửi lên
            'phone_number' => [
                'sometimes',
                'nullable',
                'regex:/^\d{10,13}$/', // Chỉ chấp nhận số từ 10 đến 13 chữ số
                'unique:users,phone_number,' . $this->route('id'),
            ],
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'create_by' => 'sometimes|nullable|integer|between:1000,9999',
        ];

        // Kiểm tra nếu email có thay đổi không
        if ($this->has('email')) {
            $rules['email'] = 'required|string|email|max:255|unique:users,email,' . $this->route('id'); // Chỉ kiểm tra email nếu thay đổi
        }

        // Kiểm tra nếu password có thay đổi không
        if ($this->has('password')) {
            $rules['password'] = 'nullable|string|min:6'; // Mật khẩu là tùy chọn khi cập nhật
        }

        return $rules;
    }


    public function messages()
    {
        return [
            'fullname.string' => 'Tên đầy đủ phải là chuỗi ký tự.',
            'fullname.max' => 'Tên đầy đủ không được vượt quá 255 ký tự.',
            'email.email' => 'Định dạng email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại trong hệ thống.',
            'password.min' => 'Mật khẩu phải ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'avatar.image' => 'Avatar phải là một tệp hình ảnh.',
            'avatar.mimes' => 'Avatar phải là tệp có định dạng jpeg, png, jpg, hoặc gif.',
            'avatar.max' => 'Avatar không được lớn hơn 2MB.',
            'role_id.integer' => 'phân quyền không hợp lệ.',
            'role_id.exists' => 'phân quyền không tồn tại.',
            'phone_number.regex' => 'Số điện thoại phải có từ 10 đến 13 chữ số.',
            'phone_number.unique' => 'Số điện thoại đã tồn tại trong hệ thống.',
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
