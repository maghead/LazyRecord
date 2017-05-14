<?php

namespace Maghead\Schema;

use CLIFramework\Logger;
use Maghead\Runtime\Config\Config;
use Maghead\Schema\Loader\FileSchemaLoader;
use ReflectionObject;
use ReflectionClass;

class SchemaUtils
{
    /*
    static public function find_schema_parents(array $classes)
    {
        $parents = [];
        foreach ($classes as $class) {
            $schema = new $class; // declare schema
            foreach ($schema->relations as $relKey => $rel ) {
                if (!isset($rel['foreign_schema'])) {
                    continue;
                }
                $foreignClass = ltrim($rel['foreign_schema'],'\\');
                $schema = new $foreignClass;
                if ($rel->type == Relationship::BELONGS_TO) {
                    $parents[$class][] = $foreignClass;
                } else if ($rel->type == Relationship::HAS_ONE || $rel->type == Relationship::HAS_MANY) {
                    $parents[$foreignClass][] = $class;
                }
            }
        }
        return $parents;
    }
    */




    /**
     * Get referenced schema classes and put them in order.
     *
     * @param string[] schema objects
     */
    public static function expandSchemas(SchemaCollection $collection)
    {
        $map = [];
        $schemas = [];
        foreach ($collection->evaluate() as $schema) {

            // expand reference
            if ($refs = $schema->getReferenceSchemas()) {
                foreach ($refs as $refClass => $v) {
                    if (isset($map[$refClass])) {
                        continue;
                    }
                    $schemas[] = new $refClass();
                    $map[$refClass] = true;
                }
            }

            if ($schema instanceof TemplateSchema) {
                $expandedSchemas = $schema->provideSchemas();
                foreach ($expandedSchemas as $expandedSchema) {
                    if (isset($map[get_class($expandedSchema)])) {
                        continue;
                    }
                    $schemas[] = $expandedSchema;
                    $map[get_class($expandedSchema)] = true;
                }
            } else {
                $class = get_class($schema);
                if (isset($map[$class])) {
                    continue;
                }
                $schemas[] = $schema;
                $map[$class] = true;
            }
        }

        return $schemas;
    }

    public static function findSchemasByPaths(array $paths = null)
    {
        if ($paths && !empty($paths)) {
            $loader = new FileSchemaLoader($paths);
            $loadedFiles = $loader->load();
        }

        return SchemaLoader::loadDeclaredSchemas();
    }

    /**
     * Use the given config object to load schema files.
     *
     * @param Config       $config
     */
    public static function findSchemasByConfig(Config $config)
    {
        return self::findSchemasByPaths($config->getSchemaPaths());
    }

    public static function argumentsToSchemaObjects(array $args)
    {
        if (empty($args)) {
            return SchemaCollection::declared()->buildable()->evaluate();
        }
        return SchemaCollection::create($args)->exists()->unique()->buildable()->evaluate();
    }

    /**
     * Given a list of schema object,
     * return the schema objects that are defined with shard mapping
     */
    public static function filterShardMappingSchemas($mappingId, array $schemas)
    {
        $mappingIds = (array) $mappingId;
        return array_filter($schemas, function (DeclareSchema $s) use ($mappingIds) {
            return in_array($s->shardMapping, $mappingIds);
        });
    }
}
