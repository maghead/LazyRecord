<?php
namespace Lazy\SchemaSqlBuilder;
use Lazy\Schema\SchemaDeclare;
use Lazy\QueryDriver;

class MysqlDriver
	implements DriverInterface
{

    function buildColumnSql($schema, $column) {      
		$name = $column->name;
		$isa  = $column->isa ?: 'str';
		$type = $column->type;
		if( ! $type && $isa == 'str' )
			$type = 'text';

		$sql  = $name;
		$sql .= ' ' . $type;

        if( $column->required )
            $sql .= " not null";

		/* if it's callable, we should not write the result into sql schema */
		if( ($default = $column->default) !== null && ! is_callable($column->default )  ) { 

            // raw sql default value
			if( is_array($default) ) {
				$sql .= ' default ' . $default[0];
			}
			else {
                $sql .= ' default ' . QueryDriver::getInstance()->inflate($default);
			}
		}

        if( $column->primary )
            $sql .= ' primary key';
        
        if( $column->autoIncrement )
            $sql .= ' auto_increment';

		if( $column->unique )
			$sql .= ' unique';

		// build reference
        // track(
		//		FOREIGN KEY(trackartist) REFERENCES artist(artistid)
		//		artist_id INTEGER REFERENCES artist
        // )
		foreach( $schema->relations as $rel ) {
			switch( $rel['type'] ) {

                // XXX: keep this
                case SchemaDeclare::belongs_to:
                    $fs = new $rel['foreign']['schema'];
                    $fcName = $rel['foreign']['column'];
                    $fc = $fs->columns[$fcName];
                break;

				case SchemaDeclare::has_one:
					if( $rel['self']['column'] == $name ) { 
						$fs = new $rel['foreign']['schema'];
						$sql .= ' references ' . $fs->getTable();
					}
				break;
			}
		}

        return $sql;
    }


	public function build(SchemaDeclare $schema)
	{
        $sql = "CREATE TABLE " . $schema->getTable() . "( \n";
        $columnSql = array();
        foreach( $schema->columns as $name => $column ) {
			$columnSql[] = $this->buildColumnSql( $schema, $column );
        }
        $sql .= join(",\n",$columnSql);
        $sql .= "\n);\n";
        return $sql;
	}

}

