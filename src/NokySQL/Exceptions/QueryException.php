<?php

    namespace NokySQL\Exceptions;

    use NokySQL\Exceptions\DatabaseException;

    class QueryException extends DatabaseException {
        public function __construct(
            string $message,
            private string $sql,
            private array $params = []
        )   {
                parent::__construct(message: "$message [SQL: $sql]");
            }

        public function getDebugInfo(): array {
            return [
                'sql' => $this->sql,
                'params' => $this->params,
                'trace' => $this->getTrace()
            ];
        }
    }