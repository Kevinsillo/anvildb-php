# Examples

[< Back to README](../README.md)

Practical examples for every feature of the AnvilDB PHP wrapper.

## Setup

```php
<?php

use AnvilDb\AnvilDb;

$db = new AnvilDb(__DIR__ . '/data');
$db->createCollection('users');
$users = $db->collection('users');
```

## CRUD

### Insert

```php
// Single document — returns the document with auto-generated UUID
$user = $users->insert(['name' => 'Alice', 'role' => 'admin', 'age' => 30]);
echo $user['id']; // "550e8400-e29b-41d4-a716-446655440000"

// Bulk insert — more efficient than looping insert()
$docs = $users->bulkInsert([
    ['name' => 'Bob', 'role' => 'editor', 'age' => 25],
    ['name' => 'Carol', 'role' => 'viewer', 'age' => 35],
    ['name' => 'Dave', 'role' => 'editor', 'age' => 28],
]);
```

### Find

```php
$doc = $users->find('550e8400-e29b-41d4-a716-446655440000');

if ($doc !== null) {
    echo $doc['name']; // "Alice"
}
```

### Update (by ID)

```php
// Replaces all fields (except id)
$users->update($user['id'], ['name' => 'Alice', 'role' => 'admin', 'age' => 31]);
```

### Delete (by ID)

```php
$users->delete($user['id']);
```

### Update by filter

```php
// Merge fields into all matching documents (id and other fields are preserved)
$affected = $users->where('role', '=', 'viewer')
    ->update(['role' => 'contributor']);
// $affected = 1

// Multiple conditions
$affected = $users->where('role', '=', 'editor')
    ->where('age', '<', 26)
    ->update(['role' => 'senior_editor']);
```

### Delete by filter

```php
$deleted = $users->where('role', '=', 'viewer')->delete();
// $deleted = number of deleted documents

$deleted = $users->where('age', '<', 18)->delete();
```

## Queries

### Basic filtering

```php
$admins = $users->where('role', '=', 'admin')->get();

$seniors = $users->where('age', '>=', 30)
    ->where('role', '=', 'admin')
    ->get();
```

### Operators

```php
// Equality / comparison
$users->where('name', '=', 'Alice')->get();
$users->where('age', '!=', 30)->get();
$users->where('age', '>', 25)->get();
$users->where('age', '<', 40)->get();
$users->where('age', '>=', 18)->get();
$users->where('age', '<=', 65)->get();

// Contains (substring match)
$users->where('name', 'contains', 'lic')->get();

// Between (inclusive on both ends)
$users->whereBetween('age', 18, 65)->get();

// IN / NOT IN
$users->whereIn('role', ['admin', 'editor'])->get();
$users->whereNotIn('role', ['banned', 'deleted'])->get();
```

### Sorting

```php
$users->where('role', '=', 'admin')
    ->orderBy('name', 'asc')
    ->get();

$users->orderBy('age', 'desc')->get();
```

### Pagination

```php
// First page (20 items)
$page1 = $users->orderBy('name')->limit(20)->get();

// Second page
$page2 = $users->orderBy('name')->offset(20)->limit(20)->get();
```

### Count

```php
$total = $users->count();
$activeCount = $users->where('role', '=', 'admin')->count();
```

### All documents

```php
$everyone = $users->all();
```

## Joins

### Inner join

```php
$db->createCollection('orders');
$orders = $db->collection('orders');

$orders->insert(['user_id' => $user['id'], 'total' => 150.00, 'product' => 'Widget']);

// Joined fields are prefixed to avoid name collisions
$results = $orders
    ->join('users', 'user_id', 'id', 'inner', 'user_')
    ->where('user_name', '=', 'Alice')
    ->orderBy('total', 'desc')
    ->get();

// Each result has: id, user_id, total, product, user_id, user_name, user_role, user_age
```

### Left join

