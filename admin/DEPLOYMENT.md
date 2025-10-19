# Admin Panel Deployment Guide for cPanel/WHM

## Local Build Process

1. **Build the admin panel locally:**
```bash
cd /Users/rashadmirzayevprivate/projects/kredit/development/backend/admin
npm install
npm run build
```

This creates a `dist` folder with the production-ready files.

## Deployment to cPanel Server

### Method 1: Serve from Laravel Public Directory (Recommended)

1. **Upload the dist folder contents to your server:**
   - Connect via FTP/SFTP or cPanel File Manager
   - Navigate to: `public_html/admin/` (or your Laravel public directory)
   - Create an `admin` folder if it doesn't exist
   - Upload all contents from the local `dist` folder to `public_html/admin/`

2. **Access the admin panel:**
   - URL: `https://yourdomain.com/admin/`
   - The admin panel will use the existing Laravel API endpoints

### Method 2: Subdomain Setup

1. **Create a subdomain in cPanel:**
   - Go to cPanel → Subdomains
   - Create: `admin.yourdomain.com`
   - Document root: `/home/username/admin` (or preferred location)

2. **Upload files:**
   - Upload all contents from `dist` folder to the subdomain's document root

3. **Update configuration:**
   - Edit `vite.config.ts` before building:
   ```typescript
   base: '/', // Change from '/admin/' to '/'
   ```
   - Rebuild: `npm run build`

4. **Configure .htaccess for SPA routing:**
   Create `.htaccess` in the admin root directory:
   ```apache
   <IfModule mod_rewrite.c>
     RewriteEngine On
     RewriteBase /
     RewriteRule ^index\.html$ - [L]
     RewriteCond %{REQUEST_FILENAME} !-f
     RewriteCond %{REQUEST_FILENAME} !-d
     RewriteRule . /index.html [L]
   </IfModule>
   ```

## Environment Configuration

1. **Update API URL in production:**
   - Before building, create `.env.production` file:
   ```env
   VITE_API_URL=https://yourdomain.com/api
   ```

2. **Rebuild with production environment:**
   ```bash
   npm run build
   ```

## CORS Configuration (if needed)

If the admin panel is on a different domain/subdomain, update Laravel's CORS settings:

1. **Edit `config/cors.php`:**
```php
'allowed_origins' => [
    'https://yourdomain.com',
    'https://admin.yourdomain.com', // Add this if using subdomain
],
```

2. **Clear Laravel cache:**
```bash
php artisan config:clear
php artisan cache:clear
```

## Troubleshooting

### Issue: Blank page after deployment
- Check browser console for errors
- Verify the `base` path in `vite.config.ts` matches your deployment path
- Ensure all assets are loading from correct URLs

### Issue: API calls failing
- Check CORS settings
- Verify API URL in the built files
- Check Laravel logs for errors

### Issue: Routes not working (404 errors)
- Ensure `.htaccess` is properly configured for SPA routing
- Check Apache mod_rewrite is enabled

## Automated Deployment Script

Create a deployment script `deploy.sh`:

```bash
#!/bin/bash

# Build locally
npm run build

# Upload to server (adjust paths as needed)
rsync -avz --delete ./dist/ username@yourdomain.com:/home/username/public_html/admin/

echo "Deployment complete!"
```

Make it executable:
```bash
chmod +x deploy.sh
```

Run deployment:
```bash
./deploy.sh
```

## Security Considerations

1. **Protect admin routes:**
   - Ensure Laravel admin API routes require authentication
   - Consider IP whitelisting for admin access

2. **SSL Certificate:**
   - Always use HTTPS for admin panel
   - Enable auto-SSL in cPanel if not already done

3. **File permissions:**
   - Set appropriate permissions after upload:
   ```bash
   find /path/to/admin -type d -exec chmod 755 {} \;
   find /path/to/admin -type f -exec chmod 644 {} \;
   ```

## Updates and Maintenance

To update the admin panel:
1. Make changes locally
2. Test thoroughly with `npm run dev`
3. Build: `npm run build`
4. Upload the new `dist` contents to server
5. Clear browser cache

## Rollback Strategy

Keep backups of previous builds:
1. Before uploading new version, rename current admin folder to `admin_backup_[date]`
2. Upload new version
3. If issues occur, rename folders back to restore previous version