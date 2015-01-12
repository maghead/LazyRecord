<?php
namespace LazyRecord\Command;
use CLIFramework\Command;
use LazyRecord\ConfigLoader;
use LazyRecord\Metadata;
use LazyRecord\Command\CommandUtils;

class BaseCommand extends Command
{

    /**
     * @var ConfigLoader
     */
    public $config;

    public function init() {
        $this->config = CommandUtils::init_config_loader();
    }

    public function options($opts)
    {
        $self = $this;
        $opts->add('D|data-source:', 'specify data source id')
            ->validValues(function() use($self) {
                return $self->config->getDataSourceIds();
            })
            ;
    }

    public function getCurrentDataSourceId() {
        return $this->options->{'data-source'} ?: 'default';
    }

}
