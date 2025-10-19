<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Str;

class ImageUploadService
{
    protected $manager;
    
    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }
    
    /**
     * Upload and process image
     * 
     * @param UploadedFile $file
     * @param string $folder Base folder name (e.g. 'news', 'banks', etc)
     * @return string|null Relative path to the uploaded file
     */
    public function upload(UploadedFile $file, string $folder = 'uploads'): ?string
    {
        try {
            // Generate month/year folder structure (MMYYYY format)
            $monthYear = now()->format('mY');
            $directory = "{$folder}/{$monthYear}";
            
            // Log the folder and directory for debugging
            \Log::info('ImageUploadService: folder=' . $folder . ', directory=' . $directory);
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename();
            $path = "{$directory}/{$filename}.jpg";
            
            // Read and process the image
            $image = $this->manager->read($file->getRealPath());
            
            // Convert to JPEG with 80% quality
            $processedImage = $image->toJpeg(80);
            
            // Store the processed image
            Storage::disk('public')->put($path, (string) $processedImage);
            
            return $path;
        } catch (\Exception $e) {
            \Log::error('Image upload failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate unique filename
     * 
     * @return string
     */
    protected function generateUniqueFilename(): string
    {
        return Str::random(32);
    }
    
    /**
     * Delete image from storage
     * 
     * @param string|null $path
     * @return bool
     */
    public function delete(?string $path): bool
    {
        if (!$path) {
            return true;
        }
        
        return Storage::disk('public')->delete($path);
    }
}