<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Lấy tất cả thông báo của người dùng
        $userId = Auth::id(); // Sử dụng facade Auth để lấy ID người dùng
        if (!$userId) {
            return response()->json(['error' => 'User not authenticated.'], 401);
        }

        $notifications = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->get();;
        return response()->json($notifications);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id', // Đảm bảo user tồn tại
            'message' => 'required|string|max:255', // Nội dung thông báo
        ]);

        try {
            // Tạo thông báo mới
            $notification = Notification::create($validatedData);
            return response()->json(['message' => 'Notification created successfully', 'notification' => $notification], 201);
        } catch (\Exception $e) {
            // Trả về lỗi nếu có
            return response()->json(['error' => 'Failed to create notification: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notification marked as read'], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();
        return response()->json(['message' => 'Notification deleted'], 200);
    }
}
