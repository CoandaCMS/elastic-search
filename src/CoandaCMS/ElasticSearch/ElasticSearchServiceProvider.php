<?php namespace Coandacms\ElasticSearch;

use Illuminate\Support\ServiceProvider;

class ElasticSearchServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('coandacms/elastic-search', 'coanda-elastic-search');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['coandaelastic.setup'] = $this->app->share(function($app)
		{
		    return new \CoandaCMS\ElasticSearch\Artisan\Setup($app);
		});
		
		$this->commands('coandaelastic.setup');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
