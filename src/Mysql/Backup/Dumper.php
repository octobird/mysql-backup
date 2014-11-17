<?php

namespace Octobird\Mysql\Backup;

use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

class Dumper
{
    protected $connection;
    protected $fs;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->fs = new Filesystem();
    }

    public function dumpData($file, $table, $columns = [])
    {
        $query = $this->buildDumpQuery($table, $columns, $file);

        $this->connection->executeQuery($query);
    }

    public function dumpTmpTableSchema($file, $table, $tmpTableName, $columns = [])
    {
        $tmpTableSchema = $this->createTmpTableSchema($table, $tmpTableName, $columns);

        $this->fs->dumpFile($file, $tmpTableSchema);
    }

    protected function buildDumpQuery($table, $columns, $file)
    {
        $fields = $this->buildSelectedFields($columns);

        return <<<EOF
        SELECT $fields
        INTO OUTFILE '$file'
            FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
            LINES TERMINATED BY '\n'
        FROM `$table`
EOF;
    }

    protected function buildSelectedFields($columns)
    {
        if (empty($columns)) {
            $columns = '*';
        } else {
            // важно, чтобы порядок полей в схеме совпадал с порядком полей в дампе
            sort($columns);

            $columns = implode(', ', $columns);
        }

        return $columns;
    }

    public function createTmpTableSchema($table, $tmpTableName, $columns = [])
    {
        $tmpTableSchema = $this->connection->fetchColumn("SHOW CREATE TABLE `$table`", [], 1);

        $columnsSchema = [];
        $pkSchema = '';
        $engineSchema = '';

        // получаем SQL для создания нужных полей таблицы
        foreach (explode("\n", $tmpTableSchema) as $line) {
            if (strstr($line, 'PRIMARY KEY')) {
                $pkSchema = $line;
                continue;
            }
            if (strstr($line, 'ENGINE')) {
                $engineSchema = $line;
                continue;
            }


            if (strstr($line, 'CONSTRAINT') || strstr($line, 'KEY') || strstr($line, 'CREATE')) {
                continue;
            }


            if (empty($columns)) {
                $columnsSchema[] = $line;
            } else {
                foreach ($columns as $column) {
                    if (preg_match("~^\s+[`]?{$column}[`]?\s{1}\b~Us", $line)) {
                        $columnsSchema[$column] = $line;
                    }
                }
            }
        }



        $newSchemaLines = [];


        /**
         * Если несколько бэкапов работают с одной таблицей (1 бэкап по одному полю и второй по другому),
         * то они исползуют одну и ту же временную таблицу, но схема для этой таблицы для разных данных будет отличаться.
         * Поэтому нужно пересоздавать таблицу, а то может возникнуть ошибка, что таблица с таким именем уже существует.
         */
        $newSchemaLines[] = "DROP TABLE IF EXISTS `$tmpTableName`;";
        $newSchemaLines[] = "CREATE TEMPORARY TABLE `$tmpTableName` (";

        // важно, чтобы порядок полей в схеме совпадал с порядком полей в дампе
        ksort($columnsSchema);
        foreach ($columnsSchema as $columnSchema) {
            $newSchemaLines[] = $columnSchema;
        }

        $newSchemaLines[] = $pkSchema;
        $newSchemaLines[] = $engineSchema;

        return implode("\n", $newSchemaLines);
    }
} 