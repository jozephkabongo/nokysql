<?php

    use PHPUnit\Framework\TestCase;
    use NokySQL\Database;
    use NokySQL\Exceptions\DatabaseException;

    class DatabaseTest extends TestCase {

        public function testConnection(): void {
            $db = new Database(driver: 'sqlite', config: ['database' => ':memory:']);
            $this->assertInstanceOf(PDO::class, $db->getPdo());
        }
        public function testSqliteConnection(): void {
            $db = new Database(driver: 'sqlite', config: ['database' => ':memory:']);
            $this->assertInstanceOf(PDO::class, $db->getPdo());
        }

        public function testTableCreation(): void {
            $db = new Database(driver: 'sqlite', config: ['database' => ':memory:']);
            
            $db->schema()->createTable(table: 'test', blueprint: function($table): void {
                $table->id()
                    ->string('title');
            });

            $result = $db->query(sql: "SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
            $this->assertEquals('test', $result[0]['name']);
        }

        public function testInvalidDriver(): void {
            $this->expectException(DatabaseException::class);
            new Database(driver: 'oracle', config: []);
        }
    }