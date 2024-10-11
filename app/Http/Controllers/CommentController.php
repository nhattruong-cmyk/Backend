<?php

namespace App\Http\Controllers;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function index()
    {
        // Lấy tất cả các bình luận, kèm theo thông tin task và user
        $comments = Comment::with(['task', 'user', 'replies'])->get();

        return response()->json($comments, 200);
    }
    // Tạo bình luận mới
    public function store(StoreCommentRequest $request)
    {

        $validatedData = $request->validated();

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
    public function update(UpdateCommentRequest $request, $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        // Chỉ người dùng tạo bình luận mới có thể chỉnh sửa
        if ($comment->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Kiểm tra xem task_id mà người dùng muốn cập nhật có phải là task hiện tại không
        if ($comment->task_id !== $request->task_id) {
            return response()->json(['message' => 'Cannot change the task of this comment'], 400);
        }
        // Kiểm tra nếu có parent_id thì phải thuộc cùng task
        if ($request->filled('parent_id')) {
            // Lấy bình luận cha
            $parentComment = Comment::find($request->parent_id);
            if ($parentComment && $parentComment->task_id !== $comment->task_id) {
                return response()->json(['message' => 'Parent comment must belong to the same task'], 400);
            }
        }
        // Dữ liệu đã được validate qua UpdateCommentRequest
        $validatedData = $request->validated();

        // Cập nhật task_id cùng với comment nếu nó có trong request
        $comment->update([
            'comment' => $validatedData['comment'],
            'task_id' => $validatedData['task_id'], // Cho phép cập nhật task_id
            'parent_id' => $validatedData['parent_id'] ?? $comment->parent_id, // Cập nhật parent_id nếu có

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
    public function show($id)
    {
        // Tìm bình luận theo ID, kèm thông tin task và user
        $comment = Comment::with(['task', 'user', 'replies'])->find($id);

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        return response()->json($comment, 200);
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
