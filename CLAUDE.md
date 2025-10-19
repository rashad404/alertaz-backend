# Backend: Laravel API & Admin Panel

## Directory Structure
```
backend/
├── app/
│   ├── Http/Controllers/
│   │   ├── Api/          # Public API endpoints
│   │   └── Admin/        # Admin API endpoints
│   ├── Models/           # Eloquent models with translations
│   └── Services/         # Business logic services
├── admin/                # React admin panel (Vite)
├── database/
│   ├── migrations/       # Schema definitions
│   └── seeders/         # Test data seeders
└── routes/
    ├── api.php          # Public API routes
    └── admin.php        # Admin API routes
```

## Laravel Patterns & Rules
### Model Translations (Spatie\Translatable)
```php
// CORRECT - Translatable field
$offer->getTranslation('title', 'az')
$offer->title  // Gets current locale

// INCORRECT - Non-translatable field
$company->getTranslation('addresses', 'az')  // WILL THROW ERROR!
$company->addresses  // Use directly
```

### API Response Format
```php
return response()->json([
    'status' => 'success',
    'data' => $data,
    'message' => trans('messages.success')
], 200);
```

### Controller Methods
- `index()` - List with pagination
- `show($id)` - Single item detail
- `store(Request $request)` - Create new
- `update(Request $request, $id)` - Update existing
- `destroy($id)` - Delete item

## Admin Panel (React + Vite)
### Configuration
- Port: 5174
- API URL: `http://100.89.150.50:8000/api`
- Build: `npm run build` outputs to `public/admin`

### API Authentication
```javascript
// admin/src/services/api.ts
const API_URL = import.meta.env.VITE_API_URL || 'http://100.89.150.50:8000/api';
axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
```

### Common Components
- `RichTextEditor` - Content editing with formatting
- `ImageUpload` - Handle image uploads
- `DataTable` - Sortable/filterable tables
- `FormValidation` - Zod schema validation

## Database Conventions
### Migrations
```php
// Naming: YYYY_MM_DD_HHMMSS_create_TABLE_table.php
Schema::create('offers', function (Blueprint $table) {
    $table->id();
    $table->json('title');  // For translatable
    $table->string('slug')->unique();
    $table->foreignId('company_id')->constrained();
    $table->timestamps();
});
```

### Seeders
```php
// Run specific seeder
php artisan db:seed --class=OfferSeeder

// Fresh migration with seeds
php artisan migrate:fresh --seed
```

## API Routes Pattern
```php
// routes/api.php
Route::prefix('{locale}')->group(function () {
    Route::get('/offers', [OfferController::class, 'index']);
    Route::get('/offers/{id}', [OfferController::class, 'show']);
});

// Middleware
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Admin routes
});
```

## Common Commands
```bash
# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear

# Generate application key
php artisan key:generate

# Create controller
php artisan make:controller Api/ExampleController

# Create model with migration
php artisan make:model Example -m

# Create seeder
php artisan make:seeder ExampleSeeder

# Run tinker (interactive shell)
php artisan tinker
```

## Service Classes
```php
// app/Services/ImageUploadService.php
class ImageUploadService
{
    public function upload($file, $path = 'uploads')
    {
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move(public_path($path), $filename);
        return $path . '/' . $filename;
    }
}
```

## Error Handling
```php
try {
    // Operation
} catch (\Exception $e) {
    Log::error('Operation failed: ' . $e->getMessage());
    return response()->json([
        'status' => 'error',
        'message' => 'Operation failed'
    ], 500);
}
```

## Testing
```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=OfferTest

# Create test
php artisan make:test OfferTest
```

## Performance Optimization
- Use eager loading: `with(['company', 'category'])`
- Cache queries: `Cache::remember('key', 3600, fn() => ...)`
- Paginate large datasets: `paginate(20)`
- Use database indexes on frequently queried columns

## Security Checklist
- [ ] Validate all inputs
- [ ] Use prepared statements (Eloquent does this)
- [ ] Sanitize file uploads
- [ ] Implement rate limiting
- [ ] Use CSRF protection
- [ ] Hash passwords with bcrypt
- [ ] Never expose .env file