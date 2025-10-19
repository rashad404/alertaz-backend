<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop old tables if they exist (with foreign key checks disabled)
        Schema::disableForeignKeyConstraints();
        
        // Drop old company-related tables
        Schema::dropIfExists('company_company_type');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('company_types');
        
        Schema::enableForeignKeyConstraints();
        
        // 1. Create company_types table
        Schema::create('company_types', function (Blueprint $table) {
            $table->id();
            $table->string('type_name', 100)->unique()->comment('Unique type identifier: bank, insurance, retail');
            $table->text('description')->nullable()->comment('Description of the company type');
            $table->timestamps();
            
            $table->comment('Defines different types of companies in the system');
        });
            
            // 2. Create companies table
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255)->comment('Company name');
                $table->string('slug', 255)->unique()->comment('URL-friendly identifier');
                $table->foreignId('company_type_id')->constrained('company_types')->onDelete('restrict');
                $table->boolean('is_active')->default(true);
                $table->integer('display_order')->default(0);
                $table->timestamps();
                
                $table->index('company_type_id');
                $table->index('name');
                $table->index('slug');
                $table->index('is_active');
                
                $table->comment('Main companies table with basic information');
            });
            
            // 3. Create company_entity_types table
            Schema::create('company_entity_types', function (Blueprint $table) {
                $table->id();
                $table->string('entity_name', 100)->comment('Entity type name: branch, deposit, credit_card');
                $table->foreignId('parent_company_type_id')->constrained('company_types')->onDelete('cascade');
                $table->text('description')->nullable();
                $table->integer('display_order')->default(0);
                $table->timestamps();
                
                $table->unique(['entity_name', 'parent_company_type_id'], 'unique_entity_per_company_type');
                $table->index('parent_company_type_id');
                
                $table->comment('Defines entity types available for each company type');
            });
            
            // 4. Create company_attribute_groups table (for UI organization)
            Schema::create('company_attribute_groups', function (Blueprint $table) {
                $table->id();
                $table->string('group_name', 100)->comment('Group name: Contact Info, Financial Details');
                $table->foreignId('company_type_id')->nullable()->constrained('company_types')->onDelete('cascade');
                $table->foreignId('entity_type_id')->nullable()->constrained('company_entity_types')->onDelete('cascade');
                $table->integer('display_order')->default(0);
                $table->timestamps();
                
                $table->index(['company_type_id', 'entity_type_id']);
                
                $table->comment('Groups attributes for better UI organization');
            });
            
            // 5. Create company_attribute_definitions table
            Schema::create('company_attribute_definitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_type_id')->nullable()->constrained('company_entity_types')->onDelete('cascade');
                $table->foreignId('company_type_id')->nullable()->constrained('company_types')->onDelete('cascade');
                $table->foreignId('attribute_group_id')->nullable()->constrained('company_attribute_groups')->onDelete('set null');
                $table->string('attribute_name', 100)->comment('Human-readable name');
                $table->string('attribute_key', 100)->comment('Programmatic key: swift_code, interest_rate');
                $table->enum('data_type', ['string', 'number', 'decimal', 'date', 'boolean', 'json', 'text']);
                $table->boolean('is_required')->default(false);
                $table->boolean('is_translatable')->default(false)->comment('Whether this attribute supports multiple languages');
                $table->json('validation_rules')->nullable()->comment('JSON validation rules');
                $table->integer('display_order')->default(0);
                $table->timestamps();
                
                $table->index('entity_type_id');
                $table->index('company_type_id');
                $table->index('attribute_group_id');
                $table->index('attribute_key');
                
                // Ensure either entity_type_id OR company_type_id is set, not both
                $table->comment('Defines available attributes for companies and entities');
            });
            
            // Add CHECK constraint after table creation
            DB::statement('ALTER TABLE company_attribute_definitions ADD CONSTRAINT check_entity_or_company CHECK ((entity_type_id IS NULL) != (company_type_id IS NULL))');
            
            // 6. Create company_attribute_values table
            Schema::create('company_attribute_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
                $table->foreignId('attribute_definition_id')->constrained('company_attribute_definitions')->onDelete('restrict');
                $table->text('value_text')->nullable();
                $table->decimal('value_number', 20, 6)->nullable();
                $table->date('value_date')->nullable();
                $table->json('value_json')->nullable();
                $table->timestamps();
                
                $table->unique(['company_id', 'attribute_definition_id'], 'unique_company_attribute');
                $table->index('company_id');
                $table->index('attribute_definition_id');
                
                // Add full-text index for searching
                $table->fullText('value_text');
                
                $table->comment('Stores attribute values for companies');
            });
            
            // 7. Create company_entities table
            Schema::create('company_entities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
                $table->foreignId('entity_type_id')->constrained('company_entity_types')->onDelete('restrict');
                $table->string('entity_name', 255)->nullable()->comment('Optional name like Main Branch');
                $table->string('entity_code', 100)->nullable()->comment('Optional unique identifier');
                $table->boolean('is_active')->default(true);
                $table->integer('display_order')->default(0);
                $table->timestamps();
                
                $table->index(['company_id', 'entity_type_id'], 'idx_company_entity');
                $table->index('is_active');
                
                $table->comment('Stores entities belonging to companies (branches, products, etc)');
            });
            
            // 8. Create company_entity_attribute_values table
            Schema::create('company_entity_attribute_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained('company_entities')->onDelete('cascade');
                $table->foreignId('attribute_definition_id')->constrained('company_attribute_definitions')->onDelete('restrict');
                $table->text('value_text')->nullable();
                $table->decimal('value_number', 20, 6)->nullable();
                $table->date('value_date')->nullable();
                $table->json('value_json')->nullable();
                $table->timestamps();
                
                $table->unique(['entity_id', 'attribute_definition_id'], 'unique_entity_attribute');
                $table->index('entity_id');
                
                $table->comment('Stores attribute values for company entities');
            });
            
            // 9. Create company_attribute_options table (for dropdown fields)
            Schema::create('company_attribute_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attribute_definition_id')->constrained('company_attribute_definitions')->onDelete('cascade');
                $table->string('option_value', 255);
                $table->string('option_label', 255);
                $table->integer('display_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index('attribute_definition_id');
                $table->index('is_active');
                
                $table->comment('Stores predefined options for dropdown/select attributes');
            });
            
            // Create views for easier querying
            $this->createViews();
            
            // Insert sample data
            $this->insertSampleData();
    }

    /**
     * Create database views for easier querying
     */
    private function createViews(): void
    {
        // View: v_company_attributes - Flattened view of all company attributes with values
        DB::statement("
            CREATE OR REPLACE VIEW v_company_attributes AS
            SELECT 
                c.id AS company_id,
                c.name AS company_name,
                c.slug AS company_slug,
                ct.type_name AS company_type,
                cad.attribute_key,
                cad.attribute_name,
                cad.data_type,
                cad.is_required,
                CASE 
                    WHEN cad.data_type = 'string' OR cad.data_type = 'text' THEN cav.value_text
                    WHEN cad.data_type = 'number' OR cad.data_type = 'decimal' THEN CAST(cav.value_number AS CHAR)
                    WHEN cad.data_type = 'date' THEN CAST(cav.value_date AS CHAR)
                    WHEN cad.data_type = 'boolean' THEN IF(cav.value_number = 1, 'true', 'false')
                    WHEN cad.data_type = 'json' THEN CAST(cav.value_json AS CHAR)
                END AS attribute_value,
                cag.group_name AS attribute_group,
                cad.display_order
            FROM companies c
            INNER JOIN company_types ct ON c.company_type_id = ct.id
            LEFT JOIN company_attribute_definitions cad ON cad.company_type_id = ct.id
            LEFT JOIN company_attribute_values cav ON cav.company_id = c.id AND cav.attribute_definition_id = cad.id
            LEFT JOIN company_attribute_groups cag ON cad.attribute_group_id = cag.id
            WHERE c.is_active = 1
            ORDER BY c.id, cad.display_order
        ");
        
        // View: v_company_entities - List of all entities with their parent company info
        DB::statement("
            CREATE OR REPLACE VIEW v_company_entities AS
            SELECT 
                ce.id AS entity_id,
                ce.entity_name,
                ce.entity_code,
                cet.entity_name AS entity_type,
                c.id AS company_id,
                c.name AS company_name,
                c.slug AS company_slug,
                ct.type_name AS company_type,
                ce.is_active,
                ce.display_order
            FROM company_entities ce
            INNER JOIN companies c ON ce.company_id = c.id
            INNER JOIN company_types ct ON c.company_type_id = ct.id
            INNER JOIN company_entity_types cet ON ce.entity_type_id = cet.id
            ORDER BY c.id, ce.display_order, ce.id
        ");
        
        // View: v_entity_attributes - Flattened view of all entity attributes with values
        DB::statement("
            CREATE OR REPLACE VIEW v_entity_attributes AS
            SELECT 
                ce.id AS entity_id,
                ce.entity_name,
                ce.entity_code,
                cet.entity_name AS entity_type,
                c.id AS company_id,
                c.name AS company_name,
                cad.attribute_key,
                cad.attribute_name,
                cad.data_type,
                CASE 
                    WHEN cad.data_type = 'string' OR cad.data_type = 'text' THEN ceav.value_text
                    WHEN cad.data_type = 'number' OR cad.data_type = 'decimal' THEN CAST(ceav.value_number AS CHAR)
                    WHEN cad.data_type = 'date' THEN CAST(ceav.value_date AS CHAR)
                    WHEN cad.data_type = 'boolean' THEN IF(ceav.value_number = 1, 'true', 'false')
                    WHEN cad.data_type = 'json' THEN CAST(ceav.value_json AS CHAR)
                END AS attribute_value,
                cad.display_order
            FROM company_entities ce
            INNER JOIN companies c ON ce.company_id = c.id
            INNER JOIN company_entity_types cet ON ce.entity_type_id = cet.id
            LEFT JOIN company_attribute_definitions cad ON cad.entity_type_id = cet.id
            LEFT JOIN company_entity_attribute_values ceav ON ceav.entity_id = ce.id AND ceav.attribute_definition_id = cad.id
            WHERE ce.is_active = 1
            ORDER BY ce.id, cad.display_order
        ");
    }
    
    /**
     * Create stored procedures for common operations
     * Note: Stored procedures removed due to MySQL compatibility issues
     * These can be implemented as Laravel service methods instead
     */
    private function createStoredProcedures(): void
    {
        // Stored procedures will be implemented as Laravel service methods
        return;
        // Procedure: sp_add_company_with_attributes
        DB::unprepared("
            DROP PROCEDURE IF EXISTS sp_add_company_with_attributes;
            CREATE PROCEDURE sp_add_company_with_attributes(
                IN p_name VARCHAR(255),
                IN p_slug VARCHAR(255),
                IN p_company_type_id INT,
                IN p_attributes JSON
            )
            BEGIN
                DECLARE v_company_id INT;
                DECLARE v_attr_key VARCHAR(100);
                DECLARE v_attr_value TEXT;
                DECLARE v_attr_def_id INT;
                DECLARE v_data_type VARCHAR(20);
                DECLARE done INT DEFAULT FALSE;
                DECLARE attr_cursor CURSOR FOR 
                    SELECT JSON_UNQUOTE(JSON_EXTRACT(p_attributes, CONCAT('$.', attr_key))) as attr_value,
                           attr_key
                    FROM (
                        SELECT JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(p_attributes), CONCAT('$[', idx, ']'))) as attr_key
                        FROM (
                            SELECT 0 as idx UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
                            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
                        ) as numbers
                        WHERE idx < JSON_LENGTH(JSON_KEYS(p_attributes))
                    ) as keys;
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
                
                START TRANSACTION;
                
                -- Insert company
                INSERT INTO companies (name, slug, company_type_id, created_at, updated_at)
                VALUES (p_name, p_slug, p_company_type_id, NOW(), NOW());
                
                SET v_company_id = LAST_INSERT_ID();
                
                -- Insert attributes
                OPEN attr_cursor;
                read_loop: LOOP
                    FETCH attr_cursor INTO v_attr_value, v_attr_key;
                    IF done THEN
                        LEAVE read_loop;
                    END IF;
                    
                    -- Get attribute definition
                    SELECT id, data_type INTO v_attr_def_id, v_data_type
                    FROM company_attribute_definitions
                    WHERE attribute_key = v_attr_key AND company_type_id = p_company_type_id
                    LIMIT 1;
                    
                    IF v_attr_def_id IS NOT NULL THEN
                        -- Insert attribute value based on data type
                        IF v_data_type IN ('string', 'text') THEN
                            INSERT INTO company_attribute_values 
                            (company_id, attribute_definition_id, value_text, created_at, updated_at)
                            VALUES (v_company_id, v_attr_def_id, v_attr_value, NOW(), NOW());
                        ELSEIF v_data_type IN ('number', 'decimal') THEN
                            INSERT INTO company_attribute_values 
                            (company_id, attribute_definition_id, value_number, created_at, updated_at)
                            VALUES (v_company_id, v_attr_def_id, CAST(v_attr_value AS DECIMAL(20,6)), NOW(), NOW());
                        ELSEIF v_data_type = 'date' THEN
                            INSERT INTO company_attribute_values 
                            (company_id, attribute_definition_id, value_date, created_at, updated_at)
                            VALUES (v_company_id, v_attr_def_id, CAST(v_attr_value AS DATE), NOW(), NOW());
                        ELSEIF v_data_type = 'boolean' THEN
                            INSERT INTO company_attribute_values 
                            (company_id, attribute_definition_id, value_number, created_at, updated_at)
                            VALUES (v_company_id, v_attr_def_id, IF(v_attr_value = 'true', 1, 0), NOW(), NOW());
                        ELSEIF v_data_type = 'json' THEN
                            INSERT INTO company_attribute_values 
                            (company_id, attribute_definition_id, value_json, created_at, updated_at)
                            VALUES (v_company_id, v_attr_def_id, v_attr_value, NOW(), NOW());
                        END IF;
                    END IF;
                END LOOP;
                CLOSE attr_cursor;
                
                COMMIT;
                
                SELECT v_company_id AS company_id;
            END
        ");
        
        // Procedure: sp_get_company_full_data
        DB::unprepared("
            DROP PROCEDURE IF EXISTS sp_get_company_full_data;
            CREATE PROCEDURE sp_get_company_full_data(
                IN p_company_id INT
            )
            BEGIN
                -- Get company basic info
                SELECT 
                    c.*,
                    ct.type_name AS company_type
                FROM companies c
                INNER JOIN company_types ct ON c.company_type_id = ct.id
                WHERE c.id = p_company_id;
                
                -- Get company attributes
                SELECT * FROM v_company_attributes
                WHERE company_id = p_company_id;
                
                -- Get company entities
                SELECT * FROM v_company_entities
                WHERE company_id = p_company_id;
                
                -- Get entity attributes
                SELECT * FROM v_entity_attributes
                WHERE company_id = p_company_id;
            END
        ");
    }
    
    /**
     * Insert sample data
     */
    private function insertSampleData(): void
    {
        // Insert company types
        $bankTypeId = DB::table('company_types')->insertGetId([
            'type_name' => 'bank',
            'description' => 'Banking institutions',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $insuranceTypeId = DB::table('company_types')->insertGetId([
            'type_name' => 'insurance',
            'description' => 'Insurance companies',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $creditOrgTypeId = DB::table('company_types')->insertGetId([
            'type_name' => 'credit_organization',
            'description' => 'Non-bank credit organizations',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Insert entity types for banks
        $branchEntityId = DB::table('company_entity_types')->insertGetId([
            'entity_name' => 'branch',
            'parent_company_type_id' => $bankTypeId,
            'description' => 'Bank branches',
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $depositEntityId = DB::table('company_entity_types')->insertGetId([
            'entity_name' => 'deposit',
            'parent_company_type_id' => $bankTypeId,
            'description' => 'Deposit products',
            'display_order' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $creditCardEntityId = DB::table('company_entity_types')->insertGetId([
            'entity_name' => 'credit_card',
            'parent_company_type_id' => $bankTypeId,
            'description' => 'Credit card products',
            'display_order' => 3,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Insert entity types for insurance
        $insuranceProductEntityId = DB::table('company_entity_types')->insertGetId([
            'entity_name' => 'insurance_product',
            'parent_company_type_id' => $insuranceTypeId,
            'description' => 'Insurance products',
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Create attribute groups
        $contactGroupId = DB::table('company_attribute_groups')->insertGetId([
            'group_name' => 'Contact Information',
            'company_type_id' => $bankTypeId,
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $financialGroupId = DB::table('company_attribute_groups')->insertGetId([
            'group_name' => 'Financial Details',
            'company_type_id' => $bankTypeId,
            'display_order' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Insert attribute definitions for banks (company-level)
        DB::table('company_attribute_definitions')->insert([
            [
                'company_type_id' => $bankTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => $financialGroupId,
                'attribute_name' => 'SWIFT Code',
                'attribute_key' => 'swift_code',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => json_encode(['pattern' => '^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$']),
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $bankTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => $financialGroupId,
                'attribute_name' => 'Bank Code',
                'attribute_key' => 'bank_code',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $bankTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => $financialGroupId,
                'attribute_name' => 'VOEN',
                'attribute_key' => 'voen',
                'data_type' => 'string',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $bankTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => $contactGroupId,
                'attribute_name' => 'Phone',
                'attribute_key' => 'phone',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $bankTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => $contactGroupId,
                'attribute_name' => 'Email',
                'attribute_key' => 'email',
                'data_type' => 'string',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 5,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $bankTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => $contactGroupId,
                'attribute_name' => 'Website',
                'attribute_key' => 'website',
                'data_type' => 'string',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 6,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $bankTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'About',
                'attribute_key' => 'about',
                'data_type' => 'text',
                'is_required' => false,
                'is_translatable' => true,
                'validation_rules' => null,
                'display_order' => 7,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $bankTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Logo',
                'attribute_key' => 'logo',
                'data_type' => 'string',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 8,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
        
        // Insert attribute definitions for insurance companies
        DB::table('company_attribute_definitions')->insert([
            [
                'company_type_id' => $insuranceTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'License Number',
                'attribute_key' => 'license_number',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $insuranceTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Phone',
                'attribute_key' => 'phone',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $insuranceTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Email',
                'attribute_key' => 'email',
                'data_type' => 'string',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'company_type_id' => $insuranceTypeId,
                'entity_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Website',
                'attribute_key' => 'website',
                'data_type' => 'string',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
        
        // Insert attribute definitions for branches (entity-level)
        DB::table('company_attribute_definitions')->insert([
            [
                'entity_type_id' => $branchEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Branch Address',
                'attribute_key' => 'address',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => true,
                'validation_rules' => null,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'entity_type_id' => $branchEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Branch Phone',
                'attribute_key' => 'phone',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'entity_type_id' => $branchEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Branch Code',
                'attribute_key' => 'branch_code',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'entity_type_id' => $branchEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Working Hours',
                'attribute_key' => 'working_hours',
                'data_type' => 'json',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
        
        // Insert attribute definitions for deposits (entity-level)
        DB::table('company_attribute_definitions')->insert([
            [
                'entity_type_id' => $depositEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Deposit Name',
                'attribute_key' => 'deposit_name',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => true,
                'validation_rules' => null,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'entity_type_id' => $depositEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Interest Rate',
                'attribute_key' => 'interest_rate',
                'data_type' => 'decimal',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => json_encode(['min' => 0, 'max' => 100]),
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'entity_type_id' => $depositEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Minimum Amount',
                'attribute_key' => 'minimum_amount',
                'data_type' => 'decimal',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => json_encode(['min' => 0]),
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'entity_type_id' => $depositEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Term (months)',
                'attribute_key' => 'term_months',
                'data_type' => 'number',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
        
        // Insert attribute definitions for credit cards (entity-level)
        DB::table('company_attribute_definitions')->insert([
            [
                'entity_type_id' => $creditCardEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Card Name',
                'attribute_key' => 'card_name',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => true,
                'validation_rules' => null,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'entity_type_id' => $creditCardEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Card Type',
                'attribute_key' => 'card_type',
                'data_type' => 'string',
                'is_required' => true,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'entity_type_id' => $creditCardEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Annual Fee',
                'attribute_key' => 'annual_fee',
                'data_type' => 'decimal',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => null,
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'entity_type_id' => $creditCardEntityId,
                'company_type_id' => null,
                'attribute_group_id' => null,
                'attribute_name' => 'Cashback Rate',
                'attribute_key' => 'cashback_rate',
                'data_type' => 'decimal',
                'is_required' => false,
                'is_translatable' => false,
                'validation_rules' => json_encode(['min' => 0, 'max' => 100]),
                'display_order' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop views
            DB::statement('DROP VIEW IF EXISTS v_entity_attributes');
            DB::statement('DROP VIEW IF EXISTS v_company_entities');
            DB::statement('DROP VIEW IF EXISTS v_company_attributes');
            
            // Drop tables in reverse order
            Schema::dropIfExists('company_attribute_options');
            Schema::dropIfExists('company_entity_attribute_values');
            Schema::dropIfExists('company_entities');
            Schema::dropIfExists('company_attribute_values');
            Schema::dropIfExists('company_attribute_definitions');
            Schema::dropIfExists('company_attribute_groups');
            Schema::dropIfExists('company_entity_types');
            Schema::dropIfExists('companies');
            Schema::dropIfExists('company_types');
    }
};