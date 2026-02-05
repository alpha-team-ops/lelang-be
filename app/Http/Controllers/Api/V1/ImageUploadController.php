<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    /**
     * Upload image file to MinIO storage
     * POST /api/v1/images/upload
     * 
     * Accepts multipart form data with 'file' field
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // max 5MB
            ]);

            $file = $request->file('file');
            
            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = 'auctions/' . $filename;

            // Store file in MinIO with public visibility
            $disk = Storage::disk('minio');
            $disk->put($path, file_get_contents($file), [
                'visibility' => 'public',
                'CacheControl' => 'max-age=31536000',
            ]);

            // Generate public URL
            $url = $disk->url($path);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'filename' => $filename,
                    'path' => $path,
                    'url' => $url,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage(),
                'code' => 'UPLOAD_ERROR',
            ], 500);
        }
    }

    /**
     * Bulk upload multiple images
     * POST /api/v1/images/bulk-upload
     * 
     * Accepts multipart form data with multiple 'files' fields
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        try {
            // Get all files with key 'files'
            $files = $request->file('files');
            
            // If only one file or no files
            if (!is_array($files)) {
                $files = $files ? [$files] : [];
            }
            
            // Validate array
            if (empty($files) || count($files) > 10) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => [
                        'files' => count($files) === 0 
                            ? ['At least one file is required'] 
                            : ['Maximum 10 files allowed']
                    ],
                ], 422);
            }
            
            // Validate each file
            $uploadedFiles = [];
            $disk = Storage::disk('minio');
            $errors = [];

            foreach ($files as $index => $file) {
                if (!$file || !$file->isValid()) {
                    $errors[$index] = 'Invalid file';
                    continue;
                }
                
                // Check file type
                if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $errors[$index] = 'Invalid image format. Allowed: jpeg, png, gif, webp';
                    continue;
                }
                
                // Check file size (max 5MB)
                if ($file->getSize() > 5120 * 1024) {
                    $errors[$index] = 'File size exceeds 5MB limit';
                    continue;
                }
                
                try {
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $path = 'auctions/' . $filename;

                    $disk->put($path, file_get_contents($file), [
                        'visibility' => 'public',
                        'CacheControl' => 'max-age=31536000',
                    ]);
                    $url = $disk->url($path);

                    $uploadedFiles[] = [
                        'filename' => $filename,
                        'path' => $path,
                        'url' => $url,
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ];
                } catch (\Exception $e) {
                    $errors[$index] = 'Upload failed: ' . $e->getMessage();
                }
            }

            return response()->json([
                'success' => empty($errors),
                'message' => 'Images uploaded successfully',
                'data' => [
                    'images' => $uploadedFiles,
                    'count' => count($uploadedFiles),
                    'errors' => !empty($errors) ? $errors : null,
                ],
            ], !empty($uploadedFiles) ? 201 : 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage(),
                'code' => 'UPLOAD_ERROR',
            ], 500);
        }
    }

    /**
     * Delete image from MinIO
     * DELETE /api/v1/images/:path
     */
    public function delete(Request $request, string $path): JsonResponse
    {
        try {
            $disk = Storage::disk('minio');

            if (!$disk->exists($path)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File not found',
                    'code' => 'FILE_NOT_FOUND',
                ], 404);
            }

            $disk->delete($path);

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Delete failed: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR',
            ], 500);
        }
    }

    /**
     * Get image URL by path
     * GET /api/v1/images/url/:path
     */
    public function getUrl(string $path): JsonResponse
    {
        try {
            $disk = Storage::disk('minio');

            if (!$disk->exists($path)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File not found',
                    'code' => 'FILE_NOT_FOUND',
                ], 404);
            }

            $url = $disk->url($path);

            return response()->json([
                'success' => true,
                'data' => [
                    'path' => $path,
                    'url' => $url,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error: ' . $e->getMessage(),
                'code' => 'ERROR',
            ], 500);
        }
    }
}
