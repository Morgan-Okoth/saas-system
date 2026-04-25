<?php

namespace App\Services;

use App\Models\School;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

/**
 * Cloudinary Storage Service (Multi-Tenant)
 * 
 * Manages tenant-isolated file uploads to Cloudinary.
 * All public IDs include school_id prefix to prevent cross-tenant access.
 * 
 * Security: Tenant isolation enforced at storage level via public ID namespacing.
 */
class CloudinaryStorageService
{
    /**
     * Upload file to Cloudinary with tenant isolation
     */
    public function upload(School $school, $file, string $folder = 'uploads', array $options = []): array
    {
        $publicId = $this->generatePublicId($school, $folder, $file);

        $uploadOptions = array_merge([
            'public_id' => $publicId,
            'folder' => $this->getTenantFolder($school),
            'resource_type' => 'auto',
            'overwrite' => false,
        ], $options);

        $result = Cloudinary::upload($file->getRealPath(), $uploadOptions);

        return [
            'public_id' => $publicId,
            'secure_url' => $result->getSecurePath(),
            'url' => $result->getPath(),
            'format' => $result->getExtension(),
            'size' => $result->getFileSize(),
            'width' => $result->getWidth(),
            'height' => $result->getHeight(),
            'school_id' => $school->id,
            'uploaded_at' => now(),
        ];
    }

    /**
     * Upload student profile photo
     */
    public function uploadStudentPhoto(School $school, $file, string $studentId): array
    {
        return $this->upload($school, $file, 'students/photos', [
            'public_id' => "school_{$school->id}/students/{$studentId}/profile",
            'transformation' => [
                'width' => 300,
                'height' => 300,
                'crop' => 'fill',
                'gravity' => 'face',
            ],
        ]);
    }

    /**
     * Upload document (e.g., certificates, reports)
     */
    public function uploadDocument(School $school, $file, string $documentType, string $title): array
    {
        return $this->upload($school, $file, "documents/{$documentType}", [
            'public_id' => "school_{$school->id}/documents/{$documentType}/" . \Str::slug($title),
            'resource_type' => 'auto',
        ]);
    }

    /**
     * Upload teacher profile photo
     */
    public function uploadTeacherPhoto(School $school, $file, string $teacherId): array
    {
        return $this->upload($school, $file, 'teachers/photos', [
            'public_id' => "school_{$school->id}/teachers/{$teacherId}/profile",
            'transformation' => [
                'width' => 300,
                'height' => 300,
                'crop' => 'fill',
            ],
        ]);
    }

    /**
     * Delete file from Cloudinary
     */
    public function delete(string $publicId): bool
    {
        try {
            Cloudinary::destroy($publicId);
            return true;
        } catch (\Exception $e) {
            \Log::error('Cloudinary delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete student photo
     */
    public function deleteStudentPhoto(School $school, string $studentId): bool
    {
        $publicId = "school_{$school->id}/students/{$studentId}/profile";
        return $this->delete($publicId);
    }

    /**
     * Get file URL by public ID
     */
    public function getUrl(string $publicId, array $transformation = []): string
    {
        $cloudinaryUrl = Cloudinary::getUrl($publicId);

        if (!empty($transformation)) {
            $cloudinaryUrl = $cloudinaryUrl->setTransformation($transformation);
        }

        return (string) $cloudinaryUrl;
    }

    /**
     * Generate secure, tenant-isolated public ID
     */
    protected function generatePublicId(School $school, string $folder, $file): string
    {
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $uniqueId = \Str::uuid()->toString();

        // Format: school_{school_id}/{folder}/{filename}_{uuid}.{ext}
        return "school_{$school->id}/{$folder}/" . \Str::slug($filename) . "_{$uniqueId}.{$extension}";
    }

    /**
     * Get tenant-specific folder path
     */
    protected function getTenantFolder(School $school): string
    {
        return "school_{$school->id}";
    }

    /**
     * List all files for a school (with pagination)
     */
    public function listFiles(School $school, string $prefix = '', int $maxResults = 50): array
    {
        // Cloudinary API call to list resources
        // Note: This may require Cloudinary account with enabled API access
        try {
            $resources = Cloudinary::listResources([
                'prefix' => $this->getTenantFolder($school) . '/' . $prefix,
                'max_results' => $maxResults,
                'type' => 'upload',
            ]);

            return $resources['resources'] ?? [];
        } catch (\Exception $e) {
            \Log::error('Cloudinary list resources failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate file before upload
     */
    public function validateFile($file, array $allowedTypes = [], int $maxSize = 10485760): bool
    {
        // Check file size (default 10MB)
        if ($file->getSize() > $maxSize) {
            return false;
        }

        // Check MIME type if specified
        if (!empty($allowedTypes) && !in_array($file->getMimeType(), $allowedTypes)) {
            return false;
        }

        return true;
    }
}
