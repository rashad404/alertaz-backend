<?php

use Illuminate\Support\Facades\Route;

use App\Models\Company;
use App\Models\News;
use App\Models\Category;
use App\Models\CompanyType;
use App\Models\Credit;
use App\Models\MetaTag;
use App\Models\User;
use App\Models\File;
use App\Models\Audio;
use App\Models\Video;
use App\Models\Image;

Route::get('/', function () {
    return response()->json(['message' => 'Kredit.az API', 'status' => 'running']);
});

// Serve the React admin panel for all /admin routes
// This must come FIRST to take precedence over any fallback routes
Route::get('/admin/{any?}', function ($any = null) {
    $path = public_path('admin/index.html');
    if (!file_exists($path)) {
        abort(404, "Admin panel file not found at: " . $path);
    }
    $content = file_get_contents($path);
    return response($content, 200)->header('Content-Type', 'text/html');
})->where('any', '.*')->name('admin.spa');



Route::get('/api/companies', fn() => Company::all());
Route::get('/api/news', fn() => News::all());
Route::get('/api/categories', fn() => Category::all());
Route::get('/api/company-types', fn() => CompanyType::all());
Route::get('/api/credits', fn() => Credit::all());
Route::get('/api/meta-tags', fn() => MetaTag::all());
Route::get('/api/users', fn() => User::all());
Route::get('/api/files', fn() => File::all());
Route::get('/api/audios', fn() => Audio::all());
Route::get('/api/videos', fn() => Video::all());
Route::get('/api/images', fn() => Image::all());
