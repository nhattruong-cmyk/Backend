<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Role;


class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('role')->get();
        return response()->json($users);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $user = User::create($request->validated());
        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = User::with('role')->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        // Tìm user theo ID
        $user = User::find($id);
        
        if ($user) {
            // Lấy dữ liệu đã được xác thực từ request
            $validatedData = $request->validated();
    
            // Nếu trong request có mật khẩu, thì mã hóa mật khẩu và cập nhật
            if (isset($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            }
    
            // Cập nhật dữ liệu người dùng
            $user->update($validatedData);
    
            // Trả về phản hồi thành công
            return response()->json(['message' => 'User updated successfully'], 200);
        } else {
            // Nếu không tìm thấy user, trả về phản hồi 404
            return response()->json(['message' => 'User not found'], 404);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
