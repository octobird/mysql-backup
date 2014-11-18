<?php

namespace Octobird\Tests\Mysql;

use Octobird\Mysql\Dumper;

class DumperTest extends BackupTest
{
    /**
     * @var Dumper
     */
    private $dumper;

    public function setUp()
    {
        parent::setUp();

        $this->dumper = new Dumper($this->conn, $this->backupDir);
    }


    public function testDump_AllTable()
    {
        $this->dumper->dump($this->originalTable);

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

    public function testDump_SelectedFields()
    {
        // порядок полей умышленно начинается не с id, чтобы проверить что создаваемый порядок полей верный
        $this->dumper->dump($this->originalTable, ['param', 'id', 'value']);

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


}