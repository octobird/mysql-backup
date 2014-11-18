<?php

namespace Octobird\Mysql;

use Doctrine\DBAL\Connection;
use Exception;

class Restorer
{
    private $connection;
    private $backupDir;

    public function __construct(Connection $connection, $backupDir)
    {
        $this->connection = $connection;
        $this->backupDir = $backupDir;
    }

    /**
     * @param $table
     * @param array $columns - список полей, содержащийся в бэкапе (здесь и поля с данными и PK).
     * @param array $pkColumns - отдельно указываются поля, входящие в PK, он также должны присутствовать в $columns.
     */
    public function restore($table, $columns = [], $pkColumns = ['id'])
    {
        sort($columns);

        $tmpTable = NamerHelper::getTmpTableName($table);
        $dataFile = NamerHelper::getFile($this->backupDir, $table, $columns);
        $schemaFile = NamerHelper::getFile($this->backupDir, $tmpTable, $columns);

        $this->createTmpTable($schemaFile);
        $this->loadDataInTmpTable($dataFile, $tmpTable);
        $this->restoreOriginalTableData($table, $tmpTable, $columns, $pkColumns);
    }


    private function createTmpTable($schemaFile)
    {
        $tmpTableSchema = file_get_contents($schemaFile);

        $this->connection->exec($tmpTableSchema);
    }

    private function loadDataInTmpTable($file, $tmpTableName)
    {
        $loadDataQuery = $this->buildLoadDataQuery($file, $tmpTableName);

        $this->connection->executeQuery($loadDataQuery);
    }

    private function restoreOriginalTableData($originalTable, $tmpTable, $columnsForRestore = [], $pkColumns = ['id'])
    {
        if (empty($columnsForRestore)) {
            $this->restoreFullTableData($originalTable, $tmpTable);
        } else {
            $this->restoreSelectedColumns($originalTable, $tmpTable, $pkColumns, $columnsForRestore);
        }
    }

    /**
     * @param $originalTable
     * @param $tmpTable
     */
    private function restoreFullTableData($originalTable, $tmpTable)
    {
        $columns = $this->getTableColumns($tmpTable);
        $columns = implode(', ', $columns);

        $query = <<<EOF
        INSERT INTO `$originalTable` ($columns)
        SELECT $columns FROM `$tmpTable`
EOF;

        $this->connection->executeQuery($query);
    }

    private function restoreSelectedColumns($originalTable, $tmpTable, $pkColumns = ['id'], $columnsForRestore)
    {
        $pkConditions = $this->buildPkConditions($pkColumns);
        $setValuesPart = $this->buildSetValuesPart($tmpTable, $pkColumns, $columnsForRestore);

        $query = <<<EOF
        UPDATE `$originalTable` o
         JOIN $tmpTable t ON $pkConditions
         SET $setValuesPart
EOF;

        $this->connection->executeQuery($query);
    }

    private function buildPkConditions($pkColumns)
    {
        $pkConditions = [];

        foreach ($pkColumns as $pkColumn) {
            $pkConditions[] = "o.$pkColumn = t.$pkColumn";
        }

        return implode(', ', $pkConditions);
    }

    private function getTableColumns($table)
    {
        $fieldsInfo = $this->connection->fetchAll("SHOW COLUMNS FROM `$table`");

        $columns = [];

        foreach ($fieldsInfo as $fieldInfo) {
            $columns[] = $fieldInfo['Field'];
        }

        return $columns;
    }

    private function buildSetValuesPart($tmpTable, $pkColumns, $columnsForRestore)
    {
        if (empty($columnsForRestore)) {
            $columnsForRestore = $this->getTableColumns($tmpTable);
        }

        $setValuesParts = [];

        // воостанавливаем только поля, которые не входят в PK
        foreach ($columnsForRestore as $i => $columnForRestore) {
            if (in_array($columnForRestore, $pkColumns)) {
                unset($columnsForRestore[$i]);
            } else {
                $setValuesParts[] = "o.$columnForRestore = t.$columnForRestore";
            }
        }

        if (empty($setValuesParts)) {
            throw new Exception("No data for restore");
        }

        return implode(', ', $setValuesParts);
    }

    private function buildLoadDataQuery($file, $table)
    {
        return <<<EOF
        LOAD DATA INFILE '$file'
        INTO TABLE `$table`
            FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\''
            LINES TERMINATED BY '\n'
        ;
EOF;
    }

}