<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Lấy tất cả thông báo của người dùng
    public function index()
    {
        $notifications = auth()->user()->notifications;  // Lấy thông báo của người dùng đã đăng nhập

        return response()->json($notifications);
    }

    // Lấy thông báo chi tiết
    public function show($id)
    {
        $notification = Notification::findOrFail($id);
        return response()->json($notification);
    }

    // Tạo thông báo mới (ví dụ: chỉ khi có admin tạo thông báo)
    public function store(Request $request)
    {
        // Validate input
        $validatedData = $request->validate([
            'data' => 'required|array',  // Ví dụ: dữ liệu thông báo dưới dạng mảng
        ]);

        $notification = Notification::create([
            'notifiable_id' => $request->user()->id,  // Gửi cho người dùng hiện tại
            'notifiable_type' => 'App\Models\User',
            'data' => json_encode($validatedData['data']),
        ]);

        return response()->json($notification, 201);
    }

    // Đánh dấu thông báo là đã đọc
    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->markAsRead();  // Sử dụng phương thức markAsRead() của Laravel
        return response()->json($notification);
    }

    // Xóa thông báo (soft delete)
    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();
        return response()->json(['message' => 'Notification deleted']);
    }

    // Xóa thông báo vĩnh viễn
    public function forceDelete($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->forceDelete();
        return response()->json(['message' => 'Notification permanently deleted']);
    }

    // Lấy các thông báo đã bị xóa mềm

    public function getTrashed()
    {
        $trashedNotifications = Notification::onlyTrashed()->get();
        return response()->json($trashedNotifications);
    }

    // Khôi phục thông báo đã bị xóa mềm
    public function restore($id)
    {
        $notification = Notification::withTrashed()->findOrFail($id);
        $notification->restore();
        return response()->json(['message' => 'Notification restored']);
    }
}
