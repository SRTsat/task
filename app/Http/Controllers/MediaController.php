<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\User;
use App\Services\StorageConfigService;
use App\Services\DynamicStorageService;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
class MediaController extends Controller
{
    public function __construct(private PlanLimitService $planLimitService)
    {
    }
    public function index()
    {
        $user = auth()->user();
        $mediaItems = MediaItem::with('media')->latest()->get();
        
        $media = $mediaItems->flatMap(function ($item) use ($user) {
            $mediaQuery = $item->getMedia('images');
            
            if ($user->type === 'superadmin') {
                // No user_id filter for superadmin
            }
            else {
                $mediaQuery = $mediaQuery->where('user_id', $user->id);
            }
            
            return $mediaQuery->map(function ($media) {
                try {
                    $originalUrl = $this->getFullUrl($media->getUrl());
                    $thumbUrl = $originalUrl;
                    
                    try {
                        $thumbUrl = $this->getFullUrl($media->getUrl('thumb'));
                    } catch (\Exception $e) {
                        // If thumb conversion fails, use original
                    }
                    
                    return [
                        'id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'url' => $originalUrl,
                        'thumb_url' => $thumbUrl,
                        'size' => $media->size,
                        'mime_type' => $media->mime_type,
                        'user_id' => $media->user_id,
                        'created_at' => $media->created_at,
                    ];
                } catch (\Exception $e) {
                    // Skip media files with unavailable storage disks
                    return null;
                }
            })->filter(); // Remove null entries
        });

        return response()->json($media);
    }

