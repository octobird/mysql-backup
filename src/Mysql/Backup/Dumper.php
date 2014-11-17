<?php

namespace Octobird\Mysql\Backup;

use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

class Dumper
{
    const QUERY_LIMIT = 2;

    protected $connection;
    protected $fs;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->fs = new Filesystem();
    }

    public function dumpData($file, $table, $columns = [])
    {
        $this->prepareFile($file);

        $baseQuery = $this->buildDumpQuery($table, $columns, $file);

        $offset = 0;

        while ($records = $this->connection->fetchAll($baseQuery . " LIMIT $offset, " . self::QUERY_LIMIT)) {
            $this->dumpDataChunk($file, $records);

            $offset += self::QUERY_LIMIT;
        }
    }

    public function dumpTmpTableSchema($file, $table, $tmpTableName, $columns = [])
    {
        $this->prepareFile($file);

        $tmpTableSchema = $this->createTmpTableSchema($table, $tmpTableName, $columns);

        $this->fs->dumpFile($file, $tmpTableSchema);
    }

    protected function dumpDataChunk($file, $records)
    {
        $data = [];

        foreach ($records as $recordFields) {
            $values = [];

            foreach ($recordFields as $recordField) {
                $values[] = $this->connection->quote($recordField);
            }

            $data[] = implode(',', $values);
        }

        file_put_contents($file, implode("\n", $data) . "\n", FILE_APPEND);
    }

    protected function buildDumpQuery($table, $columns)
    {
        $fields = $this->buildSelectedFields($columns);

        return "SELECT $fields FROM `$table`";
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

    protected function prepareFile($file)
    {
        if ($this->fs->exists($file)) {
            $this->fs->remove($file);
        }

        $this->fs->mkdir(dirname($file));
        $this->fs->touch($file);
        $this->fs->chmod($file, 0777);
    }
} 