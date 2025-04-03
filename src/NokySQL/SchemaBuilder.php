<?php

namespace NokySQL;

class SchemaBuilder {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function createTable(string $table, \Closure $blueprint): self {
        $schema = new TableSchema(table: $table, driver: $this->db->getDriver());
        $blueprint($schema);

        $columns = [];
        foreach ($schema->getColumns() as $column) {
            $columns[] = $column->build();
        }

        $sql = "CREATE TABLE $table (\n" . implode(separator: ",\n", array: $columns) . "\n)";
        
        if ($this->db->getDriver() === 'mysql') {
            $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        $this->db->query(sql: $sql);
        return $this;
    }

    public function dropTable(string $table): self {
        $this->db->query(sql: "DROP TABLE IF EXISTS $table");
        return $this;
    }
}

class TableSchema {
    private array $columns = [];
    private string $driver;

    public function __construct(private string $table, string $driver) {
        $this->driver = $driver;
    }

    public function id(string $name = 'id'): self {
        $type = match($this->driver) {
            'pgsql' => 'SERIAL',
            default => 'INTEGER'
        };

        $this->columns[] = "$name $type PRIMARY KEY" . 
            ($this->driver === 'mysql' ? ' AUTO_INCREMENT' : '');

        return $this;
    }

    public function string(string $name, int $length = 255): self {
        $this->columns[] = "$name VARCHAR($length)";
        return $this;
    }

    public function integer(string $name): self {
        $this->columns[] = "$name INTEGER";
        return $this;
    }

    public function boolean(string $name): self {
        $this->columns[] = "$name BOOLEAN";
        return $this;
    }

    public function timestamp(string $name): self {
        $type = match($this->driver) {
            'pgsql' => 'TIMESTAMP',
            default => 'DATETIME'
        };
        $this->columns[] = "$name $type";
        return $this;
    }

    public function getColumns(): array {
        return $this->columns;
    }
}