<?php

use App\Http\Controllers\RoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ActivityLogController;




Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    //role
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

    //user
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('destroy');
    Route::patch('/users/{id}/restore', [UserController::class, 'restore'])->name('restore');
    Route::get('/users/trashed', [UserController::class, 'getTrashedUsers'])->name('trashed');


    //department
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::get('departments/{id}', [DepartmentController::class, 'show']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::post('departments/{department_id}/add-user', [DepartmentController::class, 'addUserToDepartment']);
    Route::post('/departments/{department_id}/remove-users', [DepartmentController::class, 'removeUserFromDepartment']);
    Route::put('departments/{department_id}', [DepartmentController::class, 'update']);
    Route::delete('departments/{department_id}', [DepartmentController::class, 'destroy']);
    Route::post('/departments/{department_id}/add-users', [DepartmentController::class, 'addUsersToDepartment']);

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{project_id}/add-tasks', [ProjectController::class, 'addTasks']); // Thêm nhiều task vào dự án
    Route::post('/projects/{project_id}/add-departments', [ProjectController::class, 'addDepartmentToProject']);
    Route::post('/projects/{project_id}/remove-departments', [ProjectController::class, 'removeDepartmentFromProject']);


    // Tasks
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{id}', [TaskController::class, 'show']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    Route::post('/tasks/{task_id}/add-users', [TaskController::class, 'addUsers']); // Thêm nhiều user vào task
    Route::get('/projects/{id}/departments', [TaskController::class, 'getDepartmentsByProjectId']);


    // Assignments
    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::get('/assignments/{id}', [AssignmentController::class, 'show']);
    Route::post('/assignments', [AssignmentController::class, 'store']);
    Route::put('/assignments/{id}', [AssignmentController::class, 'update']);
    Route::delete('/assignments/{id}', [AssignmentController::class, 'destroy']);
    Route::get('/tasks/{id}/departments', [AssignmentController::class, 'getDepartmentsByTask']);
    Route::get('/departments/{id}/users', [AssignmentController::class, 'getUsersByDepartment']);

    // Notification
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Files liên kết với task cụ thể
    Route::get('/tasks/{id}/files', [FileController::class, 'getTaskFiles']); // Lấy danh sách file của task cụ thể
    Route::post('tasks/{taskId}/files', [FileController::class, 'uploadFiles'])->name('tasks.files.upload');
    Route::get('files/{fileId}/download', [FileController::class, 'downloadFile'])->name('files.download');

    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']); // xóa mềm file
    Route::put('/files/{id}', [FileController::class, 'update']);

    // Route cho restore và force delete
    Route::post('/files/{id}/restore', [FileController::class, 'restore']); // Khôi phục file đã bị soft delete
    Route::get('/files/trashed', [FileController::class, 'trashed']); // Lấy danh sách file đã bị soft delete
    Route::delete('/files/{id}/force-delete', [FileController::class, 'forceDelete']); // Xóa hoàn toàn file (hard delete)

    //Route cho Comment
    Route::get('/comments', [CommentController::class, 'index']);
    Route::get('/comments/{id}', [CommentController::class, 'show']);

    Route::post('/comments', [CommentController::class, 'store']); // Tạo bình luận
    Route::put('/comments/{id}', [CommentController::class, 'update']); // Cập nhật bình luận
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']); // Xóa bình luận
    Route::get('/tasks/{taskId}/comments', [CommentController::class, 'getCommentsByTask']); // Lấy bình luận của task


    // Route lấy tất cả lịch sử
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);

    // Route lấy lịch sử của một user cụ thể
    Route::get('/activity-logs/user/{userId}', [ActivityLogController::class, 'getUserLogs']);
});




Route::get('/', function () {
    return 'API';
});
