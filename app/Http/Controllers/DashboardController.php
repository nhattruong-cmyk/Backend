<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Lấy tất cả project và các task liên quan, bao gồm assignments, status, start_date, end_date và notes
        $projects = Project::with('tasks.assignments')->get();

        // Biến để lưu trữ kết quả
        $projectDetails = [];

        // Duyệt qua từng project
        foreach ($projects as $project) {
            // Biến để lưu thông tin task của từng project
            $taskDetails = [];

            // Duyệt qua các task của project
            foreach ($project->tasks as $task) {
                $taskStatus = 'Pending'; // Mặc định trạng thái task
                $isLate = false;
                $isDelayed = false; // Kiểm tra nếu task trễ so với thời gian start_date hoặc end_date

                // Kiểm tra nếu task đã hoàn thành
                if ($task->status == 'completed') {
                    $taskStatus = 'Completed';
                }

                // Kiểm tra nếu task đã trễ tiến độ
                if ($task->end_date && Carbon::now()->gt(Carbon::parse($task->end_date)) && $task->status != 'completed') {
                    $taskStatus = 'Late';
                    $isLate = true;
                }

                // Kiểm tra nếu task đã trễ từ start_date
                if ($task->start_date && Carbon::now()->lt(Carbon::parse($task->start_date)) && $task->status != 'completed') {
                    $isDelayed = true; // Nếu start_date chưa đến mà task đã bắt đầu thì là trễ
                }

                // Lấy danh sách assignments (người được phân công cho task)
                $assignments = $task->assignments->pluck('name')->toArray();

                // Lấy ghi chú của task (nếu có)
                $notes = $task->note ?? 'No notes available';

                // Lưu thông tin task vào mảng
                $taskDetails[] = [
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'status' => $taskStatus,
                    'is_late' => $isLate,
                    'is_delayed' => $isDelayed, // Thêm trạng thái task trễ tiến độ
                    'assignments' => $assignments,
                    'notes' => $notes,
                    'start_date' => $task->start_date ? Carbon::parse($task->start_date)->format('Y-m-d') : null,
                    'end_date' => $task->end_date ? Carbon::parse($task->end_date)->format('Y-m-d') : null,
                ];
            }

            // Lưu thông tin project vào mảng
            $projectDetails[] = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'tasks' => $taskDetails,
            ];
        }

        // Trả về kết quả dưới dạng JSON
        return response()->json([
            'projects' => $projectDetails,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
