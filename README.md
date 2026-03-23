<p align="center">
  <img src="docs/logotipo.png" alt="AnvilDB" width="648">
</p>

<p align="center"><strong>High-performance embedded JSON database for PHP.</strong></p>

<p align="center">Zero external dependencies. No extensions required. Just PHP and raw speed.</p>

---

## Requirements

- PHP >= 8.1

### Optional

- **FFI extension** (`ffi.enable=true` in php.ini) — enables the FFI driver for maximum performance. Without it, the wrapper automatically falls back to the process driver (`anvildb-server` binary via `proc_open`), which works on any PHP installation with no extensions.

## Installation

Add the repository and require the package in your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kevinsillo/anvildb-php"
        }
    ],
    "require": {
        "kevinsillo/anvildb": "v0.6.0"
    }
}
```

Then run:

```bash
composer update
```

The package includes precompiled binaries for all supported platforms. No Rust toolchain needed.

## Quick Start

```php
<?php

use AnvilDb\AnvilDb;

$db = new AnvilDb(__DIR__ . '/data');

$db->createCollection('users');
$users = $db->collection('users');

// Insert — returns document with auto-generated UUID
$user = $users->insert(['name' => 'Kevin', 'role' => 'admin', 'age' => 30]);

// Find, update, delete by ID
$found = $users->find($user['id']);
$users->update($user['id'], ['name' => 'Kevin', 'role' => 'admin', 'age' => 31]);
$users->delete($user['id']);

// Query with filters
$admins = $users->where('role', '=', 'admin')
    ->where('age', '>', 25)
    ->orderBy('name', 'asc')
    ->limit(10)
    ->get();

// Update/delete by filter
$users->where('role', '=', 'viewer')->update(['role' => 'contributor']);
$users->where('age', '<', 18)->delete();

$db->close();
```

## Documentation

- [Examples](docs/examples.md) — practical examples for every feature (queries, joins, aggregations, indexes, schemas, encryption, CSV, buffering)
- [API Reference](docs/api-reference.md) — full API for AnvilDb, Collection, QueryBuilder, Exceptions
- [AnvilDB Core](https://github.com/kevinsillo/anvildb) — main repository with architecture docs and contributing guide

## License

[MIT](https://github.com/kevinsillo/anvildb/blob/main/LICENCE)
