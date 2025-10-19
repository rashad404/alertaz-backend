<?php
/**
 * Admin Panel Setup Helper
 * 
 * This file helps serve the React admin panel from Laravel's public directory.
 * Upload the built admin files to public/admin/ directory.
 * 
 * Directory structure should be:
 * public/
 *   admin/
 *     index.html
 *     assets/
 *       index-*.js
 *       index-*.css
 *     vite.svg
 */

// Check if admin directory exists
$adminPath = __DIR__ . '/admin';

if (!is_dir($adminPath)) {
    die("Admin directory not found. Please upload the built admin files to: $adminPath");
}

if (!file_exists($adminPath . '/index.html')) {
    die("Admin index.html not found. Please upload the built admin files to: $adminPath");
}

// Redirect to admin panel
header('Location: /admin/');
exit;