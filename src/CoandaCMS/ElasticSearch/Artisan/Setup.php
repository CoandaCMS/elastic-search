<?php namespace CoandaCMS\ElasticSearch\Artisan;

use Illuminate\Console\Command;
use Coanda;

use CoandaCMS\ElasticSearch\CoandaElasticSearchProvider;

class Setup extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'coanda:elasticsearchsetup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the elastic search index';

    /**
     * Run the command
     */
    public function fire()
    {
    	$this->info('Setting up Elastic Search index.');

    	$search_provider = new CoandaElasticSearchProvider;

    	$indexResult = $search_provider->addPagesIndex();

    	$this->info(var_export($indexResult));

    	$mappingResult = $search_provider->setupMappings();

    	$this->info(var_export($mappingResult));

    	$this->info('Done.');
    }
}