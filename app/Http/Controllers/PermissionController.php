<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $permissions = Permission::with('children')->whereNull('parent_id')->get();

        return response()->json($permissions);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePermissionRequest $request)
    {
        if (!$request->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $permission = Permission::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id, // Gán parent_id

        ]);

        return response()->json($permission, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $permission = Permission::with('children')->find($id);

        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        return response()->json($permission);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePermissionRequest $request, $id)
    {
        // Chỉ cho phép Admin sửa vai trò
        $user = $request->user();
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Tìm permission theo ID
        $permission = Permission::find($id);

        // Kiểm tra xem permission có tồn tại không
        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        // Kiểm tra giá trị `parent_id` trong yêu cầu
        $newParentId = $request->input('parent_id');

        // Nếu `parent_id` là null, nghĩa là muốn xóa mối liên kết với quyền cha
        if (is_null($newParentId)) {
            $permission->parent_id = null;
        } else {
            // Nếu `parent_id` là một giá trị hợp lệ khác, kiểm tra xem quyền cha có tồn tại không
            if (!Permission::where('id', $newParentId)->exists()) {
                return response()->json(['message' => 'Invalid parent_id specified'], 400);
            }
            $permission->parent_id = $newParentId;
        }

        // Cập nhật các thuộc tính khác của quyền
        $permission->name = $request->input('name', $permission->name);
        $permission->save();

        return response()->json($permission, 200); // Trả về quyền đã được cập nhật
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        // Chỉ cho phép Admin xóa quyền
        $user = $request->user();
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        // Kiểm tra xem permission có đang được sử dụng trong roles không
        if ($permission->roles()->count() > 0) {
            return response()->json([
                'message' => 'Permission cannot be deleted because it is associated with roles.',
                'roles' => $permission->roles()->pluck('name') // Lấy danh sách tên roles
            ], 400);
        }
        // Nếu quyền có quyền con (children), có thể xóa mềm hoặc xóa tất cả
        if ($permission->children()->count() > 0) {
            // Nếu muốn xóa mềm, sử dụng soft delete
            $permission->delete(); // Bỏ comment nếu sử dụng Soft Delete
            // Hoặc có thể xóa tất cả quyền con
            foreach ($permission->children as $child) {
                $child->delete(); // Xóa từng quyền con
            }
        }

        // Xóa permission
        $permission->delete();
        return response()->json(['message' => 'Permission deleted successfully']);
    }
    /**
     * Khôi phục một permission đã xóa mềm.
     */
    public function restore($id)
    {
        // Chỉ cho phép Admin khôi phục quyền
        $user = request()->user();
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Tìm permission đã bị xóa
        $permission = Permission::onlyTrashed()->find($id);

        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        // Khôi phục permission
        $permission->restore();
        // Khôi phục tất cả quyền con (children) của permission
        $children = Permission::onlyTrashed()->where('parent_id', $id)->get();
        foreach ($children as $child) {
            $child->restore(); // Khôi phục từng quyền con
        }
        return response()->json(['message' => 'Permission restored successfully']);
    }
    public function trashed()
    {
        // Lấy tất cả các permission đã bị xóa mềm
        $trashedPermissions = Permission::onlyTrashed()->with('children')->get();

        return response()->json($trashedPermissions);
    }

    /**
     * Xóa hoàn toàn một permission.
     */
    public function forceDelete($id)
    {
        // Chỉ cho phép Admin xóa hoàn toàn quyền
        $user = request()->user();
        if (!$user->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Tìm permission đã bị xóa
        $permission = Permission::onlyTrashed()->find($id);

        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        // Xóa hoàn toàn permission
        $permission->forceDelete();

        return response()->json(['message' => 'Permission permanently deleted']);
    }

}
