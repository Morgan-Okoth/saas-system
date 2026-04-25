<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Student API Controller
 * 
 * RESTful CRUD operations for student records.
 * All operations are tenant-scoped via school_id.
 */
class StudentController extends Controller
{
    /**
     * List all students
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $school = $request->user()->school;

        $students = Student::where('school_id', $school->id)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', '%' . $search . '%')
                      ->orWhere('last_name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->when($request->grade_level, function ($query, $grade) {
                $query->where('grade_level', $grade);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->per_page ?? 20);

        return response()->json($students);
    }

    /**
     * Create new student
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:students,email'],
            'date_of_birth' => ['nullable', 'date'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ]);

        $student = new Student(array_merge($validated, [
            'school_id' => $school->id,
        ]));

        $student->save();

        return response()->json([
            'message' => 'Student created successfully',
            'student' => $student,
        ], 201);
    }

    /**
     * Get student details
     */
    public function show(Student $student, Request $request): \Illuminate\Http\JsonResponse
    {
        // Ensure student belongs to user's school
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json([
                'error' => 'Student not found',
            ], 404);
        }

        return response()->json($student);
    }

    /**
     * Update student
     */
    public function update(Request $request, Student $student): \Illuminate\Http\JsonResponse
    {
        // Ensure student belongs to user's school
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json([
                'error' => 'Student not found',
            ], 404);
        }

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:students,email,' . $student->id],
            'date_of_birth' => ['nullable', 'date'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ]);

        $student->update($validated);

        return response()->json([
            'message' => 'Student updated successfully',
            'student' => $student,
        ]);
    }

    /**
     * Delete student
     */
    public function destroy(Student $student, Request $request): \Illuminate\Http\JsonResponse
    {
        // Ensure student belongs to user's school
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json([
                'error' => 'Student not found',
            ], 404);
        }

        $student->delete();

        return response()->json([
            'message' => 'Student deleted successfully',
        ]);
    }

    /**
     * Upload student photo
     */
    public function uploadPhoto(Request $request, Student $student): \Illuminate\Http\JsonResponse
    {
        // Ensure student belongs to user's school
        if ($student->school_id !== $request->user()->school_id) {
            return response()->json([
                'error' => 'Student not found',
            ], 404);
        }

        $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
        ]);

        // TODO: Integrate with Cloudinary service
        $path = $request->file('photo')->store(
            'students/' . $student->id,
            'public'
        );

        $student->update([
            'photo_path' => $path,
        ]);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'photo_url' => Storage::url($path),
        ]);
    }

    /**
     * Export students to CSV
     */
    public function export(Request $request): \Illuminate\Http\JsonResponse
    {
        $school = $request->user()->school;

        $students = Student::where('school_id', $school->id)->get();

        $csv = "ID,First Name,Last Name,Email,Grade Level,Date of Birth\n";

        foreach ($students as $student) {
            $csv .= implode(',', [
                $student->id,
                '"' . $student->first_name . '"',
                '"' . $student->last_name . '"',
                $student->email ?? '',
                $student->grade_level ?? '',
                $student->date_of_birth ?? '',
            ]) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="students_' . now()->format('Y-m-d') . '.csv"');
    }
}
