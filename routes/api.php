<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Team;
use App\Models\RecentUpdate;
// Originally has middleware('auth:sanctum')
Route::get('/user/{user}', function (Request $request, User $user) {
    return $user;
});

Route::get('/users', function (Request $request) {
    $search = $request->query('search');
    
    $query = User::select('id', 'name', 'email', 'profile_picture');
    
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('name', 'LIKE', '%' . $search . '%')
              ->orWhere('email', 'LIKE', '%' . $search . '%');
        });
    }
    
    return $query->orderBy('name')->get();
});

// Auth routes
Route::post('/register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
    ->name('register');

Route::post('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store'])
    ->name('login');

Route::post('/logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])
    ->name('logout');

// Team routes
Route::post('/team-store', [App\Http\Controllers\TeamController::class, 'store'])
    ->name('team.store');

Route::get('/team-list/{user}', [App\Http\Controllers\TeamController::class, 'index'])
    ->name('team.index');

Route::post('/team-join', [App\Http\Controllers\TeamController::class, 'join_team'])
    ->middleware('auth:sanctum')
    ->name('team.join');

Route::get('/all-teams', [App\Http\Controllers\TeamController::class, 'all_teams'])
    ->name('team.all');

Route::delete('/team-delete/{team}', [App\Http\Controllers\TeamController::class, 'destroy'])
    ->name('team.destroy');

Route::post('/add-member/{team}', [App\Http\Controllers\TeamController::class, 'add_member'])->middleware('auth:sanctum');
Route::delete('/remove-member/{team}', [App\Http\Controllers\TeamController::class, 'remove_member']);

Route::get('/update-progress/{team}', function(Team $team) {
    // Get the completed task of the team
    // $_team = $team->with('tasks');
    $totalTasks = $team->tasks->count();
    $completedTasks = $team->tasks->where('status', 'Completed')->count();
    
    $progress = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;
    $team->progress = $progress;
    $team->save();

    $recentUpdate = RecentUpdate::updateOrCreate(
        ['team_id' => $team->id], // match on team_id
        [
            'team_name' => $team->name,
            'chapter' => 'Overall Progress',
            'progress' => $progress,
            'completed_tasks' => $completedTasks,
            'total_tasks' => $totalTasks,
        ]
    );

    return response()->json($recentUpdate);

});

Route::get('/recent-updates', function() {
    $updates = RecentUpdate::orderBy('updated_at', 'desc')->take(9)->get();

    return response()->json($updates);
});

// Activity routes
Route::get('user-activities/{user}', function ($user) {
    $activities = Spatie\Activitylog\Models\Activity::with(['causer', 'subject'])
        ->where('causer_id', $user->id)
        ->get();

    return response()->json(['activities' => $activities], 200);
})->name('activity.log');

// Task routes
Route::post('/task-assign', [App\Http\Controllers\TaskController::class, 'store'])
    ->name('task.assign');
Route::get('/tasks/{user}', [App\Http\Controllers\TaskController::class, 'index'])
    ->name('task.index');
Route::post('/task-update/{task}', [App\Http\Controllers\TaskController::class, 'update'])
    ->name('task.update');
Route::get('/tasks', [App\Http\Controllers\TaskController::class, 'all']);
Route::get('/tasks-delete/all', [App\Http\Controllers\TaskController::class, 'delete_all']);
Route::delete('/task-delete/{task}', [App\Http\Controllers\TaskController::class, 'destroy']);
Route::get('/tasks/pending/{user}', [App\Http\Controllers\TaskController::class, 'pending_tasks']);
Route::get('/tasks/complete/{user}', [App\Http\Controllers\TaskController::class, 'complete_tasks']);


// Upload routes
Route::post('/upload', [App\Http\Controllers\UploadController::class, 'store'])
    ->name('upload.store');
Route::get('/uploads', [App\Http\Controllers\UploadController::class, 'index'])
    ->name('upload.index');
Route::get('/download/{task}', [App\Http\Controllers\UploadController::class, 'download'])
    ->name('upload.download');
Route::delete('/upload/{task}', [App\Http\Controllers\UploadController::class, 'destroy'])
    ->name('upload.destroy');

// Settings routes
Route::post('settings/{user}', [App\Http\Controllers\SettingsController::class, 'index'])
    ->name('settings');
Route::post('change-password/{user}', [App\Http\Controllers\SettingsController::class, 'changePassword'])
    ->name('change.password');

Route::get('user-team-activities/{user}', function (\App\Models\User $user) {
    // Get all team IDs where user is leader or member
    $teamIds = \App\Models\Team::where('leader_id', $user->id)
        ->orWhereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->pluck('id');

    // Get activities for those teams
    $activities = \Spatie\Activitylog\Models\Activity::with(['causer', 'subject'])
        ->where('subject_type', \App\Models\Team::class)
        ->whereIn('subject_id', $teamIds)
        ->latest()
        ->get();

    return response()->json(['activities' => $activities], 200);
})->name('user.team.activities');


// Notification routes 
Route::get('/user-notifications/{user}', function(\App\Models\User $user) {
    $memberTeamIds = $user->teams()->pluck('teams.id');

    $leaderTeamIds = Team::where('leader_id', $user->id)->pluck('id');

    // Merge and get unique team_ids
    $allTeamIds = $memberTeamIds->merge($leaderTeamIds)->unique();

    // Get notifications from those teams
    $notifications = \App\Models\Notification::whereIn('team_id', $allTeamIds)
        ->latest()
        ->get();

    return response()->json($notifications);
});

Route::get('/delete-notification/{notification}', function(\App\Models\Notification $notification) {
    $notification->delete();

    return response()->json(['message' => 'Notification deleted']);
});

// Forgot Password Routes
Route::post('/verify-user', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'verifyUser'])
    ->name('api.password.verify');

Route::post('/reset-password-custom', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'resetPassword'])
    ->name('api.password.reset.custom');