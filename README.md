# JSON Schema Defaults

[![Coverage Status](https://coveralls.io/repos/github/sokil/php-json-schema-defaults/badge.svg?branch=master)](https://coveralls.io/github/sokil/php-json-schema-defaults?branch=master)
[![ci](https://github.com/sokil/php-json-schema-defaults/actions/workflows/ci.yml/badge.svg)](https://github.com/sokil/php-json-schema-defaults/actions/workflows/ci.yml)

Extracting default value from JSON Schema.

Supports drafts from v.7 or its dialects.
Extractor trusts that passed schema is valid, so perform external validation for it.

## Installation

```
composer req sokil/php-json-schema-defaults
```

## Usage

```php
$schema = '{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "array", 
    "items": {"type": "string", "default": "hello"}
}';

$extractor = new Extractor();
$defaultValue = $extractor->extract($schema); // ["hello"]
```

## Console command

To get default value from schema in console use next bin:

```
./vendor/bin/json-schema-defaults ~/path/to/schema.json
```
