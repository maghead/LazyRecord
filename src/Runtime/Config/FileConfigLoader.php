<?php

namespace Maghead\Runtime\Config;

use ConfigKit\ConfigCompiler;
use RuntimeException;

class FileConfigLoader
{
    /**
     * Load config from the YAML config file...
     *
     * @param string $file
     */
    public static function load($sourceFile, $force = false)
    {
        return new Config(self::compile($sourceFile, $force), $sourceFile);
    }

    public static function compile($sourceFile, $force = false)
    {
        $compiledFile = ConfigCompiler::compiled_filename($sourceFile);
        if ($force || ConfigCompiler::test($sourceFile, $compiledFile)) {
            $config = ConfigCompiler::parse($sourceFile);
            if ($config === false) {
                throw new RuntimeException("Can't parse config file '$sourceFile'.");
            }
            $config = ConfigPreprocessor::preprocess($config);
            ConfigCompiler::write($compiledFile, $config);
            return $config;
        } else {
            return require $compiledFile;
        }
    }
}
