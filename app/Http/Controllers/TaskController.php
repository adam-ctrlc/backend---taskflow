<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Team;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(User $user)
    {
        $teams = Team::with(['leader', 'members', 'tasks.users'])
            ->where('leader_id', $user->id)
            ->orWhereHas('members', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->get();

        $tasks = $teams->flatMap(function ($team) {
            return $team->tasks;
        });


        return response()->json([
            'tasks' => $tasks,
            'teams' => $teams
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'due_date' => 'required|date',
            'team_id' => 'required|exists:teams,id',
        ]);
        // $user = User::findOrfail($request->user_id);
        $dueDate = \Carbon\Carbon::parse($request->input('due_date'))->format('Y-m-d H:i:s');
        // Create the task
        $task = Task::create([
            'title' => $request->title,
            'due_date' => $dueDate,
            'original_due_date' => $dueDate,
            'team_id' => $request->team_id,
        ]);


        // Assign the user to the task
        $task->users()->attach($request->user_id);
        $team = Team::findOrFail($request->team_id);

        $assignee = $task->users->first();

        activity()
            ->performedOn($team)
            ->causedBy($assignee)
            ->withProperties(['role' => $request->input('role', 'member')])
            ->log($task->title . " due on " . \Carbon\Carbon::parse($dueDate)->format('F j, Y'));
        // ->log($task->title . ": " . $assigne->name . " uploaded a " . $type);


        return response()->json(
            [
                'message' => 'Task assigned successfully.',
                'team_id' => $task->team_id,
                'team_name' => $task->team->name,
                'team' => $team->load('leader', 'members.tasks', 'tasks.users')
            ],
            201
        );
    }

    public function pending_tasks(User $user)
    {
        $pending_tasks = Task::with(['users', 'team']) // eager load
            ->where('status', 'Pending')
            ->whereHas('team', function ($q) use ($user) {
                $q->where('leader_id', $user->id) // user is leader
                    ->orWhereHas('members', fn($q2) => $q2->where('users.id', $user->id)); // user is member
            })
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'due_date' => $task->due_date,
                    'team' => $task->team?->name ?? 'Unknown Team',
                    'assignee' => $task->users->first()?->name ?? 'Unassigned',
                    'status' => $task->status,
                ];
            });

        return response()->json(['pending_tasks' => $pending_tasks]);
    }

    public function complete_tasks(User $user)
    {
        $completed_tasks = Task::with(['users', 'team']) // eager load
            ->where('status', 'Completed')
            ->whereHas('team', function ($q) use ($user) {
                $q->where('leader_id', $user->id) // user is leader
                    ->orWhereHas('members', fn($q2) => $q2->where('users.id', $user->id)); // user is member
            })
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'due_date' => $task->due_date,
                    'original_due_date' => $task->original_due_date,
                    'team' => $task->team?->name ?? 'Unknown Team',
                    'assignee' => $task->users->first()?->name ?? 'Unassigned',
                    'status' => $task->status,
                ];
            });

        return response()->json(['completed_tasks' => $completed_tasks]);
    }


    public function all(Task $task)
    {
        return response()->json(['tasks' => $task->get()]);
    }

    public function delete_all()
    {
        Task::truncate();
        return response()->json(['message' => 'deleted all']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        $request->validate([
            'user_name' => 'required|exists:users,name',
            'team_id' => 'required|exists:teams,id'
        ]);
        if ($task->status == 'Completed') {
            $task->update([
                'status' => 'Pending',
                'due_date' => $task->original_due_date
            ]);

            return response()->json(['message' => 'Task status updated to Incomplete.'], 200);
        }
        $team = $task->team;
        $task->update([
            'status' => 'Completed',
            'due_date' => null
        ]);

        $assignee = $task->users->first();

        // activity()
        //     ->performedOn($team)
        //     ->causedBy($assignee)
        //     ->withProperties(['role' => $request->input('role', 'member')])
        //     ->log($task->title . ": " . "Marked as completed by" . $assignee->name);

        \App\Models\Notification::create([
            'team_id' => $request->team_id,
            'type' => 'complete',
            'message' => $assignee->name . " completed the task for " . $task->title
        ]);

        return response()->json(['message' => 'Task updated successfully.'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $team = $task->team;
        $assignee = $task->users->first();

        // Detach the user from the task
        $task->users()->detach();

        // Delete the task
        $task->delete();

        // Update recent_updates table with new task counts
        $totalTasks = $team->tasks()->count();
        $completedTasks = $team->tasks()->where('status', 'Completed')->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        \App\Models\RecentUpdate::updateOrCreate(
            ['team_id' => $team->id],
            [
                'team_name' => $team->name,
                'chapter' => 'Overall Progress',
                'progress' => $progress,
                'completed_tasks' => $completedTasks,
                'total_tasks' => $totalTasks,
            ]
        );

        // Create notification
        if ($assignee) {
            \App\Models\Notification::create([
                'team_id' => $team->id,
                'type' => 'delete',
                'message' => "Task '" . $task->title . "' has been deleted"
            ]);

            // Log activity
            activity()
                ->performedOn($team)
                ->causedBy($assignee)
                ->log("Task '" . $task->title . "' has been deleted");
        }

        return response()->json(['message' => 'Task deleted successfully.'], 200);
    }
}
    