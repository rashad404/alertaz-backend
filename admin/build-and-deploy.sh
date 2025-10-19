#!/bin/bash

# Build and Deploy Admin Panel Script
# This script builds the admin panel and copies it to Laravel's public folder

echo "🔨 Building admin panel..."
npm run build

if [ $? -ne 0 ]; then
    echo "❌ Build failed!"
    exit 1
fi

echo "📁 Removing old admin files from Laravel public..."
rm -rf ../public/admin

echo "📋 Copying new build to Laravel public folder..."
cp -r dist ../public/admin

echo "📝 Creating .htaccess for SPA routing..."
cat > ../public/admin/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /admin/
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /admin/index.html [L]
</IfModule>
EOF

echo "✅ Admin panel built and deployed to Laravel public folder!"
echo "📌 Next steps:"
echo "   1. cd to backend folder: cd .."
echo "   2. Add to git: git add public/admin"
echo "   3. Commit: git commit -m 'Update admin panel build'"
echo "   4. Push to production: git push origin main"
echo ""
echo "🌐 The admin panel will be available at: https://kredit.az/admin/"