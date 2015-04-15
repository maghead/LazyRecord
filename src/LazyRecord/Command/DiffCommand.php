<?php
namespace LazyRecord\Command;
use Exception;
use ReflectionClass;
use ReflectionObject;
use CLIFramework\Command;
use LazyRecord\Schema;
use LazyRecord\Command\BaseCommand;
use LazyRecord\Schema\SchemaFinder;
use LazyRecord\Schema\SchemaDeclare;
use LazyRecord\Schema\SchemaUtils;
use LazyRecord\ConfigLoader;
use LazyRecord\TableParser\TableParser;
use LazyRecord\Schema\Comparator;
use LazyRecord\Schema\Comparator\ConsolePrinter as ComparatorConsolePrinter;

class DiffCommand extends BaseCommand
{

    public function brief()
    {
        return 'diff database schema.';
    }

    public function execute()
    {
        $formatter = new \CLIFramework\Formatter;
        $options = $this->options;
        $logger = $this->logger;

        $connectionManager = \LazyRecord\ConnectionManager::getInstance();

        // XXX: from config files
        $dsId = $this->getCurrentDataSourceId();
        
        $conn = $connectionManager->getConnection($dsId);
        $driver = $connectionManager->getQueryDriver($dsId);

        $this->logger->info('Comparing...');


        // XXX: currently only mysql support
        $parser = TableParser::create( $driver, $conn );
        $tables = $parser->getTables();
        $tableSchemas = $parser->getTableSchemaMap();

        $found = false;
        $comparator = new Comparator;
        foreach ($tableSchemas as $t => $b) {
            $class = $b->getModelClass();
            $ref = new ReflectionClass($class);

            $filepath = $ref->getFilename();
            $filepath = substr($filepath,strlen(getcwd()) + 1);

            if (in_array($t, $tables)) {
                $before = $parser->reverseTableSchema($t);
                $diff = $comparator->compare($before, $b );
                if (count($diff)) {
                    $found = true;
                }
                $printer = new ComparatorConsolePrinter($diff);
                $printer->beforeName = $t . ":data source [$dsId]";
                $printer->afterName = $t . ':' . $filepath ;
                $printer->output();
            }
            else {
                $msg = sprintf("+ table %-20s %s", "'" . $t . "'" ,$filepath);
                echo $formatter->format( $msg,'green') , "\n";

                $a = isset($tableSchemas[ $t ]) ? $tableSchemas[ $t ] : null;
                $diff = $comparator->compare(new SchemaDeclare, $b);
                foreach( $diff as $diffItem ) {
                    echo "  ", $diffItem->toColumnAttrsString() , "\n";
                }



                $found = true;
            }
        }

        if( false === $found ) {
            $this->logger->info("No diff found");
        }
    }
}

