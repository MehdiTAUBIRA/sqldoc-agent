<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\DbDescription;
use App\Models\TableDescription;
use App\Models\TableStructure;
use App\Models\TableIndex;
use App\Models\TableRelation;
use App\Models\ViewDescription;
use App\Models\ViewInformation;
use App\Models\ViewColumn;
use App\Models\PsDescription;
use App\Models\PsInformation;
use App\Models\PsParameter;
use App\Models\FunctionDescription;
use App\Models\FuncInformation;
use App\Models\FuncParameter;
use App\Models\TriggerDescription;
use App\Models\TriggerInformation;

class DatabaseStructureService
{
    /**
     * Extrait et sauvegarde toute la structure de la base de données
     *
     * @param string $connectionName Nom de la connexion à utiliser
     * @param int $dbId ID de l'entrée dans db_description
     * @return bool
     */

    private function formatDataType($column)
    {
        if (!isset($column->data_type)) {
            return 'unknown';
        }

        $type = $column->data_type;

        if (in_array(strtolower($type), ['varchar', 'nvarchar', 'char', 'nchar', 'binary', 'varbinary'])) {
            if (isset($column->max_length)) {
                // Pour nvarchar/nchar, la longueur est en caractères, pas en octets
                $maxLength = in_array(strtolower($type), ['nvarchar', 'nchar'])
                    ? $column->max_length / 2
                    : $column->max_length;

                $type .= "(" . ($maxLength == -1 ? 'MAX' : $maxLength) . ")";
            }
        } else if (in_array(strtolower($type), ['decimal', 'numeric'])) {
            if (isset($column->precision) && isset($column->scale)) {
                $type .= "({$column->precision},{$column->scale})";
            }
        }

        return $type;
    }


    private function formatMySqlDataType($column)
    {
 
        return $this->formatDataType($column);
    }

