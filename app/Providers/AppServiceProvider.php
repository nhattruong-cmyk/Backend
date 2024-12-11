<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Task;
use App\Models\Worktimes;
use App\Models\Project;
use App\Models\Department;
use App\Models\Assignment;
use App\Policies\TaskPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\WorktimePolicy;
use App\Policies\AssignmentPolicy;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Đăng ký Policy cho Task
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Worktimes::class, WorktimePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Assignment::class, AssignmentPolicy::class);

        Gate::define('update-assignment', [AssignmentPolicy::class, 'update']);

        // Có thể cấu hình các scope, observers tại đây
        // Ví dụ: Áp dụng scope toàn cục cho Task
        Task::addGlobalScope('notCompleted', function ($query) {
            $query->where('status', '!=', 'completed');
        });
        

        // Đăng ký Observers nếu có
        // Task::observe(TaskObserver::class);
    }
}
