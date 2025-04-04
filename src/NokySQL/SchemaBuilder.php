<?php

namespace NokySQL;
use \Closure;

class SchemaBuilder {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function build(string $table, Closure $blueprint): string {
        $schema = new TableSchema($table, $this->db->getDriver());
        $blueprint($schema);

        $columns = $schema->getColumns();
        $constraints = $schema->getConstraints();

        $sql = "CREATE TABLE $table (\n"
             . implode(separator: ",\n", array: $columns);

        if (!empty($constraints)) {
            $sql .= ",\n" . implode(separator: ",\n", array: $constraints);
        }

        $sql .= "\n)";

        switch ($this->db->getDriver()) {
            case 'mysql':
                return $sql . " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            case 'pgsql':
                return $sql . " WITH (OIDS=FALSE)";
            default:
                return $sql;
        }
    }

    public function createTable(string $table, Closure $blueprint): self {
        $sql = $this->build(table: $table, blueprint: $blueprint);
        $this->db->query(sql: $sql);
        return $this;
    }

    public function dropTable(string $table): self {
        $this->db->query(sql: "DROP TABLE IF EXISTS $table");
        return $this;
    }
}

class TableSchema {
    private string $driver;
    private array $columns = [];
    private ?string $currentColumn = null;
    private array $constraints = [];
    

    public function __construct(private string $table, string $driver) {
        $this->driver = $driver;
    }

    public function id(string $name = 'id'): self {
        $this->currentColumn = match($this->driver) {
            'mysql' => "$name INT AUTO_INCREMENT PRIMARY KEY",
            'pgsql' => "$name SERIAL PRIMARY KEY",
            'sqlite' => "$name INTEGER PRIMARY KEY AUTOINCREMENT"
        };
        $this->columns[] = $this->currentColumn;
        return $this;
    }

    public function string(string $name, int $length = 255): self {
        $this->currentColumn = "$name VARCHAR($length)";
        $this->columns[] = $this->currentColumn;
        return $this;
    }

    public function integer(string $name): self {
        $this->currentColumn = "$name INT NOT NULL";
        $this->columns[] = $this->currentColumn;
        return $this;
    }

    public function boolean(string $name): self {
        $this->currentColumn = "$name BOOLEAN NOT NULL";
        $this->columns[] = $this->currentColumn;
        return $this;
    }

    public function unique(): self {
        if (empty($this->columns)) {
            throw new \RuntimeException(message: "Cannot set unique constraint without column");
        }
        $this->columns[count(value: $this->columns)-1] .= " UNIQUE";
        return $this;
    }

    public function addUniqueConstraint(array $columns): self {
        $this->constraints[] = 'UNIQUE (' . implode(separator: ', ', array: $columns) . ')';
        return $this;
    }
    public function nullable(): self {
        $this->columns[count(value: $this->columns)-1] = str_replace(
            search: 'NOT NULL', 
            replace: 'NULL', 
            subject: $this->columns[count(value: $this->columns)-1]
        );
        return $this;
    }

    public function default($value): self {
        $value = is_string(value: $value) ? "'".addslashes(string: $value)."'" : $value;
        $this->columns[count(value: $this->columns)-1] .= " DEFAULT $value";
        return $this;
    }

    public function required(): self {
        $colIndex = count($this->columns) - 1;
        $current = $this->columns[$colIndex];
        $current = preg_replace(pattern: '/\s(NULL|NOT NULL)/', replacement: '', subject: $current);
        $this->columns[$colIndex] = $current . ' NOT NULL';
        
        return $this;
    }

    public function timestamp(string $name): self {
        $this->currentColumn = "$name TIMESTAMP NULL";
        $this->columns[] = $this->currentColumn;
        return $this;
    }

    public function timestamps(): self {
        $driver = $this->driver;
        $this->timestamp('created_at')->nullable();
        $updatedAtDefault = match($driver) {
            'mysql' => 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'pgsql' => 'DEFAULT CURRENT_TIMESTAMP',
            'sqlite' => 'DEFAULT CURRENT_TIMESTAMP',
            default => ''
        };
        $this->columns[] = "updated_at TIMESTAMP NULL $updatedAtDefault";
        return $this;
    }

    public function getColumns(): array {
        return $this->columns;
    }
    
    public function getConstraints(): array {
        return $this->constraints;
    }
}
