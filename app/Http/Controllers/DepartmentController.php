<?php

namespace App\Http\Controllers;

use Exception;

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
use App\Notifications\UpdateDepartmentNotification;
use App\Notifications\UserAddedToDepartment;

class DepartmentController extends Controller
{
    public function index()
    {
        $user = auth()->user();
    
        // Kiểm tra quyền của người dùng (sử dụng Policy)
        $this->authorize('viewAny', Department::class); // Đảm bảo có phương thức 'viewAny' trong DepartmentPolicy
    
        // Lấy phòng ban theo vai trò người dùng
        if ($user->role_id === 1 || $user->role_id === 2) {
            // Nếu người dùng là Admin (role_id = 1) hoặc Manager (role_id = 2), lấy tất cả phòng ban
            $departments = Department::with('users')->get();
        } elseif ($user->role_id === 3) {
            // Nếu người dùng là Staff (role_id = 3)
            $createBy = json_decode($user->create_by, true); // Lấy giá trị create_by và chuyển thành mảng
    
            // Lấy các phòng ban mà người dùng đã tạo
            $createdDepartments = collect(); // Tạo một Collection trống
    
            if (!empty($createBy)) {
                $createdDepartments = Department::with('users')
                    ->whereIn('id', $createBy) // Lọc phòng ban mà user đã tạo
                    ->get();
            }
    
            // Lấy các phòng ban mà người dùng là thành viên
            $memberDepartments = $user->departments()->with('users')->get();
    
            // Kết hợp cả hai bộ dữ liệu: các phòng ban đã tạo và các phòng ban thành viên
            $departments = $createdDepartments->merge($memberDepartments); // merge() làm việc với Collection
    
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

            // Nếu người dùng có role_id = 3 (Staff), thêm người tạo vào phòng ban và cập nhật cột create_by
            if ($request->user()->role_id == 3) {
                // Thêm người tạo vào phòng ban (user)
                $department->users()->attach($request->user()->id);

                // Lấy giá trị 'create_by' hiện tại của người dùng
                $createBy = $request->user()->create_by;

                // Nếu 'create_by' không phải là null, kiểm tra xem nó có phải là mảng hay không
                if ($createBy) {
                    $createBy = json_decode($createBy, true) ?: []; // Chuyển đổi thành mảng nếu không phải mảng
                } else {
                    $createBy = [];
                }

                // Thêm ID của phòng ban mới vào mảng create_by
                $createBy[] = $department->id;

                // Cập nhật lại cột 'create_by' của user
                $request->user()->update(['create_by' => json_encode($createBy)]); // Lưu lại mảng các ID phòng ban đã tạo

                // Tạo yêu cầu xác nhận cho người tạo phòng ban
                ConfirmationRequest::create([
                    'user_id' => $request->user()->id,
                    'department_id' => $department->id,
                    'status' => 'confirmed',  // Đặt trạng thái là confirmed ngay khi tạo phòng ban
                    'confirmation_token' => Str::random(32), // Tạo token xác nhận ngẫu nhiên
                ]);
            }

            // Nếu có danh sách người dùng và người dùng có role_id = 1 (Admin) hoặc 2 (Manager), gán họ vào phòng ban
            if ($request->has('user_ids') && in_array($request->user()->role_id, [1, 2])) {
                // Lấy danh sách người dùng được chỉ định từ request
                $userIds = $request->input('user_ids');

                // Gán những người dùng này vào phòng ban
                $department->users()->attach($userIds);
            }

            // Trả về phản hồi thành công với thông tin phòng ban mới được tạo
            return response()->json([
                'message' => 'Department created successfully',
                'department' => $department,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create department: ' . $e->getMessage()], 500);
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
        $this->authorize('delete', $department);

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
                $user = User::findOrFail($userId);
                Mail::to($user->email)->send(new JoinDepartmentMail($user, $department, $confirmationToken));

                // Gửi thông báo cho người dùng về việc thêm vào phòng ban
                $user->notify(new UserAddedToDepartment($department));
            }

            return response()->json([
                'message' => 'Users have been successfully added to the department and confirmation requests sent.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred: ' . $e->getMessage(),
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
            $this->authorize('update', $department);

            // Kiểm tra quyền cập nhật phòng ban
            if (!$department->hasPermission($user->role_id)) {
                return response()->json([
                    'error' => 'Bạn không có quyền cập nhật phòng ban này.'
                ], 403);
            }

            $validatedData = $request->validated();

            // Lưu trữ thông tin ban đầu của phòng ban để so sánh sau khi cập nhật
            $originalName = $department->department_name;
            $originalDescription = $department->description;

            // Cập nhật phòng ban
            $department->update($validatedData);

            // Kiểm tra thay đổi tên hoặc mô tả
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
                foreach ($department->users as $userInDepartment) {
                    // Gửi thông báo qua database cho mỗi user
                    $userInDepartment->notify(new UpdateDepartmentNotification($department));
                }
            }

            return response()->json([
                'success' => 'Cập nhật phòng ban thành công và thông báo đã được gửi tới các thành viên.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Có lỗi xảy ra, vui lòng thử lại sau.'
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

            // Lấy tất cả người dùng trong phòng ban, loại trừ người tạo
            $usersInDepartment = $department->users->pluck('id')->toArray();
            $creatorUserId = $department->create_by;

            // Kiểm tra nếu có người khác ngoài người tạo
            if (count($usersInDepartment) > 1 && !in_array($creatorUserId, $usersInDepartment)) {
                return response()->json([
                    'error' => 'Department cannot be deleted because there are other members besides the creator.'
                ], 400);
            }

            // Thực hiện xóa mềm (soft delete) phòng ban nếu không có lỗi
            // Nếu muốn xóa vĩnh viễn, sử dụng $department->forceDelete();
            $department->delete(); // Xóa mềm (soft delete)

            return response()->json(['message' => 'Department soft deleted successfully'], 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Nếu người dùng không có quyền, trả về lỗi 403
            return response()->json([
                'error' => 'You do not have permission to delete this department.'
            ], 403);
        } catch (\Illuminate\Database\QueryException $e) {
            // Xử lý lỗi khóa ngoại, nếu có
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'Department cannot be deleted due to foreign key constraints.'
                ], 400);
            }
            return response()->json(['error' => 'Failed to delete department: ' . $e->getMessage()], 500);
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

            // Kiểm tra xem có người tạo (create_by) không
            if ($department->create_by) {
                // Lấy người tạo từ bảng users
                $creator = User::find($department->create_by);

                // Thêm người tạo vào phòng ban nếu người tạo tồn tại
                if ($creator) {
                    // Gắn lại người tạo vào phòng ban (chắc chắn người đó chưa có trong phòng ban)
                    if (!$department->users()->where('user_id', $creator->id)->exists()) {
                        $department->users()->attach($creator->id);
                    }
                }
            }

            return response()->json(['message' => 'Department restored and creator added successfully'], 200);
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

            // Lấy danh sách người dùng có liên quan đến phòng ban này
            $usersInDepartment = $department->users;

            // Loại bỏ ID phòng ban khỏi cột create_by trong bảng users của tất cả người dùng
            foreach ($usersInDepartment as $user) {
                if ($user->create_by) {
                    // Kiểm tra xem id của phòng ban có trong mảng create_by không, nếu có thì loại bỏ
                    $createByArray = json_decode($user->create_by, true);

                    if (is_array($createByArray) && ($key = array_search($department->id, $createByArray)) !== false) {
                        // Loại bỏ ID phòng ban khỏi mảng
                        unset($createByArray[$key]);

                        // Cập nhật lại cột create_by với mảng đã loại bỏ phần tử
                        // Dùng array_values để đảm bảo các chỉ số của mảng không bị bỏ trống
                        $user->update(['create_by' => json_encode(array_values($createByArray))]);
                    }
                }
            }

            // Xóa tất cả các liên kết người dùng với phòng ban
            $department->users()->detach(); // Gỡ tất cả user khỏi phòng ban
            // Thực hiện xóa cứng phòng ban
            $department->forceDelete();

            return response()->json(['message' => 'Department permanently deleted successfully'], 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Nếu người dùng không có quyền, trả về lỗi 403
            return response()->json([
                'error' => 'You do not have permission to permanently delete this department.'
            ], 403);
        } catch (\Illuminate\Database\QueryException $e) {
            // Bắt lỗi và trả về thông báo lỗi database
            return response()->json(['error' => 'Failed to permanently delete department: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            // Bắt lỗi chung
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // lấy danh sách dự án đã xóa mềm
    public function getTrashed()
    {
        try {
            // Kiểm tra quyền người dùng trước khi truy vấn
            $this->authorize('viewAny', Department::class);

            // Lấy danh sách tất cả Project đã bị xóa mềm
            $trashedDepartments = Department::onlyTrashed()->get();

            return response()->json([
                'message' => 'Danh sách các phòng ban đã bị xóa được lấy thành công',
                'departments' => $trashedDepartments,
            ], 200);
        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'Bạn không có quyền xem các phòng ban đã xóa: ' . $e->getMessage()
            ], 403);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Lỗi cơ sở dữ liệu khi lấy danh sách phòng ban đã xóa: ' . $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Lấy danh sách phòng ban đã xóa thất bại: ' . $e->getMessage()
            ], 500);
        }
    }
}
