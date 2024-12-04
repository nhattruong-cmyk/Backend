<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\JoinDepartmentMail;
use App\Models\ConfirmationRequest;
use Illuminate\Support\Str;
use App\Http\Controllers\AuthorizationException;
use App\Models\DepartmentConfirmation;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ConfirmRequestController;

class DepartmentController extends Controller
{
    public function index()
    {
        // Kiểm tra quyền của người dùng (sử dụng Policy)
        $this->authorize('viewAny', Department::class);

        // Lấy phòng ban theo vai trò người dùng
        $user = auth()->user();

        if ($user->role_id === 1 || $user->role_id === 2) {
            // Nếu người dùng là Admin hoặc Manager, lấy tất cả phòng ban
            $departments = Department::with('users')->get();
        } elseif ($user->role_id === 3) {
            // Nếu người dùng là Staff, chỉ lấy phòng ban mà họ là thành viên
            $departments = $user->departments()->with('users')->get();
        } else {
            // Trả về lỗi nếu người dùng không có quyền truy cập
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Trả về kết quả dưới dạng JSON
        return response()->json($departments);
    }

    public function store(StoreDepartmentRequest $request)
    {
        try {
            // Kiểm tra quyền của người dùng (sử dụng Policy)
            $this->authorize('create', Department::class); // Sử dụng policy để kiểm tra quyền create

            // Dữ liệu đã được xác thực bởi StoreDepartmentRequest
            $validatedData = $request->validated();

            // Tạo phòng ban mới
            $department = Department::create($validatedData);

            // Nếu người dùng có role_id = 3, thêm người tạo vào phòng ban và cập nhật cột create_by
            if ($request->user()->role_id == 3) {
                // Thêm người tạo vào phòng ban
                $department->users()->attach($request->user()->id);

                // Thêm giá trị ngẫu nhiên vào cột create_by của user
                $randomValue = rand(1000, 9999); // Sinh một số ngẫu nhiên từ 1000 đến 9999
                $request->user()->update(['create_by' => $randomValue]); // Cập nhật cột create_by

                // Lấy lại thông tin người tạo để trả về
                $userWithCreateBy = $request->user()->fresh(); // Lấy lại thông tin người dùng đã được cập nhật

                // Tạo yêu cầu xác nhận và gán trạng thái 'confirmed'
                ConfirmationRequest::create([
                    'user_id' => $request->user()->id,
                    'department_id' => $department->id,
                    'status' => 'confirmed',  // Đặt trạng thái là confirmed ngay khi tạo phòng ban
                    'confirmation_token' => Str::random(32), // Tạo token xác nhận ngẫu nhiên
                ]);
            }

            // Nếu có danh sách người dùng và người dùng có role_id = 1 hoặc 2, gán họ vào phòng ban
            if ($request->has('user_ids') && ($request->user()->role_id == 1 || $request->user()->role_id == 2)) {
                $department->users()->sync($request->input('user_ids'));
            }

            // Gửi thông báo cho các người dùng đã được thêm vào phòng ban
            $users = $department->users;
            foreach ($users as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'message' => "Bạn đã được thêm vào phòng ban '{$department->department_name}'",
                    'read' => false
                ]);
            }

            // Trả về thông tin phòng ban đã tạo, bao gồm thông tin của người tạo
            return response()->json([
                'message' => 'Department created successfully',
                'department' => $department->load('users'),
                'created_by' => $userWithCreateBy->create_by, // Trả về giá trị created_by của người tạo
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Bắt lỗi phân quyền, trả về thông báo không đủ quyền
            return response()->json([
                'error' => 'You do not have permission to create a department.'
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Bắt lỗi xác thực và trả về thông báo lỗi
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Bắt lỗi chung và trả về thông báo lỗi
            return response()->json([
                'error' => 'Failed to create department: ' . $e->getMessage()
            ], 500);
        }
    }



    public function getUsersWithStatus($departmentId)
    {
        $users = DB::table('confirmation_requests')
            ->join('users', 'confirmation_requests.user_id', '=', 'users.id')
            ->select('users.id', 'users.fullname', 'confirmation_requests.status as confirmation_status')
            ->where('confirmation_requests.department_id', $departmentId)
            ->get();

        return response()->json($users);
    }

    public function removeUserFromDepartment($departmentId, $userId)
    {
        // Gọi phương thức xóa người dùng khỏi phòng ban
        $this->deleteUserFromDepartment($departmentId, $userId);

        // Gọi phương thức xóa yêu cầu xác nhận
        $this->deleteRequest($departmentId, $userId);

        return response()->json(['message' => 'Người dùng đã được xóa khỏi phòng ban và yêu cầu xác nhận đã được xóa thành công.']);
    }

    // Phương thức xóa người dùng khỏi phòng ban
    public function deleteUserFromDepartment($departmentId, $userId)
    {
        $department = Department::find($departmentId);

        if (!$department) {
            return response()->json(['message' => 'Không tìm thấy phòng ban.'], 404);
        }

        // Tìm và xóa người dùng khỏi phòng ban
        $user = $department->users()->find($userId);

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng trong phòng ban này.'], 404);
        }

        $department->users()->detach($userId);  // Giả sử bạn dùng quan hệ nhiều - nhiều giữa departments và users

        return response()->json(['message' => 'Người dùng đã được xóa khỏi phòng ban thành công.']);
    }

    // Phương thức xóa yêu cầu xác nhận của người dùng
    public function deleteRequest($departmentId, $userId)
    {
        $request = ConfirmationRequest::where('department_id', $departmentId)
            ->where('user_id', $userId)
            ->first();

        if ($request) {
            $request->delete();
            return response()->json(['message' => 'Yêu cầu xác nhận đã được xóa thành công.']);
        }

        return response()->json(['message' => 'Không tìm thấy yêu cầu xác nhận.'], 404);
    }


    public function confirmUser($department_id, $token)
    {
        try {
            // Tìm yêu cầu xác nhận
            $confirmation = ConfirmationRequest::where('confirmation_token', $token)
                ->where('department_id', $department_id)
                ->first();

            if (!$confirmation) {
                return response()->json(['error' => 'Liên kết xác nhận không hợp lệ hoặc đã hết hạn.'], 400);
            }

            // Kiểm tra trạng thái yêu cầu
            if ($confirmation->status === 'confirmed') {
                return response()->json(['message' => 'Bạn đã tham gia phòng ban thành công.'], 200);
            }

            // Thực hiện xác nhận
            $confirmation->update(['status' => 'confirmed']);

            // Kiểm tra người dùng đã tham gia phòng ban chưa
            $department = Department::findOrFail($department_id);
            if ($department->users()->where('user_id', $confirmation->user_id)->exists()) {
                return response()->json(['message' => 'Người dùng đã là thành viên phòng ban này.'], 400);
            }

            $department->users()->attach($confirmation->user_id);

            return response()->json(['message' => 'Bạn đã tham gia phòng ban thành công!'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Đã xảy ra lỗi: ' . $e->getMessage()], 500);
        }
    }

    public function addUsersToDepartment(Request $request, $department_id)
    {
        try {
            // Kiểm tra phòng ban có tồn tại
            $department = Department::findOrFail($department_id);

            // Kiểm tra quyền truy cập
            if ($request->user()->role_id === 1 || $request->user()->role_id === 2 || $request->user()->role_id === 3) {
                $this->authorize('update', $department); // Kiểm tra quyền sửa đổi phòng ban
            } else {
                return response()->json([
                    'error' => 'You do not have permission to add users to this department.'
                ], 403);
            }

            // Xác thực đầu vào
            $validatedData = $request->validate([
                'user_ids' => 'sometimes|array',
                'user_ids.*' => 'integer|exists:users,id',
                'user_id' => 'sometimes|integer|exists:users,id'
            ], [
                'user_ids.*.exists' => 'Một hoặc nhiều user không tồn tại trong hệ thống.',
                'user_id.exists' => 'User không tồn tại trong hệ thống.'
            ]);

            // Kiểm tra xem `user_ids` hay `user_id` được cung cấp
            $userIds = array_merge($validatedData['user_ids'] ?? [], isset($validatedData['user_id']) ? [$validatedData['user_id']] : []);

            // Lưu yêu cầu xác nhận và thêm người dùng vào phòng ban
            foreach ($userIds as $userId) {
                $confirmationToken = Str::random(32); // Tạo mã xác nhận ngẫu nhiên

                // Tạo yêu cầu xác nhận
                ConfirmationRequest::create([
                    'user_id' => $userId,
                    'department_id' => $department_id,
                    'confirmation_token' => $confirmationToken,
                    'status' => 'pending',
                ]);

                // Gửi email xác nhận
                $user = User::find($userId);
                if ($user && $user->email) {
                    Mail::to($user->email)->send(new JoinDepartmentMail($user, $department, $confirmationToken));
                }

                // Thêm người dùng vào phòng ban (nếu cần)
                $department->users()->attach($userId);
            }

            return response()->json([
                'message' => 'Users have been added to the confirmation queue and to the department.',
            ], 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'You do not have permission to add users to this department.'
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add users to department: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateDepartmentRequest $request, $departmentId)
    {
        try {
            // Tìm phòng ban theo ID
            $department = Department::findOrFail($departmentId);

            // Lấy người dùng hiện tại
            $user = auth()->user();

            // Kiểm tra quyền cập nhật phòng ban (sử dụng phương thức hasPermission trong model)
            if (!$department->hasPermission($user->role_id)) {
                return response()->json([
                    'error' => 'Bạn không có quyền cập nhật phòng ban này.'
                ], 403);
            }

            $validatedData = $request->validated();

            // Lưu trữ thông tin ban đầu của phòng ban để so sánh sau khi cập nhật
            $originalName = $department->department_name;
            $originalDescription = $department->description;

            // Cập nhật tên và mô tả của phòng ban nếu có
            $department->update($validatedData);

            // Kiểm tra nếu có thay đổi tên hoặc mô tả phòng ban
            $hasNameChanged = isset($validatedData['department_name']) && $originalName !== $validatedData['department_name'];
            $hasDescriptionChanged = isset($validatedData['description']) && $originalDescription !== $validatedData['description'];

            // Gửi thông báo nếu có thay đổi tên hoặc mô tả
            if ($hasNameChanged || $hasDescriptionChanged) {
                $message = 'Phòng ban của bạn đã có cập nhật mới: ';
                if ($hasNameChanged) {
                    $message .= "Tên phòng ban đã được đổi từ '{$originalName}' thành '{$department->department_name}'. ";
                }
                if ($hasDescriptionChanged) {
                    $message .= 'Mô tả phòng ban đã được thay đổi.';
                }

                // Gửi thông báo đến tất cả các user trong phòng ban
                foreach ($department->users as $user) {
                    Notification::create([
                        'user_id' => $user->id,
                        'message' => $message,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Phòng ban đã được cập nhật thành công.',
                'department' => $department->load('users')
            ], 200);
        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'Bạn không có quyền cập nhật phòng ban này.'
            ], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Cập nhật phòng ban thất bại: ' . $e->getMessage()
            ], 500);
        }
    }

    // Phương thức kiểm tra quyền cập nhật phòng ban
    private function hasPermissionToUpdate(User $user, Department $department): bool
    {
        // Admin có thể cập nhật tất cả các phòng ban
        if ($user->role_id === 1) {
            return true;
        }

        // Manager chỉ có thể cập nhật phòng ban mà họ quản lý
        if ($user->role_id === 2) {
            return true;
        }

        // Staff chỉ có thể cập nhật phòng ban nếu họ là thành viên trong phòng ban
        if ($user->role_id === 3) {
            return true;
        }

        // Nếu không có quyền, trả về false
        return false;
    }


    public function show($id)
    {
        try {
            // Tìm phòng ban theo ID, kèm theo thông tin người dùng và nhiệm vụ
            $department = Department::with('users', 'tasks')->find($id);

            // Kiểm tra nếu phòng ban không tồn tại
            if (!$department) {
                return response()->json(['message' => 'Department not found'], 404);
            }

            // Kiểm tra quyền của người dùng (sử dụng Policy)
            $this->authorize('view', $department); // Kiểm tra quyền xem phòng ban

            return response()->json($department);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Nếu người dùng không có quyền, trả về lỗi 403
            return response()->json([
                'error' => 'You do not have permission to view this department.'
            ], 403);
        } catch (\Exception $e) {
            // Bắt lỗi chung và trả về thông báo lỗi
            return response()->json([
                'error' => 'Failed to retrieve department: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Tìm phòng ban theo ID
            $department = Department::findOrFail($id);

            // Kiểm tra quyền của người dùng (sử dụng Policy)
            $this->authorize('delete', $department); // Kiểm tra quyền xóa phòng ban

            // Lấy tất cả user trong phòng ban
            $usersInDepartment = $department->users;

            // Xóa tất cả liên kết giữa user và phòng ban trong bảng department_user
            $department->users()->detach(); // Gỡ tất cả user khỏi phòng ban

            // Thực hiện xóa mềm (soft delete) phòng ban
            $department->delete();

            return response()->json(['message' => 'Department soft deleted successfully'], 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Nếu người dùng không có quyền, trả về lỗi 403
            return response()->json([
                'error' => 'You do not have permission to delete this department.'
            ], 403);
        } catch (\Illuminate\Database\QueryException $e) {
            // Bắt lỗi khóa ngoại (nếu có)
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'Department cannot be soft deleted because it is associated with users, projects, or tasks.'
                ], 400);
            }
            return response()->json(['error' => 'Failed to soft delete department: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to soft delete department: ' . $e->getMessage()], 500);
        }
    }

    public function restore($id)
    {
        try {
            // Tìm phòng ban đã xóa mềm
            $department = Department::onlyTrashed()->findOrFail($id);

            // Kiểm tra quyền của người dùng (sử dụng Policy)
            $this->authorize('restore', $department); // Kiểm tra quyền khôi phục phòng ban

            // Thực hiện khôi phục phòng ban
            $department->restore();

            return response()->json(['message' => 'Department restored successfully'], 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Nếu người dùng không có quyền, trả về lỗi 403
            return response()->json([
                'error' => 'You do not have permission to restore this department.'
            ], 403);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore department: ' . $e->getMessage()], 500);
        }
    }

    public function forceDelete($id)
    {
        try {
            // Tìm phòng ban đã bị xóa mềm
            $department = Department::onlyTrashed()->findOrFail($id);

            // Kiểm tra quyền của người dùng (sử dụng Policy)
            $this->authorize('forceDelete', $department); // Kiểm tra quyền xóa cứng phòng ban

            // Thực hiện xóa cứng phòng ban
            $department->forceDelete();

            return response()->json(['message' => 'Department permanently deleted successfully'], 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Nếu người dùng không có quyền, trả về lỗi 403
            return response()->json([
                'error' => 'You do not have permission to permanently delete this department.'
            ], 403);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => 'Failed to permanently delete department: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to permanently delete department: ' . $e->getMessage()], 500);
        }
    }
}
