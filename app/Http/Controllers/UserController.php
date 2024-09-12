<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Lấy danh sách tất cả người dùng
    public function index(Request $request)
    {
        // Lấy người dùng đã đăng nhập
        $user = $request->user();

        // Kiểm tra quyền của người dùng
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

        // Manager chỉ được xem thông tin của staff
        if ($user->role_id == 2 && $requestedUser->role_id == 3) {
            return response()->json($requestedUser);
        }

        // Staff chỉ được xem thông tin của chính mình
        if ($user->role_id == 3 && $user->id == $requestedUser->id) {
            return response()->json($requestedUser);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }


    // Tạo mới một người dùng
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password), // Mã hóa mật khẩu
        ]);

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    // Cập nhật thông tin người dùng
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $requestedUser = User::find($id);

        if (!$requestedUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Admin có thể sửa thông tin của tất cả mọi người
        if ($user->role_id == 1) {
            $requestedUser->update($request->all());
            return response()->json(['message' => 'User updated successfully', 'user' => $requestedUser]);
        }

        // Manager chỉ được sửa thông tin của staff
        if ($user->role_id == 2 && $requestedUser->role_id == 3) {
            $requestedUser->update($request->all());
            return response()->json(['message' => 'User updated successfully', 'user' => $requestedUser]);
        }

        // Staff không được phép chỉnh sửa bất kỳ ai, kể cả chính mình
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
            $requestedUser->delete();
            return response()->json(['message' => 'User deleted successfully']);
        }

        // Các vai trò khác không được phép xóa người dùng
        return response()->json(['message' => 'Unauthorized'], 403);
    }

}
