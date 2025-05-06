<?php

    namespace NokySQL;

    use NokySQL\Exceptions\QueryException;
    
    class QueryBuilder {
        private Database $db;
        private string $table;
        private string $type;
        private array $components = [
            'select' => '*',
            'join' => [],
            'where' => [],
            'order' => [],
            'limit' => null,
            'offset' => null,
            'data' => []
        ]; 
        private bool $isQueued = false;

        public function __construct(Database $db, string $table, string $type) {
            $this->db = $db;
            $this->table = $table;
            $this->type = $type;
        }

        public function select(array $columns): self {
            $this->components['select'] = implode(separator: ', ', array: $columns);
            return $this;
        }

        public function join(string $table, string $condition, string $type = 'INNER'): self {
            $this->components['join'][] = "$type JOIN $table ON $condition";
            return $this;
        }

        public function where(string $condition, array $params = []): self {
            $this->components['where'][] = $condition;
            $this->components['params'] = array_merge($this->components['params'] ?? [], $params);
            return $this;
        }

        public function orderBy(string $column, string $direction = 'ASC'): self {
            $this->components['order'][] = "$column $direction";
            return $this;
        }

        public function limit(int $limit): self {
            $this->components['limit'] = $limit;
            return $this;
        }

        public function set(array $data): self {
            $this->components['data'] = $data;
            return $this;
        }

        public function toSql(): array {
            $method = 'build' . ucfirst(string: strtolower(string: $this->type));
            if (!method_exists(object_or_class: $this, method: $method)) {
                throw new QueryException(message: "Unsupported query type", sql: '');
            }
            return $this->$method();
        }

        public function execute(): array|bool {
            $method = 'build' . ucfirst(string: strtolower(string: $this->type));
            if (!method_exists(object_or_class: $this, method: $method)) {
                throw new QueryException(message: "Unsupported query type: {$this->type}", sql: '');
            }

            [$sql, $params] = $this->$method();
            $stmt = $this->db->query(sql: $sql, params: $params);                            

            return $this->type === 'SELECT' ? $stmt->fetchAll() : true;
        }

        public function queue(): self {
            $this->isQueued = true;
            $this->db->addToQueue($this);
            return $this;
        }
    
        public function isQueued(): bool {
            return $this->isQueued;
        }

        public function offset(int $offset): self {
            $this->components['offset'] = $offset;
            return $this;
        }
        
        private function buildOffset(): string {
            if ($this->components['offset'] === null) {
                return '';
            }
        
            return match($this->db->getDriver()) {
                'mysql', 'pgsql' => " OFFSET {$this->components['offset']}",
                'sqlite' => " LIMIT -1 OFFSET {$this->components['offset']}",
                default => ''
            };
        }

        private function buildSelect(): array {
            $sql = "SELECT {$this->components['select']} FROM {$this->table}";

            if (!empty($this->components['join'])) {
                $sql .= ' ' . implode(separator: ' ', array: $this->components['join']);
            }

            if (!empty($this->components['where'])) {
                $sql .= ' WHERE ' . implode(separator: ' AND ', array: $this->components['where']);
            }

            if (!empty($this->components['order'])) {
                $sql .= ' ORDER BY ' . implode(separator: ', ', array: $this->components['order']);
            }

            if ($this->components['limit']) {
                $sql .= " LIMIT {$this->components['limit']}";
            }

            $sql .= $this->buildOffset();
            return [$sql, $this->components['params'] ?? []];
        }

        private function buildInsert(): array {
            $columns = implode(separator: ', ', array: array_keys($this->components['data']));
            $placeholders = implode(separator: ', ', array: array_fill(start_index: 0, count: count(value: $this->components['data']), value: '?'));
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
            return [$sql, array_values(array: $this->components['data'])];
        }

        private function buildUpdate(): array {
            $set = [];
            foreach (array_keys($this->components['data']) as $column) {
                $set[] = "$column = ?";
            }
            $sql = "UPDATE {$this->table} SET " . implode(separator: ', ', array: $set);
            $params = array_values(array: $this->components['data']);

            if (!empty($this->components['where'])) {
                $sql .= ' WHERE ' . implode(separator: ' AND ', array: $this->components['where']);
                $params = array_merge($params, $this->components['params'] ?? []);
            }

            return [$sql, $params];
        }

        private function buildDelete(): array {
            $sql = "DELETE FROM {$this->table}";
            $params = [];

            if (!empty($this->components['where'])) {
                $sql .= ' WHERE ' . implode(separator: ' AND ', array: $this->components['where']);
                $params = $this->components['params'] ?? [];
            }

            return [$sql, $params];
        }
    }
