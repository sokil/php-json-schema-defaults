<?php

declare(strict_types=1);

namespace Sokil\JsonSchema\DefaultValue;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase
{
    public function testInvalidJsonSchema()
    {
        $this->expectExceptionMessage('Argument must be valid JSON Schema passed as array or string');
        $schema = 'hello-im-not-a-schema';

        $extractor = new Extractor();
        $extractor->extract($schema);
    }

    public function testExtractScalarWithoutDefault()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "string"
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertNull($defaultValue);
    }

    public function testExtractScalarWithDefault()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "string", 
            "default": "hello world"
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertSame('hello world', $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testExtractArrayAsListNoDefaults()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "array", 
            "items": {"type": "string"}
            }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertSame([], $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testExtractArrayAsListWithDefaults()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "array", 
            "items": {"type": "string", "default": "hello"}
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertSame(["hello"], $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testExtractArrayAsTupleNoDefaults()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "array", 
            "items": [
                {"type": "string"}, 
                {"type": "number"}
            ]
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertSame([], $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testExtractArrayAsTupleWithDefaults()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "array", 
            "items": [
                {"type": "string", "default": "hello"}, 
                {"type": "number", "default": 42}
            ]
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertSame(["hello", 42], $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testExtractObjectScalarFieldsNoDefaults()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "object", 
            "properties": {
                "hello": {
                    "type": "number"
                }
            }
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertEquals(new \stdClass(), $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testExtractObjectScalarFieldsWithDefaults()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "object", 
            "properties": {
                "hello": {
                    "type": "number",
                    "default": 42
                }
            }
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $expected = new \stdClass();
        $expected->hello = 42;

        $this->assertEquals($expected, $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testExtractObjectComplexFieldsNoDefaults()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "object", 
            "properties": {
                "user": {
                    "type": "object",
                    "properties": {
                        "lastName": {
                            "type": "string"
                        },
                        "firstName": {
                            "type": "string"
                        }
                    }
                }
            }
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $expected = new \stdClass();

        $this->assertEquals($expected, $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testExtractObjectComplexFieldsWithDefaults()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "object", 
            "properties": {
                "user": {
                    "type": "object",
                    "properties": {
                        "lastName": {
                            "type": "string",
                            "default": "Dow"
                        },
                        "firstName": {
                            "type": "string",
                            "default": "John"
                        }
                    }
                }
            }
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $expected = new \stdClass();
        $expected->user = new \stdClass();
        $expected->user->lastName = 'Dow';
        $expected->user->firstName = 'John';

        $this->assertEquals($expected, $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testRef()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "object", 
            "definitions": {
                "user": {
                    "type": "object",
                    "properties": {
                        "lastName": {
                            "type": "string",
                            "default": "Dow"
                        },
                        "firstName": {
                            "type": "string",
                            "default": "John"
                        }
                    }
                }
            },
            "properties": {
                "user": {"$ref": "#/definitions/user"}
            }
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $expected = new \stdClass();
        $expected->user = new \stdClass();
        $expected->user->lastName = 'Dow';
        $expected->user->firstName = 'John';

        $this->assertEquals($expected, $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testUnknownRef()
    {
        $this->expectExceptionMessage('Unknown ref /some-unknown-ref');

        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "object", 
            "definitions": {},
            "properties": {
                "user": {"$ref": "#/definitions/some-unknown-ref"}
            }
        }';

        $extractor = new Extractor();
        $extractor->extract($schema);
    }

    public function testExternalRef()
    {
        $this->expectExceptionMessage('Only local refs supported');

        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "object", 
            "definitions": {},
            "properties": {
                "user": {
                    "$ref": "https://example.com/some-unknown-ref"
                }
            }
        }';

        $extractor = new Extractor();
        $extractor->extract($schema);
    }

    public function testAllOf()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "array", 
            "items": {
                "allOf": [
                    {"type": "string", "default": "hello"}, 
                    {"maxLength": 6}
                ]
            }
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertSame(["hello"], $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testOneOf()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "array", 
            "items": {
                "oneOf": [
                    {"type": "string", "default": "hello"},
                    {"type": "number", "default": "42"}
                ]
            }
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertSame(["hello"], $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    public function testAnyOf()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "array", 
            "items": {
                "anyOf": [
                    {"maxLength": 6},
                    {"type": "string", "default": "hello"}
                ]
            }
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $this->assertSame(["hello"], $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }

    private function assertIsDefaultValueValid(mixed $defaultValue, string $schema)
    {
        $validator = new Validator();

        $result = $validator->validate($defaultValue, $schema);

        if (!$result->isValid()) {
            var_dump(
                $result->error()->message(),
                $result->error()->args()
            );
        }

        $this->assertTrue($result->isValid());
    }

    public function testEmptyRequiredArray()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "title": "Advert placement configuration",
            "description": "Definitions of placements and related VAST document URLs",
            "type": "object",
            "properties": {
                "placements": {
                    "title": "Placements",
                    "type": "array",
                    "items": {
                        "type": "object",
                        "properties": {
                            "name": {
                                "type": "string",
                                "pattern": "[a-zA-Z0-9]+",
                                "title": "Placement name"
                            },
                            "vast": {
                                "type": "string",
                                "format": "url",
                                "title": "VAST URL"
                            }
                        },
                        "required": [
                            "name",
                            "vast"
                        ]
                    }
                }
            },
            "required": [
                "placements"
            ]
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $expectedDefault = new \stdClass();
        $expectedDefault->placements = [];

        $this->assertEquals($expectedDefault, $defaultValue);
    }

    public function testComplexWithRequired()
    {
        $schema = '{
            "$schema": "http://json-schema.org/draft-07/schema#",
            "title": "Authentication",
            "description": "Configuration of authentication methods and other parameters for application",
            "type": "object",
            "additionalProperties": false,
            "definitions": {
                "AuthenticationMethod": {
                    "type": "object",
                    "additionalProperties": false,
                    "properties": {
                        "enabled": {
                            "title": "Enabled",
                            "type": "boolean",
                            "default": false
                        }
                    },
                    "required": [
                        "enabled"
                    ]
                }
            },
            "properties": {
                "authenticationMethods": {
                    "title": "Authentication methods",
                    "description": "Configuration of authentication methods",
                    "type": "object",
                    "additionalProperties": false,
                    "properties": {
                        "email": {
                            "title": "Email and password",
                            "description": "Authentication by email and password",
                            "$ref": "#/definitions/AuthenticationMethod"
                        },
                        "google": {
                            "title": "Google OAuth 2.0",
                            "description": "Authentication by Google OAuth 2.0",
                            "$ref": "#/definitions/AuthenticationMethod"
                        },
                        "apple": {
                            "title": "Apple OAuth 2.0",
                            "description": "Authentication by Apple OAuth 2.0",
                            "$ref": "#/definitions/AuthenticationMethod"
                        }
                    },
                    "required": [
                        "apple",
                        "email",
                        "google"
                    ]
                }
            }
        }';

        $extractor = new Extractor();
        $defaultValue = $extractor->extract($schema);

        $expected = new \stdClass();
        $expected->authenticationMethods = new \stdClass();
        $expected->authenticationMethods->email = new \stdClass();
        $expected->authenticationMethods->email->enabled = false;
        $expected->authenticationMethods->google = new \stdClass();
        $expected->authenticationMethods->google->enabled = false;
        $expected->authenticationMethods->apple = new \stdClass();
        $expected->authenticationMethods->apple->enabled = false;

        $this->assertEquals($expected, $defaultValue);
        $this->assertIsDefaultValueValid($defaultValue, $schema);
    }
}
