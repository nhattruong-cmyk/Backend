<?php

namespace App\Http\Controllers;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\File;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        Log::info('Validated Data:', $validatedData);

        // Nếu có parent_id, kiểm tra
        if (isset($validatedData['parent_id'])) {
            // Tìm bình luận cha
            $parentComment = Comment::find($validatedData['parent_id']);

            // Kiểm tra xem bình luận cha có tồn tại không
            if (!$parentComment) {
                return response()->json(['message' => 'Bình luận cha không tồn tại.'], 400);
            }

            // Ghi log thông tin task_id để kiểm tra
            Log::info('Task ID from Request:', ['task_id' => $validatedData['task_id']]);
            Log::info('Parent Comment Task ID:', ['parent_id' => $parentComment->task_id]);

            // Kiểm tra xem bình luận cha có thuộc task này không
            if ($parentComment->task_id !== (int) $validatedData['task_id']) {
                return response()->json(['message' => 'Bình luận cha phải cùng task mới có thể trả lời.'], 400);
            }
        }

        // Tạo bình luận
        $comment = Comment::create([
            'task_id' => $validatedData['task_id'],
            'user_id' => Auth::id(),
            'comment' => $validatedData['comment'],
            'parent_id' => $validatedData['parent_id'] ?? null,
        ]);

        // Xử lý đính kèm file (nếu có)
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filePath = $file->store('comment_files', 'public');
                $fileName = $file->getClientOriginalName();

                // Lưu file vào bảng Files
                File::create([
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'task_id' => $validatedData['task_id'],
                    'comment_id' => $comment->id,
                    'uploaded_by' => Auth::id(),
                ]);
            }
        }

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
        // Nếu có file đính kèm thì xử lý
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filePath = $file->store('comment_files', 'public');
                $fileName = $file->getClientOriginalName();

                // Tạo mới file liên kết với bình luận
                File::create([
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'task_id' => $request->task_id,
                    'comment_id' => $comment->id,
                    'uploaded_by' => Auth::id(), // Người upload
                ]);
            }
        }
        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => $comment,
        ], 200);
    }


    // Xóa bình luận
    public function destroy($id)
    {
        // Tìm bình luận với các phản hồi (replies) và file liên quan
        $comment = Comment::with('replies', 'files')->findOrFail($id);

        // Kiểm tra nếu người dùng là admin (role = 1)
        if (Auth::user()->role_id === 1) {
            // Nếu là admin, xóa tất cả các bình luận con (replies)
            foreach ($comment->replies as $reply) {
                // Xóa file liên quan đến bình luận con nếu có
                foreach ($reply->files as $file) {
                    $file->delete();
                }
                // Xóa bình luận con
                $reply->delete();
            }

            // Xóa tất cả các file liên quan đến bình luận gốc
            foreach ($comment->files as $file) {
                $file->delete();
            }
        } else {
            // Nếu không phải là admin, chỉ cho phép người dùng xóa bình luận của chính họ
            if ($comment->user_id !== Auth::id()) {
                return response()->json(['message' => 'Bạn không có quyền xóa bình luận này.'], 403);
            }
        }

        // Xóa bình luận gốc (bao gồm cả file đính kèm nếu có)
        $comment->delete();

        return response()->json(['message' => 'Bình luận đã được xóa thành công.'], 200);
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
