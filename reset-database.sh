#!/bin/bash

echo "========================================="
echo "Database Reset Script for kredit.az"
echo "========================================="
echo ""
echo "⚠️  WARNING: This will DROP and recreate all tables!"
echo "Press Ctrl+C to cancel, or wait 5 seconds to continue..."
sleep 5

echo ""
echo "🗑️  Dropping all tables..."
php artisan migrate:fresh

if [ $? -ne 0 ]; then
    echo "❌ Migration failed!"
    exit 1
fi

echo "✓ Tables dropped and recreated"
echo ""

echo "🌱 Running seeders..."

# Run individual seeders in correct order
echo "  → Seeding categories..."
php artisan db:seed --class=CategorySeeder

echo "  → Seeding banks..."
php artisan db:seed --class=BankSeeder

echo "  → Seeding offers categories..."
php artisan db:seed --class=OffersCategorySeeder

echo "  → Seeding offers..."
php artisan db:seed --class=OfferSeeder

echo "  → Seeding deposit offers..."
php artisan db:seed --class=DepositOfferSeeder

echo "  → Seeding news..."
php artisan db:seed --class=NewsSeeder

echo "  → Seeding insurance companies..."
php artisan db:seed --class=InsuranceCompanySeeder

echo "  → Seeding insurance products..."
php artisan db:seed --class=InsuranceProductSeeder

echo "  → Seeding blogs..."
php artisan db:seed --class=BlogSeeder

echo ""
echo "✅ Database reset complete!"
echo "✅ All tables created and seeded successfully"