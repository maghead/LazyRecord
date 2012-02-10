<?php
namespace Lazy\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use Lazy\CodeGen;

/**
 * builder for building static schema class file
 */
class SchemaGenerator
{
	public $schemaPaths = array();

	public $logger;

	public function __construct() {  

	}

	public function addPath( $path )
	{
		$this->schemaPaths[] = rtrim($path, DIRECTORY_SEPARATOR);
	}

	public function setLogger($logger)
	{
		$this->logger = $logger;
	}

	protected function getTemplatePath()
	{
		$refl = new \ReflectionObject($this);
		return dirname($refl->getFilename()) . DIRECTORY_SEPARATOR . 'Templates';
	}

	protected function renderCode($file, $args)
	{
		$codegen = new \Lazy\CodeGen( $this->getTemplatePath() );
		$codegen->stash = $args;
		return $codegen->renderFile($file);
	}

	protected function generateClass($targetDir,$templateFile,$cTemplate,$extra = array(), $overwrite = false)
	{
		$source = $this->renderCode( $templateFile , array_merge( array(
			'class'   => $cTemplate,
		), $extra ) );

		$sourceFile = $targetDir 
            . DIRECTORY_SEPARATOR 
            . $cTemplate->class->getName() . '.php';

		$class = ltrim($cTemplate->class->getFullName(),'\\');
		$this->logger->info( "Generating model class: $class => $sourceFile" );
		$this->preventFileDir( $sourceFile );

		if( $overwrite || ! file_exists( $sourceFile ) ) {
			if( file_put_contents( $sourceFile , $source ) === false ) {
				throw new Exception("$sourceFile write failed.");
			}
		}

        if( ! class_exists($class) )  {
            $this->tryRequire( $sourceFile );
        }
		return array( $class, $sourceFile );
	}


	private function preventFileDir($path,$mode = 0755)
	{
		$dir = dirname($path);
		if( ! file_exists($dir) )
			mkdir( $dir , $mode, true );
	}

	protected function tryRequire($file)
	{
		// try to require 
		try {
            require $file;
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
			throw $e;
		}
	}

	protected function buildSchemaProxyClass($schema)
	{
		$schemaArray = $schema->export();
		$source = $this->renderCode( 'Schema.php.twig', array(
			'schema_data' => $schemaArray,
			'schema' => $schema,
		));

		$schemaClass = $schema->getClass();
		$modelClass  = $schema->getModelClass();
		$schemaProxyClass = $schema->getSchemaProxyClass();

  		$cTemplate = new \Lazy\CodeGen\ClassTemplate( $schemaProxyClass );
		$cTemplate->addConst( 'schema_class' , '\\' . ltrim($schemaClass,'\\') );
		$cTemplate->addConst( 'model_class' , '\\' . ltrim($modelClass,'\\') );
        $cTemplate->addConst( 'table', $schema->getTable() );

		/*
			return $this->generateClass( 'Class.php', $cTemplate );
		 */


		/**
		* classname with namespace 
		*/
		$schemaClass = $schema->getClass();
		$modelClass  = $schema->getModelClass();
		$schemaProxyClass = $schema->getSchemaProxyClass();


        $filename = explode( '\\' , $schemaProxyClass );
        $filename = (string) end($filename);
        $sourceFile = $schema->getDir() 
            . DIRECTORY_SEPARATOR . $filename . '.php';

		$this->preventFileDir( $sourceFile );

		if( file_exists($sourceFile) ) {
			$this->logger->info("$sourceFile found, overwriting.");
		}

		$this->logger->info( "Generating schema proxy $schemaProxyClass => $sourceFile" );
		file_put_contents( $sourceFile , $source );

        if( ! class_exists($schemaProxyClass) )  {
            $this->tryRequire( $sourceFile );
        }

		return array( $schemaProxyClass , $sourceFile );
	}

	protected function buildBaseModelClass($schema)
	{
		$baseClass = $schema->getBaseModelClass();
		$cTemplate = new CodeGen\ClassTemplate( $baseClass );
		$cTemplate->addConst( 'schema_proxy_class' , '\\' . ltrim($schema->getSchemaProxyClass(),'\\') );
		$cTemplate->addConst( 'collection_class' , '\\' . ltrim($schema->getCollectionClass(),'\\') );
		$cTemplate->addConst( 'model_class' , '\\' . ltrim($schema->getModelClass(),'\\') );

		$cTemplate->extendClass( 'Lazy\\BaseModel' );
		return $this->generateClass( $schema->getDir(), 'Class.php.twig', $cTemplate , array() , true );
	}

	protected function buildModelClass($schema)
	{
		$baseClass = $schema->getBaseModelClass();
		$modelClass = $schema->getModelClass();
		$cTemplate = new CodeGen\ClassTemplate( $schema->getModelClass() );
		$cTemplate->addConst( 'schema_proxy_class' , '\\' . ltrim($schema->getSchemaProxyClass(),'\\') );
		$cTemplate->extendClass( $baseClass );
		return $this->generateClass( $schema->getDir() , 'Class.php.twig', $cTemplate );
	}

	protected function buildBaseCollectionClass($schema)
	{
		$baseCollectionClass = $schema->getBaseCollectionClass();

		$cTemplate = new CodeGen\ClassTemplate( $baseCollectionClass );
		$cTemplate->addConst( 'schema_proxy_class' , '\\' . ltrim($schema->getSchemaProxyClass(),'\\') );
		$cTemplate->addConst( 'model_class' , '\\' . ltrim($schema->getModelClass(),'\\') );
		$cTemplate->extendClass( 'Lazy\\BaseCollection' );
		return $this->generateClass( $schema->getDir(), 'Class.php.twig', $cTemplate , array() , true ); // overwrite
	}

	protected function buildCollectionClass($schema)
	{
		$collectionClass = $schema->getCollectionClass();
		$baseCollectionClass = $schema->getBaseCollectionClass();

		$cTemplate = new CodeGen\ClassTemplate( $collectionClass );
		$cTemplate->extendClass( $baseCollectionClass );
		return $this->generateClass( $schema->getDir() , 'Class.php.twig', $cTemplate );
	}

	public function generate()
	{
        $finder = new SchemaFinder;
        $finder->paths = $this->schemaPaths;
        $finder->load();
		$classes = $finder->getSchemas();

		/**
		 * schema class mapping 
		 */
		$classMap = array();

		$this->logger->info( 'Found schema classes: ' . join(', ', $classes ) );
		foreach( $classes as $class ) {
			$schema = new $class;

			$this->logger->info( 'Building schema proxy class: ' . $class );
			list( $schemaProxyClass, $schemaProxyFile ) = $this->buildSchemaProxyClass( $schema );
			$classMap[ $schemaProxyClass ] = $schemaProxyFile;

			$this->logger->info( 'Building base model class: ' . $class );
			list( $baseModelClass, $baseModelFile ) = $this->buildBaseModelClass( $schema );
			$classMap[ $baseModelClass ] = $baseModelFile;

			$this->logger->info( 'Building model class: ' . $class );
			list( $modelClass, $modelFile ) = $this->buildModelClass( $schema );
			$classMap[ $modelClass ] = $modelFile;

			$this->logger->info( 'Building base collection class: ' . $class );
			list( $c, $f ) = $this->buildBaseCollectionClass( $schema );
			$classMap[ $c ] = $f;

			$this->logger->info( 'Building collection class: ' . $class );
			list( $c, $f ) = $this->buildCollectionClass( $schema );
			$classMap[ $c ] = $f;
		}
		return $classMap;
	}
}

