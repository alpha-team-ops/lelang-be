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
            $files = [];
            
            // Method 1: Try to get via Laravel's file() helper
            $requestFiles = $request->file('files');
            
            // Handle different structures
            if (is_array($requestFiles)) {
                // Multiple files sent as array
                $files = $requestFiles;
            } elseif ($requestFiles instanceof \Illuminate\Http\UploadedFile) {
                // Single file
                $files = [$requestFiles];
            }
            
            // Method 2: If Method 1 failed, try allFiles()
            if (empty($files)) {
                $allFiles = $request->allFiles();
                
                if (isset($allFiles['files'])) {
                    $f = $allFiles['files'];
                    if (is_array($f)) {
                        $files = $f;
                    } else {
                        $files = [$f];
                    }
                }
            }
            
            // Method 3: Check allFiles() for indexed format (files[0], files[1], etc)
            if (empty($files)) {
                $allFiles = $request->allFiles();
                foreach ($allFiles as $key => $value) {
                    if (strpos($key, 'files') === 0) {
                        if (is_array($value)) {
                            $files = array_merge($files, $value);
                        } else {
                            $files[] = $value;
                        }
                    }
                }
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
                if (!$file) {
                    $errors[$index] = 'File is null or missing';
                    continue;
                }
                
                if (!$file->isValid()) {
                    $errorCode = $file->getError();
                    $errorMessage = match($errorCode) {
                        UPLOAD_ERR_INI_SIZE => 'File exceeds php.ini upload_max_filesize',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload',
                        default => 'Unknown upload error (code: ' . $errorCode . ')'
                    };
                    $errors[$index] = 'Upload error: ' . $errorMessage;
                    continue;
                }
                
                // Check file type
                if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $errors[$index] = 'Invalid image format. Allowed: jpeg, png, gif, webp. Got: ' . $file->getMimeType();
                    continue;
                }
                
                // Check file size (max 5MB)
                if ($file->getSize() > 5120 * 1024) {
                    $errors[$index] = 'File size exceeds 5MB limit (size: ' . round($file->getSize() / 1024 / 1024, 2) . 'MB)';
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

            $statusCode = 201;
            $message = 'Images uploaded successfully';
            $success = true;

            // Determine response status based on upload results
            if (!empty($errors) && empty($uploadedFiles)) {
                // All files failed
                $statusCode = 422;
                $message = 'Image upload failed';
                $success = false;
            } elseif (!empty($errors)) {
                // Some files failed (partial success)
                $statusCode = 201;
                $message = 'Images uploaded with errors';
                $success = false;
            }

            return response()->json([
                'success' => $success,
                'message' => $message,
                'data' => [
                    'images' => $uploadedFiles,
                    'count' => count($uploadedFiles),
                    'errors' => !empty($errors) ? $errors : null,
                ],
            ], $statusCode);

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
