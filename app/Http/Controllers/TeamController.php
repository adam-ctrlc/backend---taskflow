<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(User $user)
    {
        // dd($user);
        $teams = Team::with(['leader', 'members.tasks', 'tasks', 'tasks.users'])
            ->where('leader_id', $user->id)
            ->orWhereHas('members', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->get();
        
        

        return response()->json(['teams' => $teams], 200);
    }

    public function all_teams()
    {
        $teams = Team::with(['leader', 'members'])->get();

        return response()->json(['teams' => $teams], 200);
    }

    public function join_team(Request $request) {
        $request->validate([
            'team_uuid' => 'required|exists:teams,uuid',
            // 'role' => 'sometimes|string|in:member,admin,leader',
        ]);

        $team = Team::where('uuid', $request->input('team_uuid'))->firstOrFail();
        $user = User::findOrFail(Auth::id());

        $team->members()->attach($user->id, [
            'role' => $request->input('role', 'member'), 
        ]);

        \App\Models\Notification::create([
            "type" => "join",
            "team_id" => $team->id,
            "message" => $user->name . " has joined the team " . $team->name ,            
        ]);
        // activity()
        //     ->performedOn($team)
        //     ->causedBy($user)
        //     ->withProperties(['role' => $request->input('role', 'member')])
        //     ->log("User {$user->name} joined the team {$team->name} as {$request->input('role', 'member')}.");

        return response()->json([
            'message' => 'User joined the team successfully.',
            'team' => $team->load('members'),
            // 'activity' => Activity::where('subject_type', Team::class)
            //     ->where('subject_id', $team->id)
            //     ->latest()
            //     ->get(),
        ], 200);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'leader_id' => 'required|exists:users,id',
            'due_date' => 'required|date',
        ]);
        $dueDate = \Carbon\Carbon::parse($request->input('due_date'))->format('Y-m-d H:i:s');
        $uuid = Str::random(5);

        // Create a new team
        $team = Team::create([
            'name' => $request->input('name'),
            'leader_id' => $request->input('leader_id'),
            'due_date' => $dueDate,
            'uuid' => $uuid
        ]);

        // Return a response or redirect
        return response()->json(['team' => $team], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Team $team)
    {
        return response()->json(['team' => $team], 200);
    }

    public function add_member(Team $team, Request $request)
    {
        $request->validate([
            'member_id' => 'required|exists:users,id'
        ]);

        $user = User::findOrFail($request->input('member_id'));
        
        // Check if user is trying to add themselves
        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => 'You cannot add yourself to the team.',
            ], 400);
        }
        
        // Check if user is already a member of the team
        if ($team->members()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is already a member of this team.',
            ], 400);
        }
        
        $team->members()->attach($user->id, [
            'role' => $request->input('role', 'member'), 
        ]);

        \App\Models\Notification::create([
            "type" => "invite",
            "team_id" => $team->id,
            "message" => $user->name . " has been invited to the team " . $team->name ,
        ]);

        return response()->json([
            'message' => $user->name . ' has been added to team ' . $team->name . ' successfully.',
            'team' => $team->load(['leader', 'members.tasks']),
            // 'activity' => Activity::where('subject_type', Team::class)
            //     ->where('subject_id', $team->id)
            //     ->latest()
            //     ->get(),
        ], 200);

    }

    public function remove_member(Team $team, Request $request)
    {
        $request->validate([
            'member_id' => 'required|exists:users,id'
        ]);

        $user = User::findOrFail($request->input('member_id'));
        
        // Check if user is a member of the team
        if (!$team->members()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is not a member of this team.',
            ], 400);
        }
        
        // Remove user from team
        $team->members()->detach($user->id);
        
        // Create notification
        \App\Models\Notification::create([
            "type" => "remove",
            "team_id" => $team->id,
            "message" => $user->name . " has been removed from the team " . $team->name,
        ]);
        
        // Log activity
        activity()
            ->performedOn($team)
            ->causedBy($user)
            ->log($user->name . " has been removed from team " . $team->name);

        return response()->json([
            'message' => $user->name . ' has been removed from team ' . $team->name . ' successfully.',
            'team' => $team->load(['leader', 'members.tasks']),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Team $team)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team)
    {
        // Delete all related data
        // Tasks will be deleted automatically due to cascade on delete in migration
        // Members will be detached automatically due to cascade on delete in migration
        // Activities will be deleted automatically due to cascade on delete

        $teamName = $team->name;
        $team->delete();

        return response()->json([
            'message' => "Team '{$teamName}' has been deleted successfully.",
        ], 200);
    }
}
