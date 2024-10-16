<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;


class UserController extends Controller
{
    // Lấy danh sách tất cả người dùng
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role_id == 1) { // Admin
            // Trả về tất cả người dùng nếu là admin
            $users = User::all();
            return response()->json($users);
        } elseif ($user->role_id == 2) { // Manager
            // Trả về tất cả nhân viên (role_id = 3) nếu là manager
            $users = User::where('role_id', 3)->get();
            return response()->json($users);
        } else {
            // Nếu là Staff thì không được phép xem danh sách người dùng khác
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    }
    // Lấy thông tin chi tiết của một người dùng
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $requestedUser = User::find($id);

        if (!$requestedUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Admin có thể xem thông tin của tất cả mọi người
        if ($user->role_id == 1) {
            return response()->json($requestedUser);
        }

        // Manager có thể xem thông tin của staff hoặc chính mình
        if ($user->role_id == 2) {
            if ($requestedUser->role_id == 3 || $user->id == $requestedUser->id) {
                return response()->json($requestedUser);
            }
        }

        // Staff chỉ được xem thông tin của chính mình
        if ($user->role_id == 3 && $user->id == $requestedUser->id) {
            return response()->json($requestedUser);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
    // Tạo mới một người dùng
    public function store(StoreUserRequest $request)
    {
        try {
            // Lấy dữ liệu đã xác thực từ StoreUserRequest
            $validatedData = $request->validated();

            // Tạo user mới
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => bcrypt($validatedData['password']), // Mã hóa mật khẩu
            ]);

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


    // Cập nhật thông tin người dùng
    public function update(UpdateUserRequest $request, $id)
    {
        $user = $request->user(); // Người đang thực hiện cập nhật
        $requestedUser = User::find($id); // Người dùng được cập nhật

        if (!$requestedUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Admin có thể sửa thông tin của tất cả mọi người
        if ($user->role_id == 1) {
            $requestedUser->update($request->validated());

            // Ghi lại lịch sử hoạt động sau khi cập nhật user thành công
            ActivityLog::create([
                'user_id' => $user->id, // Người thực hiện
                'loggable_id' => $requestedUser->id, // Người dùng được cập nhật
                'loggable_type' => 'App\Models\User',
                'action' => 'updated',
                'changes' => json_encode($request->validated()), // Lưu lại các thay đổi
            ]);

            return response()->json(['message' => 'User updated successfully', 'user' => $requestedUser]);
        }

        // Manager có thể sửa thông tin của tất cả Staff hoặc chính mình
        if ($user->role_id == 2) {
            if ($requestedUser->role_id == 3 || $user->id == $requestedUser->id) {
                $requestedUser->update($request->validated());

                ActivityLog::create([
                    'user_id' => $user->id,
                    'loggable_id' => $requestedUser->id,
                    'loggable_type' => 'App\Models\User',
                    'action' => 'updated',
                    'changes' => json_encode($request->validated()),
                ]);

                return response()->json(['message' => 'User updated successfully', 'user' => $requestedUser]);
            }
        }

        // Staff chỉ được sửa thông tin của chính mình
        if ($user->role_id == 3 && $user->id == $requestedUser->id) {
            $requestedUser->update($request->validated());

            ActivityLog::create([
                'user_id' => $user->id,
                'loggable_id' => $requestedUser->id,
                'loggable_type' => 'App\Models\User',
                'action' => 'updated',
                'changes' => json_encode($request->validated()),
            ]);

            return response()->json(['message' => 'User updated successfully', 'user' => $requestedUser]);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }


    // Xóa người dùng
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $requestedUser = User::find($id);

        if (!$requestedUser) {
            return response()->json(['message' => 'User not found'], 404);
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

    public function getTrashedUsers()
    {
        $trashedUsers = User::onlyTrashed()->get();
        return response()->json($trashedUsers);
    }
}
