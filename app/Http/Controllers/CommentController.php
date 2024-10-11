<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    // Tạo bình luận mới
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'comment' => 'required|string',
            'parent_id' => 'nullable|exists:comments,id' // Nếu là phản hồi, parent_id phải tồn tại
        ]);

        // Tạo bình luận
        $comment = Comment::create([
            'task_id' => $validatedData['task_id'],
            'user_id' => Auth::id(),
            'comment' => $validatedData['comment'],
            'parent_id' => $validatedData['parent_id'] ?? null, // null nếu là bình luận gốc
        ]);

        return response()->json([
            'message' => 'Comment created successfully',
            'comment' => $comment,
        ], 201);
    }

    // Cập nhật bình luận
    public function update(Request $request, $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        // Chỉ người dùng tạo bình luận mới có thể chỉnh sửa
        if ($comment->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Thêm điều kiện để có thể cập nhật task_id nếu cần
        $validatedData = $request->validate([
            'comment' => 'required|string|max:255',
            'task_id' => 'required|exists:tasks,id',  // Validate task_id
        ]);

        // Cập nhật task_id cùng với comment nếu nó có trong request
        $comment->update([
            'comment' => $validatedData['comment'],
            'task_id' => $validatedData['task_id'], // Cho phép cập nhật task_id
        ]);

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => $comment,
        ], 200);
    }


    // Xóa bình luận
    public function destroy($id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        if ($comment->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully'], 200);
    }

    // Lấy tất cả bình luận và phản hồi của task
    public function getCommentsByTask($taskId)
    {
        $comments = Comment::where('task_id', $taskId)
            ->whereNull('parent_id') // Lấy bình luận gốc, không lấy phản hồi
            ->with('replies') // Lấy tất cả các phản hồi của bình luận
            ->get();

        return response()->json($comments, 200);
    }
}
