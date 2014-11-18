<?php

namespace Octobird\Tests\Mysql;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit_Framework_TestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class BackupTest extends PHPUnit_Framework_TestCase
{
    protected $originalTable = 'main';
    protected $tmpTable = 'main_tmp';

    protected $backupDir;

    protected $originalData = [];


    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var Filesystem
     */
    protected $fs;


    public function setUp()
    {
        $this->backupDir = $GLOBALS['backup_dir'];
        $this->fs = new Filesystem();

        $this->getConnection();
        $this->prepareDb();

        $this->originalData = $this->conn->fetchAll("SELECT * FROM `{$this->originalTable}`");
        $this->assertCount(3, $this->originalData);
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