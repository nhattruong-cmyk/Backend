<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index()
    {
        try {
            // Lấy thông tin user hiện tại
            $user = auth()->user();
    
            if ($user->role_id === 1 || $user->role_id === 2) {
                // Nếu role_id = 1 hoặc 2 (Admin hoặc Manager), lấy danh sách hoạt động của tất cả user
                $logs = ActivityLog::with('user', 'loggable')->get();
            } elseif ($user->role_id === 3) {
                // Nếu role_id = 3 (Staff), chỉ lấy danh sách hoạt động của user đang đăng nhập
                $logs = ActivityLog::with('user', 'loggable')->where('user_id', $user->id)->get();
            } else {
                // Nếu không thuộc role hợp lệ, trả về lỗi Unauthorized
                return response()->json(['error' => 'Unauthorized'], 403);
            }
    
            // Trả về dữ liệu dưới dạng JSON
            return response()->json([
                'message' => 'Danh sách hoạt động được lấy thành công.',
                'logs' => $logs,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi lấy danh sách hoạt động: ' . $e->getMessage(),
            ], 500);
        }
    }
    // Lấy lịch sử thao tác của một user cụ thể

}