    public function extractAndSaveAllStructures($connectionName, $dbId)
    {
        try {
            // Déterminer le type de base de données pour adapter les requêtes
            $databaseType = DB::connection($connectionName)->getDriverName();
            Log::info("Extraction de la structure pour: {$databaseType}");

            // 1. Extraire et sauvegarder les tables
            $this->extractAndSaveTables($connectionName, $dbId, $databaseType);

            // 2. Extraire et sauvegarder les vues
            $this->extractAndSaveViews($connectionName, $dbId, $databaseType);

            // 3. Extraire et sauvegarder les fonctions
            $this->extractAndSaveFunctions($connectionName, $dbId, $databaseType);

            // 4. Extraire et sauvegarder les procédures stockées
            $this->extractAndSaveProcedures($connectionName, $dbId, $databaseType);

            // 5. Extraire et sauvegarder les triggers
            $this->extractAndSaveTriggers($connectionName, $dbId, $databaseType);

            Log::info("Extraction de la structure terminée avec succès pour la base de données ID: {$dbId}");

            return true;
        } catch (\Exception $e) {
            Log::error('Erreur dans extractAndSaveAllStructures', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Extrait et sauvegarde les tables de la base de données
     */
    private function extractAndSaveTables($connectionName, $dbId, $databaseType)
    {
        try {
            Log::info('Début extraction des tables...');

            if ($databaseType === 'sqlsrv') {
                $this->extractAndSaveSqlServerTables($connectionName, $dbId);
            } elseif ($databaseType === 'mysql') {
                $this->extractAndSaveMySqlTables($connectionName, $dbId);
            } elseif ($databaseType === 'pgsql') {
                $this->extractAndSavePostgreSqlTables($connectionName, $dbId);
            } else {
                Log::warning('Type de base de données non supporté pour l\'extraction des tables', ['type' => $databaseType]);
            }

            Log::info('Fin extraction des tables');
        } catch (\Exception $e) {
            Log::error('Erreur dans extractAndSaveTables', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Extrait et sauvegarde les tables de SQL Server
     */
    private function extractAndSaveSqlServerTables($connectionName, $dbId)
{
    try {
        // ✅ UNE SEULE REQUÊTE pour tout récupérer
        $allData = DB::connection($connectionName)->select("
            WITH TableInfo AS (
                SELECT
                    t.name AS table_name,
                    t.object_id AS table_id,
                    s.name AS schema_name,
                    ISNULL(CONVERT(VARCHAR(8000), ep.value), '') AS table_description
                FROM sys.tables t
                INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
                LEFT JOIN sys.extended_properties ep ON ep.major_id = t.object_id
                    AND ep.minor_id = 0
                    AND ep.name = 'MS_Description'
                WHERE t.is_ms_shipped = 0
            ),
            ColumnInfo AS (
                SELECT
                    c.object_id AS table_id,
                    c.name AS column_name,
                    t.name AS data_type,
                    c.max_length,
                    c.precision,
                    c.scale,
                    c.is_nullable,
                    CASE WHEN pk.column_id IS NOT NULL THEN 'PK' ELSE '' END AS key_type,
                    ISNULL(CONVERT(VARCHAR(8000), ep.value), '') AS description,
                    c.column_id
                FROM sys.columns c
                INNER JOIN sys.types t ON c.user_type_id = t.user_type_id
                LEFT JOIN (
                    SELECT ic.column_id, ic.object_id
                    FROM sys.indexes i
                    INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                    WHERE i.is_primary_key = 1
                ) pk ON pk.column_id = c.column_id AND pk.object_id = c.object_id
                LEFT JOIN sys.extended_properties ep ON ep.major_id = c.object_id
                    AND ep.minor_id = c.column_id
                    AND ep.name = 'MS_Description'
            ),
            IndexInfo AS (
                SELECT
                    i.object_id AS table_id,
                    i.name AS index_name,
                    i.type_desc AS index_type,
                    STRING_AGG(c.name, ', ') WITHIN GROUP (ORDER BY ic.key_ordinal) AS column_names,
                    i.is_unique,
                    i.is_primary_key
                FROM sys.indexes i
                INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
                WHERE i.name IS NOT NULL
                GROUP BY i.object_id, i.name, i.type_desc, i.is_unique, i.is_primary_key
            ),
            ForeignKeyInfo AS (
                SELECT
                    fk.parent_object_id AS table_id,
                    fk.name AS constraint_name,
                    COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS column_name,
                    OBJECT_NAME(fkc.referenced_object_id) AS referenced_table,
                    COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS referenced_column,
                    CASE
                        WHEN fk.delete_referential_action = 1 THEN 'CASCADE'
                        WHEN fk.delete_referential_action = 2 THEN 'SET NULL'
                        WHEN fk.delete_referential_action = 3 THEN 'SET DEFAULT'
                        ELSE 'NO ACTION'
                    END AS delete_action
                FROM sys.foreign_keys fk
                INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
            )
            SELECT
                ti.table_name,
                ti.table_description,
                -- Colonnes en JSON
                (
                    SELECT
                        ci.column_name,
                        ci.data_type,
                        ci.max_length,
                        ci.precision,
                        ci.scale,
                        ci.is_nullable,
                        ci.key_type,
                        ci.description
                    FROM ColumnInfo ci
                    WHERE ci.table_id = ti.table_id
                    ORDER BY ci.column_id
                    FOR JSON PATH
                ) AS columns_json,
                -- Index en JSON
                (
                    SELECT
                        ii.index_name,
                        ii.index_type,
                        ii.column_names,
                        ii.is_unique,
                        ii.is_primary_key
                    FROM IndexInfo ii
                    WHERE ii.table_id = ti.table_id
                    FOR JSON PATH
                ) AS indexes_json,
                -- Foreign Keys en JSON
                (
                    SELECT
                        fki.constraint_name,
                        fki.column_name,
                        fki.referenced_table,
                        fki.referenced_column,
                        fki.delete_action
                    FROM ForeignKeyInfo fki
                    WHERE fki.table_id = ti.table_id
                    FOR JSON PATH
                ) AS foreign_keys_json
            FROM TableInfo ti
            ORDER BY ti.schema_name, ti.table_name
        ");

        Log::info('Tables SQL Server trouvées: ' . count($allData));

        // ✅ Traitement en mémoire avec les données déjà chargées
        foreach ($allData as $tableData) {
            DB::transaction(function () use ($tableData, $dbId) {
                try {
                    // Créer/mettre à jour la table
                    $tableDescription = TableDescription::updateOrCreate(
                        [
                            'dbid' => $dbId,
                            'tablename' => $tableData->table_name
                        ],
                        [
                            'language' => 'en',
                            'description' => $tableData->table_description ?? null,
                            'updated_at' => now()
                        ]
                    );

                    // --- Colonnes ---
                    TableStructure::where('id_table', $tableDescription->id)->delete();
                    
                    $columns = json_decode($tableData->columns_json ?? '[]', true);
                    $columnsToInsert = [];
                    
                    foreach ($columns as $column) {
                        $dataType = $this->formatDataType((object)$column);
                        $columnsToInsert[] = [
                            'id_table' => $tableDescription->id,
                            'column' => $column['column_name'],
                            'type' => $dataType,
                            'nullable' => $column['is_nullable'] ? 1 : 0,
                            'key' => $column['key_type'] ?? '',
                            'description' => $column['description'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    
                    if (!empty($columnsToInsert)) {
                        TableStructure::insert($columnsToInsert);
                    }

                    // --- Index ---
                    TableIndex::where('id_table', $tableDescription->id)->delete();
                    
                    $indexes = json_decode($tableData->indexes_json ?? '[]', true);
                    $indexesToInsert = [];
                    
                    foreach ($indexes as $index) {
                        $properties = [];
                        if ($index['is_primary_key']) $properties[] = 'PRIMARY KEY';
                        if ($index['is_unique']) $properties[] = 'UNIQUE';

                        $indexesToInsert[] = [
                            'id_table' => $tableDescription->id,
                            'name' => $index['index_name'],
                            'type' => $index['index_type'],
                            'column' => $index['column_names'],
                            'properties' => implode(', ', $properties),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    
                    if (!empty($indexesToInsert)) {
                        TableIndex::insert($indexesToInsert);
                    }

                    // --- Foreign Keys ---
                    TableRelation::where('id_table', $tableDescription->id)->delete();
                    
                    $foreignKeys = json_decode($tableData->foreign_keys_json ?? '[]', true);
                    $foreignKeysToInsert = [];
                    
                    foreach ($foreignKeys as $fk) {
                        $foreignKeysToInsert[] = [
                            'id_table' => $tableDescription->id,
                            'constraints' => $fk['constraint_name'],
                            'column' => $fk['column_name'],
                            'referenced_table' => $fk['referenced_table'],
                            'referenced_column' => $fk['referenced_column'],
                            'action' => $fk['delete_action'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    
                    if (!empty($foreignKeysToInsert)) {
                        TableRelation::insert($foreignKeysToInsert);
                    }

                } catch (\Exception $e) {
                    Log::error('Erreur lors du traitement de la table', [
                        'table' => $tableData->table_name,
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            });
        }

        Log::info('Toutes les tables SQL Server ont été traitées.');
        
    } catch (\Exception $e) {
        Log::error('Erreur globale dans extractAndSaveSqlServerTables', [
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

    /**
     * Extrait et sauvegarde les tables de Mysql
     */

    private function extractAndSaveMySqlTables($connectionName, $dbId)
{
    try {
        $connection = DB::connection($connectionName);
        $database = $connection->getDatabaseName();

        // ⚠️ Important : éviter la troncature des JSON
        $connection->statement("SET SESSION group_concat_max_len = 1000000");

        // ✅ Requête compatible MySQL
        $allTablesData = $connection->select("
            SELECT
                t.TABLE_NAME AS table_name,
                t.TABLE_SCHEMA AS schema_name,
                t.TABLE_COMMENT AS description,
                t.CREATE_TIME AS create_date,
                t.UPDATE_TIME AS modify_date,

                -- Colonnes en JSON (ordre garanti)
                (
                    SELECT CONCAT(
                        '[',
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'column_name', c.COLUMN_NAME,
                                'data_type', c.DATA_TYPE,
                                'max_length', c.CHARACTER_MAXIMUM_LENGTH,
                                'precision', c.NUMERIC_PRECISION,
                                'scale', c.NUMERIC_SCALE,
                                'is_nullable', IF(c.IS_NULLABLE = 'YES', 1, 0),
                                'key_type', c.COLUMN_KEY,
                                'description', c.COLUMN_COMMENT
                            )
                            ORDER BY c.ORDINAL_POSITION
                            SEPARATOR ','
                        ),
                        ']'
                    )
                    FROM INFORMATION_SCHEMA.COLUMNS c
                    WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA
                      AND c.TABLE_NAME = t.TABLE_NAME
                ) AS columns_json,

                -- Index en JSON
                (
                    SELECT CONCAT(
                        '[',
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'index_name', s.INDEX_NAME,
                                'index_type', CASE
                                    WHEN s.INDEX_TYPE = 'FULLTEXT' THEN 'FULLTEXT'
                                    WHEN s.NON_UNIQUE = 0 AND s.INDEX_NAME = 'PRIMARY' THEN 'PRIMARY KEY'
                                    WHEN s.NON_UNIQUE = 0 THEN 'UNIQUE'
                                    ELSE 'INDEX'
                                END,
                                'column_names', s.COLUMN_NAMES,
                                'is_primary_key', IF(s.INDEX_NAME = 'PRIMARY', 1, 0),
                                'is_unique', IF(s.NON_UNIQUE = 0, 1, 0)
                            )
                            SEPARATOR ','
                        ),
                        ']'
                    )
                    FROM (
                        SELECT 
                            INDEX_NAME,
                            INDEX_TYPE,
                            NON_UNIQUE,
                            GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS COLUMN_NAMES
                        FROM INFORMATION_SCHEMA.STATISTICS
                        WHERE TABLE_SCHEMA = t.TABLE_SCHEMA
                          AND TABLE_NAME = t.TABLE_NAME
                        GROUP BY INDEX_NAME, INDEX_TYPE, NON_UNIQUE
                    ) s
                ) AS indexes_json,

                -- Foreign Keys en JSON
                (
                    SELECT CONCAT(
                        '[',
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'constraint_name', kcu.CONSTRAINT_NAME,
                                'column_name', kcu.COLUMN_NAME,
                                'referenced_table', kcu.REFERENCED_TABLE_NAME,
                                'referenced_column', kcu.REFERENCED_COLUMN_NAME,
                                'delete_action', COALESCE(rc.DELETE_RULE, 'NO ACTION')
                            )
                            SEPARATOR ','
                        ),
                        ']'
                    )
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                    LEFT JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                        ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                       AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
                    WHERE kcu.TABLE_SCHEMA = t.TABLE_SCHEMA
                      AND kcu.TABLE_NAME = t.TABLE_NAME
                      AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                ) AS foreign_keys_json

            FROM INFORMATION_SCHEMA.TABLES t
            WHERE t.TABLE_SCHEMA = ?
              AND t.TABLE_TYPE = 'BASE TABLE'
            ORDER BY t.TABLE_NAME
        ", [$database]);

        Log::info('Tables MySQL trouvées: ' . count($allTablesData));

        foreach ($allTablesData as $tableData) {
            DB::transaction(function () use ($tableData, $dbId) {

                // --- TABLE ---
                $tableDescription = TableDescription::updateOrCreate(
                    [
                        'dbid' => $dbId,
                        'tablename' => $tableData->table_name
                    ],
                    [
                        'language' => 'en',
                        'description' => $tableData->description ?? null,
                        'updated_at' => now()
                    ]
                );

                // --- COLONNES ---
                TableStructure::where('id_table', $tableDescription->id)->delete();

                $columns = json_decode($tableData->columns_json ?? '[]', true) ?: [];
                $columnsToInsert = [];

                foreach ($columns as $column) {
                    $keyType = '';
                    if ($column['key_type'] === 'PRI') $keyType = 'PK';
                    elseif ($column['key_type'] === 'MUL') $keyType = 'FK';
                    elseif ($column['key_type'] === 'UNI') $keyType = 'UK';

                    $columnsToInsert[] = [
                        'id_table'   => $tableDescription->id,
                        'column'     => $column['column_name'],
                        'type'       => $this->formatMySqlDataType((object)$column),
                        'nullable'   => $column['is_nullable'] ? 1 : 0,
                        'key'        => $keyType,
                        'description'=> $column['description'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($columnsToInsert) {
                    TableStructure::insert($columnsToInsert);
                }

                // --- INDEX ---
                TableIndex::where('id_table', $tableDescription->id)->delete();

                $indexes = json_decode($tableData->indexes_json ?? '[]', true) ?: [];
                $indexesToInsert = [];

                foreach ($indexes as $index) {
                    $properties = [];
                    if ($index['is_primary_key']) $properties[] = 'PRIMARY KEY';
                    if ($index['is_unique']) $properties[] = 'UNIQUE';

                    $indexesToInsert[] = [
                        'id_table'   => $tableDescription->id,
                        'name'       => $index['index_name'],
                        'type'       => $index['index_type'],
                        'column'     => $index['column_names'],
                        'properties' => implode(', ', $properties),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($indexesToInsert) {
                    TableIndex::insert($indexesToInsert);
                }

                // --- FOREIGN KEYS ---
                TableRelation::where('id_table', $tableDescription->id)->delete();

                $foreignKeys = json_decode($tableData->foreign_keys_json ?? '[]', true) ?: [];
                $foreignKeysToInsert = [];

                foreach ($foreignKeys as $fk) {
                    $foreignKeysToInsert[] = [
                        'id_table'          => $tableDescription->id,
                        'constraints'       => $fk['constraint_name'],
                        'column'            => $fk['column_name'],
                        'referenced_table'  => $fk['referenced_table'],
                        'referenced_column' => $fk['referenced_column'],
                        'action'            => $fk['delete_action'],
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }

                if ($foreignKeysToInsert) {
                    TableRelation::insert($foreignKeysToInsert);
                }
            });
        }

        Log::info('Toutes les tables MySQL ont été traitées.');

    } catch (\Exception $e) {
        Log::error('Erreur globale dans extractAndSaveMySqlTables', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}


    

    /**
     * Extrait et sauvegarde les tables de PostgreSQL
     */
    private function extractAndSavePostgreSqlTables($connectionName, $dbId)
{
    try {
        // ✅ UNE SEULE REQUÊTE pour tout récupérer
        $allTablesData = DB::connection($connectionName)->select("
            WITH TableInfo AS (
                SELECT
                    c.oid AS table_oid,
                    c.relname AS table_name,
                    n.nspname AS schema_name,
                    COALESCE(d.description, '') AS description
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                LEFT JOIN pg_description d ON d.objoid = c.oid AND d.objsubid = 0
                WHERE c.relkind = 'r'
                AND n.nspname NOT IN ('pg_catalog', 'information_schema')
            ),
            ColumnInfo AS (
                SELECT
                    a.attrelid,
                    a.attname AS column_name,
                    pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type,
                    CASE
                        WHEN a.atttypmod > 0 THEN a.atttypmod - 4
                        ELSE NULL
                    END AS max_length,
                    NOT a.attnotnull AS is_nullable,
                    CASE
                        WHEN pk.contype = 'p' THEN 'PK'
                        WHEN fk.conname IS NOT NULL THEN 'FK'
                        ELSE ''
                    END AS key_type,
                    COALESCE(d.description, '') AS description,
                    a.attnum
                FROM pg_catalog.pg_attribute a
                JOIN pg_catalog.pg_class c ON c.oid = a.attrelid
                LEFT JOIN pg_catalog.pg_description d ON d.objoid = a.attrelid AND d.objsubid = a.attnum
                LEFT JOIN (
                    SELECT contype, conrelid, conkey, conname
                    FROM pg_catalog.pg_constraint
                    WHERE contype = 'p'
                ) pk ON pk.conrelid = c.oid AND a.attnum = ANY(pk.conkey)
                LEFT JOIN (
                    SELECT conname, conrelid, conkey
                    FROM pg_catalog.pg_constraint
                    WHERE contype = 'f'
                ) fk ON fk.conrelid = c.oid AND a.attnum = ANY(fk.conkey)
                WHERE a.attnum > 0
                AND NOT a.attisdropped
            ),
            IndexInfo AS (
                SELECT
                    ix.indrelid,
                    i.relname AS index_name,
                    am.amname AS index_type,
                    array_to_string(array_agg(a.attname ORDER BY indseq.ord), ', ') AS column_names,
                    ix.indisprimary AS is_primary_key,
                    ix.indisunique AS is_unique
                FROM pg_index ix
                JOIN pg_class i ON i.oid = ix.indexrelid
                JOIN pg_class t ON t.oid = ix.indrelid
                JOIN pg_am am ON am.oid = i.relam
                JOIN pg_attribute a ON a.attrelid = t.oid
                JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS indseq(key, ord)
                    ON a.attnum = indseq.key
                GROUP BY ix.indrelid, i.relname, am.amname, ix.indisprimary, ix.indisunique
            ),
            ForeignKeyInfo AS (
                SELECT
                    con.conrelid,
                    con.conname AS constraint_name,
                    att.attname AS column_name,
                    cl.relname AS referenced_table,
                    att2.attname AS referenced_column,
                    CASE con.confdeltype
                        WHEN 'a' THEN 'NO ACTION'
                        WHEN 'r' THEN 'RESTRICT'
                        WHEN 'c' THEN 'CASCADE'
                        WHEN 'n' THEN 'SET NULL'
                        WHEN 'd' THEN 'SET DEFAULT'
                    END AS delete_action
                FROM (
                    SELECT conname, conrelid, confrelid, conkey, confkey, confdeltype
                    FROM pg_constraint
                    WHERE contype = 'f'
                ) con
                JOIN pg_class cl ON cl.oid = con.confrelid
                JOIN pg_attribute att ON att.attrelid = con.conrelid AND att.attnum = ANY(con.conkey)
                JOIN pg_attribute att2 ON att2.attrelid = con.confrelid AND att2.attnum = ANY(con.confkey)
            )
            SELECT
                ti.table_name,
                ti.schema_name,
                ti.description,
                -- Colonnes en JSON
                (
                    SELECT json_agg(
                        json_build_object(
                            'column_name', ci.column_name,
                            'data_type', ci.data_type,
                            'max_length', ci.max_length,
                            'is_nullable', ci.is_nullable,
                            'key_type', ci.key_type,
                            'description', ci.description
                        ) ORDER BY ci.attnum
                    )
                    FROM ColumnInfo ci
                    WHERE ci.attrelid = ti.table_oid
                ) AS columns_json,
                -- Index en JSON
                (
                    SELECT json_agg(
                        json_build_object(
                            'index_name', ii.index_name,
                            'index_type', ii.index_type,
                            'column_names', ii.column_names,
                            'is_primary_key', ii.is_primary_key,
                            'is_unique', ii.is_unique
                        ) ORDER BY ii.is_primary_key DESC, ii.index_name
                    )
                    FROM IndexInfo ii
                    WHERE ii.indrelid = ti.table_oid
                ) AS indexes_json,
                -- Foreign Keys en JSON
                (
                    SELECT json_agg(
                        json_build_object(
                            'constraint_name', fki.constraint_name,
                            'column_name', fki.column_name,
                            'referenced_table', fki.referenced_table,
                            'referenced_column', fki.referenced_column,
                            'delete_action', fki.delete_action
                        ) ORDER BY fki.constraint_name
                    )
                    FROM ForeignKeyInfo fki
                    WHERE fki.conrelid = ti.table_oid
                ) AS foreign_keys_json
            FROM TableInfo ti
            ORDER BY ti.schema_name, ti.table_name
        ");

        Log::info('Tables PostgreSQL trouvées: ' . count($allTablesData));

        // ✅ Traitement en mémoire
        foreach ($allTablesData as $tableData) {
            DB::transaction(function () use ($tableData, $dbId) {
                try {
                    // Créer/mettre à jour la table
                    $tableDescription = TableDescription::updateOrCreate(
                        [
                            'dbid' => $dbId,
                            'tablename' => $tableData->table_name
                        ],
                        [
                            'language' => 'en',
                            'description' => $tableData->description ?? null,
                            'updated_at' => now()
                        ]
                    );

                    // --- Colonnes ---
                    TableStructure::where('id_table', $tableDescription->id)->delete();
                    
                    $columns = json_decode($tableData->columns_json ?? '[]', true) ?: [];
                    $columnsToInsert = [];
                    
                    foreach ($columns as $column) {
                        $columnsToInsert[] = [
                            'id_table' => $tableDescription->id,
                            'column' => $column['column_name'],
                            'type' => $column['data_type'],
                            'nullable' => $column['is_nullable'] ? 1 : 0,
                            'key' => $column['key_type'],
                            'description' => $column['description'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    
                    if (!empty($columnsToInsert)) {
                        TableStructure::insert($columnsToInsert);
                    }

                    // --- Index ---
                    TableIndex::where('id_table', $tableDescription->id)->delete();
                    
                    $indexes = json_decode($tableData->indexes_json ?? '[]', true) ?: [];
                    $indexesToInsert = [];
                    
                    foreach ($indexes as $index) {
                        $properties = [];
                        if ($index['is_primary_key']) $properties[] = 'PRIMARY KEY';
                        if ($index['is_unique']) $properties[] = 'UNIQUE';

                        $indexesToInsert[] = [
                            'id_table' => $tableDescription->id,
                            'name' => $index['index_name'],
                            'type' => $index['index_type'],
                            'column' => $index['column_names'],
                            'properties' => implode(', ', $properties),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    
                    if (!empty($indexesToInsert)) {
                        TableIndex::insert($indexesToInsert);
                    }

                    // --- Foreign Keys ---
                    TableRelation::where('id_table', $tableDescription->id)->delete();
                    
                    $foreignKeys = json_decode($tableData->foreign_keys_json ?? '[]', true) ?: [];
                    $foreignKeysToInsert = [];
                    
                    foreach ($foreignKeys as $fk) {
                        $foreignKeysToInsert[] = [
                            'id_table' => $tableDescription->id,
                            'constraints' => $fk['constraint_name'],
                            'column' => $fk['column_name'],
                            'referenced_table' => $fk['referenced_table'],
                            'referenced_column' => $fk['referenced_column'],
                            'action' => $fk['delete_action'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    
                    if (!empty($foreignKeysToInsert)) {
                        TableRelation::insert($foreignKeysToInsert);
                    }

                } catch (\Exception $e) {
                    Log::error('Erreur lors du traitement de la table PostgreSQL', [
                        'table' => $tableData->table_name,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            });
        }

        Log::info('Toutes les tables PostgreSQL ont été traitées.');
        
    } catch (\Exception $e) {
        Log::error('Erreur globale dans extractAndSavePostgreSqlTables', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

    private function extractAndSaveViews($connectionName, $dbId, $databaseType)
{
    Log::info('Début extraction des vues...');
    try {
        $allViewsData = [];
        
        // ✅ UNE SEULE REQUÊTE par type de base de données
        if ($databaseType === 'sqlsrv') {
            $allViewsData = DB::connection($connectionName)->select("
                WITH ViewInfo AS (
                    SELECT
                        v.object_id,
                        v.name AS view_name,
                        s.name AS schema_name,
                        OBJECT_DEFINITION(v.object_id) AS definition,
                        v.create_date,
                        v.modify_date,
                        ISNULL(CONVERT(VARCHAR(8000), ep.value), '') AS description
                    FROM sys.views v
                    INNER JOIN sys.schemas s ON v.schema_id = s.schema_id
                    LEFT JOIN sys.extended_properties ep ON ep.major_id = v.object_id
                        AND ep.minor_id = 0
                        AND ep.name = 'MS_Description'
                    WHERE v.is_ms_shipped = 0
                ),
                ViewColumns AS (
                    SELECT
                        v.object_id,
                        c.name AS column_name,
                        t.name AS data_type,
                        c.max_length,
                        c.precision,
                        c.scale,
                        c.is_nullable,
                        c.column_id,
                        ISNULL(CONVERT(VARCHAR(8000), ep.value), '') AS description
                    FROM sys.views v
                    INNER JOIN sys.columns c ON v.object_id = c.object_id
                    INNER JOIN sys.types t ON c.user_type_id = t.user_type_id
                    LEFT JOIN sys.extended_properties ep ON ep.major_id = c.object_id
                        AND ep.minor_id = c.column_id
                        AND ep.name = 'MS_Description'
                    WHERE v.is_ms_shipped = 0
                )
                SELECT
                    vi.view_name,
                    vi.schema_name,
                    vi.definition,
                    vi.create_date,
                    vi.modify_date,
                    vi.description,
                    -- Colonnes en JSON
                    (
                        SELECT
                            vc.column_name,
                            vc.data_type,
                            vc.max_length,
                            vc.precision,
                            vc.scale,
                            vc.is_nullable,
                            vc.description
                        FROM ViewColumns vc
                        WHERE vc.object_id = vi.object_id
                        ORDER BY vc.column_id
                        FOR JSON PATH
                    ) AS columns_json
                FROM ViewInfo vi
                ORDER BY vi.schema_name, vi.view_name
            ");
            
        } elseif ($databaseType === 'mysql') {

            $connection = DB::connection($connectionName);
            $database = $connection->getDatabaseName();

            // éviter la troncature JSON
            $connection->statement("SET SESSION group_concat_max_len = 1000000");

            $allViewsData = $connection->select("
                SELECT
                    v.TABLE_NAME AS view_name,
                    v.TABLE_SCHEMA AS schema_name,
                    v.VIEW_DEFINITION AS definition,
                    NULL AS create_date,
                    NULL AS modify_date,
                    '' AS description,

                    -- Colonnes en JSON (MySQL safe)
                    (
                        SELECT CONCAT(
                            '[',
                            GROUP_CONCAT(
                                JSON_OBJECT(
                                    'column_name', c.COLUMN_NAME,
                                    'data_type', c.DATA_TYPE,
                                    'max_length', c.CHARACTER_MAXIMUM_LENGTH,
                                    'precision', c.NUMERIC_PRECISION,
                                    'scale', c.NUMERIC_SCALE,
                                    'is_nullable', IF(c.IS_NULLABLE = 'YES', 1, 0),
                                    'description', ''
                                )
                                ORDER BY c.ORDINAL_POSITION
                                SEPARATOR ','
                            ),
                            ']'
                        )
                        FROM INFORMATION_SCHEMA.COLUMNS c
                        WHERE c.TABLE_SCHEMA = v.TABLE_SCHEMA
                        AND c.TABLE_NAME = v.TABLE_NAME
                    ) AS columns_json

                FROM INFORMATION_SCHEMA.VIEWS v
                WHERE v.TABLE_SCHEMA = ?
                ORDER BY v.TABLE_NAME
            ", [$database]);
        }
        elseif ($databaseType === 'pgsql') {
            $allViewsData = DB::connection($connectionName)->select("
                WITH ViewInfo AS (
                    SELECT
                        c.oid,
                        c.relname AS view_name,
                        n.nspname AS schema_name,
                        pg_get_viewdef(c.oid, true) AS definition,
                        NULL AS create_date,
                        NULL AS modify_date,
                        COALESCE(d.description, '') AS description
                    FROM pg_class c
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    LEFT JOIN pg_description d ON d.objoid = c.oid AND d.objsubid = 0
                    WHERE c.relkind IN ('v', 'm')
                    AND n.nspname NOT IN ('pg_catalog', 'information_schema')
                )
                SELECT
                    vi.view_name,
                    vi.schema_name,
                    vi.definition,
                    vi.create_date,
                    vi.modify_date,
                    vi.description,
                    -- Colonnes en JSON
                    (
                        SELECT json_agg(
                            json_build_object(
                                'column_name', a.attname,
                                'data_type', pg_catalog.format_type(a.atttypid, a.atttypmod),
                                'max_length', CASE WHEN a.atttypmod > 0 THEN a.atttypmod - 4 ELSE NULL END,
                                'precision', NULL,
                                'scale', NULL,
                                'is_nullable', CASE WHEN a.attnotnull THEN 0 ELSE 1 END,
                                'description', COALESCE(d.description, '')
                            ) ORDER BY a.attnum
                        )
                        FROM pg_attribute a
                        LEFT JOIN pg_description d ON d.objoid = a.attrelid AND d.objsubid = a.attnum
                        WHERE a.attrelid = vi.oid
                        AND a.attnum > 0
                        AND NOT a.attisdropped
                    ) AS columns_json
                FROM ViewInfo vi
                ORDER BY vi.schema_name, vi.view_name
            ");
        }

        Log::info('Vues trouvées: ' . count($allViewsData));

        // ✅ Traitement en mémoire
        foreach ($allViewsData as $viewData) {
            DB::transaction(function () use ($viewData, $dbId, $databaseType) {
                try {
                    // Créer/mettre à jour la vue
                    $viewDescription = ViewDescription::updateOrCreate(
                        [
                            'dbid' => $dbId,
                            'viewname' => $viewData->view_name
                        ],
                        [
                            'language' => ($databaseType === 'mysql' ? 'en' : 'fr'),
                            'description' => $viewData->description ?? null,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );

                    // Sauvegarder ViewInformation
                    ViewInformation::updateOrCreate(
                        ['id_view' => $viewDescription->id],
                        [
                            'schema_name' => $viewData->schema_name,
                            'definition' => $viewData->definition,
                            'creation_date' => $viewData->create_date,
                            'last_change_date' => $viewData->modify_date,
                            'updated_at' => now()
                        ]
                    );

                    // --- Colonnes de la vue ---
                    ViewColumn::where('id_view', $viewDescription->id)->delete();

                    // Décoder le JSON des colonnes
                    $columns = [];
                    if ($databaseType === 'sqlsrv') {
                        $columns = json_decode($viewData->columns_json ?? '[]', true) ?: [];
                    } elseif ($databaseType === 'mysql') {
                        $columns = json_decode($viewData->columns_json ?? '[]', true) ?: [];
                    } elseif ($databaseType === 'pgsql') {
                        $columns = json_decode($viewData->columns_json ?? '[]', true) ?: [];
                    }

                    $columnsToInsert = [];
                    foreach ($columns as $column) {
                        $dataType = $this->formatDataType((object)$column);
                        $columnsToInsert[] = [
                            'id_view' => $viewDescription->id,
                            'name' => $column['column_name'],
                            'type' => $dataType,
                            'max_length' => $column['max_length'] ?? null,
                            'precision' => $column['precision'] ?? null,
                            'scale' => $column['scale'] ?? null,
                            'is_nullable' => $column['is_nullable'] ? 1 : 0,
                            'description' => $column['description'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    // Insertion par chunks
                    $chunkSize = 150;
                    if (!empty($columnsToInsert)) {
                        foreach (array_chunk($columnsToInsert, $chunkSize) as $chunk) {
                            ViewColumn::insert($chunk);
                        }
                    }

                } catch (\Exception $e) {
                    Log::error('Erreur lors du traitement de la vue', [
                        'view' => $viewData->view_name,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            });
        }

        Log::info('Fin extraction des vues.');
        
    } catch (\Exception $e) {
        Log::error('Erreur globale dans extractAndSaveViews', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

    
    private function extractAndSaveFunctions($connectionName, $dbId, $databaseType)
{
    Log::info('Début extraction des fonctions...');
    try {
        $allFunctionsData = [];
        
        // ✅ UNE SEULE REQUÊTE par type de base de données
        if ($databaseType === 'sqlsrv') {
            $allFunctionsData = DB::connection($connectionName)->select("
                WITH FunctionInfo AS (
                    SELECT
                        o.object_id,
                        o.name AS function_name,
                        s.name AS schema_name,
                        o.type_desc AS function_type,
                        OBJECT_DEFINITION(o.object_id) AS definition,
                        o.create_date,
                        o.modify_date,
                        ISNULL(CONVERT(VARCHAR(8000), ep.value), '') AS description
                    FROM sys.objects o
                    INNER JOIN sys.schemas s ON o.schema_id = s.schema_id
                    LEFT JOIN sys.extended_properties ep ON ep.major_id = o.object_id
                        AND ep.minor_id = 0
                        AND ep.name = 'MS_Description'
                    WHERE o.type IN ('FN', 'IF', 'TF')
                    AND o.is_ms_shipped = 0
                ),
                FunctionParameters AS (
                    SELECT
                        p.object_id,
                        p.name AS parameter_name,
                        TYPE_NAME(p.user_type_id) AS data_type,
                        CASE WHEN p.is_output = 1 THEN 'OUTPUT' ELSE 'INPUT' END AS output_type,
                        p.parameter_id,
                        ISNULL(CONVERT(VARCHAR(8000), ep.value), '') AS description
                    FROM sys.parameters p
                    INNER JOIN sys.objects o ON p.object_id = o.object_id
                    LEFT JOIN sys.extended_properties ep ON ep.major_id = p.object_id
                        AND ep.minor_id = p.parameter_id
                        AND ep.name = 'MS_Description'
                    WHERE o.type IN ('FN', 'IF', 'TF')
                    AND o.is_ms_shipped = 0
                )
                SELECT
                    fi.function_name,
                    fi.schema_name,
                    fi.function_type,
                    fi.definition,
                    fi.create_date,
                    fi.modify_date,
                    fi.description,
                    NULL AS return_type,
                    -- Paramètres en JSON
                    (
                        SELECT
                            fp.parameter_name,
                            fp.data_type,
                            fp.output_type,
                            fp.description
                        FROM FunctionParameters fp
                        WHERE fp.object_id = fi.object_id
                        ORDER BY fp.parameter_id
                        FOR JSON PATH
                    ) AS parameters_json
                FROM FunctionInfo fi
                ORDER BY fi.schema_name, fi.function_name
            ");
            
        } elseif ($databaseType === 'mysql') {
            $database = DB::connection($connectionName)->getDatabaseName();
            $allFunctionsData = DB::connection($connectionName)->select("
                SELECT
                    r.ROUTINE_NAME AS function_name,
                    r.ROUTINE_SCHEMA AS schema_name,
                    r.ROUTINE_TYPE AS function_type,
                    r.ROUTINE_DEFINITION AS definition,
                    r.CREATED AS create_date,
                    r.LAST_ALTERED AS modify_date,
                    r.ROUTINE_COMMENT AS description,
                    NULL AS return_type,
                    -- Paramètres en JSON
                    (
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'parameter_name', p.PARAMETER_NAME,
                                'data_type', p.DTD_IDENTIFIER,
                                'output_type', p.PARAMETER_MODE,
                                'description', ''
                            )
                        )
                        FROM INFORMATION_SCHEMA.PARAMETERS p
                        WHERE p.SPECIFIC_SCHEMA = r.ROUTINE_SCHEMA
                        AND p.SPECIFIC_NAME = r.ROUTINE_NAME
                        AND p.ROUTINE_TYPE = 'FUNCTION'
                        ORDER BY p.ORDINAL_POSITION
                    ) AS parameters_json
                FROM INFORMATION_SCHEMA.ROUTINES r
                WHERE r.ROUTINE_SCHEMA = ?
                AND r.ROUTINE_TYPE = 'FUNCTION'
                ORDER BY r.ROUTINE_NAME
            ", [$database]);
            
        } elseif ($databaseType === 'pgsql') {
            $allFunctionsData = DB::connection($connectionName)->select("
                WITH FunctionInfo AS (
                    SELECT
                        p.oid,
                        p.proname AS function_name,
                        n.nspname AS schema_name,
                        CASE p.proretset WHEN TRUE THEN 'Table-valued function' ELSE 'Scalar function' END AS function_type,
                        pg_get_functiondef(p.oid) AS definition,
                        NULL AS create_date,
                        NULL AS modify_date,
                        COALESCE(d.description, '') AS description,
                        pg_catalog.format_type(p.prorettype, NULL) AS return_type,
                        p.proargnames,
                        p.proargtypes,
                        --  Ajout pour identifier les surcharges
                        pg_get_function_identity_arguments(p.oid) AS function_signature,
                        ROW_NUMBER() OVER (PARTITION BY p.proname ORDER BY p.oid) AS rn
                    FROM pg_proc p
                    JOIN pg_namespace n ON n.oid = p.pronamespace
                    LEFT JOIN pg_description d ON d.objoid = p.oid
                    WHERE p.prokind = 'f'
                    AND n.nspname NOT IN ('pg_catalog', 'information_schema')
                )
                SELECT
                    fi.function_name,
                    fi.schema_name,
                    fi.function_type,
                    fi.definition,
                    fi.create_date,
                    fi.modify_date,
                    fi.description,
                    fi.return_type,
                    fi.function_signature, --  Pour différencier les surcharges
                    -- Paramètres en JSON
                    (
                        SELECT json_agg(
                            json_build_object(
                                'parameter_name', argnames.parameter_name,
                                'data_type', pg_catalog.format_type(argtypes_oid.type_oid, NULL),
                                'output_type', 'INPUT',
                                'description', ''
                            ) ORDER BY argnames.ord
                        )
                        FROM unnest(fi.proargnames) WITH ORDINALITY AS argnames(parameter_name, ord)
                        LEFT JOIN unnest(fi.proargtypes) WITH ORDINALITY AS argtypes_oid(type_oid, ord2)
                            ON argnames.ord = argtypes_oid.ord2
                    ) AS parameters_json
                FROM FunctionInfo fi
                WHERE fi.rn = 1  --  Ne garder que la première occurrence de chaque nom
                ORDER BY fi.schema_name, fi.function_name
            ");
        }

        Log::info('Fonctions trouvées: ' . count($allFunctionsData));

        // Traitement en mémoire
        foreach ($allFunctionsData as $functionData) {
            DB::transaction(function () use ($functionData, $dbId, $databaseType) {
                try {
                    // Créer/mettre à jour la fonction
                    $functionDescription = FunctionDescription::updateOrCreate(
                        [
                            'dbid' => $dbId,
                            'functionname' => $functionData->function_name
                        ],
                        [
                            'language' => ($databaseType === 'mysql' ? 'en' : 'fr'),
                            'description' => $functionData->description ?? null,
                            'updated_at' => now()
                        ]
                    );

                    // Sauvegarder FuncInformation
                    FuncInformation::updateOrCreate(
                        ['id_func' => $functionDescription->id],
                        [
                            'type' => $functionData->function_type,
                            'return_type' => $functionData->return_type ?? null,
                            'definition' => $functionData->definition,
                            'creation_date' => $functionData->create_date,
                            'last_change_date' => $functionData->modify_date,
                            'updated_at' => now()
                        ]
                    );

                    // --- Paramètres de la fonction ---
                    FuncParameter::where('id_func', $functionDescription->id)->delete();

                    // Décoder le JSON des paramètres
                    $parameters = json_decode($functionData->parameters_json ?? '[]', true) ?: [];

                    $parametersToInsert = [];
                    foreach ($parameters as $param) {
                        $parametersToInsert[] = [
                            'id_func' => $functionDescription->id,
                            'name' => $param['parameter_name'],
                            'type' => $param['data_type'],
                            'output' => $param['output_type'],
                            'description' => $param['description'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    
                    if (!empty($parametersToInsert)) {
                        FuncParameter::insert($parametersToInsert);
                    }

                } catch (\Exception $e) {
                    Log::error('Erreur lors du traitement de la fonction', [
                        'function' => $functionData->function_name,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            });
        }

        Log::info('Fin extraction des fonctions.');
        
    } catch (\Exception $e) {
        Log::error('Erreur globale dans extractAndSaveFunctions', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

    
    private function extractAndSaveProcedures($connectionName, $dbId, $databaseType)
{
    Log::info('Début extraction des procédures stockées...');
    try {
        $allProceduresData = [];
        
        // UNE SEULE REQUÊTE par type de base de données
        if ($databaseType === 'sqlsrv') {
            $allProceduresData = DB::connection($connectionName)->select("
                WITH ProcedureInfo AS (
                    SELECT
                        o.object_id,
                        o.name AS procedure_name,
                        s.name AS schema_name,
                        OBJECT_DEFINITION(o.object_id) AS definition,
                        o.create_date,
                        o.modify_date,
                        ISNULL(CONVERT(VARCHAR(8000), ep.value), '') AS description
                    FROM sys.objects o
                    INNER JOIN sys.schemas s ON o.schema_id = s.schema_id
                    LEFT JOIN sys.extended_properties ep ON ep.major_id = o.object_id
                        AND ep.minor_id = 0
                        AND ep.name = 'MS_Description'
                    WHERE o.type = 'P'
                    AND o.is_ms_shipped = 0
                ),
                ProcedureParameters AS (
                    SELECT
                        p.object_id,
                        p.name AS parameter_name,
                        TYPE_NAME(p.user_type_id) AS data_type,
                        CASE WHEN p.is_output = 1 THEN 'OUTPUT' ELSE 'INPUT' END AS output_type,
                        p.parameter_id,
                        ISNULL(CONVERT(VARCHAR(8000), ep.value), '') AS description
                    FROM sys.parameters p
                    INNER JOIN sys.objects o ON p.object_id = o.object_id
                    LEFT JOIN sys.extended_properties ep ON ep.major_id = p.object_id
                        AND ep.minor_id = p.parameter_id
                        AND ep.name = 'MS_Description'
                    WHERE o.type = 'P'
                    AND o.is_ms_shipped = 0
                )
                SELECT
                    pi.procedure_name,
                    pi.schema_name,
                    pi.definition,
                    pi.create_date,
                    pi.modify_date,
                    pi.description,
                    -- Paramètres en JSON
                    (
                        SELECT
                            pp.parameter_name,
                            pp.data_type,
                            pp.output_type,
                            pp.description
                        FROM ProcedureParameters pp
                        WHERE pp.object_id = pi.object_id
                        ORDER BY pp.parameter_id
                        FOR JSON PATH
                    ) AS parameters_json
                FROM ProcedureInfo pi
                ORDER BY pi.schema_name, pi.procedure_name
            ");
            
        } elseif ($databaseType === 'mysql') {
            $database = DB::connection($connectionName)->getDatabaseName();
            $allProceduresData = DB::connection($connectionName)->select("
                SELECT
                    r.ROUTINE_NAME AS procedure_name,
                    r.ROUTINE_SCHEMA AS schema_name,
                    r.ROUTINE_DEFINITION AS definition,
                    r.CREATED AS create_date,
                    r.LAST_ALTERED AS modify_date,
                    r.ROUTINE_COMMENT AS description,
                    -- Paramètres en JSON
                    (
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'parameter_name', p.PARAMETER_NAME,
                                'data_type', p.DTD_IDENTIFIER,
                                'output_type', p.PARAMETER_MODE,
                                'description', ''
                            )
                        )
                        FROM INFORMATION_SCHEMA.PARAMETERS p
                        WHERE p.SPECIFIC_SCHEMA = r.ROUTINE_SCHEMA
                        AND p.SPECIFIC_NAME = r.ROUTINE_NAME
                        AND p.ROUTINE_TYPE = 'PROCEDURE'
                        ORDER BY p.ORDINAL_POSITION
                    ) AS parameters_json
                FROM INFORMATION_SCHEMA.ROUTINES r
                WHERE r.ROUTINE_SCHEMA = ?
                AND r.ROUTINE_TYPE = 'PROCEDURE'
                ORDER BY r.ROUTINE_NAME
            ", [$database]);
            
        } elseif ($databaseType === 'pgsql') {
            $allProceduresData = DB::connection($connectionName)->select("
                WITH ProcedureInfo AS (
                    SELECT
                        p.oid,
                        p.proname AS procedure_name,
                        n.nspname AS schema_name,
                        pg_get_functiondef(p.oid) AS definition,
                        NULL AS create_date,
                        NULL AS modify_date,
                        COALESCE(d.description, '') AS description,
                        p.proargnames,
                        p.proargtypes
                    FROM pg_proc p
                    JOIN pg_namespace n ON n.oid = p.pronamespace
                    LEFT JOIN pg_description d ON d.objoid = p.oid
                    WHERE p.prokind = 'p'
                    AND n.nspname NOT IN ('pg_catalog', 'information_schema')
                )
                SELECT
                    pi.procedure_name,
                    pi.schema_name,
                    pi.definition,
                    pi.create_date,
                    pi.modify_date,
                    pi.description,
                    -- Paramètres en JSON
                    (
                        SELECT json_agg(
                            json_build_object(
                                'parameter_name', argnames.parameter_name,
                                'data_type', pg_catalog.format_type(argtypes_oid.type_oid, NULL),
                                'output_type', 'INPUT',
                                'description', ''
                            ) ORDER BY argnames.ord
                        )
                        FROM unnest(pi.proargnames) WITH ORDINALITY AS argnames(parameter_name, ord)
                        LEFT JOIN unnest(pi.proargtypes) WITH ORDINALITY AS argtypes_oid(type_oid, ord2)
                            ON argnames.ord = argtypes_oid.ord2
                    ) AS parameters_json
                FROM ProcedureInfo pi
                ORDER BY pi.schema_name, pi.procedure_name
            ");
        }

        Log::info('Procédures trouvées: ' . count($allProceduresData));

        // ✅ Traitement en mémoire
        foreach ($allProceduresData as $procedureData) {
            DB::transaction(function () use ($procedureData, $dbId, $databaseType) {
                try {
                    // Créer/mettre à jour la procédure
                    $psDescription = PsDescription::updateOrCreate(
                        [
                            'dbid' => $dbId,
                            'psname' => $procedureData->procedure_name
                        ],
                        [
                            'language' => ($databaseType === 'mysql' ? 'en' : 'fr'),
                            'description' => $procedureData->description ?? null,
                            'updated_at' => now()
                        ]
                    );

                    // Sauvegarder PsInformation
                    PsInformation::updateOrCreate(
                        ['id_ps' => $psDescription->id],
                        [
                            'schema' => $procedureData->schema_name,
                            'definition' => $procedureData->definition,
                            'creation_date' => $procedureData->create_date,
                            'last_change_date' => $procedureData->modify_date,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );

                    // --- Paramètres de la procédure ---
                    PsParameter::where('id_ps', $psDescription->id)->delete();

                    // Décoder le JSON des paramètres
                    $parameters = json_decode($procedureData->parameters_json ?? '[]', true) ?: [];

                    $parametersToInsert = [];
                    foreach ($parameters as $param) {
                        $parametersToInsert[] = [
                            'id_ps' => $psDescription->id,
                            'name' => $param['parameter_name'] ?? $param['PARAMETER_NAME'] ?? '',
                            'type' => $param['data_type'] ?? $param['DATA_TYPE'] ?? 'unknown',  // ← FIX
                            'output' => $param['output_type'] ?? $param['OUTPUT_TYPE'] ?? 'INPUT',
                            'description' => $param['description'] ?? $param['DESCRIPTION'] ?? null,
                            'default_value' => $param['default_value'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    
                    if (!empty($parametersToInsert)) {
                        PsParameter::insert($parametersToInsert);
                    }

                } catch (\Exception $e) {
                    Log::error('Erreur lors du traitement de la procédure', [
                        'procedure' => $procedureData->procedure_name,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            });
        }

        Log::info('Fin extraction des procédures stockées.');
        
    } catch (\Exception $e) {
        Log::error('Erreur globale dans extractAndSaveProcedures', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

    
    private function extractAndSaveTriggers($connectionName, $dbId, $databaseType)
{
    Log::info('Début extraction des triggers...', ['dbId' => $dbId, 'connectionName' => $connectionName]);
    
    try {
        $allTriggersData = [];
        $startTime = microtime(true);

        // Requêtes optimisées avec CTEs pour chaque type de base de données
        if ($databaseType === 'sqlsrv') {
            $allTriggersData = DB::connection($connectionName)->select("
                WITH TriggerInfo AS (
                    SELECT
                        t.object_id,
                        t.name AS trigger_name,
                        OBJECT_NAME(t.parent_id) AS table_name,
                        SCHEMA_NAME(tbl.schema_id) AS schema_name,
                        OBJECT_DEFINITION(t.object_id) AS definition,
                        t.create_date,
                        t.modify_date,
                        CASE WHEN t.is_disabled = 1 THEN 1 ELSE 0 END AS is_disabled,
                        CASE
                            WHEN t.is_instead_of_trigger = 1 THEN 'INSTEAD OF'
                            ELSE 'AFTER'
                        END AS trigger_type,
                        ISNULL(CONVERT(VARCHAR(8000), ep.value), '') AS description
                    FROM sys.triggers t
                    INNER JOIN sys.tables tbl ON t.parent_id = tbl.object_id
                    INNER JOIN sys.schemas s ON tbl.schema_id = s.schema_id
                    LEFT JOIN sys.extended_properties ep ON ep.major_id = t.object_id
                        AND ep.minor_id = 0
                        AND ep.name = 'MS_Description'
                    WHERE t.is_ms_shipped = 0
                )
                SELECT
                    ti.trigger_name,
                    ti.table_name,
                    ti.schema_name,
                    ti.definition,
                    ti.create_date,
                    ti.modify_date,
                    ti.is_disabled,
                    ti.trigger_type,
                    ti.description,
                    (
                        SELECT STRING_AGG(tev.type_desc, ',')
                        FROM sys.trigger_events tev
                        WHERE tev.object_id = ti.object_id
                    ) AS trigger_event
                FROM TriggerInfo ti
                ORDER BY ti.trigger_name
            ");
            
        } elseif ($databaseType === 'mysql') {

                $database = DB::connection($connectionName)->getDatabaseName();

                $allTriggersData = DB::connection($connectionName)->select("
                    SELECT
                        t.TRIGGER_NAME AS trigger_name,
                        t.EVENT_OBJECT_TABLE AS table_name,
                        t.TRIGGER_SCHEMA AS schema_name,
                        t.ACTION_STATEMENT AS definition,
                        t.CREATED AS create_date,
                        NULL AS modify_date,
                        0 AS is_disabled,
                        t.ACTION_TIMING AS trigger_type,
                        t.EVENT_MANIPULATION AS trigger_event,
                        '' AS description
                    FROM INFORMATION_SCHEMA.TRIGGERS t
                    WHERE t.TRIGGER_SCHEMA = ?
                    ORDER BY t.TRIGGER_NAME
                ", [$database]);
            } elseif ($databaseType === 'pgsql') {
            $allTriggersData = DB::connection($connectionName)->select("
                WITH TriggerInfo AS (
                    SELECT
                        t.oid,
                        t.tgname AS trigger_name,
                        c.relname AS table_name,
                        n.nspname AS schema_name,
                        pg_get_triggerdef(t.oid, true) AS definition,
                        NULL AS create_date,
                        NULL AS modify_date,
                        CASE WHEN t.tgenabled = 'D' THEN 1 ELSE 0 END AS is_disabled,
                        CASE 
                            WHEN (t.tgtype & 2) = 2 THEN 'BEFORE'
                            WHEN (t.tgtype & 64) = 64 THEN 'INSTEAD OF'
                            ELSE 'AFTER'
                        END AS trigger_type,
                        t.tgtype,
                        COALESCE(d.description, '') AS description
                    FROM pg_trigger t
                    JOIN pg_class c ON c.oid = t.tgrelid
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    LEFT JOIN pg_description d ON d.objoid = t.oid AND d.objsubid = 0
                    WHERE NOT t.tgisinternal
                    AND n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
                    AND c.relkind = 'r'
                )
                SELECT
                    ti.trigger_name,
                    ti.table_name,
                    ti.schema_name,
                    ti.definition,
                    ti.create_date,
                    ti.modify_date,
                    ti.is_disabled,
                    ti.trigger_type,
                    ti.description,
                    ARRAY_TO_STRING(
                        ARRAY_REMOVE(ARRAY[
                            CASE WHEN (ti.tgtype & 4) = 4 THEN 'INSERT' END,
                            CASE WHEN (ti.tgtype & 8) = 8 THEN 'DELETE' END,
                            CASE WHEN (ti.tgtype & 16) = 16 THEN 'UPDATE' END,
                            CASE WHEN (ti.tgtype & 32) = 32 THEN 'TRUNCATE' END
                        ], NULL),
                        ','
                    ) AS trigger_event
                FROM TriggerInfo ti
                ORDER BY ti.trigger_name
            ");
            
        } else {
            throw new \InvalidArgumentException("Type de base de données non supporté: {$databaseType}");
        }

        $triggerCount = count($allTriggersData);
        Log::info("Triggers trouvés: {$triggerCount}");

        if ($triggerCount === 0) {
            Log::info('Aucun trigger trouvé dans la base de données');
            return;
        }

        $successCount = 0;
        $errorCount = 0;

        // ✅ Traitement en mémoire avec gestion d'erreurs améliorée
        foreach ($allTriggersData as $triggerData) {
            try {
                DB::transaction(function () use ($triggerData, $dbId) {
                    // Validation des données
                    $triggerName = trim($triggerData->trigger_name ?? '');
                    if (empty($triggerName)) {
                        throw new \InvalidArgumentException('Nom de trigger vide');
                    }

                    // Créer/mettre à jour le trigger
                    $triggerDescription = TriggerDescription::updateOrCreate(
                        [
                            'dbid' => $dbId,
                            'triggername' => $triggerName
                        ],
                        [
                            'language' => 'fr',
                            'description' => $triggerData->description ?? '',
                            'updated_at' => now()
                        ]
                    );

                    // Sauvegarder TriggerInformation
                    TriggerInformation::updateOrCreate(
                        ['id_trigger' => $triggerDescription->id],
                        [
                            'table' => trim($triggerData->table_name ?? ''),
                            'schema' => trim($triggerData->schema_name ?? ''),
                            'type' => trim($triggerData->trigger_type ?? ''),
                            'event' => trim($triggerData->trigger_event ?? ''),
                            'state' => $triggerData->is_disabled ? 0 : 1,
                            'definition' => $triggerData->definition ?? '',
                            'creation_date' => $triggerData->create_date,
                            'last_change_date' => $triggerData->modify_date,
                            'updated_at' => now()
                        ]
                    );
                });
                
                $successCount++;
                
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Erreur lors du traitement d\'un trigger', [
                    'trigger_name' => $triggerData->trigger_name ?? 'unknown',
                    'table_name' => $triggerData->table_name ?? 'unknown',
                    'error' => $e->getMessage(),
                    'line' => $e->getLine()
                ]);
                
                // Continuer même en cas d'erreur sur un trigger
                continue;
            }
        }

        $executionTime = round(microtime(true) - $startTime, 2);
        
        Log::info('Extraction des triggers terminée', [
            'total_found' => $triggerCount,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'execution_time' => "{$executionTime}s"
        ]);
        
        if ($errorCount > 0) {
            Log::warning("Extraction partiellement réussie: {$errorCount} erreur(s) sur {$triggerCount} trigger(s)");
        }
        
    } catch (\Exception $e) {
        Log::error('Erreur globale dans extractAndSaveTriggers', [
            'dbId' => $dbId,
            'connectionName' => $connectionName,
            'databaseType' => $databaseType,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        throw $e;
    }
}
}