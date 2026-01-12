<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FileAttachmentController;
use App\Http\Controllers\UserSessionController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminAnnouncementController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminAIController;
use App\Http\Controllers\Admin\AdminSystemController;
use App\Http\Controllers\Admin\AdminLogController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\StudentDashboardController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/signup', [AuthController::class, 'signup']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Google OAuth routes (public)
Route::get('/auth/google', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

// Protected routes (require authentication + 30-min inactivity timeout)
Route::middleware(['auth:sanctum', 'session.activity'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard/summary', [StudentDashboardController::class, 'summary']);

    // Semesters CRUD
    Route::get('/semesters', [SemesterController::class, 'index']);
    Route::get('/semesters/{id}', [SemesterController::class, 'show']);
    Route::post('/semesters', [SemesterController::class, 'store']);
    Route::put('/semesters/{id}', [SemesterController::class, 'update']);
    Route::delete('/semesters/{id}', [SemesterController::class, 'destroy']);

    // Courses CRUD (scoped to semesters)
    Route::get('/semesters/{semesterId}/courses', [CourseController::class, 'index']);
    Route::post('/semesters/{semesterId}/courses', [CourseController::class, 'store']);
    Route::put('/courses/{courseId}', [CourseController::class, 'update']);
    Route::delete('/courses/{courseId}', [CourseController::class, 'destroy']);

    // Assessments CRUD (scoped to courses)
    Route::get('/courses/{courseId}/assessments', [AssessmentController::class, 'index']);
    Route::post('/courses/{courseId}/assessments', [AssessmentController::class, 'store']);
    Route::get('/assessments/{assessmentId}', [AssessmentController::class, 'show']);
    Route::put('/assessments/{assessmentId}', [AssessmentController::class, 'update']);
    Route::delete('/assessments/{assessmentId}', [AssessmentController::class, 'destroy']);

    // Notes CRUD (scoped to courses) + Enhancements
    Route::get('/courses/{courseId}/notes', [NoteController::class, 'index']);
    Route::post('/courses/{courseId}/notes', [NoteController::class, 'store']);
    Route::get('/notes/{noteId}', [NoteController::class, 'show']);
    Route::put('/notes/{noteId}', [NoteController::class, 'update']);
    Route::delete('/notes/{noteId}', [NoteController::class, 'destroy']);
    Route::post('/notes/{noteId}/pin', [NoteController::class, 'togglePin']);
    Route::post('/notes/{noteId}/favorite', [NoteController::class, 'toggleFavorite']);
    Route::post('/notes/{noteId}/share', [NoteController::class, 'generateShareLink']);
    Route::post('/notes/{noteId}/revoke-share', [NoteController::class, 'revokeShareLink']);
    Route::get('/notes/search', [NoteController::class, 'search']);

    // Public note access (no auth required)
    Route::get('/notes/public/{token}', [NoteController::class, 'showPublic']);

    // File Attachments
    Route::get('/attachments', [FileAttachmentController::class, 'index']);
    Route::post('/attachments/upload', [FileAttachmentController::class, 'upload']);
    Route::get('/attachments/{id}/download', [FileAttachmentController::class, 'download']);
    Route::delete('/attachments/{id}', [FileAttachmentController::class, 'destroy']);

    // User Sessions
    Route::get('/sessions', [UserSessionController::class, 'index']);
    Route::delete('/sessions/{id}', [UserSessionController::class, 'destroy']);
    Route::post('/sessions/revoke-all', [UserSessionController::class, 'revokeAll']);

    // Transactions / Budget
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);
    Route::get('/transactions/summary', [TransactionController::class, 'summary']);
    Route::get('/transactions/reports', [TransactionController::class, 'reports']);
    Route::get('/transactions/export-csv', [TransactionController::class, 'exportCsv']);

    // Recurring Transactions
    Route::get('/recurring-transactions', [RecurringTransactionController::class, 'index']);
    Route::post('/recurring-transactions', [RecurringTransactionController::class, 'store']);
    Route::put('/recurring-transactions/{id}', [RecurringTransactionController::class, 'update']);
    Route::delete('/recurring-transactions/{id}', [RecurringTransactionController::class, 'destroy']);

    // Budgets
    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::post('/budgets', [BudgetController::class, 'store']);
    Route::put('/budgets/{id}', [BudgetController::class, 'update']);
    Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);

    // Goals
    Route::get('/goals', [GoalController::class, 'index']);
    Route::post('/goals', [GoalController::class, 'store']);
    Route::put('/goals/{id}', [GoalController::class, 'update']);
    Route::delete('/goals/{id}', [GoalController::class, 'destroy']);
    Route::post('/goals/{id}/complete', [GoalController::class, 'complete']);

    // Tasks (standalone and goal-linked)
    Route::get('/tasks', [TaskController::class, 'listAll']);
    Route::post('/tasks', [TaskController::class, 'storeStandalone']);
    
    // Tasks (under goals, with optional parent_task_id)
    Route::get('/goals/{goalId}/tasks', [TaskController::class, 'index']);
    Route::post('/goals/{goalId}/tasks', [TaskController::class, 'store']);
    
    // Task operations
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::post('/tasks/{id}/complete', [TaskController::class, 'complete']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

    // Habits
    Route::get('/habits', [HabitController::class, 'index']);
    Route::post('/habits', [HabitController::class, 'store']);
    Route::put('/habits/{habitId}', [HabitController::class, 'update']);
    Route::post('/habits/{habitId}/mark-today', [HabitController::class, 'markToday']);
    Route::get('/habits/{habitId}/history', [HabitController::class, 'history']);
    Route::delete('/habits/{habitId}', [HabitController::class, 'destroy']);

    // Events (calendar)
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);

    // Google Calendar Sync
    Route::post('/events/{id}/sync-google', [GoogleCalendarController::class, 'syncEvent']);
    Route::post('/assessments/{id}/sync-google', [GoogleCalendarController::class, 'syncAssessment']);

    // Google Account Management
    Route::post('/google/disconnect', [GoogleAuthController::class, 'disconnect']);

    // Admin routes (require admin role)
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Dashboard / Analytics
        Route::get('/analytics', [AdminDashboardController::class, 'analytics']);

        // Users Management
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::put('/users/{id}/role', [AdminUserController::class, 'updateRole']);
        Route::post('/users/{id}/suspend', [AdminUserController::class, 'suspend']);
        Route::post('/users/{id}/activate', [AdminUserController::class, 'activate']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

        // Announcements
        Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
        Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
        Route::get('/announcements/{id}', [AdminAnnouncementController::class, 'show']);
        Route::post('/announcements/{id}/send', [AdminAnnouncementController::class, 'send']);
        Route::delete('/announcements/{id}', [AdminAnnouncementController::class, 'destroy']);

        // Subscriptions
        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);

        // AI Monitoring
        Route::get('/ai/sessions', [AdminAIController::class, 'sessions']);
        Route::get('/ai/usage-stats', [AdminAIController::class, 'usageStats']);
        Route::get('/ai/flagged-messages', [AdminAIController::class, 'flaggedMessages']);
        Route::get('/ai/failed-requests', [AdminAIController::class, 'failedRequests']);

        // System Settings
        Route::get('/system/settings', [AdminSystemController::class, 'settings']);
        Route::put('/system/settings', [AdminSystemController::class, 'updateSettings']);

        // Logs
        Route::get('/logs', [AdminLogController::class, 'index']);
        Route::get('/logs/{id}', [AdminLogController::class, 'show']);
        Route::get('/logs/errors', [AdminLogController::class, 'errors']);
        Route::get('/logs/auth-failures', [AdminLogController::class, 'authFailures']);
        Route::get('/logs/api-errors', [AdminLogController::class, 'apiErrors']);
    });
});

