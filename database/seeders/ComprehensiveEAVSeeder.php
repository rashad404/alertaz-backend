<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ComprehensiveEAVSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, clear existing data (except company_types which is managed by CompanyTypeHierarchySeeder)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('company_entity_attribute_values')->truncate();
        DB::table('company_entities')->truncate();
        DB::table('company_attribute_values')->truncate();
        DB::table('companies')->truncate();
        DB::table('company_attribute_definitions')->truncate();
        DB::table('company_entity_types')->truncate();
        // DO NOT TRUNCATE company_types - it's managed by CompanyTypeHierarchySeeder
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        // Skip creating company types - they're already created by CompanyTypeHierarchySeeder
        // $this->createCompanyTypes();
        
        // Create entity types
        $this->createEntityTypes();
        
        // Create attribute definitions
        $this->createAttributeDefinitions();
        
        // Add sample companies with comprehensive data
        $this->createBanksWithFullData();
        $this->createInsuranceCompanies();
        $this->createCreditOrganizations();
        $this->createInvestmentCompanies();
        $this->createLeasingCompanies();
        
        echo "Comprehensive EAV data seeded successfully!\n";
    }
    
    private function createCompanyTypes(): void
    {
        $types = [
            ['type_name' => 'bank', 'description' => 'Banking institutions'],
            ['type_name' => 'insurance', 'description' => 'Insurance companies'],
            ['type_name' => 'credit_organization', 'description' => 'Non-bank credit organizations'],
            ['type_name' => 'investment', 'description' => 'Investment companies'],
            ['type_name' => 'leasing', 'description' => 'Leasing companies'],
            ['type_name' => 'payment_system', 'description' => 'Payment system providers'],
        ];
        
        foreach ($types as $type) {
            DB::table('company_types')->insert([
                'type_name' => $type['type_name'],
                'description' => $type['description'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    private function createEntityTypes(): void
    {
        // Use the IDs from CompanyTypeHierarchySeeder
        $bankTypeId = 1; // Banks
        $insuranceTypeId = 2; // Insurance Companies
        $creditOrgTypeId = 3; // Credit Organizations
        $investmentTypeId = 4; // Investment (if exists and active)
        $leasingTypeId = 5; // Leasing (if exists and active)
        
        // Bank entity types
        $bankEntities = [
            ['entity_name' => 'branch', 'description' => 'Bank branches'],
            ['entity_name' => 'atm', 'description' => 'ATM locations'],
            ['entity_name' => 'deposit', 'description' => 'Deposit products'],
            ['entity_name' => 'credit_card', 'description' => 'Credit card products'],
            ['entity_name' => 'loan', 'description' => 'Loan products'],
            ['entity_name' => 'mortgage', 'description' => 'Mortgage products'],
        ];
        
        foreach ($bankEntities as $entity) {
            DB::table('company_entity_types')->insert([
                'entity_name' => $entity['entity_name'],
                'parent_company_type_id' => $bankTypeId,
                'description' => $entity['description'],
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Insurance entity types
        $insuranceEntities = [
            ['entity_name' => 'insurance_product', 'description' => 'Insurance products'],
            ['entity_name' => 'claim_center', 'description' => 'Claim centers'],
            ['entity_name' => 'partner_hospital', 'description' => 'Partner hospitals'],
        ];
        
        foreach ($insuranceEntities as $entity) {
            DB::table('company_entity_types')->insert([
                'entity_name' => $entity['entity_name'],
                'parent_company_type_id' => $insuranceTypeId,
                'description' => $entity['description'],
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Credit organization entity types
        $creditOrgEntities = [
            ['entity_name' => 'credit_loan', 'description' => 'Loan products'],
            ['entity_name' => 'service_point', 'description' => 'Service points'],
        ];
        
        foreach ($creditOrgEntities as $entity) {
            DB::table('company_entity_types')->insert([
                'entity_name' => $entity['entity_name'],
                'parent_company_type_id' => $creditOrgTypeId,
                'description' => $entity['description'],
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Investment company entity types
        $investmentEntities = [
            ['entity_name' => 'investment_fund', 'description' => 'Investment funds'],
            ['entity_name' => 'portfolio', 'description' => 'Investment portfolios'],
        ];
        
        foreach ($investmentEntities as $entity) {
            DB::table('company_entity_types')->insert([
                'entity_name' => $entity['entity_name'],
                'parent_company_type_id' => $investmentTypeId,
                'description' => $entity['description'],
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Leasing entity types
        $leasingEntities = [
            ['entity_name' => 'leasing_product', 'description' => 'Leasing products'],
            ['entity_name' => 'vehicle_type', 'description' => 'Available vehicle types'],
        ];
        
        foreach ($leasingEntities as $entity) {
            DB::table('company_entity_types')->insert([
                'entity_name' => $entity['entity_name'],
                'parent_company_type_id' => $leasingTypeId,
                'description' => $entity['description'],
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    private function createAttributeDefinitions(): void
    {
        // Use the IDs from CompanyTypeHierarchySeeder
        $bankTypeId = 1; // Banks
        $insuranceTypeId = 2; // Insurance Companies  
        $creditOrgTypeId = 3; // Credit Organizations
        $investmentTypeId = 4; // Investment (may be inactive)
        $leasingTypeId = 5; // Leasing (may be inactive)
        $paymentTypeId = 6; // Payment System (may be inactive)
        
        // Bank attributes
        $bankAttributes = [
            ['key' => 'swift_code', 'name' => 'SWIFT Code', 'type' => 'string', 'required' => true],
            ['key' => 'bank_code', 'name' => 'Bank Code', 'type' => 'string', 'required' => true],
            ['key' => 'voen', 'name' => 'VOEN', 'type' => 'string', 'required' => false],
            ['key' => 'correspondent_account', 'name' => 'Correspondent Account', 'type' => 'string', 'required' => false],
            ['key' => 'reuters_dealing', 'name' => 'Reuters Dealing', 'type' => 'string', 'required' => false],
            ['key' => 'phone', 'name' => 'Phone', 'type' => 'string', 'required' => true],
            ['key' => 'email', 'name' => 'Email', 'type' => 'string', 'required' => false],
            ['key' => 'website', 'name' => 'Website', 'type' => 'string', 'required' => false],
            ['key' => 'headquarters', 'name' => 'Headquarters', 'type' => 'string', 'required' => false],
            ['key' => 'founding_date', 'name' => 'Founding Date', 'type' => 'date', 'required' => false],
            ['key' => 'total_assets', 'name' => 'Total Assets (AZN)', 'type' => 'decimal', 'required' => false],
            ['key' => 'employees_count', 'name' => 'Number of Employees', 'type' => 'number', 'required' => false],
            ['key' => 'branches_count', 'name' => 'Number of Branches', 'type' => 'number', 'required' => false],
            ['key' => 'atm_count', 'name' => 'Number of ATMs', 'type' => 'number', 'required' => false],
            ['key' => 'about', 'name' => 'About', 'type' => 'text', 'required' => false, 'translatable' => true],
        ];
        
        foreach ($bankAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'company_type_id' => $bankTypeId,
                'entity_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Insurance attributes
        $insuranceAttributes = [
            ['key' => 'license_number', 'name' => 'License Number', 'type' => 'string', 'required' => true],
            ['key' => 'license_date', 'name' => 'License Issue Date', 'type' => 'date', 'required' => false],
            ['key' => 'regulator_number', 'name' => 'Regulator Registration', 'type' => 'string', 'required' => false],
            ['key' => 'phone', 'name' => 'Phone', 'type' => 'string', 'required' => true],
            ['key' => 'emergency_phone', 'name' => '24/7 Emergency Phone', 'type' => 'string', 'required' => false],
            ['key' => 'email', 'name' => 'Email', 'type' => 'string', 'required' => false],
            ['key' => 'website', 'name' => 'Website', 'type' => 'string', 'required' => false],
            ['key' => 'headquarters', 'name' => 'Headquarters', 'type' => 'string', 'required' => false],
            ['key' => 'founding_date', 'name' => 'Founding Date', 'type' => 'date', 'required' => false],
            ['key' => 'coverage_types', 'name' => 'Coverage Types', 'type' => 'json', 'required' => false],
            ['key' => 'claim_process_time', 'name' => 'Average Claim Time (days)', 'type' => 'number', 'required' => false],
            ['key' => 'about', 'name' => 'About', 'type' => 'text', 'required' => false, 'translatable' => true],
        ];
        
        foreach ($insuranceAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'company_type_id' => $insuranceTypeId,
                'entity_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Credit organization attributes
        $creditOrgAttributes = [
            ['key' => 'license_number', 'name' => 'License Number', 'type' => 'string', 'required' => true],
            ['key' => 'regulator_number', 'name' => 'CBAR Registration', 'type' => 'string', 'required' => false],
            ['key' => 'phone', 'name' => 'Phone', 'type' => 'string', 'required' => true],
            ['key' => 'email', 'name' => 'Email', 'type' => 'string', 'required' => false],
            ['key' => 'website', 'name' => 'Website', 'type' => 'string', 'required' => false],
            ['key' => 'max_loan_amount', 'name' => 'Maximum Loan Amount', 'type' => 'decimal', 'required' => false],
            ['key' => 'min_loan_amount', 'name' => 'Minimum Loan Amount', 'type' => 'decimal', 'required' => false],
            ['key' => 'approval_time', 'name' => 'Approval Time (hours)', 'type' => 'number', 'required' => false],
            ['key' => 'about', 'name' => 'About', 'type' => 'text', 'required' => false, 'translatable' => true],
        ];
        
        foreach ($creditOrgAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'company_type_id' => $creditOrgTypeId,
                'entity_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Investment company attributes
        $investmentAttributes = [
            ['key' => 'license_number', 'name' => 'Securities License', 'type' => 'string', 'required' => true],
            ['key' => 'phone', 'name' => 'Phone', 'type' => 'string', 'required' => true],
            ['key' => 'email', 'name' => 'Email', 'type' => 'string', 'required' => false],
            ['key' => 'website', 'name' => 'Website', 'type' => 'string', 'required' => false],
            ['key' => 'aum', 'name' => 'Assets Under Management', 'type' => 'decimal', 'required' => false],
            ['key' => 'min_investment', 'name' => 'Minimum Investment', 'type' => 'decimal', 'required' => false],
            ['key' => 'about', 'name' => 'About', 'type' => 'text', 'required' => false, 'translatable' => true],
        ];
        
        foreach ($investmentAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'company_type_id' => $investmentTypeId,
                'entity_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Leasing company attributes
        $leasingAttributes = [
            ['key' => 'license_number', 'name' => 'License Number', 'type' => 'string', 'required' => true],
            ['key' => 'phone', 'name' => 'Phone', 'type' => 'string', 'required' => true],
            ['key' => 'email', 'name' => 'Email', 'type' => 'string', 'required' => false],
            ['key' => 'website', 'name' => 'Website', 'type' => 'string', 'required' => false],
            ['key' => 'min_down_payment', 'name' => 'Minimum Down Payment %', 'type' => 'decimal', 'required' => false],
            ['key' => 'max_term_months', 'name' => 'Maximum Term (months)', 'type' => 'number', 'required' => false],
            ['key' => 'vehicle_types', 'name' => 'Vehicle Types', 'type' => 'json', 'required' => false],
            ['key' => 'about', 'name' => 'About', 'type' => 'text', 'required' => false, 'translatable' => true],
        ];
        
        foreach ($leasingAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'company_type_id' => $leasingTypeId,
                'entity_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Now add entity-level attribute definitions
        $this->createEntityAttributeDefinitions();
    }
    
    private function createEntityAttributeDefinitions(): void
    {
        // Branch attributes
        $branchTypeId = DB::table('company_entity_types')->where('entity_name', 'branch')->value('id');
        $branchAttributes = [
            ['key' => 'branch_name', 'name' => 'Branch Name', 'type' => 'string', 'required' => true, 'translatable' => true],
            ['key' => 'branch_code', 'name' => 'Branch Code', 'type' => 'string', 'required' => true],
            ['key' => 'address', 'name' => 'Address', 'type' => 'string', 'required' => true, 'translatable' => true],
            ['key' => 'phone', 'name' => 'Phone', 'type' => 'string', 'required' => true],
            ['key' => 'working_hours', 'name' => 'Working Hours', 'type' => 'json', 'required' => false],
            ['key' => 'latitude', 'name' => 'Latitude', 'type' => 'decimal', 'required' => false],
            ['key' => 'longitude', 'name' => 'Longitude', 'type' => 'decimal', 'required' => false],
            ['key' => 'is_24_7', 'name' => '24/7 Service', 'type' => 'boolean', 'required' => false],
        ];
        
        foreach ($branchAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'entity_type_id' => $branchTypeId,
                'company_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Deposit product attributes
        $depositTypeId = DB::table('company_entity_types')->where('entity_name', 'deposit')->value('id');
        $depositAttributes = [
            ['key' => 'product_name', 'name' => 'Product Name', 'type' => 'string', 'required' => true, 'translatable' => true],
            ['key' => 'interest_rate', 'name' => 'Interest Rate %', 'type' => 'decimal', 'required' => true],
            ['key' => 'min_amount', 'name' => 'Minimum Amount', 'type' => 'decimal', 'required' => true],
            ['key' => 'max_amount', 'name' => 'Maximum Amount', 'type' => 'decimal', 'required' => false],
            ['key' => 'term_months', 'name' => 'Term (months)', 'type' => 'number', 'required' => false],
            ['key' => 'currency', 'name' => 'Currency', 'type' => 'string', 'required' => true],
            ['key' => 'early_withdrawal', 'name' => 'Early Withdrawal Allowed', 'type' => 'boolean', 'required' => false],
            ['key' => 'capitalization', 'name' => 'Interest Capitalization', 'type' => 'boolean', 'required' => false],
        ];
        
        foreach ($depositAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'entity_type_id' => $depositTypeId,
                'company_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Credit card attributes
        $cardTypeId = DB::table('company_entity_types')->where('entity_name', 'credit_card')->value('id');
        $cardAttributes = [
            ['key' => 'card_name', 'name' => 'Card Name', 'type' => 'string', 'required' => true, 'translatable' => true],
            ['key' => 'card_network', 'name' => 'Card Network', 'type' => 'string', 'required' => true],
            ['key' => 'card_level', 'name' => 'Card Level', 'type' => 'string', 'required' => true],
            ['key' => 'annual_fee', 'name' => 'Annual Fee', 'type' => 'decimal', 'required' => false],
            ['key' => 'cashback_rate', 'name' => 'Cashback Rate %', 'type' => 'decimal', 'required' => false],
            ['key' => 'grace_period', 'name' => 'Grace Period (days)', 'type' => 'number', 'required' => false],
            ['key' => 'credit_limit_min', 'name' => 'Min Credit Limit', 'type' => 'decimal', 'required' => false],
            ['key' => 'credit_limit_max', 'name' => 'Max Credit Limit', 'type' => 'decimal', 'required' => false],
            ['key' => 'benefits', 'name' => 'Benefits', 'type' => 'json', 'required' => false],
        ];
        
        foreach ($cardAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'entity_type_id' => $cardTypeId,
                'company_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Loan product attributes
        $loanTypeId = DB::table('company_entity_types')->where('entity_name', 'loan')->value('id');
        $loanAttributes = [
            ['key' => 'loan_name', 'name' => 'Loan Name', 'type' => 'string', 'required' => true, 'translatable' => true],
            ['key' => 'loan_type', 'name' => 'Loan Type', 'type' => 'string', 'required' => true],
            ['key' => 'min_amount', 'name' => 'Minimum Amount', 'type' => 'decimal', 'required' => true],
            ['key' => 'max_amount', 'name' => 'Maximum Amount', 'type' => 'decimal', 'required' => true],
            ['key' => 'interest_rate_min', 'name' => 'Min Interest Rate %', 'type' => 'decimal', 'required' => true],
            ['key' => 'interest_rate_max', 'name' => 'Max Interest Rate %', 'type' => 'decimal', 'required' => false],
            ['key' => 'term_months_min', 'name' => 'Min Term (months)', 'type' => 'number', 'required' => true],
            ['key' => 'term_months_max', 'name' => 'Max Term (months)', 'type' => 'number', 'required' => true],
            ['key' => 'collateral_required', 'name' => 'Collateral Required', 'type' => 'boolean', 'required' => false],
        ];
        
        foreach ($loanAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'entity_type_id' => $loanTypeId,
                'company_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Insurance product attributes
        $insuranceProductTypeId = DB::table('company_entity_types')->where('entity_name', 'insurance_product')->value('id');
        $insuranceProductAttributes = [
            ['key' => 'product_name', 'name' => 'Product Name', 'type' => 'string', 'required' => true, 'translatable' => true],
            ['key' => 'product_type', 'name' => 'Product Type', 'type' => 'string', 'required' => true],
            ['key' => 'coverage_amount_min', 'name' => 'Min Coverage', 'type' => 'decimal', 'required' => false],
            ['key' => 'coverage_amount_max', 'name' => 'Max Coverage', 'type' => 'decimal', 'required' => false],
            ['key' => 'premium_monthly', 'name' => 'Monthly Premium', 'type' => 'decimal', 'required' => false],
            ['key' => 'deductible', 'name' => 'Deductible', 'type' => 'decimal', 'required' => false],
            ['key' => 'coverage_details', 'name' => 'Coverage Details', 'type' => 'json', 'required' => false],
        ];
        
        foreach ($insuranceProductAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'entity_type_id' => $insuranceProductTypeId,
                'company_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Credit organization loan attributes
        $creditLoanTypeId = DB::table('company_entity_types')->where('entity_name', 'credit_loan')->value('id');
        $creditLoanAttributes = [
            ['key' => 'loan_name', 'name' => 'Loan Name', 'type' => 'json', 'required' => true, 'translatable' => true],
            ['key' => 'loan_type', 'name' => 'Loan Type', 'type' => 'string', 'required' => true],
            ['key' => 'interest_rate', 'name' => 'Interest Rate %', 'type' => 'decimal', 'required' => true],
            ['key' => 'min_amount', 'name' => 'Minimum Amount', 'type' => 'decimal', 'required' => true],
            ['key' => 'max_amount', 'name' => 'Maximum Amount', 'type' => 'decimal', 'required' => true],
            ['key' => 'max_term_months', 'name' => 'Max Term (months)', 'type' => 'number', 'required' => true],
            ['key' => 'requirements', 'name' => 'Requirements', 'type' => 'json', 'required' => false, 'translatable' => true],
            ['key' => 'processing_fee', 'name' => 'Processing Fee', 'type' => 'decimal', 'required' => false],
        ];
        
        foreach ($creditLoanAttributes as $attr) {
            DB::table('company_attribute_definitions')->insert([
                'entity_type_id' => $creditLoanTypeId,
                'company_type_id' => null,
                'attribute_name' => $attr['name'],
                'attribute_key' => $attr['key'],
                'data_type' => $attr['type'],
                'is_required' => $attr['required'],
                'is_translatable' => $attr['translatable'] ?? false,
                'validation_rules' => null,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    private function createBanksWithFullData(): void
    {
        $bankTypeId = 1; // Banks
        
        $banks = [
            [
                'name' => 'Kapital Bank',
                'slug' => 'kapital-bank',
                'attributes' => [
                    'swift_code' => 'AIIBAZ2X',
                    'bank_code' => '200028',
                    'voen' => '1500031691',
                    'correspondent_account' => 'AZ37NABZ01350100000000001944',
                    'reuters_dealing' => 'KPTL',
                    'phone' => '+994 12 196',
                    'email' => 'info@kapitalbank.az',
                    'website' => 'https://kapitalbank.az',
                    'headquarters' => 'Fizuli küçəsi 71, Bakı AZ1014',
                    'founding_date' => '1874-01-01',
                    'total_assets' => 5500000000,
                    'employees_count' => 3500,
                    'branches_count' => 106,
                    'atm_count' => 650,
                    'about' => json_encode([
                        'az' => 'Kapital Bank 1874-cü ildən fəaliyyət göstərən Azərbaycanın ən qədim və aparıcı bankıdır.',
                        'en' => 'Kapital Bank is Azerbaijan\'s oldest and leading bank, operating since 1874.',
                        'ru' => 'Kapital Bank - старейший и ведущий банк Азербайджана, работающий с 1874 года.'
                    ]),
                ],
                'branches' => [
                    ['name' => 'Baş Ofis', 'code' => 'HQ001', 'address' => 'Fizuli küçəsi 71', 'phone' => '+994 12 196', 'is_24_7' => false],
                    ['name' => 'Gənclik Mall Filialı', 'code' => 'GM002', 'address' => 'Gənclik Mall, Fətəli Xan Xoyski 111', 'phone' => '+994 12 196', 'is_24_7' => true],
                    ['name' => '28 Mall Filialı', 'code' => '28M003', 'address' => '28 Mall, Azadlıq prospekti', 'phone' => '+994 12 196', 'is_24_7' => true],
                ],
                'deposits' => [
                    ['name' => 'Standart Əmanət', 'rate' => 8.5, 'min' => 100, 'max' => 1000000, 'term' => 12, 'currency' => 'AZN'],
                    ['name' => 'Premium Əmanət', 'rate' => 10.5, 'min' => 5000, 'max' => 5000000, 'term' => 24, 'currency' => 'AZN'],
                    ['name' => 'Dollar Əmanət', 'rate' => 4.5, 'min' => 500, 'max' => 1000000, 'term' => 12, 'currency' => 'USD'],
                    ['name' => 'Uşaq Əmanəti', 'rate' => 11.0, 'min' => 50, 'max' => 100000, 'term' => 60, 'currency' => 'AZN'],
                ],
                'cards' => [
                    ['name' => 'Birbank Cashback', 'network' => 'Visa', 'level' => 'Classic', 'fee' => 0, 'cashback' => 1.5, 'grace' => 45],
                    ['name' => 'Birbank Premium', 'network' => 'Mastercard', 'level' => 'Gold', 'fee' => 60, 'cashback' => 3.0, 'grace' => 56],
                    ['name' => 'Birbank Platinum', 'network' => 'Visa', 'level' => 'Platinum', 'fee' => 120, 'cashback' => 5.0, 'grace' => 62],
                ],
                'loans' => [
                    ['name' => 'Nağd Kredit', 'type' => 'consumer', 'min' => 300, 'max' => 50000, 'rate_min' => 11.9, 'rate_max' => 28, 'term_min' => 3, 'term_max' => 48],
                    ['name' => 'Avto Kredit', 'type' => 'auto', 'min' => 5000, 'max' => 200000, 'rate_min' => 9, 'rate_max' => 18, 'term_min' => 6, 'term_max' => 84],
                ],
            ],
            [
                'name' => 'Pasha Bank',
                'slug' => 'pasha-bank',
                'attributes' => [
                    'swift_code' => 'PASAAZ22',
                    'bank_code' => '505201',
                    'voen' => '1401555071',
                    'correspondent_account' => 'AZ07NABZ01350100000000002944',
                    'reuters_dealing' => 'PASH',
                    'phone' => '+994 12 496 50 00',
                    'email' => 'info@pashabank.az',
                    'website' => 'https://pashabank.az',
                    'headquarters' => 'Bakı şəhəri, Yasamal rayonu, C.Cabbarlı küç. 15',
                    'founding_date' => '2007-05-04',
                    'total_assets' => 3800000000,
                    'employees_count' => 850,
                    'branches_count' => 15,
                    'atm_count' => 45,
                    'about' => json_encode([
                        'az' => 'PASHA Bank Azərbaycanın aparıcı korporativ və investisiya bankıdır.',
                        'en' => 'PASHA Bank is Azerbaijan\'s leading corporate and investment bank.',
                        'ru' => 'PASHA Bank - ведущий корпоративный и инвестиционный банк Азербайджана.'
                    ]),
                ],
                'branches' => [
                    ['name' => 'Baş Ofis', 'code' => 'PB001', 'address' => 'C.Cabbarlı küç. 15', 'phone' => '+994 12 496 50 00', 'is_24_7' => false],
                    ['name' => 'Port Baku Filialı', 'code' => 'PB002', 'address' => 'Port Baku, Neftçilər prospekti', 'phone' => '+994 12 496 50 00', 'is_24_7' => false],
                ],
                'deposits' => [
                    ['name' => 'PASHA Əmanət', 'rate' => 9.0, 'min' => 1000, 'max' => 10000000, 'term' => 12, 'currency' => 'AZN'],
                    ['name' => 'VIP Əmanət', 'rate' => 11.0, 'min' => 50000, 'max' => 50000000, 'term' => 24, 'currency' => 'AZN'],
                ],
                'cards' => [
                    ['name' => 'PASHA Business', 'network' => 'Visa', 'level' => 'Business', 'fee' => 100, 'cashback' => 2.0, 'grace' => 50],
                    ['name' => 'PASHA Platinum', 'network' => 'Mastercard', 'level' => 'Platinum', 'fee' => 200, 'cashback' => 4.0, 'grace' => 60],
                ],
                'loans' => [
                    ['name' => 'Biznes Kredit', 'type' => 'business', 'min' => 10000, 'max' => 5000000, 'rate_min' => 8, 'rate_max' => 15, 'term_min' => 12, 'term_max' => 60],
                ],
            ],
            [
                'name' => 'ABB (Azerbaijan International Bank)',
                'slug' => 'abb',
                'attributes' => [
                    'swift_code' => 'IBAZAZ2X',
                    'bank_code' => '805620',
                    'voen' => '9900001881',
                    'correspondent_account' => 'AZ03AIIB32051019441000206159',
                    'reuters_dealing' => 'AIBB',
                    'phone' => '+994 12 493 00 91',
                    'email' => 'info@abb-bank.az',
                    'website' => 'https://abb-bank.az',
                    'headquarters' => 'Nizami küçəsi 67, Bakı AZ1005',
                    'founding_date' => '1992-01-10',
                    'total_assets' => 2100000000,
                    'employees_count' => 2000,
                    'branches_count' => 40,
                    'atm_count' => 180,
                    'about' => json_encode([
                        'az' => 'ABB müasir bank xidmətləri və innovativ həllər təklif edən universal bankdır.',
                        'en' => 'ABB is a universal bank offering modern banking services and innovative solutions.',
                        'ru' => 'ABB - универсальный банк, предлагающий современные банковские услуги и инновационные решения.'
                    ]),
                ],
                'branches' => [
                    ['name' => 'Baş Ofis', 'code' => 'ABB001', 'address' => 'Nizami küçəsi 67', 'phone' => '+994 12 493 00 91', 'is_24_7' => false],
                ],
                'deposits' => [
                    ['name' => 'ABB Əmanət', 'rate' => 8.0, 'min' => 200, 'max' => 500000, 'term' => 12, 'currency' => 'AZN'],
                ],
                'cards' => [
                    ['name' => 'ABB Miles', 'network' => 'Visa', 'level' => 'Gold', 'fee' => 50, 'cashback' => 2.5, 'grace' => 50],
                ],
                'loans' => [
                    ['name' => 'Ekspress Kredit', 'type' => 'consumer', 'min' => 500, 'max' => 30000, 'rate_min' => 14, 'rate_max' => 24, 'term_min' => 6, 'term_max' => 36],
                ],
            ],
        ];
        
        foreach ($banks as $bankData) {
            $companyId = $this->createCompany($bankData['name'], $bankData['slug'], $bankTypeId);
            $this->addCompanyAttributes($companyId, $bankTypeId, $bankData['attributes']);
            
            // Add branches
            foreach ($bankData['branches'] as $branch) {
                $this->addBankBranch($companyId, $branch);
            }
            
            // Add deposits
            foreach ($bankData['deposits'] as $deposit) {
                $this->addBankDeposit($companyId, $deposit);
            }
            
            // Add credit cards
            foreach ($bankData['cards'] as $card) {
                $this->addBankCreditCard($companyId, $card);
            }
            
            // Add loans
            foreach ($bankData['loans'] as $loan) {
                $this->addBankLoan($companyId, $loan);
            }
        }
    }
    
    private function createInsuranceCompanies(): void
    {
        $insuranceTypeId = 2; // Insurance Companies
        
        $companies = [
            [
                'name' => 'Pasha Sığorta',
                'slug' => 'pasha-sigorta',
                'attributes' => [
                    'license_number' => 'MF-022',
                    'license_date' => '2006-11-17',
                    'regulator_number' => 'CBAR/INS/2006-022',
                    'phone' => '+994 12 598 17 17',
                    'emergency_phone' => '+994 12 911',
                    'email' => 'info@pasha-insurance.az',
                    'website' => 'https://pasha-insurance.az',
                    'headquarters' => 'Bakı şəhəri, C.Cabbarlı küç. 15',
                    'founding_date' => '2006-11-17',
                    'coverage_types' => json_encode(['auto', 'health', 'property', 'life', 'travel']),
                    'claim_process_time' => 7,
                    'about' => json_encode([
                        'az' => 'Pasha Sığorta Azərbaycanın lider sığorta şirkətidir.',
                        'en' => 'Pasha Insurance is Azerbaijan\'s leading insurance company.',
                        'ru' => 'Pasha Insurance - ведущая страховая компания Азербайджана.'
                    ]),
                ],
                'products' => [
                    ['name' => 'Avto Sığorta', 'type' => 'auto', 'coverage_min' => 500, 'coverage_max' => 100000, 'premium' => 50],
                    ['name' => 'Tibbi Sığorta', 'type' => 'health', 'coverage_min' => 1000, 'coverage_max' => 500000, 'premium' => 100],
                    ['name' => 'Əmlak Sığortası', 'type' => 'property', 'coverage_min' => 10000, 'coverage_max' => 5000000, 'premium' => 200],
                ],
            ],
            [
                'name' => 'AXA MBASK',
                'slug' => 'axa-mbask',
                'attributes' => [
                    'license_number' => 'MF-033',
                    'license_date' => '2007-03-22',
                    'regulator_number' => 'CBAR/INS/2007-033',
                    'phone' => '+994 12 497 77 77',
                    'emergency_phone' => '+994 12 912',
                    'email' => 'info@axa-mbask.az',
                    'website' => 'https://axa.az',
                    'headquarters' => 'Landau küç. 16, Bakı',
                    'founding_date' => '2007-03-22',
                    'coverage_types' => json_encode(['auto', 'health', 'property', 'travel', 'liability']),
                    'claim_process_time' => 5,
                    'about' => json_encode([
                        'az' => 'AXA MBASK beynəlxalq standartlarda sığorta xidmətləri təklif edir.',
                        'en' => 'AXA MBASK offers insurance services at international standards.',
                        'ru' => 'AXA MBASK предлагает страховые услуги на международном уровне.'
                    ]),
                ],
                'products' => [
                    ['name' => 'KASKO', 'type' => 'auto', 'coverage_min' => 1000, 'coverage_max' => 200000, 'premium' => 80],
                    ['name' => 'Səyahət Sığortası', 'type' => 'travel', 'coverage_min' => 10000, 'coverage_max' => 100000, 'premium' => 30],
                ],
            ],
        ];
        
        foreach ($companies as $companyData) {
            $companyId = $this->createCompany($companyData['name'], $companyData['slug'], $insuranceTypeId);
            $this->addCompanyAttributes($companyId, $insuranceTypeId, $companyData['attributes']);
            
            // Add insurance products
            foreach ($companyData['products'] as $product) {
                $this->addInsuranceProduct($companyId, $product);
            }
        }
    }
    
    private function createCreditOrganizations(): void
    {
        $creditOrgTypeId = 3; // Credit Organizations
        
        $companies = [
            [
                'name' => 'TBC Kredit',
                'slug' => 'tbc-kredit',
                'attributes' => [
                    'license_number' => 'BOKT-019',
                    'regulator_number' => 'CBAR/NBCO/2015-019',
                    'phone' => '+994 12 599 89 89',
                    'email' => 'info@tbckredit.az',
                    'website' => 'https://tbckredit.az',
                    'max_loan_amount' => 20000,
                    'min_loan_amount' => 100,
                    'approval_time' => 1,
                    'about' => json_encode([
                        'az' => 'TBC Kredit sürətli və rahat kredit xidmətləri təklif edir.',
                        'en' => 'TBC Credit offers fast and convenient credit services.',
                        'ru' => 'TBC Кредит предлагает быстрые и удобные кредитные услуги.'
                    ]),
                ],
                'loans' => [
                    [
                        'name' => json_encode(['az' => 'Nağd Kredit', 'en' => 'Cash Loans', 'ru' => 'Наличный кредит']),
                        'code' => 'CL001',
                        'type' => 'cash',
                        'interest_rate' => 16.0,
                        'min_amount' => 1000,
                        'max_amount' => 55000,
                        'max_term' => 48,
                        'requirements' => json_encode(['az' => 'Yaş 20-65, sabit gəlir', 'en' => 'Age 20-65, stable income', 'ru' => 'Возраст 20-65, стабильный доход'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Sürətli Nağd', 'en' => 'Cash Loans', 'ru' => 'Быстрая наличность']),
                        'code' => 'CL002',
                        'type' => 'cash',
                        'interest_rate' => 18.5,
                        'min_amount' => 500,
                        'max_amount' => 25000,
                        'max_term' => 36,
                        'requirements' => json_encode(['az' => 'Minimum sənədlər', 'en' => 'Minimum documents', 'ru' => 'Минимум документов'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Təcili Kredit', 'en' => 'Express Loan', 'ru' => 'Экспресс кредит']),
                        'code' => 'EL001',
                        'type' => 'express',
                        'interest_rate' => 22,
                        'min_amount' => 100,
                        'max_amount' => 5000,
                        'max_term' => 12,
                        'requirements' => json_encode(['az' => 'Şəxsiyyət vəsiqəsi kifayətdir', 'en' => 'ID card is sufficient', 'ru' => 'Достаточно удостоверения личности'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Biznes Kredit', 'en' => 'Business Loan', 'ru' => 'Бизнес кредит']),
                        'code' => 'BL001',
                        'type' => 'business',
                        'interest_rate' => 16,
                        'min_amount' => 1000,
                        'max_amount' => 50000,
                        'max_term' => 48,
                        'requirements' => json_encode(['az' => 'VÖEN, maliyyə hesabatı', 'en' => 'TIN, financial statements', 'ru' => 'ИНН, финансовая отчетность'])
                    ],
                    [
                        'name' => json_encode(['az' => 'TBC Tələbə Krediti', 'en' => 'TBC Student Loan', 'ru' => 'TBC Студенческий кредит']),
                        'code' => 'SL006',
                        'type' => 'student',
                        'interest_rate' => 11,
                        'min_amount' => 800,
                        'max_amount' => 15000,
                        'max_term' => 84,
                        'requirements' => json_encode(['az' => 'Universitet təsdiq məktubu, valideyn zaminliyi', 'en' => 'University confirmation, parent guarantee', 'ru' => 'Подтверждение из университета, поручительство родителей'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Peşə Təhsili Krediti', 'en' => 'Vocational Education Loan', 'ru' => 'Кредит на профессиональное образование']),
                        'code' => 'SL007',
                        'type' => 'student',
                        'interest_rate' => 12.5,
                        'min_amount' => 200,
                        'max_amount' => 5000,
                        'max_term' => 36,
                        'requirements' => json_encode(['az' => 'Kurs qeydiyyat forması', 'en' => 'Course registration form', 'ru' => 'Форма регистрации на курс'])
                    ],
                ],
            ],
            [
                'name' => 'FinEx',
                'slug' => 'finex',
                'attributes' => [
                    'license_number' => 'BOKT-025',
                    'regulator_number' => 'CBAR/NBCO/2016-025',
                    'phone' => '+994 12 404 44 44',
                    'email' => 'info@finex.az',
                    'website' => 'https://finex.az',
                    'max_loan_amount' => 15000,
                    'min_loan_amount' => 200,
                    'approval_time' => 2,
                    'about' => json_encode([
                        'az' => 'FinEx innovativ maliyyə həlləri təklif edir.',
                        'en' => 'FinEx offers innovative financial solutions.',
                        'ru' => 'FinEx предлагает инновационные финансовые решения.'
                    ]),
                ],
                'loans' => [
                    [
                        'name' => json_encode(['az' => 'Nağd Pul Krediti', 'en' => 'Cash Loans', 'ru' => 'Наличный кредит']),
                        'code' => 'CL003',
                        'type' => 'cash',
                        'interest_rate' => 15.8,
                        'min_amount' => 800,
                        'max_amount' => 30000,
                        'max_term' => 60,
                        'requirements' => json_encode(['az' => 'Əmək haqqı arayışı', 'en' => 'Salary certificate', 'ru' => 'Справка о зарплате'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Premium Nağd', 'en' => 'Cash Loans', 'ru' => 'Премиум наличные']),
                        'code' => 'CL004',
                        'type' => 'cash',
                        'interest_rate' => 14.5,
                        'min_amount' => 2000,
                        'max_amount' => 75000,
                        'max_term' => 84,
                        'requirements' => json_encode(['az' => 'Yüksək gəlir, zamin', 'en' => 'High income, guarantor', 'ru' => 'Высокий доход, поручитель'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Onlayn Kredit', 'en' => 'Online Loan', 'ru' => 'Онлайн кредит']),
                        'code' => 'OL001',
                        'type' => 'online',
                        'interest_rate' => 19,
                        'min_amount' => 200,
                        'max_amount' => 15000,
                        'max_term' => 24,
                        'requirements' => json_encode(['az' => 'Onlayn müraciət, video zəng', 'en' => 'Online application, video call', 'ru' => 'Онлайн заявка, видеозвонок'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Tələbə Krediti', 'en' => 'Student Loan', 'ru' => 'Студенческий кредит']),
                        'code' => 'SL001',
                        'type' => 'student',
                        'interest_rate' => 12,
                        'min_amount' => 500,
                        'max_amount' => 10000,
                        'max_term' => 60,
                        'requirements' => json_encode(['az' => 'Tələbə bileti, zamin', 'en' => 'Student ID, guarantor', 'ru' => 'Студенческий билет, поручитель'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Magistr Krediti', 'en' => 'Master\'s Loan', 'ru' => 'Магистерский кредит']),
                        'code' => 'SL002',
                        'type' => 'student',
                        'interest_rate' => 10.5,
                        'min_amount' => 1000,
                        'max_amount' => 20000,
                        'max_term' => 72,
                        'requirements' => json_encode(['az' => 'Magistr təhsili təsdiqləyən sənəd', 'en' => 'Master\'s enrollment proof', 'ru' => 'Подтверждение обучения в магистратуре'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Doktorantura Krediti', 'en' => 'PhD Loan', 'ru' => 'Кредит для докторантуры']),
                        'code' => 'SL003',
                        'type' => 'student',
                        'interest_rate' => 9,
                        'min_amount' => 2000,
                        'max_amount' => 30000,
                        'max_term' => 96,
                        'requirements' => json_encode(['az' => 'Doktorantura qəbul məktubu', 'en' => 'PhD acceptance letter', 'ru' => 'Письмо о зачислении в докторантуру'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Lombard Krediti', 'en' => 'Pawn Loan', 'ru' => 'Ломбардный кредит']),
                        'code' => 'PL001',
                        'type' => 'pawn',
                        'interest_rate' => 24,
                        'min_amount' => 100,
                        'max_amount' => 5000,
                        'max_term' => 6,
                        'requirements' => json_encode(['az' => 'Qızıl və ya texnika girovu', 'en' => 'Gold or electronics collateral', 'ru' => 'Залог золота или техники'])
                    ],
                ],
            ],
            [
                'name' => 'Express Kredit',
                'slug' => 'express-kredit',
                'attributes' => [
                    'license_number' => 'BOKT-031',
                    'regulator_number' => 'CBAR/NBCO/2018-031',
                    'phone' => '+994 12 505 55 55',
                    'email' => 'info@expresskredit.az',
                    'website' => 'https://expresskredit.az',
                    'max_loan_amount' => 40000,
                    'min_loan_amount' => 300,
                    'approval_time' => 30,
                    'about' => json_encode([
                        'az' => 'Express Kredit 30 dəqiqədə kredit təklif edir.',
                        'en' => 'Express Credit offers loans in 30 minutes.',
                        'ru' => 'Express Кредит предлагает кредиты за 30 минут.'
                    ]),
                ],
                'loans' => [
                    [
                        'name' => json_encode(['az' => 'Ultra Nağd', 'en' => 'Cash Loans', 'ru' => 'Ультра наличные']),
                        'code' => 'CL005',
                        'type' => 'cash',
                        'interest_rate' => 17.2,
                        'min_amount' => 600,
                        'max_amount' => 40000,
                        'max_term' => 72,
                        'requirements' => json_encode(['az' => 'Şəxsiyyət və gəlir', 'en' => 'ID and income proof', 'ru' => 'Удостоверение и справка о доходах'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Mini Ekspress', 'en' => 'Express Loan', 'ru' => 'Мини экспресс']),
                        'code' => 'EL002',
                        'type' => 'express',
                        'interest_rate' => 25,
                        'min_amount' => 100,
                        'max_amount' => 3000,
                        'max_term' => 6,
                        'requirements' => json_encode(['az' => 'Yalnız şəxsiyyət vəsiqəsi', 'en' => 'ID only', 'ru' => 'Только удостоверение личности'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Təhsil Plus', 'en' => 'Education Plus', 'ru' => 'Образование Плюс']),
                        'code' => 'SL004',
                        'type' => 'student',
                        'interest_rate' => 13.5,
                        'min_amount' => 300,
                        'max_amount' => 8000,
                        'max_term' => 48,
                        'requirements' => json_encode(['az' => 'Tələbə bileti, minimum 1 zamin', 'en' => 'Student ID, minimum 1 guarantor', 'ru' => 'Студенческий билет, минимум 1 поручитель'])
                    ],
                    [
                        'name' => json_encode(['az' => 'Xarici Təhsil Krediti', 'en' => 'Study Abroad Loan', 'ru' => 'Кредит на обучение за рубежом']),
                        'code' => 'SL005',
                        'type' => 'student',
                        'interest_rate' => 14,
                        'min_amount' => 5000,
                        'max_amount' => 50000,
                        'max_term' => 120,
                        'requirements' => json_encode(['az' => 'Qəbul məktubu, təhsil haqqı fakturası', 'en' => 'Acceptance letter, tuition invoice', 'ru' => 'Письмо о зачислении, счет за обучение'])
                    ],
                ],
            ],
        ];
        
        foreach ($companies as $companyData) {
            $companyId = $this->createCompany($companyData['name'], $companyData['slug'], $creditOrgTypeId);
            $this->addCompanyAttributes($companyId, $creditOrgTypeId, $companyData['attributes']);
            
            // Add loan products
            if (isset($companyData['loans'])) {
                foreach ($companyData['loans'] as $loan) {
                    $this->addCreditOrgLoan($companyId, $loan);
                }
            }
        }
    }
    
    private function createInvestmentCompanies(): void
    {
        $investmentTypeId = 4; // Investment
        
        $companies = [
            [
                'name' => 'PASHA Capital',
                'slug' => 'pasha-capital',
                'attributes' => [
                    'license_number' => 'SQ-157',
                    'phone' => '+994 12 498 87 87',
                    'email' => 'info@pashacapital.az',
                    'website' => 'https://pashacapital.az',
                    'aum' => 500000000,
                    'min_investment' => 10000,
                    'about' => json_encode([
                        'az' => 'PASHA Capital Azərbaycanın aparıcı investisiya şirkətidir.',
                        'en' => 'PASHA Capital is Azerbaijan\'s leading investment company.',
                        'ru' => 'PASHA Capital - ведущая инвестиционная компания Азербайджана.'
                    ]),
                ],
            ],
        ];
        
        foreach ($companies as $companyData) {
            $companyId = $this->createCompany($companyData['name'], $companyData['slug'], $investmentTypeId);
            $this->addCompanyAttributes($companyId, $investmentTypeId, $companyData['attributes']);
        }
    }
    
    private function createLeasingCompanies(): void
    {
        $leasingTypeId = 5; // Leasing
        
        $companies = [
            [
                'name' => 'MCB Leasing',
                'slug' => 'mcb-leasing',
                'attributes' => [
                    'license_number' => 'LZ-045',
                    'phone' => '+994 12 444 98 98',
                    'email' => 'info@mcbleasing.az',
                    'website' => 'https://mcbleasing.az',
                    'min_down_payment' => 20,
                    'max_term_months' => 60,
                    'vehicle_types' => json_encode(['passenger', 'commercial', 'trucks', 'special']),
                    'about' => json_encode([
                        'az' => 'MCB Leasing avtomobil və avadanlıq lizinqi xidmətləri təklif edir.',
                        'en' => 'MCB Leasing offers vehicle and equipment leasing services.',
                        'ru' => 'MCB Leasing предлагает услуги лизинга автомобилей и оборудования.'
                    ]),
                ],
            ],
        ];
        
        foreach ($companies as $companyData) {
            $companyId = $this->createCompany($companyData['name'], $companyData['slug'], $leasingTypeId);
            $this->addCompanyAttributes($companyId, $leasingTypeId, $companyData['attributes']);
        }
    }
    
    // Helper methods
    private function createCompany(string $name, string $slug, int $typeId): int
    {
        return DB::table('companies')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'company_type_id' => $typeId,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    private function addCompanyAttributes(int $companyId, int $typeId, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $attrDef = DB::table('company_attribute_definitions')
                ->where('company_type_id', $typeId)
                ->where('attribute_key', $key)
                ->first();
            
            if (!$attrDef) {
                continue;
            }
            
            $data = [
                'company_id' => $companyId,
                'attribute_definition_id' => $attrDef->id,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            switch ($attrDef->data_type) {
                case 'string':
                case 'text':
                    $data['value_text'] = $value;
                    break;
                case 'number':
                case 'decimal':
                    $data['value_number'] = $value;
                    break;
                case 'date':
                    $data['value_date'] = $value;
                    break;
                case 'json':
                    $data['value_json'] = is_string($value) ? $value : json_encode($value);
                    break;
                case 'boolean':
                    $data['value_number'] = $value ? 1 : 0;
                    break;
            }
            
            DB::table('company_attribute_values')->insert($data);
        }
    }
    
    private function addBankBranch(int $bankId, array $branch): void
    {
        $branchTypeId = DB::table('company_entity_types')->where('entity_name', 'branch')->value('id');
        
        // Convert entity name to JSON format if not already
        $entityName = is_string($branch['name']) && $this->isJson($branch['name']) ?
            $branch['name'] :
            (is_array($branch['name']) ? 
                json_encode($branch['name']) : 
                json_encode([
                    'az' => $branch['name'],
                    'en' => $branch['name'], 
                    'ru' => $branch['name']
                ]));
        
        $entityId = DB::table('company_entities')->insertGetId([
            'company_id' => $bankId,
            'entity_type_id' => $branchTypeId,
            'entity_name' => $entityName,
            'entity_code' => $branch['code'],
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->addEntityAttribute($entityId, $branchTypeId, 'branch_name', json_encode([
            'az' => $branch['name'],
            'en' => $branch['name'],
            'ru' => $branch['name']
        ]));
        $this->addEntityAttribute($entityId, $branchTypeId, 'branch_code', $branch['code']);
        $this->addEntityAttribute($entityId, $branchTypeId, 'address', json_encode([
            'az' => $branch['address'],
            'en' => $branch['address'],
            'ru' => $branch['address']
        ]));
        $this->addEntityAttribute($entityId, $branchTypeId, 'phone', $branch['phone']);
        $this->addEntityAttribute($entityId, $branchTypeId, 'is_24_7', $branch['is_24_7'] ?? false);
        $this->addEntityAttribute($entityId, $branchTypeId, 'working_hours', json_encode([
            'weekdays' => '09:00-18:00',
            'saturday' => '10:00-15:00',
            'sunday' => 'closed'
        ]));
    }
    
    private function addBankDeposit(int $bankId, array $deposit): void
    {
        $depositTypeId = DB::table('company_entity_types')->where('entity_name', 'deposit')->value('id');
        
        // Convert entity name to JSON format
        $entityName = is_array($deposit['name']) ? 
            json_encode($deposit['name']) : 
            json_encode([
                'az' => $deposit['name'],
                'en' => $deposit['name'], 
                'ru' => $deposit['name']
            ]);
        
        $entityId = DB::table('company_entities')->insertGetId([
            'company_id' => $bankId,
            'entity_type_id' => $depositTypeId,
            'entity_name' => $entityName,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->addEntityAttribute($entityId, $depositTypeId, 'product_name', json_encode([
            'az' => $deposit['name'],
            'en' => str_replace('Əmanət', 'Deposit', $deposit['name']),
            'ru' => str_replace('Əmanət', 'Депозит', $deposit['name'])
        ]));
        $this->addEntityAttribute($entityId, $depositTypeId, 'interest_rate', $deposit['rate']);
        $this->addEntityAttribute($entityId, $depositTypeId, 'min_amount', $deposit['min']);
        $this->addEntityAttribute($entityId, $depositTypeId, 'max_amount', $deposit['max'] ?? null);
        $this->addEntityAttribute($entityId, $depositTypeId, 'term_months', $deposit['term']);
        $this->addEntityAttribute($entityId, $depositTypeId, 'currency', $deposit['currency']);
        $this->addEntityAttribute($entityId, $depositTypeId, 'early_withdrawal', true);
        $this->addEntityAttribute($entityId, $depositTypeId, 'capitalization', true);
    }
    
    private function addBankCreditCard(int $bankId, array $card): void
    {
        $cardTypeId = DB::table('company_entity_types')->where('entity_name', 'credit_card')->value('id');
        
        // Convert entity name to JSON format
        $entityName = is_array($card['name']) ? 
            json_encode($card['name']) : 
            json_encode([
                'az' => $card['name'],
                'en' => $card['name'], 
                'ru' => $card['name']
            ]);
        
        $entityId = DB::table('company_entities')->insertGetId([
            'company_id' => $bankId,
            'entity_type_id' => $cardTypeId,
            'entity_name' => $entityName,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->addEntityAttribute($entityId, $cardTypeId, 'card_name', json_encode([
            'az' => $card['name'],
            'en' => $card['name'],
            'ru' => $card['name']
        ]));
        $this->addEntityAttribute($entityId, $cardTypeId, 'card_network', $card['network']);
        $this->addEntityAttribute($entityId, $cardTypeId, 'card_level', $card['level']);
        $this->addEntityAttribute($entityId, $cardTypeId, 'annual_fee', $card['fee']);
        $this->addEntityAttribute($entityId, $cardTypeId, 'cashback_rate', $card['cashback']);
        $this->addEntityAttribute($entityId, $cardTypeId, 'grace_period', $card['grace']);
        $this->addEntityAttribute($entityId, $cardTypeId, 'benefits', json_encode([
            'lounge_access' => $card['level'] === 'Platinum',
            'travel_insurance' => $card['level'] !== 'Classic',
            'concierge' => $card['level'] === 'Platinum'
        ]));
    }
    
    private function addBankLoan(int $bankId, array $loan): void
    {
        $loanTypeId = DB::table('company_entity_types')->where('entity_name', 'loan')->value('id');
        
        // Convert entity name to JSON format
        $entityName = is_array($loan['name']) ? 
            json_encode($loan['name']) : 
            json_encode([
                'az' => $loan['name'],
                'en' => $loan['name'], 
                'ru' => $loan['name']
            ]);
        
        $entityId = DB::table('company_entities')->insertGetId([
            'company_id' => $bankId,
            'entity_type_id' => $loanTypeId,
            'entity_name' => $entityName,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->addEntityAttribute($entityId, $loanTypeId, 'loan_name', json_encode([
            'az' => $loan['name'],
            'en' => str_replace('Kredit', 'Loan', $loan['name']),
            'ru' => str_replace('Kredit', 'Кредит', $loan['name'])
        ]));
        $this->addEntityAttribute($entityId, $loanTypeId, 'loan_type', $loan['type']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'min_amount', $loan['min']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'max_amount', $loan['max']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'interest_rate_min', $loan['rate_min']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'interest_rate_max', $loan['rate_max'] ?? null);
        $this->addEntityAttribute($entityId, $loanTypeId, 'term_months_min', $loan['term_min']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'term_months_max', $loan['term_max']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'collateral_required', $loan['type'] === 'auto' || $loan['type'] === 'mortgage');
    }
    
    private function addCreditOrgLoan(int $companyId, array $loan): void
    {
        $loanTypeId = DB::table('company_entity_types')->where('entity_name', 'credit_loan')->value('id');
        
        if (!$loanTypeId) {
            // Create the entity type if it doesn't exist
            $creditOrgTypeId = 3; // Credit Organizations
            $loanTypeId = DB::table('company_entity_types')->insertGetId([
                'entity_name' => 'credit_loan',
                'parent_company_type_id' => $creditOrgTypeId,
                'description' => 'Loan products for credit organizations',
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Convert entity name to JSON format if not already (avoid double encoding)
        $entityName = is_string($loan['name']) && $this->isJson($loan['name']) ?
            $loan['name'] :
            (is_array($loan['name']) ? 
                json_encode($loan['name']) : 
                json_encode([
                    'az' => $loan['name'],
                    'en' => $loan['name'], 
                    'ru' => $loan['name']
                ]));
        
        $entityId = DB::table('company_entities')->insertGetId([
            'company_id' => $companyId,
            'entity_type_id' => $loanTypeId,
            'entity_name' => $entityName,
            'entity_code' => $loan['code'] ?? null,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Add loan attributes
        $this->addEntityAttribute($entityId, $loanTypeId, 'loan_name', $loan['name']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'loan_type', $loan['type']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'interest_rate', $loan['interest_rate']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'min_amount', $loan['min_amount']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'max_amount', $loan['max_amount']);
        $this->addEntityAttribute($entityId, $loanTypeId, 'max_term_months', $loan['max_term']);
        if (isset($loan['requirements'])) {
            $this->addEntityAttribute($entityId, $loanTypeId, 'requirements', $loan['requirements']);
        }
    }
    
    private function addInsuranceProduct(int $companyId, array $product): void
    {
        $productTypeId = DB::table('company_entity_types')->where('entity_name', 'insurance_product')->value('id');
        
        // Convert entity name to JSON format
        $entityName = is_array($product['name']) ? 
            json_encode($product['name']) : 
            json_encode([
                'az' => $product['name'],
                'en' => $product['name'], 
                'ru' => $product['name']
            ]);
        
        $entityId = DB::table('company_entities')->insertGetId([
            'company_id' => $companyId,
            'entity_type_id' => $productTypeId,
            'entity_name' => $entityName,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->addEntityAttribute($entityId, $productTypeId, 'product_name', json_encode([
            'az' => $product['name'],
            'en' => str_replace('Sığorta', 'Insurance', $product['name']),
            'ru' => str_replace('Sığorta', 'Страхование', $product['name'])
        ]));
        $this->addEntityAttribute($entityId, $productTypeId, 'product_type', $product['type']);
        $this->addEntityAttribute($entityId, $productTypeId, 'coverage_amount_min', $product['coverage_min']);
        $this->addEntityAttribute($entityId, $productTypeId, 'coverage_amount_max', $product['coverage_max']);
        $this->addEntityAttribute($entityId, $productTypeId, 'premium_monthly', $product['premium']);
    }
    
    private function addEntityAttribute($entityId, $entityTypeId, $key, $value): void
    {
        $attrDef = DB::table('company_attribute_definitions')
            ->where('entity_type_id', $entityTypeId)
            ->where('attribute_key', $key)
            ->first();
        
        if (!$attrDef) {
            return;
        }
        
        $data = [
            'entity_id' => $entityId,
            'attribute_definition_id' => $attrDef->id,
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        switch ($attrDef->data_type) {
            case 'string':
            case 'text':
                $data['value_text'] = $value;
                break;
            case 'number':
            case 'decimal':
                $data['value_number'] = $value;
                break;
            case 'date':
                $data['value_date'] = $value;
                break;
            case 'json':
                $data['value_json'] = is_string($value) ? $value : json_encode($value);
                break;
            case 'boolean':
                $data['value_number'] = $value ? 1 : 0;
                break;
        }
        
        DB::table('company_entity_attribute_values')->insert($data);
    }

    /**
     * Check if a string is valid JSON
     */
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}