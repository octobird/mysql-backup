<?php

namespace Octobird\Tests\Mysql;

use Octobird\Mysql\Restorer;

class RestorerTest extends BackupTest
{
    /**
     * @var Restorer
     */
    private $restorer;

    public function setUp()
    {
        parent::setUp();

        $this->restorer = new Restorer($this->conn, $this->backupDir);
    }


    public function testRestore_AllTable()
    {
        $this->prepareDumpFiles_AllTable();

        // убиваем все содержимое таблицы и убеждаемся, что она пуста
        $this->conn->exec("TRUNCATE TABLE `{$this->originalTable}`");
        // убеждаемся, что текущее состояние таблицы не равно первоначальному
        $this->assertNotEquals($this->originalData, $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`"));


        $this->restorer->restore($this->originalTable);

        // сравниваем, что было до удаления данных и то, что стало после восстановления данных
        $this->assertEquals($this->originalData, $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`"));
    }


    private function prepareDumpFiles_AllTable()
    {
        $dataFileContent = implode("\n", ["'1','1','need \' escape','1'", "'2','2','2','2'", "'3','3','3','3'", '']);
        $this->fs->dumpFile($this->backupDir . "/{$this->originalTable}-all.txt", $dataFileContent);


        $schemaFileContent = implode(
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

        $this->fs->dumpFile($this->backupDir . "/{$this->tmpTable}-all.txt", $schemaFileContent);
    }


    public function testRestore_SelectedFields()
    {
        $this->prepareDumpFiles_SelectedFields();

        // удаляем содержимое столбцов с данными, которые мы забекапили и проверяем, что они пусты
        $this->conn->exec("UPDATE `{$this->originalTable}` SET param = NULL, value = NULL");

        // убеждаемся, что текущее состояние таблицы не равно первоначальному
        $this->assertNotEquals($this->originalData, $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`"));

        $this->restorer->restore($this->originalTable, ['id', 'param', 'value']);

        // сравниваем, что было до удаления данных и то, что стало после восстановления данных
        $this->assertEquals($this->originalData, $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`"));
    }


    private function prepareDumpFiles_SelectedFields()
    {
        $dataFileContent = implode("\n", ["'1','1','need \' escape'", "'2','2','2'", "'3','3','3'", '']);
        $this->fs->dumpFile($this->backupDir . "/{$this->originalTable}-id-param-value.txt", $dataFileContent);


        $schemaFileContent = implode(
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

        $this->fs->dumpFile($this->backupDir . "/{$this->tmpTable}-id-param-value.txt", $schemaFileContent);
    }

}