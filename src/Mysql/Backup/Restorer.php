<?php

namespace Octobird\Mysql\Backup;

use Doctrine\DBAL\Connection;
use Exception;

class Restorer
{
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }


    public function createTmpTable($schemaFile)
    {
        $tmpTableSchema = file_get_contents($schemaFile);

        $this->connection->exec($tmpTableSchema);
    }

    public function loadDataInTmpTable($file, $tmpTableName)
    {
        $loadDataQuery = $this->buildLoadDataQuery($file, $tmpTableName);

        $this->connection->executeQuery($loadDataQuery);
    }

    public function restoreOriginalTableData($originalTable, $tmpTable, $columnsForRestore = [], $pkColumns = ['id'])
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
    protected function restoreFullTableData($originalTable, $tmpTable)
    {
        $columns = $this->getTableColumns($tmpTable);
        $columns = implode(', ', $columns);

        $query = <<<EOF
        INSERT INTO `$originalTable` ($columns)
        SELECT $columns FROM `$tmpTable`
EOF;

        $this->connection->executeQuery($query);
    }

    protected function restoreSelectedColumns($originalTable, $tmpTable, $pkColumns = ['id'], $columnsForRestore)
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

    protected function buildPkConditions($pkColumns)
    {
        $pkConditions = [];

        foreach ($pkColumns as $pkColumn) {
            $pkConditions[] = "o.$pkColumn = t.$pkColumn";
        }

        return implode(', ', $pkConditions);
    }

    protected function getTableColumns($table)
    {
        $fieldsInfo = $this->connection->fetchAll("SHOW COLUMNS FROM `$table`");

        $columns = [];

        foreach ($fieldsInfo as $fieldInfo) {
            $columns[] = $fieldInfo['Field'];
        }

        return $columns;
    }

    protected function buildSetValuesPart($tmpTable, $pkColumns, $columnsForRestore)
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

    protected function buildLoadDataQuery($file, $table)
    {
        return <<<EOF
        LOAD DATA INFILE '$file'
        INTO TABLE `$table`
            FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
            LINES TERMINATED BY '\n'
        ;
EOF;
    }

}