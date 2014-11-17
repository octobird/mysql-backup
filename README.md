1) У пользователя mysql, под которым идет соединение с БД должна быть установлена дополнительно привелегии:  FILE, CREATE TEMPORARY TABLES


Для запуска тестов:
-------------------
1) Скопировать phpunit.xml.dist в phpunit.xml
2) Изменить параметры доступа к БД.
3) phpunit -c phpunit.xml src/Tests
