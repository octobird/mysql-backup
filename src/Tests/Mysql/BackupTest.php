<?php

namespace Octobird\Tests\Mysql;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Octobird\Mysql\Backup;
use PHPUnit_Framework_TestCase;

class BackupTest extends PHPUnit_Framework_TestCase
{
    protected $originalTable = 'main';
    protected $tmpTable = 'main_tmp';

    protected $backupDir;

    protected $originalData = [];

    /**
     * @var Backup
     */
    protected $backup;

    /**
     * @var Connection
     */
    protected $conn;


    public function setUp()
    {
        $this->backupDir = $GLOBALS['backup_dir'];

        $this->getConnection();
        $this->getBackupObject();

        $this->prepareDb();

        $this->originalData = $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`");
        $this->assertCount(3, $this->originalData);
    }

    public function testBackupTable()
    {
        $this->dumpTable();

        // убиваем все содержимое таблицы и убеждаемся, что она пуста
        $this->conn->exec("TRUNCATE TABLE `{$this->originalTable}`");
        // убеждаемся, что текущее состояние таблицы не равно первоначальному
        $this->assertNotEquals($this->originalData, $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`"));

        $this->restoreTable();
    }

    protected function dumpTable()
    {
        $this->backup->dump($this->originalTable);

        $expectedDataFile = $this->backupDir . "/{$this->originalTable}-all.txt";
        $expectedDataFileContent = implode("\n", ["'1','1','need \' escape','1'", "'2','2','2','2'", "'3','3','3','3'", '']);

        $this->assertFileExists($expectedDataFile);
        $this->assertStringEqualsFile($expectedDataFile, $expectedDataFileContent);


        $expectedSchemaFile = $this->backupDir . "/{$this->tmpTable}-all.txt";
        $expectedSchemaFileContent = implode(
            "\n", [
                "DROP TABLE IF EXISTS `{$this->tmpTable}`;",
                "CREATE TEMPORARY TABLE `{$this->tmpTable}` (",
                "  `id` int(11) NOT NULL,",
                "  `name` varchar(255) NOT NULL,",
                "  `value` text NOT NULL,",
                "  `param` int(11) NOT NULL,",
                "  PRIMARY KEY (`id`)",
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8",
            ]
        );

        $this->assertFileExists($expectedSchemaFile);
        $this->assertStringEqualsFile($expectedSchemaFile, $expectedSchemaFileContent);
    }

    protected function restoreTable()
    {
        $this->backup->restore($this->originalTable);

        // сравниваем, что было до удаления данных и то, что стало после восстановления данных
        $this->assertEquals($this->originalData, $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`"));
    }

    public function testBackupSelectedFields()
    {
        $this->dumpSelectedFields();

        // удаляем содержимое столбцов с данными, которые мы забекапили и проверяем, что они пусты
        $this->conn->exec("UPDATE `{$this->originalTable}` SET param = NULL, value = NULL");

        // убеждаемся, что текущее состояние таблицы не равно первоначальному
        $this->assertNotEquals($this->originalData, $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`"));

        $this->restoreSelectedFields();
    }


    protected function dumpSelectedFields()
    {
        // порядок полей умышленно начинается не с id, чтобы проверить что создаваемый порядок полей верный
        $this->backup->dump($this->originalTable, ['param', 'id', 'value']);

        $expectedFile = $this->backupDir . "/{$this->originalTable}-id-param-value.txt";
        $expectedFileContent = implode("\n", ["'1','1','need \' escape'", "'2','2','2'", "'3','3','3'", '']);

        $this->assertFileExists($expectedFile);
        $this->assertStringEqualsFile($expectedFile, $expectedFileContent);

        $expectedSchemaFile = $this->backupDir . "/{$this->tmpTable}-id-param-value.txt";
        $expectedSchemaFileContent = implode(
            "\n", [
                "DROP TABLE IF EXISTS `{$this->tmpTable}`;",
                "CREATE TEMPORARY TABLE `{$this->tmpTable}` (",
                "  `id` int(11) NOT NULL,",
                "  `param` int(11) NOT NULL,",
                "  `value` text NOT NULL,",
                "  PRIMARY KEY (`id`)",
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8",
            ]
        );

        $this->assertFileExists($expectedSchemaFile);
        $this->assertStringEqualsFile($expectedSchemaFile, $expectedSchemaFileContent);
    }


    protected function restoreSelectedFields()
    {
        $this->backup->restore($this->originalTable, ['id', 'param', 'value']);

        // сравниваем, что было до удаления данных и то, что стало после восстановления данных
        $this->assertEquals($this->originalData, $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`"));
    }

    protected function getBackupObject()
    {
        if (!$this->backup) {
            $this->backup = new Backup($this->getConnection(), $this->backupDir);
        }

        return $this->backup;
    }

    /**
     * @return Connection
     */
    protected function getConnection()
    {
        if (!$this->conn) {
            $connectionParams = [
                'driver' => $GLOBALS['db_type'],
                'dbname' => $GLOBALS['db_name'],
                'user' => $GLOBALS['db_username'],
                'password' => $GLOBALS['db_password'],
                'host' => $GLOBALS['db_host'],
            ];

            $this->conn = DriverManager::getConnection($connectionParams, new Configuration());
        }

        return $this->conn;
    }

    protected function prepareDb()
    {
        $this->conn->executeQuery("DROP TABLE IF EXISTS `{$this->originalTable}`");

        $query = <<<EOF
        CREATE TABLE `{$this->originalTable}` (
        id int(11) NOT NULL,
        name varchar(255) NOT NULL,
        value text NOT NULL,
        param int(11) NOT NULL,
        PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
EOF;

        $this->conn->executeQuery($query);


        $query = <<<EOF
        INSERT INTO `{$this->originalTable}`
            (id, name, value, param)
        VALUES
            (1, '1', "need ' escape", 1),
            (2, '2', '2', 2),
            (3, '3', '3', 3)
EOF;

        $this->conn->executeQuery($query);
    }
}