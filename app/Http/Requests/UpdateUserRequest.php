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
        return [
            'fullname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Thêm quy tắc cho ảnh đại diện
            'phone_number' => 'nullable|numeric|unique:users,phone_number', // Thêm xác thực cho số điện thoại
            'create_by' => 'sometimes|nullable|integer|between:1000,9999', // Thêm quy tắc cho create_by nếu cần
        ];
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
            'phone_number.digits' => 'Số điện thoại phải có 10 chữ số.',
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
