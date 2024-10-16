<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index()
    {
        // Lấy tất cả các bản ghi từ bảng activity_logs, cùng với thông tin người dùng và đối tượng liên quan
        $logs = ActivityLog::with('user', 'loggable')->get();

        // Trả về dữ liệu dưới dạng JSON (hoặc bạn có thể truyền sang view nếu muốn)
        return response()->json($logs);
    }
    // Phương thức lấy lịch sử thao tác của một user cụ thể
    public function getUserLogs($userId)
    {
        // Lấy tất cả các bản ghi của một người dùng
        $logs = ActivityLog::with('loggable')
            ->where('user_id', $userId)
            ->get();

        return response()->json($logs);
    }
}
