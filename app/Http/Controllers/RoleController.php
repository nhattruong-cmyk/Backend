<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\Request;
use App\Models\Permission;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Kiểm tra nếu vai trò của user là Admin
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Lấy tất cả roles và kèm theo permissions
        $roles = Role::with(['permissions', 'permissions.children'])->get();

        // Trả về dữ liệu roles và permissions
        return response()->json($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        // Chỉ cho phép Admin thêm vai trò
        $user = $request->user();
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Nếu validation không thành công, Laravel sẽ tự động trả về lỗi.
        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Gán quyền cho vai trò nếu có
        if ($request->has('permissions')) {
            $role->permissions()->attach($request->permissions);

            // Nếu có quyền lớn, cũng tự động gán quyền nhỏ tương ứng
            foreach ($request->permissions as $permissionId) {
                $permission = Permission::find($permissionId);
                if ($permission && $permission->parent_id) {
                    $role->permissions()->attach($permission->parent_id);
                }
            }
        }

        return response()->json($role->load('permissions'), 201);
    }
    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        // Lấy người dùng hiện tại từ request
        $user = $request->user();

        // Kiểm tra nếu người dùng không phải là admin (role_id != 1)
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Tìm kiếm role theo ID
        $role = Role::with(['permissions', 'permissions.children'])->find($id);

        // Nếu role không tồn tại, trả về lỗi
        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Nếu người dùng là admin và role tồn tại, trả về thông tin của role
        return response()->json($role);
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        // Chỉ cho phép Admin sửa vai trò
        $user = $request->user();
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Tìm vai trò theo ID
        $role = Role::findOrFail($id);

        // Kiểm tra xem người dùng có cố gắng thêm quyền mà đã tồn tại không
        if ($request->has('permissions')) {
            $existingPermissions = $role->permissions()->pluck('permissions.id')->toArray(); // Sửa tại đây
            $newPermissions = $request->permissions;

            // Tìm các quyền trùng lặp
            $duplicates = array_intersect($existingPermissions, $newPermissions);

            // Nếu có quyền trùng lặp, trả về thông báo lỗi cụ thể
            if (!empty($duplicates)) {
                return response()->json([
                    'message' => 'The following permissions are already associated with this role: ' . implode(', ', $duplicates)
                ], 400);
            }

            // Thêm các quyền mới chưa tồn tại vào vai trò
            $role->permissions()->attach(array_diff($newPermissions, $existingPermissions));
        }

        // Cập nhật thông tin vai trò
        $role->update($request->validated());

        // Kiểm tra xem người dùng có cố gắng đổi tên vai trò "Admin" hay không
        if ($request->has('name') && strtolower($request->name) === 'admin' && strtolower($role->name) !== 'admin') {
            return response()->json(['message' => 'You cannot change the role name to "Admin".'], 403);
        }

        return response()->json($role->load('permissions')); // Trả về thông tin vai trò cùng quyền đã gán
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        // Chỉ cho phép Admin xóa vai trò
        $user = $request->user();
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::find($id);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        try {
            // Kiểm tra có liên kết với permission hay không trước khi xóa
            if ($role->permissions()->exists()) {
                return response()->json(['error' => 'Role cannot be deleted because it is associated with permissions.'], 400);
            }

            $role->delete();
            return response()->json(['message' => 'Role deleted successfully']);
        } catch (\Illuminate\Database\QueryException $e) {
            // Mã lỗi 23000 là lỗi ràng buộc khóa ngoại
            if ($e->getCode() === '23000') {
                return response()->json([
                    'error' => 'Role cannot be deleted because it is associated with other records (users).'
                ], 400);
            }
            return response()->json([
                'error' => 'Failed to delete role: ' . $e->getMessage()
            ], 500);
        }
    }
    public function deletePermission(Request $request, $id)
    {
        // Chỉ cho phép Admin xóa quyền khỏi vai trò
        $user = $request->user();
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Tìm vai trò theo ID
        $role = Role::findOrFail($id);

        // Kiểm tra xem `permissions` có được gửi trong request không
        if (!$request->has('permissions') || !is_array($request->permissions)) {
            return response()->json(['message' => 'No permissions specified'], 400);
        }

        $permissionsToRemove = $request->permissions;

        // Lọc các `permission_id` hợp lệ từ bảng Permission
        $validPermissions = Permission::whereIn('id', $permissionsToRemove)->pluck('id')->toArray();
        $invalidPermissions = array_diff($permissionsToRemove, $validPermissions);

        // Nếu không có quyền hợp lệ nào trong request, trả về thông báo lỗi
        if (empty($validPermissions)) {
            return response()->json([
                'message' => 'No valid permissions to remove',
                'invalid_permissions' => $invalidPermissions
            ], 400); // Trả về mã lỗi 400
        }

        // Kiểm tra quyền có thuộc vai trò hay không
        $existingPermissions = $role->permissions()->pluck('permissions.id')->toArray();
        $permissionsToActuallyRemove = array_intersect($validPermissions, $existingPermissions);

        // Nếu không có quyền nào thực sự tồn tại trong vai trò, trả về lỗi
        if (empty($permissionsToActuallyRemove)) {
            return response()->json([
                'message' => 'No permissions exist in this role to remove',
                'invalid_permissions' => $validPermissions
            ], 400); // Đảm bảo trả về mã lỗi 400
        }

        // Loại bỏ các quyền hợp lệ khỏi vai trò
        $role->permissions()->detach($permissionsToActuallyRemove);

        // Trả về thông báo thành công mà không có dữ liệu đi kèm
        return response()->json([
            'message' => 'Permissions removed successfully'
        ]);
    }






}
