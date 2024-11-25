<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
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

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        try {
            // Tìm notification theo ID
            $notification = Notification::findOrFail($id);

            // Thực hiện xóa mềm
            $notification->delete();

            return response()->json(['message' => 'Notification soft deleted'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to soft delete notification: ' . $e->getMessage()], 500);
        }
    }
    public function forceDelete(string $id)
    {
        try {
            // Tìm notification đã bị xóa mềm
            $notification = Notification::onlyTrashed()->findOrFail($id);

            // Thực hiện xóa cứng
            $notification->forceDelete();

            return response()->json(['message' => 'Notification permanently deleted'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to permanently delete notification: ' . $e->getMessage()], 500);
        }
    }

    public function getTrashed()
    {
        try {
            // Lấy danh sách thông báo đã xóa mềm
            $trashedNotifications = Notification::onlyTrashed()->get();

            if ($trashedNotifications->isEmpty()) {
                return response()->json(['message' => 'No trashed notifications found'], 404);
            }

            return response()->json(['data' => $trashedNotifications], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve trashed notifications: ' . $e->getMessage()], 500);
        }
    }

    public function restore(string $id)
    {
        try {
            // Tìm notification đã xóa mềm
            $notification = Notification::onlyTrashed()->findOrFail($id);

            // Khôi phục thông báo
            $notification->restore();

            return response()->json(['message' => 'Notification restored successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore notification: ' . $e->getMessage()], 500);
        }
    }
}
