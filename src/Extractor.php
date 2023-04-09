<?php

declare(strict_types=1);

namespace Sokil\JsonSchema\DefaultValue;

/**
 * Supports drafts from v.7 or its dialects
 * Extractor trusts that passed schema is valid, so perform external validation for it
 */
class Extractor
{
    public function extract(
        string|array $schema,
        string $definitionsFieldName = 'definitions'
    ): mixed {
        if (empty($schema)) {
            throw new \InvalidArgumentException('Argument must be valid JSON Schema passed as array or string');
        }

        if (is_string($schema)) {
            $schemaArray = \json_decode($schema, true, \JSON_THROW_ON_ERROR);
            if (empty($schemaArray) || !is_array($schemaArray)) {
                throw new \InvalidArgumentException('Argument must be valid JSON Schema passed as array or string');
            }

            $schema = $schemaArray;
        }


        $definitions = (array) ($schema[$definitionsFieldName] ?? []);

        return $this->getDefaults($schema, $definitions);
    }

    private function getDefaults(array $schemaElement, array $definitions): mixed
    {
        if (array_key_exists('default', $schemaElement)) {
            return $schemaElement['default'];
        } elseif (!empty($schemaElement['allOf'])) {
            $mergedItem = $this->mergeAllOf($schemaElement['allOf'], $definitions);
            return $this->getDefaults($mergedItem, $definitions);
        } elseif (!empty($schemaElement['oneOf']) || !empty($schemaElement['anyOf'])) {
            // find first with default
            $chooses = !empty($schemaElement['oneOf']) ? $schemaElement['oneOf'] : $schemaElement['anyOf'];
            foreach ($chooses as $choose) {
                $default = $this->getDefaults($choose, $definitions);
                if (!empty($default)) {
                    return $default;
                }
            }
            return null;
        } elseif (!empty($schemaElement['$ref'])) {
            $reference = $this->getDefinitionByLocalRef($schemaElement['$ref'], $definitions);
            return $this->getDefaults($reference, $definitions);
        } elseif (!empty($schemaElement['type'])) {
            if ($schemaElement['type'] === 'object') {
                if (empty($schemaElement['properties'])) {
                    return new \stdClass();
                }

                $propertiesDefaults = new \stdClass();
                foreach ($schemaElement['properties'] as $propertyName => $property) {
                    $propertyDefaults = $this->getDefaults($property, $definitions);
                    if ($propertyDefaults !== null) {
                        if (!$propertyDefaults instanceof \stdClass || count(get_object_vars($propertyDefaults)) > 0) {
                            $propertiesDefaults->{$propertyName} = $propertyDefaults;
                        }
                    }
                }

                return $propertiesDefaults;
            } elseif ($schemaElement['type'] === 'array') {
                if (empty($schemaElement['items'])) {
                    return [];
                }

                $minItemsCount = $schemaElement['minItems'] ?? 0;

                // Tuple validation: a sequence of fixed length where each item may have a different schema
                // Example: {"items": [{"type": string}, {"type": "int"}]}
                if (!empty($schemaElement['items'][0])) {
                    $values = array_map(
                        fn ($item) => $this->getDefaults($item, $definitions),
                        $schemaElement['items']
                    );

                    // preserver at least {minItems} default values
                    for ($i = count($values) - 1; $i >= 0; $i--) {
                        if (!empty($values[$i])) {
                            break;
                        } elseif ($minItemsCount > 0 && $i <= $minItemsCount - 1) {
                            break;
                        } else {
                            array_pop($values);
                        }
                    }

                    return $values;
                } else {
                    // List validation: a sequence of arbitrary length where each item matches the same schema.
                    // Example: {"items": {"type": string}}
                    $value = $this->getDefaults($schemaElement['items'], $definitions);

                    if (empty($value) || ($value instanceof \stdClass && count(get_object_vars($value)) === 0)) {
                        return [];
                    } else {
                        return array_fill(0, max(1, $minItemsCount), $value);
                    }
                }
            } elseif ($schemaElement['type'] === 'string') {
                return null;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    private function mergeAllOf(array $allOfList, $definitions): array
    {
        $length = count($allOfList);
        $index = -1;
        $result = [];

        while (++$index < $length) {
            $item = $allOfList[$index];
            $item = !empty($item['$ref']) ? $this->getDefinitionByLocalRef($item['$ref'], $definitions) : $item;
            $result = array_merge($result, $item);
        }

        return $result;
    }

    private function getDefinitionByLocalRef(string $refValue, array $definitions): array
    {
        if (substr($refValue, 0, 2) !== '#/') {
            throw new \Exception('Only local refs supported');
        }

        $refChunks = explode(
            '/',
            \preg_replace('/^#\/.+\//', '', $refValue)
        );

        $definition = $definitions;
        $fullRef = '';

        foreach ($refChunks as $refChunk) {
            $fullRef .= '/' . $refChunk;

            if (empty($definition[$refChunk])) {
                throw new \Exception(sprintf('Unknown ref %s', $fullRef));
            }

            $definition = &$definition[$refChunk];
        }

        return $definition;
    }
}
