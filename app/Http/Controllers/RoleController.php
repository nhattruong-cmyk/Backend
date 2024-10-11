<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::all();
        return response()->json($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        // Nếu validation không thành công, Laravel sẽ tự động trả về lỗi.
        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json($role, 201);
    }
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        return response()->json($role);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        $role = Role::findOrFail($id);

        $validatedData = $request->validated();

        // Cập nhật thông tin phòng ban
        $role->update($validatedData);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        return response()->json($role);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        try {
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
}
