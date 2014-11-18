<?php

namespace Octobird\Tests\Mysql;

use Octobird\Mysql\NamerHelper;
use PHPUnit_Framework_TestCase;

class NamerHelperTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getFile_Provider
     */
    public function testGetFile($columns, $expectedFileNameColumnPart)
    {
        $backupDir = '/tmp/backups';
        $table = 'test';

        $this->assertEquals('/tmp/backups/test-' . $expectedFileNameColumnPart . '.txt', NamerHelper::getFile($backupDir, $table, $columns));
    }

    public function testGetTmpTableName()
    {
        $this->assertEquals('test_table_tmp', NamerHelper::getTmpTableName('test_table'));
    }

    public function getFile_Provider()
    {
        return [
            // все поля
            [
                [],
                'all',
            ],
            // поля по порядку
            [
                ['id', 'name', 'params', 'value'],
                'id-name-params-value',
            ],
            // меняется порядок полей при сортировке
            [
                ['name', 'value', 'params', 'id'],
                'id-name-params-value',
            ],
            // Очень много полей, вынужнены использовать md5, чтобы не превысить допустимую длинную на имя файла
            [
                [
                    'id', 'campaign_id', 'user_id', 'state', 'name', 'url', 'type', 'title', 'about', 'html', 'currency',
                    'price', 'is_alarm', 'is_subs', 'note', 'is_rotate', 'alt_sources_config', 'created_at',
                    'updated_at', 'image_216x36', 'image_300x50', 'image_468x60', 'other-field1', 'other-field2',
                ],
                'dadce1517cbd0ebdb6c32712cba9a3a0',
            ],

        ];
    }
}
 