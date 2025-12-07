<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
            'task_id' => 'required|exists:tasks,id',
            'user_id' => 'required|exists:users,id',
            'team_id' => 'required|exists:teams,id',

            'submission_file_base64' => 'nullable|string',
            'submission_filename' => 'nullable|string',
            'submission_mime_type' => 'nullable|string',
            'submission_link' => [
                'nullable',
                'url',
                'regex:/^https?:\/\/(docs\.google\.com)\/.+$/'
            ],
        ], [
            'submission_link.regex' => 'The submission link must be a valid Google Docs URL.',
        ]);

        $task = Task::findOrFail($request->input('task_id'));
        $team = Team::findOrFail($task->team_id);
        $type = '';

        if (!$request->filled('submission_file_base64') && !$request->filled('submission_link')) {
            return response()->json([
                'message' => 'Please upload either a file or a Google Docs Link.',
                'response' => 'error'
            ], 500);
        }

        if ($request->filled('submission_file_base64')) {
            $task->update([
                'submission_base64' => $request->input('submission_file_base64'),
                'submission_filename' => $request->input('submission_filename'),
                'submission_mime_type' => $request->input('submission_mime_type'),
                'submission' => null,
                'submitted_date' => Carbon::now(),
            ]);
            $type = "file";

        } else if ($request->filled('submission_link')) {
            $task->update([
                'submission' => $request->input('submission_link'),
                'submission_base64' => null,
                'submission_filename' => null,
                'submission_mime_type' => null,
                'submitted_date' => Carbon::now(),
            ]);
            $type = "link";
        }


        $user = User::findOrFail($request->input('user_id'));

        activity()
            ->performedOn($team)
            ->causedBy($user)
            ->withProperties(['role' => $request->input('role', 'member')])
            ->log($task->title . ": " . $user->name . " uploaded a " . $type);

        Notification::create([
            "team_id" => $team->id,
            "type" => "upload",
            "message" => $user->name . " uploaded a " . $type . " for " . $task->title
        ]);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'task' => $task,
            'response' => 'success'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function download(Task $task)
    {
        // Check if it's a base64 file
        if ($task->submission_base64) {
            $fileData = base64_decode($task->submission_base64);
            $filename = $task->submission_filename ?? 'download';
            $mimeType = $task->submission_mime_type ?? 'application/octet-stream';

            return response($fileData)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        // Fallback to old file system method
        $file = $task->submission;
        if (!$file) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        // Get file size (in bytes)
        $size = Storage::disk('public')->exists($file) ? Storage::disk('public')->size($file) : null;

        if ($size === null) {
            return response()->json(['message' => 'File not found on disk.'], 404);
        }

        return Storage::disk('public')->download($file);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Upload $upload)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Upload $upload)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        // Delete file from storage if it exists
        $file = $task->submission;
        if ($file && Storage::disk('public')->exists($file)) {
            Storage::disk('public')->delete($file);
        }

        $task->update([
            'submission' => null,
            'submission_base64' => null,
            'submission_filename' => null,
            'submission_mime_type' => null,
            'submitted_date' => null,
        ]);

        return response()->json(['message' => 'File deleted successfully.', 'task' => $task], 200);
    }
}
