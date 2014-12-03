Пример использования в миграциях:
----------------------
1) Дамп одного поля:
```php
<?php

namespace Application\Migrations;

use Octobird\Mysql\Dumper;
use Octobird\Mysql\Restorer;
use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class VersionXXX extends AbstractMigration
{
    private $backupDir = "/tmp/mysql-backup";
    
    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");

        $dumper = new Dumper($this->connection, $this->backupDir);
        $dumper->dump('full_table_for_restore');
        $dumper->dump('table_with_fields_for_restore', ['id', 'dropField_1', 'dropField_2']);


        $this->addSql("ALTER TABLE table_with_fields_for_restore DROP dropField_1, DROP dropField_2;");
        $this->addSql("DROP TABLE full_table_for_restore;");
    }

    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");
        
        $this->addSql("ALTER TABLE table_with_fields_for_restore ADD dropField_1 VARCHAR( 255 ), dropField_2 VARCHAR( 255 );");
        $this->addSql("CREATE TABLE full_table_for_restore (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL) PRIMARY KEY(id)) ENGINE = InnoDB");
        
        $restorer = new Restorer($this->connection, $this->backupDir);
        $restorer->restore('full_table_for_restore');
        $restorer->restore('table_with_fields_for_restore', ['id', 'dropField_1', 'dropField_2'], ['id']);
    }
}
```

Особое внимание следует обратить на дамп и восстановление выбранных полей, а не таблицы целиком.
Важно кроме поля с данными явно передавать PK (1 поле или несколько, если PK составной), как при дампе, так и при восстановлении данных.



У пользователя mysql, под которым идет соединение с БД должна быть установлена дополнительно привелегии:  FILE, CREATE TEMPORARY TABLES


Для запуска тестов:
-------------------
1) Скопировать phpunit.xml.dist в phpunit.xml
2) Изменить параметры доступа к БД.
3) phpunit -c phpunit.xml src/Tests
