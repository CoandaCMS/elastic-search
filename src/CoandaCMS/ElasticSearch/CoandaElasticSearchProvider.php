<?php namespace CoandaCMS\ElasticSearch;

use CoandaCMS\Coanda\Search\CoandaSearchProvider;

use Elasticsearch, Log, Config, Input, View, Paginator, Coanda;

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

	public function handleSearch()
	{
		$query = Input::has('q') ? Input::get('q') : false;
		$page = Input::has('page') ? Input::get('page') : 1;
		$per_page = 10;
		$results_template = Config::get('coanda-elastic-search::elastic.results_template');

		$total = false;
		$results = false;

		if (!$query)
		{
			$results = [];
			$total = 0;
		}
		else
		{
			$this->initClient();

			$query_params['index'] = $this->indexName();
			$query_params['q'] = $query;

			$offset = ($page - 1) * $per_page;

			$query_params['from'] = $offset;
			$query_params['size'] = $per_page;

			try
			{
				$search_results = $this->client->search($query_params);

				$results = $search_results['hits']['hits'];
				$total = $search_results['hits']['total'];
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

		$paginated_results = Paginator::make($results, $total, $per_page);

		$layout = Coanda::module('layout')->layoutFor('search:results');

		$layout_data = [
			'content' => View::make($results_template, [ 'results' => $paginated_results, 'query' => $query ]),
			'meta' => [
				'title' => 'Search for "' . $query . '"',
				'description' => ''
			],
			'layout' => $layout,
			'module' => 'search',
			'module_identifier' => 'results'
		];

		return View::make($layout->template(), $layout_data);
	}

}