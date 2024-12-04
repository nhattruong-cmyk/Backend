<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Task;
use App\Models\Worktimes;
use App\Models\Department;
use App\Policies\TaskPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\WorktimePolicy;


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

        // Có thể cấu hình các scope, observers tại đây
        // Ví dụ: Áp dụng scope toàn cục cho Task
        Task::addGlobalScope('notCompleted', function ($query) {
            $query->where('status', '!=', 'completed');
        });
        

        // Đăng ký Observers nếu có
        // Task::observe(TaskObserver::class);
    }
}
