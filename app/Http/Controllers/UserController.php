<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Project;
use App\Models\Department;
use App\Models\Task;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\VerifyEmailMail;
use Illuminate\Auth\Events\Verified;
use App\Mail\AccountDeleted;
use App\Mail\DeleteAccountConfirmation;
use App\Services\MailchimpService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Google\Client;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Comment;


class UserController extends Controller
{
    // Lấy danh sách tất cả người dùng
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('Admin')) {
            // Trả về tất cả người dùng nếu là admin
            $users = User::all();
        } elseif ($user->hasRole('Manager')) {
            // Trả về tất cả staff và chính manager
            $users = User::whereHas('role', function ($query) {
                $query->where('name', 'Staff');
            })->orWhere('id', $user->id)->get();
        } elseif ($user->hasRole('Staff')) {
            // Thêm quyền để role 3 (Staff) cũng có thể xem tất cả người dùng
            $users = User::all();  // Thay vì chỉ trả về chính người dùng đó
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($users);
    }
    // Lấy thông tin chi tiết của một người dùng
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $requestedUser = User::find($id);

        if (!$requestedUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->role_id == 1 || $user->role_id == 2) {  // Admin
            return response()->json($requestedUser); // Admin sees all details
        } elseif ($user->role_id == 3) {  // Manager or Staff
            $sharedTasks = $user->tasks->pluck('id');
            $isSameTask = Comment::whereIn('task_id', $sharedTasks)->where('user_id', $requestedUser->id)->exists();

            if ($isSameTask || $requestedUser->role_id == 1) {
                return response()->json([
                    'fullname' => $requestedUser->fullname,
                    'role' => $requestedUser->role_id,
                    'comments' => $requestedUser->comments->map(function ($comment) {
                        return [
                            'content' => $comment->content,
                            'task_id' => $comment->task_id,
                        ];
                    }),
                ]);
            }
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
    // Tạo mới một người dùng
    public function store(StoreUserRequest $request)
    {
        try {
            // Lấy dữ liệu đã xác thực từ StoreUserRequest
            $validatedData = $request->validated();

            // Chuẩn hóa số điện thoại
            $phoneNumber = $validatedData['phone_number'];
            if (substr($phoneNumber, 0, 1) === '0') {
                $phoneNumber = '84' . substr($phoneNumber, 1);
            } elseif (substr($phoneNumber, 0, 1) !== '8') {
                $phoneNumber = '84' . $phoneNumber;
            }

            // Kiểm tra và xử lý tệp avatar nếu có
            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $avatarFile = $request->file('avatar');

                // Đặt tên tệp avatar duy nhất với thời gian hiện tại và tên gốc
                $avatarFileName = time() . '_' . $avatarFile->getClientOriginalName();

                // Lưu tệp vào thư mục public/avatar
                $avatarPath = $avatarFile->storeAs('avatar', $avatarFileName, 'public');
            }

            // Tạo mã xác nhận ngẫu nhiên
            $verificationCode = Str::random(6); // Mã xác nhận ngẫu nhiên

            // Tạo user mới với avatar path (nếu có)
            $user = User::create([
                'fullname' => $validatedData['fullname'],
                'email' => $validatedData['email'],
                'password' => bcrypt($validatedData['password']), // Mã hóa mật khẩu
                'phone_number' => $phoneNumber, // Số điện thoại chuẩn hóa lưu vào cơ sở dữ liệu
                'avatar' => $avatarPath, // Lưu đường dẫn avatar
                'verification_code' => $verificationCode, // Lưu mã xác nhận
                'verification_code_expires_at' => now()->addMinutes(3), // Ví dụ: mã xác minh hết hạn sau 10 phút
                'otp_expires_at' => now()->addMinutes(5), // OTP hết hạn sau 5 phút
            ]);

            // Gửi email xác nhận
            Mail::to($user->email)->send(new VerifyEmailMail($user, $verificationCode));

            // Thêm người dùng vào danh sách Mailchimp
            $mailchimpService = new MailchimpService();
            $mailchimpService->addToList($user->email, env('MAILCHIMP_LIST_ID'));

            // Kiểm tra nếu có user đăng nhập
            $currentUserId = Auth::check() ? Auth::user()->id : null;

            // Ghi lại lịch sử hoạt động sau khi tạo user thành công
            ActivityLog::create([
                'user_id' => $currentUserId, // Người dùng thực hiện thao tác (nếu có auth)
                'loggable_id' => $user->id, // ID của user vừa được tạo
                'loggable_type' => 'App\Models\User', // Loại đối tượng (User)
                'action' => 'created', // Hành động được thực hiện (tạo user)
                'changes' => json_encode($request->except('password')), // Lưu lại dữ liệu đã gửi (không lưu password)
            ]);

            // Trả về phản hồi thành công
            return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
        } catch (\Exception $e) {
            // Trả về lỗi nếu có ngoại lệ
            return response()->json(['error' => 'Failed to create user: ' . $e->getMessage()], 500);
        }
    }

    public function verify($id, $hash)
    {
        // Tìm người dùng theo ID
        $user = User::findOrFail($id);

        // Kiểm tra mã hash có hợp lệ không
        if (sha1($user->email) !== $hash) {
            return response()->json(['error' => 'Invalid verification link.'], 400);
        }

        // Kiểm tra xem email đã được xác nhận chưa
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }
        // Kiểm tra mã đã hết hạn chưa
        if ($user->verification_code_expires_at < now()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your verification code has expired. Please request a new one.'
            ], 400);
        }
        // Đánh dấu email đã được xác nhận
        $user->markEmailAsVerified();
        $user->update([
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);
        // Cập nhật trạng thái thành 'subscribed' trên Mailchimp
        $mailchimpService = new MailchimpService();
        $mailchimpService->updateMemberStatus($user->email, 'subscribed');

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully.'
        ], 200);
    }

    public function requestDeleteAccount(Request $request)
    {
        $user = $request->user();  // Lấy thông tin người dùng đang đăng nhập

        // Kiểm tra nếu người dùng đã yêu cầu xóa tài khoản
        if ($user->delete_token) {
            return response()->json(['message' => 'You have already requested to delete your account. Please check your email.'], 400);
        }

        // Tạo token xác nhận
        $token = Str::random(60);
        $user->delete_token = $token;  // Lưu token vào cơ sở dữ liệu (trong cột 'delete_token')
        $user->save();

        // Gửi email xác nhận xóa tài khoản
        try {
            Mail::to($user->email)->send(new DeleteAccountConfirmation($user));
            return response()->json(['message' => 'Confirmation email sent.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send confirmation email: ' . $e->getMessage()], 500);
        }
    }
    public function confirmDeleteAccount($token)
    {
        // Tìm người dùng với token xác nhận
        $user = User::where('delete_token', $token)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid token'], 404);
        }

        // Thực hiện xóa dữ liệu người dùng
        try {
            // Hủy đăng ký người dùng trên Mailchimp
            $mailchimpService = new MailchimpService();
            $mailchimpService->unsubscribeUser($user->email);

            // Xóa mềm người dùng khỏi hệ thống
            $user->delete(); // Hoặc forceDelete() nếu bạn muốn xóa vĩnh viễn
            // Gửi email thông báo về việc xóa tài khoản
            Mail::to($user->email)->send(new AccountDeleted($user));
            // Xóa tất cả các dữ liệu liên quan (như công việc, dự án, phòng ban)
            Project::where('user_id', $user->id)->delete();
            $user->task()->detach();
            Department::whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })->detach();

            // Ghi lại lịch sử hoạt động
            ActivityLog::create([
                'user_id' => $user->id, // Người thực hiện thao tác (người dùng tự xóa tài khoản)
                'loggable_id' => $user->id,
                'loggable_type' => 'App\Models\User',
                'action' => 'deleted',
                'changes' => json_encode($user->toArray()),
            ]);

            return response()->json(['message' => 'Account deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete account: ' . $e->getMessage()], 500);
        }
    }

    // Cập nhật thông tin người dùng
    public function update(UpdateUserRequest $request, $id)
    {
        $user = $request->user(); // Người đang thực hiện cập nhật
        $requestedUser = User::find($id); // Người dùng được cập nhật

        if (!$requestedUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Lấy dữ liệu đã xác thực từ request
        $updatedData = $request->validated();

        // Bỏ qua bất kỳ thay đổi nào liên quan đến avatar và mật khẩu, email nếu không có trong request
        unset($updatedData['avatar']); // Không cập nhật avatar

        if (!$request->has('password')) {
            unset($updatedData['password']); // Không thay đổi mật khẩu nếu không có trong request
        }

        if (!$request->has('email')) {
            unset($updatedData['email']); // Không thay đổi email nếu không có trong request
        }

        // Kiểm tra và chuyển đổi số điện thoại nếu có
        if (isset($updatedData['phone_number'])) {
            $phone = $updatedData['phone_number'];

            // Kiểm tra nếu số điện thoại bắt đầu với '0'
            if (substr($phone, 0, 1) === '0') {
                // Chuyển số điện thoại từ 0xxxx thành 84xxxx
                $updatedData['phone_number'] = '84' . substr($phone, 1);
            }

            // Kiểm tra trùng lặp số điện thoại
            $existingPhone = User::where('phone_number', $updatedData['phone_number'])
                ->where('id', '!=', $id) // Đảm bảo không kiểm tra trùng với chính người dùng đang sửa
                ->exists();

            if ($existingPhone) {
                return response()->json(['message' => 'Số điện thoại đã tồn tại'], 422);
            }
        }

        // Tiếp tục kiểm tra quyền hạn người dùng và cập nhật dữ liệu như bình thường
        if ($user->hasRole('Admin')) {
            // Nếu là Admin, có quyền cập nhật mọi thông tin, bao gồm cả role
            $requestedUser->update($updatedData);
        } elseif ($user->hasRole('Manager')) {
            // Manager chỉ được phép chỉnh sửa thông tin của Staff và chính mình, nhưng không được thay đổi role thành Admin
            if ($requestedUser->hasRole('Staff') || $user->id == $requestedUser->id) {
                // Nếu có `role_id` trong yêu cầu và nó không phải `Admin` (role_id = 1)
                if (isset($updatedData['role_id']) && $updatedData['role_id'] == 1) {
                    return response()->json(['message' => 'Unauthorized to assign Admin role'], 403);
                }
                $requestedUser->update($updatedData);
            } else {
                return response()->json(['message' => 'Unauthorized to update this user'], 403);
            }
        } elseif ($user->hasRole('Staff') && $user->id == $requestedUser->id) {
            // Staff chỉ được phép chỉnh sửa thông tin của chính mình
            unset($updatedData['role_id']); // Bỏ qua mọi thay đổi liên quan đến role
            $requestedUser->update($updatedData);
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ghi lại hoạt động vào ActivityLog sau khi cập nhật user thành công
        ActivityLog::create([
            'user_id' => $user->id, // Người thực hiện
            'loggable_id' => $requestedUser->id, // Người dùng được cập nhật
            'loggable_type' => 'App\Models\User',
            'action' => 'updated',
            'changes' => json_encode($updatedData), // Lưu lại các thay đổi
        ]);

        return response()->json(['message' => 'User updated successfully', 'user' => $requestedUser]);
    }

    public function updateAvatar(Request $request, $id)
    {
        // Tìm người dùng theo ID
        $user = User::find($id);
    
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
    
        // Kiểm tra xem có file ảnh không
        if ($request->hasFile('avatar')) {
            try {
                // Xóa ảnh cũ nếu đã có
                if ($user->avatar) {
                    // Xóa ảnh cũ từ thư mục public/avatar
                    $oldAvatarPath = public_path('avatar/' . $user->avatar);
                    if (file_exists($oldAvatarPath)) {
                        unlink($oldAvatarPath); // Xóa tệp ảnh cũ
                        Log::info('Old avatar deleted:', ['avatar' => $user->avatar]); // Log thông tin ảnh cũ đã xóa
                    }
                }
    
                // Lưu avatar mới vào thư mục public/avatar
                $avatarFile = $request->file('avatar');
                $avatarFileName = time() . '_' . $avatarFile->getClientOriginalName();
                $avatarPath = public_path('avatar/' . $avatarFileName); // Đường dẫn tuyệt đối tới ảnh mới
    
                // Di chuyển file ảnh vào thư mục public/avatar
                $avatarFile->move(public_path('avatar'), $avatarFileName);
    
                // Cập nhật đường dẫn mới vào cơ sở dữ liệu (chỉ cần lưu tên file ảnh)
                $user->avatar = $avatarFileName;
                $user->save();
    
                Log::info('New avatar path saved:', ['avatar' => $avatarPath]); // Log thông tin ảnh mới
    
                return response()->json(['message' => 'Avatar updated successfully', 'avatar' => $avatarFileName]);
            } catch (\Exception $e) {
                Log::error('Failed to update avatar: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to update avatar: ' . $e->getMessage()], 500);
            }
        }
    
        return response()->json(['message' => 'No avatar file provided'], 400);
    }

    // Xóa người dùng (mềm)
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $requestedUser = User::find($id);

        if (!$requestedUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Danh sách các liên kết ngoại
        $relatedData = [];

        // Kiểm tra xem user có liên kết với các dự án không
        $relatedProjects = Project::where('user_id', $id)->pluck('id')->toArray();
        if (!empty($relatedProjects)) {
            $relatedData['projects'] = $relatedProjects;
        }

        // Kiểm tra các công việc liên kết với user (nếu có)
        $relatedTasks = $requestedUser->tasks()->pluck('tasks.id')->toArray();
        if (!empty($relatedTasks)) {
            $relatedData['tasks'] = $relatedTasks;
        }

        // Kiểm tra liên kết với phòng ban (departments)
        $relatedDepartments = Department::whereHas('users', function ($query) use ($id) {
            $query->where('users.id', $id);
        })->pluck('department_name', 'id')->toArray();

        if (!empty($relatedDepartments)) {
            $relatedData['departments'] = $relatedDepartments;
        }

        // Nếu có dữ liệu liên quan, trả về thông báo lỗi
        if (!empty($relatedData)) {
            return response()->json([
                'message' => 'Cannot delete user because of existing related data.',
                'related_data' => $relatedData
            ], 400);
        }

        // Chỉ Admin có thể xóa người dùng
        if ($user->role_id == 1) {
            $requestedUser->delete(); // Xóa mềm người dùng

            // Ghi lại lịch sử hoạt động sau khi xóa mềm user thành công
            ActivityLog::create([
                'user_id' => $user->id, // Người thực hiện thao tác
                'loggable_id' => $requestedUser->id, // Người dùng bị xóa
                'loggable_type' => 'App\Models\User',
                'action' => 'deleted',
                'changes' => json_encode($requestedUser->toArray()), // Lưu lại thông tin trước khi xóa
            ]);

            return response()->json(['message' => 'User soft deleted successfully']);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // xóa vĩnh viễn người dùng
    public function forceDestroy(Request $request, $id)
    {
        $requestedUser = User::withTrashed()->find($id);

        if (!$requestedUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Danh sách các liên kết ngoại
        $relatedData = [];

        // Kiểm tra xem user có liên kết với các dự án không
        $relatedProjects = Project::where('user_id', $id)->pluck('id')->toArray();
        if (!empty($relatedProjects)) {
            $relatedData['projects'] = $relatedProjects;
        }

        // Kiểm tra các công việc liên kết với user (nếu có)
        $relatedTasks = $requestedUser->tasks()->withTrashed()->pluck('tasks.id')->toArray();
        if (!empty($relatedTasks)) {
            $relatedData['tasks'] = $relatedTasks;
        }

        // Kiểm tra liên kết với phòng ban (departments)
        $relatedDepartments = Department::whereHas('users', function ($query) use ($id) {
            $query->where('users.id', $id);
        })->pluck('department_name', 'id')->toArray();

        if (!empty($relatedDepartments)) {
            $relatedData['departments'] = $relatedDepartments;
        }

        // Nếu có dữ liệu liên quan, trả về thông báo lỗi
        if (!empty($relatedData)) {
            return response()->json([
                'message' => 'Cannot delete user because of existing related data.',
                'related_data' => $relatedData
            ], 400);
        }

        // Thực hiện xóa vĩnh viễn nếu không có liên kết
        try {
            $requestedUser->forceDelete();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'loggable_id' => $requestedUser->id,
                'loggable_type' => 'App\Models\User',
                'action' => 'force_deleted',
                'changes' => json_encode($requestedUser->toArray()),
            ]);

            return response()->json(['message' => 'User permanently deleted']);
        } catch (\Exception $e) {
            Log::error('Failed to force delete user: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete user: ' . $e->getMessage()], 500);
        }
    }

    // khôi phục người dùng đã xóa mềm
    public function restore($id)
    {
        $requestedUser = User::withTrashed()->find($id);

        if (!$requestedUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Khôi phục người dùng
        $requestedUser->restore();

        return response()->json(['message' => 'User restored successfully']);
    }

    // lấy danh sách người dùng đã bị xóa mềm
    public function trashedUsers()
    {
        $trashedUsers = User::onlyTrashed()->get();
        return response()->json($trashedUsers);
    }
    // Đăng ký
    public function register(StoreUserRequest $request)
    {
        // // Tạo mã xác nhận ngẫu nhiên
        // $verificationCode = Str::random(6);

        // Tạo user mới với mã xác nhận và thời gian hết hạn
        $user = User::create([
            'fullname' => $request->fullname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            // 'verification_code' => $verificationCode,
            'verification_code_expires_at' => now()->addMinutes(3), // Mã xác minh hết hạn sau 10 phút
        ]);

        // Gửi email xác nhận
        Mail::to($user->email)->send(new VerifyEmailMail($user));
        // Thêm người dùng vào danh sách Mailchimp
        $mailchimpService = new MailchimpService();
        $mailchimpService->addToList($user->email, env('MAILCHIMP_LIST_ID'));

        // Kiểm tra nếu có user đăng nhập
        $currentUserId = Auth::check() ? Auth::user()->id : null;

        // Ghi lại lịch sử hoạt động sau khi tạo user thành công
        ActivityLog::create([
            'user_id' => $currentUserId, // Người dùng thực hiện thao tác (nếu có auth)
            'loggable_id' => $user->id, // ID của user vừa được tạo
            'loggable_type' => 'App\Models\User', // Loại đối tượng (User)
            'action' => 'created', // Hành động được thực hiện (tạo user)
            'changes' => json_encode($request->except('password')), // Lưu lại dữ liệu đã gửi (không lưu password)
        ]);
        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully. Please check your email to verify your account.',
        ], 201);
    }

    // Đăng nhập
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();

        // Kiểm tra xem người dùng có tồn tại và mật khẩu có đúng không
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }
        // Kiểm tra nếu mã OTP đã hết hạn
        if (!$user->hasVerifiedEmail() && $user->verification_code_expires_at < now()) {
            // Nếu mã OTP đã hết hạn, đặt lại mã và thời gian hết hạn thành null
            $user->update([
                'verification_code' => null,
                'verification_code_expires_at' => null,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Verification code expired. Please request a new one.',
            ], 400);
        }

        // Kiểm tra xem email người dùng đã được xác thực chưa
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email not verified. Please verify your email first.',
            ], 400); // Trả về mã lỗi 400 nếu email chưa được xác thực
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        $roleName = $user->role ? $user->role->name : null;
        $userId = $user->id;
        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'role' => $roleName,
            'user_id' => $userId,
            'fullname' => $user->fullname,
        ]);
    }

    // Đăng xuất
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }

    public function resendVerificationCode(Request $request)
    {
        // Lấy email từ request hoặc session
        $email = $request->email ?? session('user_email'); // Sử dụng email từ request hoặc session

        // Kiểm tra xem email có tồn tại không
        if (!$email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email is required.'
            ], 400);
        }

        // Lấy người dùng dựa trên email
        $user = User::where('email', $email)->first();

        // Kiểm tra xem người dùng có tồn tại hay không
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }

        // Kiểm tra xem mã xác nhận còn hiệu lực hay chưa
        if ($user->verification_code_expires_at > now()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã xác minh của bạn vẫn hợp lệ. Vui lòng sử dụng mã hiện có.'
            ], 400);
        }

        // Tạo mã xác nhận mới

        // Cập nhật mã xác nhận mới và thời gian hết hạn (10 phút sau)
        $user->update([
            'verification_code_expires_at' => now()->addMinutes(3),
        ]);

        // Gửi lại email xác nhận với mã mới
        Mail::to($user->email)->send(new VerifyEmailMail($user));

        return response()->json([
            'status' => 'success',
            'message' => 'Mã xác minh mới đã được gửi đến email của bạn.'
        ], 200);
    }

    public function handleGoogleLogin(Request $request)
    {
        try {
            $googleToken = $request->input('credential');

            // Xác minh token Google
            $client = new \Google\Client();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $payload = $client->verifyIdToken($googleToken);

            if (!$payload) {
                return response()->json(['message' => 'Invalid Google token'], 400);
            }

            $email = $payload['email'];
            $fullname = $payload['name'];
            $googleId = $payload['sub']; // Lấy Google ID từ payload

            // Tìm người dùng trong cơ sở dữ liệu dựa trên email hoặc google_id
            $user = User::where('email', $email)->orWhere('google_id', $googleId)->first();

            if ($user) {
                // Cập nhật Google ID nếu chưa có
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleId]);
                }

                // Kiểm tra xem email đã được xác minh chưa
                if (!$user->email_verified_at) {
                    // Nếu mã xác minh hết hạn hoặc chưa có, tạo mã mới
                    if (!$user->verification_code_expires_at || $user->verification_code_expires_at <= now()) {
                        $user->update([
                            'verification_code_expires_at' => now()->addMinutes(3),
                        ]);
                    }

                    // Gửi email xác nhận
                    Mail::to($user->email)->send(new VerifyEmailMail($user));
                    // Lưu email vào session để xử lý khi resend
                    session(['user_email' => $user->email]);

                    return response()->json([
                        'message' => 'Please verify your email to continue.',
                        'status' => 'verification_required',
                    ]);
                }

                // Nếu đã xác minh email, đăng nhập người dùng
                $token = $user->createToken('auth_token')->plainTextToken;
                return response()->json([
                    'access_token' => $token,
                    'message' => 'Login successful',
                    'status' => 'verified',
                ]);
            }

            // Nếu người dùng chưa tồn tại, tạo người dùng mới
            $user = User::create([
                'fullname' => $fullname,
                'email' => $email,
                'google_id' => $googleId,
                'password' => Hash::make(Str::random(12)), // Mật khẩu ngẫu nhiên
                'verification_code_expires_at' => now()->addMinutes(3),
            ]);

            // Gửi email xác nhận
            Mail::to($user->email)->send(new VerifyEmailMail($user));
            // Lưu email vào session để xử lý khi resend
            session(['user_email' => $user->email]);
            return response()->json([
                'message' => 'User created. Please verify your email.',
                'status' => 'verification_required',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error processing Google login', 'error' => $e->getMessage()], 500);
        }
    }
    public function hello(){
        return response()->json('hello world!');
    }
    
}
