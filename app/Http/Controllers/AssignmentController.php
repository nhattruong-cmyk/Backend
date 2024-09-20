<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use Illuminate\Http\Request;
class AssignmentController extends Controller
{
    public function index()
    {
        $assignments = Assignment::all();
        return response()->json($assignments);
    }


    public function show($id)
    {
        $assignment = Assignment::find($id);

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        return response()->json($assignment);
    }
    public function update(Request $request, $id)
    {
        $assignment = Assignment::find($id);

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        $request->validate([
            'task_id' => 'sometimes|required|exists:tasks,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'role_id' => 'sometimes|required|exists:roles,id',
            'assigned_date' => 'sometimes|required|date',
            'note' => 'nullable|string',
        ]);

        $assignment->update($request->all());
        return response()->json(['message' => 'Assignment updated successfully', 'assignment' => $assignment]);
    }

    public function assignUserToTask(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'assigned_date' => 'required|date',
        ]);

        $assignment = Assignment::create($request->all());
        return response()->json(['message' => 'User assigned to task successfully', 'assignment' => $assignment], 201);
    }


    public function removeUserFromTask(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $assignment = Assignment::where('task_id', $request->task_id)
            ->where('user_id', $request->user_id)
            ->first();

        if ($assignment) {
            $assignment->delete();
            return response()->json(['message' => 'User removed from task']);
        }

        return response()->json(['message' => 'Assignment not found'], 404);
    }
}
