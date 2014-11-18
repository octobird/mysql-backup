<?php

namespace Octobird\Mysql;

class NamerHelper
{
    static public function getFile($backupDir, $table, $columns = [])
    {
        $columns = self::buildColumnsFileNamePart($columns);

        $file = $backupDir . '/' . $table . "-$columns.txt";

        return $file;
    }

    static public function getTmpTableName($table)
    {
        return $table . '_tmp';
    }

    static public function buildColumnsFileNamePart($columns)
    {
        if (empty($columns)) {
            $columns = 'all';
        } else {
            sort($columns);
            $columns = implode('-', $columns);
        }

        if (strlen($columns) > 200) {
            $columns = md5($columns);
        }

        return $columns;
    }
} 