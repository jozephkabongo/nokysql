<?php

    namespace NokySQL\Exceptions;

    use NokySQL\Exceptions\DatabaseException;

    class QueryException extends DatabaseException {
        private string $sql;
        private array $params;
        public function __construct(
            string $message,
            string $sql,
            array $params = [],
            \Throwable $previous = null
        )   {
                parent::__construct(message: $message, code: 0, previous: $previous);
                $this->sql = $sql;
                $this->params = $params;
            }

        public function getDebugInfo(): array {
            return [
                'sql' => $this->sql,
                'params' => $this->params,
                'trace' => $this->getTrace()
            ];
        }

        public function getSql(): string {
            return $this->sql;
        }

        public function getParams(): array {
            return $this->params;
        }
    }