```php
// All users, with or without orders
$results = $users
    ->leftJoin('orders', 'id', 'user_id', 'order_')
    ->get();
```

### Multi-way join

```php
$results = $orders
    ->join('users', 'user_id', 'id', 'inner', 'u_')
    ->join('products', 'product_id', 'id', 'inner', 'p_')
    ->where('u_role', '=', 'admin')
    ->get();
```

## Aggregations

### Simple aggregations

```php
// Returns a single result with computed values
$result = $orders->sum('total', 'total_revenue')->get();
// [['total_revenue' => 4500.00]]

$result = $orders->avg('total', 'average_order')->get();
// [['average_order' => 75.00]]

$result = $orders->min('total', 'cheapest')->get();
$result = $orders->max('total', 'most_expensive')->get();

// Multiple aggregations in one query
$result = $orders
    ->sum('total', 'revenue')
    ->avg('total', 'avg_order')
    ->min('total', 'min_order')
    ->max('total', 'max_order')
    ->get();
```

### Group by

```php
$results = $orders->groupBy('product', [
    ['function' => 'count', 'field' => 'id', 'alias' => 'order_count'],
    ['function' => 'sum', 'field' => 'total', 'alias' => 'revenue'],
    ['function' => 'avg', 'field' => 'total', 'alias' => 'avg_order'],
])->get();
// [
//     ['product' => 'Widget', 'order_count' => 42, 'revenue' => 6300.00, 'avg_order' => 150.00],
//     ['product' => 'Gadget', 'order_count' => 18, 'revenue' => 2700.00, 'avg_order' => 150.00],
// ]

// Group by multiple fields
$results = $orders->groupBy(['product', 'status'], [
    ['function' => 'count', 'field' => 'id', 'alias' => 'total'],
])->get();
```

## Indexes

```php
// Hash index — O(1) lookups for equality (=, !=, in, not_in)
$users->createIndex('role', 'hash');

// Unique index — same as hash but enforces unique values
$users->createIndex('email', 'unique');

// Range index — B-tree for range queries (>, <, >=, <=, between)
$users->createIndex('age', 'range');

// Drop an index
$users->dropIndex('role');
```

## Schema Validation

```php
// All inserts and updates are validated against this schema
$users->setSchema([
    'name'   => 'string',
    'age'    => 'int',
    'score'  => 'float',
    'active' => 'bool',
    'tags'   => 'array',
    'meta'   => 'object',
]);

// This will throw AnvilDbException (age must be int)
$users->insert(['name' => 'Eve', 'age' => 'twenty-five']);
```

## Write Buffering

```php
// Batch writes in memory, flush to disk periodically
$db->configureBuffer(maxDocs: 200, flushIntervalSecs: 10);

// Insert many docs — they accumulate in the buffer
for ($i = 0; $i < 1000; $i++) {
    $users->insert(['name' => "User $i", 'role' => 'user']);
}

// Force flush a single collection
$users->flush();

// Force flush all collections
$db->flush();

// shutdown() flushes everything before closing
$db->shutdown();
```

## Encryption

```php
// Generate a 32-byte key (64 hex characters)
$key = bin2hex(random_bytes(32));

// Encrypt an existing database
$db->encrypt($key);

// Open an encrypted database
$db = new AnvilDb('/path/to/data', $key);

// Remove encryption
$db->decrypt($key);
```

## CSV Import / Export

```php
// Export all documents to CSV
$count = $users->exportCsv('/tmp/users.csv');
echo "Exported $count documents";

// Export specific fields only
$users->exportCsv('/tmp/users_partial.csv', ['name', 'email']);

// Import from CSV (first row = headers, batched in groups of 1000)
$count = $users->importCsv('/tmp/users.csv');
echo "Imported $count documents";
```

## Error Handling

```php
use AnvilDb\Exception\AnvilDbException;

try {
    $users->insert(['name' => 123]); // schema violation
} catch (AnvilDbException $e) {
    echo $e->getMessage();
}
```
