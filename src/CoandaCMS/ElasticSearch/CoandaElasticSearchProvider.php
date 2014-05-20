<?php namespace CoandaCMS\ElasticSearch;

use CoandaCMS\Coanda\Search\CoandaSearchProvider;

use Elasticsearch, Log, Config, Input, View, Paginator;

class CoandaElasticSearchProvider implements CoandaSearchProvider {

	private $client;

	private function initClient()
	{
		$hosts = Config::get('coanda-elastic-search::elastic.hosts');

		$params = [];
		$params['hosts'] = $hosts;

		$this->client = new Elasticsearch\Client($params);
	}

	private function indexName()
	{
		$index_name = Config::get('coanda-elastic-search::elastic.index_name');

		if (!$index_name || $index_name == '')
		{
			$index_name = 'site';
		}

		return $index_name;
	}

	public function register($module, $module_id, $url, $search_data)
	{
		$this->initClient();

		$index_params = [];
		$index_params['index'] = $this->indexName();
		$index_params['type'] = $module;
		$index_params['id'] = $module_id;

		if (!isset($search_data['url']))
		{
			$search_data['url'] = $url;
		}

		$index_params['body'] = $search_data;

		$index_result = $this->client->index($index_params);

		Log::info('Elastic search indexed: ' . $module . ' -> ' . $module_id);
		Log::info($index_result);
	}

	public function unRegister($module, $module_id)
	{
		try
		{
			$this->initClient();

			$delete_params = [];
			$delete_params['index'] = $this->indexName();
			$delete_params['type'] = $module;
			$delete_params['id'] = $module_id;
		
			$delete_result = $this->client->delete($delete_params);

			Log::info('Elastic search removed: ' . $index . ' -> ' . $module . ' -> ' . $module_id);
			Log::info($delete_result);			
		}
		catch (\Elasticsearch\Common\Exceptions\Missing404Exception $exception)
		{
			Log::info('Elastic search, failed to remove: ' . $module . ' -> ' . $module_id);
		}
	}

	public function executeSearch()
	{
		$query = Input::has('q') ? Input::get('q') : false;
		$page = Input::has('page') ? Input::get('page') : 1;
		$per_page = 10;
		$results_template = Config::get('coanda-elastic-search::elastic.results_template');

		if (!$query)
		{
			return 'no query!';
		}

		$this->initClient();

		$query_params['index'] = $this->indexName();
		$query_params['q'] = $query;

		$offset = ($page - 1) * $per_page;

		$query_params['from'] = $offset;
		$query_params['size'] = $per_page;

		try
		{
			$search_results = $this->client->search($query_params);

			$results = Paginator::make($search_results['hits']['hits'], $search_results['hits']['total'], $per_page);

			return View::make($results_template, [ 'results' => $results, 'query' => $query ]);
		}
		catch (\Elasticsearch\Common\Exceptions\Missing404Exception $exception)
		{
			dd('Error running search.');
		}
		catch (\Elasticsearch\ Common\Exceptions\Curl\CouldNotConnectToHost $exception)
		{
			dd('Count not connect to host');
		}
	}

}