    private function getFullUrl($url)
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        
        $baseUrl = request()->getSchemeAndHttpHost();
        return $baseUrl . $url;
    }

    private function getUserFriendlyError(\Exception $e, $fileName): string
    {
        $message = $e->getMessage();
        $extension = strtoupper(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Handle media library collection errors
        if (str_contains($message, 'was not accepted into the collection')) {
            if (str_contains($message, 'mime:')) {
                return __('File type not allowed: :extension', ['extension' => $extension]);
            }
            return __('File format not supported: :extension', ['extension' => $extension]);
        }
        
        // Handle storage errors
        if (str_contains($message, 'storage') || str_contains($message, 'disk')) {
            return __('Storage error: :extension', ['extension' => $extension]);
        }
        
        // Handle file size errors
        if (str_contains($message, 'size') || str_contains($message, 'large')) {
            return __('File too large: :extension', ['extension' => $extension]);
        }
        
        // Handle permission errors
        if (str_contains($message, 'permission') || str_contains($message, 'denied')) {
            return __('Permission denied: :extension', ['extension' => $extension]);
        }
        
        // Generic fallback
        return __('Upload failed: :extension', ['extension' => $extension]);
    }

    public function batchStore(Request $request)
    {
        // Check storage limits using plan limit service
        $user = auth()->user();
        $totalSize = collect($request->file('files'))->sum(fn($file) => $file->getSize());
        
        $limitCheck = $this->planLimitService->canUploadFile($user, $totalSize);
        if (!$limitCheck['allowed']) {
            return response()->json([
                'message' => __('Storage limit exceeded'),
                'errors' => [$limitCheck['message']]
            ], 422);
        }
        
        $config = StorageConfigService::getStorageConfig();
        $validationRules = StorageConfigService::getFileValidationRules();
        
        // Custom validation with user-friendly messages
        $validator = \Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*' => array_merge(['image'], $validationRules),
        ], [
            'files.*.image' => __('Only image files are allowed.'),
            'files.*.mimes' => __('Only specified file types are allowed: :type',[
                    'type' => isset($config['allowed_file_types']) && $config['allowed_file_types']
                        ? strtoupper(str_replace(',', ', ', $config['allowed_file_types']))
                        : __('Please check storage settings')
                ])
                ,
            'files.*.max' => __('File size cannot exceed :max MB.', ['max' => $config['max_file_size_mb']]),
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => __('File validation failed'),
                'errors' => $validator->errors()->all(),
                'allowed_types' => $config['allowed_file_types'],
                'max_size_mb' => $config['max_file_size_mb']
            ], 422);
        }

        $uploadedMedia = [];
        $errors = [];
        
        foreach ($request->file('files') as $file) {
            try {
                $mediaItem = MediaItem::create([
                    'name' => $file->getClientOriginalName(),
                ]);

                $media = $mediaItem->addMedia($file)
                    ->toMediaCollection('images');
                
                $media->user_id = auth()->id();
                $media->save();
                
                // Update user storage usage
                $this->updateStorageUsage(auth()->user(), $media->size);

                // Force thumbnail generationAdd commentMore actions
                try {
                    $media->getUrl('thumb');
                } catch (\Exception $e) {
                    // Thumbnail generation failed, but continue
                }

                $originalUrl = $this->getFullUrl($media->getUrl());
                $thumbUrl = $originalUrl; // Default to original
                
                try {
                    $thumbUrl = $this->getFullUrl($media->getUrl('thumb'));
                } catch (\Exception $e) {
                    // If thumb conversion fails, use original
                }
                
                $uploadedMedia[] = [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'url' => $originalUrl,
                    'thumb_url' => $thumbUrl,
                    'size' => $media->size,
                    'mime_type' => $media->mime_type,
                    'user_id' => $media->user_id,
                    'created_at' => $media->created_at,
                ];
            } catch (\Exception $e) {
                if (isset($mediaItem)) {
                    $mediaItem->delete();
                }
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $this->getUserFriendlyError($e, $file->getClientOriginalName())
                ];
            }
        }
        
        if (count($uploadedMedia) > 0 && empty($errors)) {
            return response()->json([
                'message' => __(':count file(s) uploaded successfully', ['count' => count($uploadedMedia)]),
                'data' => $uploadedMedia
            ]);
        } elseif (count($uploadedMedia) > 0 && !empty($errors)) {
            return response()->json([
                'message' => __(':uploaded uploaded, :failed failed', ['uploaded' => count($uploadedMedia), 'failed' => count($errors)]),
                'data' => $uploadedMedia,
                'errors' => array_column($errors, 'error')
            ]);
        } else {
            return response()->json([
                'message' => __('Upload failed'),
                'errors' => array_column($errors, 'error')
            ], 422);
        }
    }

    public function download($id)
    {
        $user = auth()->user();
        $query = Media::where('id', $id);
        
        // SuperAdmin and users with manage-any-media can download any media
        if ($user->type !== 'superadmin' && !$user->hasPermissionTo('manage-any-media')) {
            $query->where('user_id', $user->id);
        }
        
        $media = $query->firstOrFail();
        
        try {
            $filePath = $media->getPath();
            
            if (!file_exists($filePath)) {
                abort(404, __('File not found'));
            }
            
            return response()->download($filePath, $media->file_name);
        } catch (\Exception $e) {
            abort(404, __('File storage unavailable'));
        }
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $query = Media::where('id', $id);
        
        // SuperAdmin and users with manage-any-media can delete any media
        if ($user->type !== 'superadmin' && !$user->hasPermissionTo('manage-any-media')) {
            $query->where('user_id', $user->id);
        }
        
        $media = $query->firstOrFail();
        $mediaItem = $media->model;
        
        $fileSize = $media->size;
        
        try {
            $media->delete();
        } catch (\Exception $e) {
            // If storage disk is unavailable, force delete from database
            $media->forceDelete();
        }
        
        // Update user storage usage
        $this->updateStorageUsage(auth()->user(), -$fileSize);
        
        // Delete the MediaItem if it has no more media files
        if ($mediaItem && $mediaItem->getMedia()->count() === 0) {
            $mediaItem->delete();
        }

        return response()->json(['message' => __('Media deleted successfully')]);
    }
    
    private function checkStorageLimit($files)
    {
        $user = auth()->user();
        if ($user->type === 'superadmin') return null;
        
        $limit = $this->getUserStorageLimit($user);
        if (!$limit) return null;
        
        $uploadSize = collect($files)->sum('size');
        $currentUsage = $this->getUserStorageUsage($user);
        
        if (($currentUsage + $uploadSize) > $limit) {
            return response()->json([
                'message' => __('Storage limit exceeded'),
                'errors' => [('Please delete files or upgrade plan')]
            ], 422);
        }
        
        return null;
    }
    
    private function getUserStorageLimit($user)
    {
        if (!isSaasMode()) {
            return null; // No storage limits in non-SaaS mode
        }
        
        if ($user->type === 'company' && $user->plan) {
            return $user->plan->storage_limit * 1024 * 1024;
        }
        
        if ($user->created_by) {
            $company = User::find($user->created_by);
            if ($company && $company->plan) {
                return $company->plan->storage_limit * 1024 * 1024;
            }
        }
        
        return null;
    }
    
    private function getUserStorageUsage($user)
    {
        if ($user->type === 'company') {
            return User::where('created_by', $user->id)
                ->orWhere('id', $user->id)
                ->sum('storage_limit');
        }
        
        if ($user->created_by) {
            $company = User::find($user->created_by);
            if ($company) {
                return User::where('created_by', $company->id)
                    ->orWhere('id', $company->id)
                    ->sum('storage_limit');
            }
        }
        
        return $user->storage_limit;
    }
    
    private function updateStorageUsage($user, $size)
    {
        // Storage usage is now tracked via media table, no need to update user field
    }
}