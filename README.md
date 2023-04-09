# JSON Schema Defaults

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
