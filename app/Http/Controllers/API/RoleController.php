<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;


class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Role::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        $role = Role::create($request->validated());
        return response()->json($role, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = Role::find($id);
        if($role){
            return response()->json($role);
        } else {
            return response()->json(['masage'=>'Role not found'], 404);
        };
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, string $id)
    {
        $role = Role::find($id);
        if($role){
            $role->update($request->validated);
            return response()->json(['masage'=>'update succet'], 200);
        }else{
            return response()->json(['message'=>'Role not found'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::find($id);
        if($role){
            $role->delete();
            return response()->json(['message'=>'Role deleted successfully'], 200);
        }else{
            return response()->json(['message'=>'Role not found'], 404);
        }
    }
}
