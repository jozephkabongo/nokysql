# NokySQL Documentation (NokySQL by jozeph kabongo)

## Table of Contents
1. [Core Concepts](#core-concepts)
2. [Database Connection](#database-connection)
3. [Query Builder](#query-builder)
4. [Schema Builder](#schema-builder)
5. [Transactions](#transactions)
6. [Parallel Execution](#parallel-execution)
7. [Error Handling](#error-handling)
8. [Database Drivers](#database-drivers)


## Core Concepts 

### Key Components
+-------------------+----------------------------------------------------------+
| Component         | Role                                                     |
|-------------------|----------------------------------------------------------|
| `Database`        | Manages connections and transactions                     |
| `QueryBuilder`    | Builds SQL queries through fluent interface              |
| `SchemaBuilder`   | Handles database schema modifications                    |
| `QueryException`  | Thrown for SQL execution errors (contains SQL/params)    |
+-------------------+----------------------------------------------------------+


## Database Connection 

### Initialization

    ```php
    use NokySQL\Database;

    // MySQL & PostgreSQL
    $db = new Database('mysql', [
        'host'     => 'localhost',
        'database' => 'db_name',
        'user'     => 'root',
        'password' => ''
    ]);

    // SQLite
    $db = new Database('sqlite', [
        'database' => '/path/to/database.sqlite'
    ]);

    ```

### Methods
+-------------------------------------+----------------------------------------------------------+
| Method                              | Description                                              |
|-------------------------------------|----------------------------------------------------------|
| `query(string $sql, array $params)` | Execute raw SQL with parameters                          |
| `beginTransaction()`                | Start transaction                                        |
| `commit()`                          | Commit transaction                                       |
| `rollBack()`                        | Rollback transaction                                     |
| `executeParallel()`                 | Execute queued queries (returns array of results)        |
+-------------------------------------+----------------------------------------------------------+

## Query Builder 

### Chainable Methods
    ```php
    // Basic Structure
    $db->[select|insert|update|delete]('table')
    ->[methods]
    ->execute();
    ```

### Method Reference
+---------------------------------------------------+------------------------------+---------------------------------+
| Method                                            | Parameters                   | Use Case                        |
|---------------------------------------------------|------------------------------|---------------------------------|
| `select(array $fields)`                           | Fields to select (default *) | ->select(['id', 'name'])        |
| `where(string $cond, array $params)`              | WHERE clause                 | ->where('age > ?', [18])        |
| `orderBy(string $col, string $dir)`               | Sorting                      | ->orderBy('created_at', 'DESC') |
| `limit(int $num)`                                 | Max results                  | ->limit(10)                     |
| `set(array $data)`                                | Column-value pairs           | ->set(['name' => 'Alice'])      |
| `join(string $table, string $cond, string $type)` | Table joins | ->join('posts','users.id = posts.user_id','LEFT')|
+---------------------------------------------------+------------------------------+---------------------------------+

## Schema Builder 

### Table Creation
    ```php
    $db->schema()->createTable('users', function($table) {
        $table->id()           // Auto-incrementing ID
            ->string('email', 255)->unique()
            ->integer('age')->nullable()
            ->boolean('is_admin')->default(false)
            ->timestamp('created_at');
    });
    ```

### Column Types
+--------------------------+---------------------+-----------------------------------+
| Method                   | Parameters          | SQL Equivalent                    |
|--------------------------|---------------------|-----------------------------------|
| `id()`                   |          -          | BIGINT PRIMARY KEY AUTO_INCREMENT |
| `string($name, $length)` | Column name, length | VARCHAR(length)                   |
| `integer($name)`         | Column name         | INTEGER                           |
| `boolean($name)`         | Column name         | BOOLEAN                           |
| `timestamp($name)`       | Column name         | TIMESTAMP (DB-specific)           |
| `foreignId($name)`       | Column name         | BIGINT UNSIGNED                   |
+--------------------------+---------------------+-----------------------------------+

## Transactions 

### Complete Example
    ```php
    $db->beginTransaction();
    try {
        // Transfer funds
        $db->update('accounts')
        ->set(['balance' => balance - 100])
        ->where('id = ?', [1])
        ->execute();
        
        $db->update('accounts')
        ->set(['balance' => balance + 100])
        ->where('id = ?', [2])
        ->execute();

        $db->commit();
    } catch (QueryException $e) {
        $db->rollBack();
        echo "Transfer failed: " . $e->getMessage();
    }
    ```
## Parallel Execution 

### Usage Pattern
    ```php
    // Queue queries
    $db->select('products')->where('stock > 0')->queue();
    $db->select('categories')->queue();
    $db->select('users')->where('last_login > ?', ['2024-01-01'])->queue();

    // Execute simultaneously
    [$products, $categories, $users] = $db->executeParallel();
    ```
    Note: Not supported in SQLite - falls back to sequential execution
    
## Error Handling 

### Exception Hierarchy
    Exceptions
    ├── DatabaseException (Connection issues)
    └── QueryException (Query execution errors)

### Debugging Queries
    ```php
    try {
        $db->query("SELECT invalid FROM table");
    } catch (QueryException $e) {
        echo "Failed SQL: " . $e->getSql();
        print_r($e->getParams());
        error_log($e->getTraceAsString());
    }
    ```
## Database Drivers 

### Supported Drivers
+-------------+------------------------------------------------------+--------------------------------+
| Driver      | Connection Example                                   | Notes                          |
|-------------|------------------------------------------------------|--------------------------------|
| MySQL       | `new Database('mysql', $config)`                     | Requires `pdo_mysql` extension |
| PostgreSQL  | `new Database('pgsql', $config)`                     | Requires `pdo_pgsql` extension |
| SQLite      | `new Database('sqlite', ['database' => ':memory:'])` | File path or `:memory:`        |
+-------------+------------------------------------------------------+--------------------------------+
