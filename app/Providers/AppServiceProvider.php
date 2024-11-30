<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Task;
use App\Policies\TaskPolicy;
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

        // Có thể cấu hình các scope, observers tại đây
        // Ví dụ: Áp dụng scope toàn cục cho Task
        Task::addGlobalScope('notCompleted', function ($query) {
            $query->where('status', '!=', 'completed');
        });

        // Đăng ký Observers nếu có
        // Task::observe(TaskObserver::class);
    }
}
