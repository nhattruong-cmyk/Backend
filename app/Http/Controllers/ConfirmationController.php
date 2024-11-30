<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfirmationRequest;
class ConfirmationController extends Controller
{
    public function accept($token)
    {
        $confirmationRequest = ConfirmationRequest::where('confirmation_token', $token)->first();
    
        if (!$confirmationRequest) {
            return response()->json(['error' => 'Invalid or expired confirmation token.'], 404);
        }
    
        // Cập nhật trạng thái xác nhận
        $confirmationRequest->status = 'confirmed';
        $confirmationRequest->save();
    
        // Thêm người dùng vào phòng ban
        $confirmationRequest->department->users()->syncWithoutDetaching($confirmationRequest->user_id);
    
        return response()->json(['message' => 'User has been successfully added to the department.']);
    }
}
