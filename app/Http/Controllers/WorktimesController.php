<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorktimesRequest;
use App\Models\Worktimes;
use Illuminate\Http\Request;

class WorktimesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $worktimes = Worktimes::get();
        return response()->json($worktimes, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorktimesRequest $request)
    {
        try {
            // Dữ liệu đã được xác thực bởi StoreWorktimesRequest
            $validatedData = $request->validated();

            // Tạo Worktime mới
            $worktime = Worktimes::create($validatedData);

            return response()->json([
                'message' => 'Worktime created successfully',
                'worktime' => $worktime,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Bắt lỗi xác thực và trả về thông báo lỗi
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Bắt lỗi chung và trả về thông báo lỗi
            return response()->json([
                'error' => 'Failed to create worktime: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(Worktimes $worktimes)
    {
        $worktimes = Worktimes::findOrFail($worktimes);
        return response()->json($worktimes);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreWorktimesRequest $request, $id)
    {
        try {
            // Xác thực dữ liệu đã được thực hiện bởi StoreWorktimesRequest
            $validatedData = $request->validated();

            // Tìm Worktime cần cập nhật
            $worktime = Worktimes::findOrFail($id);

            // Cập nhật Worktime với dữ liệu mới
            $worktime->update($validatedData);

            return response()->json([
                'message' => 'Worktime updated successfully',
                'worktime' => $worktime,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Bắt lỗi xác thực và trả về thông báo lỗi
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Bắt lỗi chung và trả về thông báo lỗi
            return response()->json([
                'error' => 'Failed to update worktime: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            // Tìm Worktime theo ID
            $worktime = Worktimes::findOrFail($id);

            // Xóa mềm (soft delete)
            $worktime->delete();

            return response()->json([
                'message' => 'Worktime soft deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to soft delete worktime: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTrashed()
    {
        try {
            // Lấy danh sách các bản ghi đã bị xóa mềm
            $trashedWorktimes = Worktimes::onlyTrashed()->get();

            return response()->json([
                'message' => 'Trashed worktimes retrieved successfully',
                'data' => $trashedWorktimes,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve trashed worktimes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function forceDestroy($id)
    {
        try {
            // Tìm Worktime đã bị xóa mềm
            $worktime = Worktimes::onlyTrashed()->findOrFail($id);

            // Xóa vĩnh viễn (force delete)
            $worktime->forceDelete();

            return response()->json([
                'message' => 'Worktime permanently deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to permanently delete worktime: ' . $e->getMessage()
            ], 500);
        }
    }
}
