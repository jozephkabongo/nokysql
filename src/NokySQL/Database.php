<?php

    namespace NokySQL;

    use PDO;
    use NokySQL\Exceptions\DatabaseException;
    use NokySQL\Exceptions\QueryException;

    class Database {
        private PDO $pdo;
        private string $driver;
        private array $queries = [];

        public function __construct(string $driver, array $config) {
            $this->driver = $driver;
            $this->validateConfig(driver: $driver, config: $config);
            $this->pdo = $this->createConnection(config: $config);
        }

        private function validateConfig(string $driver, array $config): void {
            $required = match ($driver) {
                'sqlite' => ['database'],
                'mysql', 'pgsql' => ['host', 'database', 'user', 'password'],
                default => throw new DatabaseException(message: "Unsupported driver: $driver")
            };

            foreach ($required as $key) {
                if (!isset($config[$key])) {
                    throw new DatabaseException(message: "Missing config key: $key");
                }
            }
        }

        private function createConnection(array $config): PDO {
            try {
                $dsn = $this->buildDsn(config: $config);
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];

                return new PDO(dsn: $dsn, username: $config['user'] ?? null, password: $config['password'] ?? null, options: $options);
            } catch (\PDOException $e) {
                throw new DatabaseException(message: "Connection failed: " . $e->getMessage());
            }
        }

        private function buildDsn(array $config): string {
            return match($this->driver) {
                'mysql' => "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
                'pgsql' => "pgsql:host={$config['host']};dbname={$config['database']}",
                'sqlite' => "sqlite:{$config['database']}",
                default => throw new DatabaseException(message: "Unsupported driver: {$this->driver}")
            };
        }

        public function query(string $sql, array $params = []): \PDOStatement {
            try {
                $stmt = $this->pdo->prepare(query: $sql);
                $stmt->execute(params: $params);
                return $stmt;
            } catch (\PDOException $e) {
                throw new QueryException(message: $e->getMessage(), sql: $sql, params: $params);
            }
        }

        // Transactions
        public function beginTransaction(): bool {
            return $this->pdo->beginTransaction();
        }

        public function commit(): bool {
            return $this->pdo->commit();
        }

        public function rollBack(): bool {
            return $this->pdo->rollBack();
        }

        // Query Builder
        public function select(string $table): QueryBuilder {
            return new QueryBuilder(db: $this, table: $table, type: 'SELECT');
        }

        public function insert(string $table): QueryBuilder {
            return new QueryBuilder(db: $this, table: $table, type: 'INSERT');
        }

        public function update(string $table): QueryBuilder {
            return new QueryBuilder(db: $this, table: $table, type: 'UPDATE');
        }

        public function delete(string $table): QueryBuilder {
            return new QueryBuilder(db: $this, table: $table, type: 'DELETE');
        }

        // Schema Builder
        public function schema(): SchemaBuilder {
            return new SchemaBuilder(db: $this);
        }

        public function getDriver(): string {
            return $this->driver;
        }

        public function getPdo(): PDO {
            return $this->pdo;
        }
    }