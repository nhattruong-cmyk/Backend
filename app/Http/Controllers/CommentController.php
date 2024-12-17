<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\File;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CommentController extends Controller
{
    public function index($taskId)
    {
        // Lấy tất cả các bình luận, kèm theo thông tin task và user
        $comments = Comment::with(['user:id,fullname', 'files:id,file_name,comment_id'])
            ->where('task_id', $taskId)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'DESC')
            ->get(['id', 'task_id', 'user_id', 'comment', 'created_at']);

        return response()->json($comments, 200);
    }
    public function show($id)
    {
        try {
            $comment = Comment::with([
                'task',
                'user:id,fullname,avatar',
                'files:id,file_name,file_path,comment_id',
                'replies.user:id,fullname,avatar',
                'replies.files:id,file_name,file_path,comment_id', // Đảm bảo load files của replies
                'replies.replies.user:id,fullname,avatar',
                'replies.replies.files:id,file_name,file_path,comment_id' // Load files của replies của replies
            ])->find($id);

            if (!$comment) {
                return response()->json(['message' => 'Comment not found'], 404);
            }

            return response()->json([
                'status' => 'success',
                'comment' => $comment
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching comment: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch comment'
            ], 500);
        }
    }
    // Lấy tất cả bình luận và phản hồi của task
    public function getCommentsByTask($taskId)
    {
        $comments = Cache::remember("comments_task_{$taskId}", 60, function () use ($taskId) {
            return Comment::where('task_id', $taskId)
                ->whereNull('parent_id')
                ->with(['user:id,fullname', 'files:id,file_name,comment_id', 'replies'])
                ->orderBy('created_at', 'DESC') // Sắp xếp giảm dần theo thời gian tạo
                ->paginate(10); // Phân trang 10 bình luận mỗi trang
        });

        return response()->json($comments, 200);
    }
    // Tạo bình luận mới
    public function store(StoreCommentRequest $request)
    {
        try {
            $comment = Comment::create([
                'task_id' => $request->task_id,
                'user_id' => Auth::id(),
                'comment' => $request->comment,
                'parent_id' => $request->parent_id ?? null,
            ]);

            $files = [];
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $filePath = $file->store('comment_files', 'public');
                    $fileRecord = File::create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $filePath,
                        'task_id' => $request->task_id,
                        'comment_id' => $comment->id,
                        'uploaded_by' => Auth::id(),
                    ]);
                    $files[] = [
                        'id' => $fileRecord->id,
                        'file_name' => $fileRecord->file_name,
                        'file_path' => $fileRecord->file_path
                    ];
                }
            }

            // Load đầy đủ relationships
            $comment->load([
                'user:id,fullname,avatar',
                'files:id,file_name,file_path,comment_id'
            ]);

            return response()->json([
                'message' => 'Comment created successfully',
                'comment' => [
                    'id' => $comment->id,
                    'task_id' => $comment->task_id,
                    'user_id' => $comment->user_id,
                    'comment' => $comment->comment,
                    'parent_id' => $comment->parent_id,
                    'created_at' => $comment->created_at,
                    'user' => [
                        'id' => $comment->user->id,
                        'fullname' => $comment->user->fullname,
                        'avatar' => $comment->user->avatar
                    ],
                    'files' => $files
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating comment: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create comment: ' . $e->getMessage()
            ], 500);
        }
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
}
