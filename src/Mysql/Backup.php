<?php

namespace Octobird\Mysql;

use Doctrine\DBAL\Connection;
use Octobird\Mysql\Backup\Dumper;
use Octobird\Mysql\Backup\Restorer;

class Backup
{
    protected $backupDir;

    protected $dumper;
    protected $restorer;

    public function __construct(Connection $connection, $backupDir)
    {
        $this->backupDir = $backupDir;

        $this->dumper = new Dumper($connection, $backupDir);
        $this->restorer = new Restorer($connection, $backupDir);
    }

    public function dump($table, $columns = [])
    {
        sort($columns);

        $tmpTable = $this->getTmpTableName($table);
        $dataFile = $this->getFile($table, $columns);
        $schemaFile = $this->getFile($tmpTable, $columns);

        $this->dumper->dumpData($dataFile, $table, $columns);
        $this->dumper->dumpTmpTableSchema($schemaFile, $table, $tmpTable, $columns);
    }


    public function restore($table, $columns = [], $pkColumns = ['id'])
    {
        sort($columns);

        $tmpTable = $this->getTmpTableName($table);
        $dataFile = $this->getFile($table, $columns);
        $schemaFile = $this->getFile($tmpTable, $columns);

        $this->restorer->createTmpTable($schemaFile);
        $this->restorer->loadDataInTmpTable($dataFile, $tmpTable);
        $this->restorer->restoreOriginalTableData($table, $tmpTable, $columns, $pkColumns);
    }

    protected function getFile($table, $columns = [])
    {
        $columns = $this->buildColumnsFileNamePart($columns);

        $file = $this->backupDir . '/' . $table . "-$columns.txt";

        return $file;
    }


    protected function getTmpTableName($table)
    {
        return $table . '_tmp';
    }

    protected function buildColumnsFileNamePart($columns)
    {
        if (empty($columns)) {
            $columns = 'all';
        } else {
            $columns = implode('-', $columns);
        }

        if (strlen($columns) > 200) {
            $columns = md5($columns);
        }

        return $columns;
    }

}