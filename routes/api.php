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
use App\Http\Controllers\WorktimesController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ConfirmationController;

Route::get('/hello', [UserController::class, 'hello']);
Route::get('/confirmation/accept/{token}', [ConfirmationController::class, 'accept'])
    ->name('confirmation.accept');
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/verify-email/{id}/{hash}', [UserController::class, 'verify'])->name('verification.verify');
Route::post('users', [UserController::class, 'store']);
Route::post('/forgot-password/request', [PasswordResetController::class, 'requestPasswordReset']);
Route::post('/forgot-password/verify', [PasswordResetController::class, 'verifyCode']);
Route::post('/forgot-password/reset', [PasswordResetController::class, 'resetPassword']);
Route::post('/resend-verification-code', [UserController::class, 'resendVerificationCode']);
Route::post('/auth/google', [UserController::class, 'handleGoogleLogin']);
// routes/web.php

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index']);

    //role
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    Route::delete('/roles/{id}/permissions', [RoleController::class, 'deletePermission']);

    //user
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('destroy');
    Route::delete('/users/{id}/force', [UserController::class, 'forceDestroy'])->name('forceDestroy');
    Route::post('/users/{id}/restore', [UserController::class, 'restore'])->name('restore');
    Route::get('users-trashed', [UserController::class, 'trashedUsers'])->name('trashedUsers');
    Route::post('/users/{id}/update-avatar', [UserController::class, 'updateAvatar']);

    // API yêu cầu xóa tài khoản
    Route::post('/request-delete-account', [UserController::class, 'requestDeleteAccount']);

    // API xác nhận xóa tài khoản
    Route::get('/confirm-delete-account/{token}', [UserController::class, 'confirmDeleteAccount']);

    //permission
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/permissions/{id}', [PermissionController::class, 'show']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);
    Route::put('/permissions/{id}', [PermissionController::class, 'update']);
    Route::post('/permissions/{id}/restore', [PermissionController::class, 'restore']);
    Route::delete('/permissions/{id}/force-delete', [PermissionController::class, 'forceDelete']);
    Route::get('/permissions-trashed', [PermissionController::class, 'trashed']);

    //department
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::get('departments/{id}', [DepartmentController::class, 'show']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::post('departments/{department_id}/add-users', [DepartmentController::class, 'addUsersToDepartment']);
    Route::post('/departments/{department_id}/remove-users', [DepartmentController::class, 'removeUsersFromDepartment']);
    Route::put('departments/{department_id}', [DepartmentController::class, 'update']);
    Route::delete('departments/{department_id}', [DepartmentController::class, 'destroy']);
    Route::delete('/departments/{id}/force', [DepartmentController::class, 'forceDelete']);
    Route::get('/trashed-departments', [DepartmentController::class, 'getTrashed']);
    Route::put('/departments/{id}/restore', [DepartmentController::class, 'restore']);
    Route::get('/departments/{department_id}/confirm/{token}', [DepartmentController::class, 'confirmUser']);
    Route::get('/departments/{department_id}/users', [DepartmentController::class, 'getUsersWithStatus']);
    Route::delete('/departments/{department_id}/users/{user_id}', [DepartmentController::class, 'removeUserFromDepartment']);

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{project_id}/add-tasks', [ProjectController::class, 'addTasks']); // Thêm nhiều task vào dự án
    Route::post('/projects/{project_id}/add-departments', [ProjectController::class, 'addDepartmentToProject']);
    Route::post('/projects/{project_id}/remove-departments', [ProjectController::class, 'removeDepartmentFromProject']);
    Route::get('/projects-trashed', [ProjectController::class, 'trashedProjects']);
    Route::post('/projects/{id}/restore', [ProjectController::class, 'restore']);
    Route::delete('/projects/{id}/force', [ProjectController::class, 'forceDelete']);

    // Tasks
    Route::get('/tasks/without-worktime', [TaskController::class, 'getTaskWithoutWorktime']);
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{id}', [TaskController::class, 'show']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    Route::post('/tasks/{task_id}/add-users', [TaskController::class, 'addUsers']); // Thêm nhiều user vào task
    Route::get('/projects/{id}/departments', [TaskController::class, 'getDepartmentsByProjectId']);
    Route::post('/tasks/{task_id}/update-location', [TaskController::class, 'updateLocationTask']);
    Route::post('/tasks/move-to-worktime', [TaskController::class, 'moveTasksToAnotherWorktime']);
    Route::delete('/tasks/{id}/force', [TaskController::class, 'forceDelete']);
    Route::get('/trashed-tasks', [TaskController::class, 'getTrashed']);
    Route::put('/tasks/{id}/restore', [TaskController::class, 'restore']);
    Route::get('/tasks/worktimes/{worktime_id}', [TaskController::class, 'getTasksByWorktimeId']);
    Route::patch('/tasks/{task_id}/worktime', [TaskController::class, 'updateWorktimeTask']);
    Route::put('/tasks/{task_id}/worktime', [TaskController::class, 'updateWorktimeId']);
    Route::put('/tasks/{task_id}/status', [TaskController::class, 'updateStatus']);
    Route::put('/tasks/{task_id}/tasktime', [TaskController::class, 'updateTaskTime']);
    Route::get('/tasks/by-project/{projectId}', [TaskController::class, 'getTasksByProject']);
    Route::get('/tasks/by-running/{id}', [TaskController::class, 'getRunningTasks']);
    Route::get('/tasks/{id}/get-task-with-user', [TaskController::class, 'getTaskWithUser']);
    Route::get('/tasks/{id}/details', [TaskController::class, 'getTaskDetails']);//test tásk


    // Assignments
    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::get('/assignments/{id}', [AssignmentController::class, 'show']);
    Route::post('/assignments', [AssignmentController::class, 'store']);
    Route::put('/assignments/{id}', [AssignmentController::class, 'update']);
    Route::delete('/assignments/{id}', [AssignmentController::class, 'destroy']);
    Route::get('/tasks/{id}/departments', [AssignmentController::class, 'getDepartmentsByTask']);
    Route::get('/departments/{id}/users', [AssignmentController::class, 'getUsersByDepartment']);
    Route::delete('/assignments/{id}/force', [AssignmentController::class, 'forceDelete']);
    Route::get('/assignments-trashed', [AssignmentController::class, 'getTrashed']);
    Route::put('/assignments/{id}/restore', [AssignmentController::class, 'restore']);


    // Notification
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications/{id}/force', [NotificationController::class, 'forceDelete']);
    Route::get('/trashed-notifications', [NotificationController::class, 'getTrashed']);
    Route::put('/notifications/{id}/restore', [NotificationController::class, 'restore']);

    // Files liên kết với task cụ thể
    Route::get('/tasks/{id}/files', [FileController::class, 'getTaskFiles']); // Lấy danh sách file của task cụ thể
    Route::post('tasks/{taskId}/files', [FileController::class, 'uploadFiles'])->name('tasks.files.upload');
    Route::get('files/{fileId}/download', [FileController::class, 'downloadFile'])->name('files.download');
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']); // xóa file
    Route::put('/files/{id}', [FileController::class, 'update']);
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


    // WorkTimes
    Route::get('/worktimes', [WorktimesController::class, 'index']);
    Route::get('/worktimes/{id}', [WorktimesController::class, 'show']);
    Route::post('/worktimes', [WorktimesController::class, 'store']);
    Route::put('/worktimes/{id}', [WorktimesController::class, 'update']);
    Route::delete('/worktimes/{id}', [WorktimesController::class, 'destroy']);
    Route::get('worktimes-trashed', [WorktimesController::class, 'trashedWorktimes'])->name('trashedWorktimes');
    Route::put('/worktimes/{id}/restore', [WorktimesController::class, 'restore']);
    Route::delete('/worktimes/{id}/force', [WorktimesController::class, 'forceDestroy']);
    Route::put('/worktimes/{id}/status', [WorktimesController::class, 'updateStatus']);

    // Route lấy tất cả lịch sử
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    // Route lấy lịch sử của một user cụ thể
    Route::get('/activity-logs/user/{userId}', [ActivityLogController::class, 'getUserLogs']);
});




Route::get('/', function () {
    return 'API';
});
