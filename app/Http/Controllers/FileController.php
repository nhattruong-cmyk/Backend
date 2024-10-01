<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

use App\Models\File;
use App\Models\Task;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\UpdateFileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $files = File::all();
        return response()->json($files, 200);
    }
    public function getTaskFiles($taskId)
    {
        // Lấy các file dựa vào task_id
        $files = File::where('task_id', $taskId)->get();
        return response()->json($files, 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $taskId)
    {
        Log::info('Request received for file upload', ['task_id' => $taskId]);

        try {
            // Validate yêu cầu
            $validatedData = $request->validate([
                'file' => 'required|file|mimes:jpg,png,pdf,doc,docx,zip|max:20480',
            ]);

            if ($request->hasFile('file')) {
                Log::info('File detected: ' . $request->file('file')->getClientOriginalName());
                $filePath = $request->file('file')->store('files');

                $file = File::create([
                    'file_name' => $request->file('file')->getClientOriginalName(),
                    'file_path' => $filePath,
                    'task_id' => $taskId,
                    'uploaded_by' => Auth::user()->id,
                ]);

                return response()->json(['success' => true, 'message' => 'File uploaded successfully!', 'file' => $file], 201);
            } else {
                Log::warning('No file detected in the request');
                return response()->json(['success' => false, 'message' => 'No file uploaded'], 400);
            }
        } catch (\Exception $e) {
            Log::error('File upload error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'File upload failed: ' . $e->getMessage()], 500);
        }
    }


    public function uploadFiles(Request $request, $taskId)
    {
        // Kiểm tra xem task có tồn tại không
        $task = Task::find($taskId);

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        // Validate việc upload file
        $request->validate([
            'files.*' => 'required|file|mimes:jpg,jpeg,png,pdf,docx|max:20480' // Hỗ trợ nhiều file
        ]);

        $uploadedFiles = [];

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                // Lấy thời gian theo định dạng 'YYYYMMDD_HHmmss'
                $timestamp = now()->format('Ymd_His');
                // Tạo tên file duy nhất và lưu file vào thư mục 'uploads'
                $fileName = $timestamp . '_' . $file->getClientOriginalName();  // Thêm timestamp trước tên file

                $filePath = $file->storeAs('uploads', $fileName, 'public');

                // Lưu thông tin file vào database
                $uploadedFile = File::create([
                    'task_id' => $task->id,
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'uploaded_by' => Auth::user()->id, // Thêm thông tin người upload
                ]);

                $uploadedFiles[] = $uploadedFile; // Thêm vào danh sách các file đã upload
            }
        }

        return response()->json([
            'message' => 'Files uploaded successfully!',
            'files' => $uploadedFiles
        ], 201);
    }


    // FileController.php
    public function downloadFile($fileId)
    {
        $file = File::find($fileId);

        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($file->file_path, $file->file_name);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $file = File::findOrFail($id);
        return response()->json($file, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $file = File::findOrFail($id);

        $validatedData = $request->validate([
            'file_name' => 'sometimes|string|max:255',
            'file' => 'sometimes|file|mimes:jpg,png,pdf,doc,docx,zip|max:20480',
        ]);

        // Xóa file cũ và cập nhật file mới nếu có
        if ($request->hasFile('file')) {
            Storage::delete($file->file_path); // Xóa file cũ
            $filePath = $request->file('file')->store('files');
            $file->update([
                'file_name' => $request->file('file')->getClientOriginalName(),
                'file_path' => $filePath,
            ]);
        }

        // Cập nhật tên file nếu cần
        $file->update($validatedData);

        return response()->json(['message' => 'File updated successfully!', 'file' => $file], 200);
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $file = File::findOrFail($id);

        // Kiểm tra quyền xóa
        if (Auth::user()->id !== $file->uploaded_by && Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'You do not have permission to delete this file.'], 403);
        }

        // Xóa mềm file (soft delete)
        $file->delete();

        return response()->json(['message' => 'File deleted successfully'], 200);
    }



    public function restore($id)
    {
        $file = File::onlyTrashed()->findOrFail($id);

        // Khôi phục file
        $file->restore();

        return response()->json(['message' => 'File restored successfully'], 200);
    }

    public function trashed()
    {
        // Lấy danh sách file đã bị soft delete
        $trashedFiles = File::onlyTrashed()->get();

        return response()->json($trashedFiles, 200);
    }


    public function forceDelete($id)
    {
        $file = File::onlyTrashed()->findOrFail($id);

        // Xóa file vật lý khỏi hệ thống
        Storage::delete($file->file_path);

        // Xóa hoàn toàn bản ghi khỏi database
        $file->forceDelete();

        return response()->json(['message' => 'File permanently deleted'], 200);
    }
}
