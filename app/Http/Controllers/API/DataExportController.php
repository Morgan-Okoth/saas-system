<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\GDPRComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Data Export & GDPR Controller
 * 
 * Handles GDPR compliance features:
 * - Data export (Right to access)
 * - Data deletion (Right to be forgotten)
 * - Data anonymization
 * - Subject Access Requests
 */
class DataExportController extends Controller
{
    protected $gdprService;

    public function __construct(GDPRComplianceService $gdprService)
    {
        $this->gdprService = $gdprService;
    }

    /**
     * Export all personal data
     */
    public function exportData(Request $request): \Illuminate\Http\JsonResponse
    {
        $school = $request->user()->school;

        // Generate export file
        $filename = $this->gdprService->generateExportFile($school);

        // Get download URL
        $url = Storage::temporaryUrl(
            "gdpr-exports/{$filename}",
            now()->addHours(24)
        );

        // Log the export
        \Log::info('GDPR data export requested', [
            'school_id' => $school->id,
            'user_id' => $request->user()->id,
            'filename' => $filename,
        ]);

        return response()->json([
            'message' => 'Data export generated successfully',
            'export_url' => $url,
            'expires_at' => now()->addHours(24)->toIso8601String(),
            'file_size' => Storage::size("gdpr-exports/{$filename}"),
        ]);
    }

    /**
     * Export data as CSV
     */
    public function exportCsv(Request $request): \Illuminate\Http\Response
    {
        $school = $request->user()->school;
        $data = $this->gdprService->exportSchoolData($school);

        // Convert to CSV
        $csv = "Category,Field,Value\n";

        foreach ($data as $category => $fields) {
            if (is_array($fields)) {
                foreach ($fields as $key => $value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $csv .= "{$category},{$key},\"{$value}\"\n";
                }
            }
        }

        $filename = "gdpr-export-{$school->id}-" . now()->format('Y-m-d') . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Anonymize user data
     */
    public function anonymizeData(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $success = $this->gdprService->anonymizeUser($user);

        if ($success) {
            return response()->json([
                'message' => 'Your data has been anonymized successfully',
                'note' => 'Some data may be retained for legal and accounting purposes',
            ]);
        }

        return response()->json([
            'error' => 'Failed to anonymize data',
            'code' => 'anonymization_failed',
        ], 500);
    }

    /**
     * Delete account (Right to be forgotten)
     */
    public function deleteAccount(Request $request): \Illuminate\Http\JsonResponse
    {
        $school = $request->user()->school;

        $request->validate([
            'confirm' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (!$request->confirm) {
            return response()->json([
                'error' => 'You must confirm deletion',
                'code' => 'confirmation_required',
            ], 400);
        }

        // Log deletion request
        \Log::warning('Account deletion requested', [
            'school_id' => $school->id,
            'user_id' => $request->user()->id,
            'reason' => $request->reason,
        ]);

        $success = $this->gdprService->deleteSchoolData($school);

        if ($success) {
            // Log out user
            $request->user()->tokens()->delete();

            return response()->json([
                'message' => 'Account deletion initiated',
                'note' => 'Your data will be permanently deleted after 30 days',
                'support_contact' => 'support@saas-system.com',
            ]);
        }

        return response()->json([
            'error' => 'Failed to delete account',
            'code' => 'deletion_failed',
        ], 500);
    }

    /**
     * Get data retention status
     */
    public function retentionStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $school = $request->user()->school;

        $status = $this->gdprService->getRetentionStatus($school);

        return response()->json($status);
    }

    /**
     * Generate Subject Access Request (SAR) report
     */
    public function sarReport(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $report = $this->gdprService->generateSARReport($user);

        // Log SAR request
        \Log::info('SAR report generated', [
            'school_id' => $user->school_id,
            'user_id' => $user->id,
        ]);

        return response()->json($report);
    }

    /**
     * Get export history
     */
    public function exportHistory(Request $request): \Illuminate\Http\JsonResponse
    {
        $school = $request->user()->school;

        $files = Storage::files('gdpr-exports');

        $history = collect($files)
            ->filter(function ($file) use ($school) {
                return str_contains($file, "school-{$school->id}-");
            })
            ->map(function ($file) {
                return [
                    'filename' => basename($file),
                    'size' => Storage::size($file),
                    'created_at' => Storage::lastModified($file),
                    'download_url' => Storage::temporaryUrl($file, now()->addHours(24)),
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return response()->json($history);
    }

    /**
     * Download specific export file
     */
    public function downloadExport(Request $request, string $filename): \Illuminate\Http\Response
    {
        $school = $request->user()->school;

        // Verify file belongs to school
        if (!str_contains($filename, "school-{$school->id}-")) {
            return response()->json([
                'error' => 'File not found',
            ], 404);
        }

        $path = "gdpr-exports/{$filename}";

        if (!Storage::exists($path)) {
            return response()->json([
                'error' => 'File not found',
            ], 404);
        }

        return Storage::download($path);
    }
}
