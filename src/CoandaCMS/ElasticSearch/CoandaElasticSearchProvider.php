<?php namespace CoandaCMS\ElasticSearch;

use CoandaCMS\Coanda\Search\CoandaSearchProvider;

use Elasticsearch, Log, Config, Input, View, Paginator, Coanda;

/**
 * Class CoandaElasticSearchProvider
 * @package CoandaCMS\ElasticSearch
 */
class CoandaElasticSearchProvider implements CoandaSearchProvider {

    /**
     * @var
     */
    private $client;

    /**
     *
     */
    private function initClient()
	{
		$hosts = Config::get('coanda-elastic-search::elastic.hosts');

		$params = [];
		$params['hosts'] = $hosts;

		$this->client = new Elasticsearch\Client($params);
	}

    /**
     * @return string
     */
    private function indexName()
	{
		$index_name = Config::get('coanda-elastic-search::elastic.index_name');

		if (!$index_name || $index_name == '')
		{
			$index_name = 'site';
		}

		return $index_name;
	}

	public function addPagesIndex()
	{
		$this->initClient();

		$result = $this->client->indices()->create(['index' => $this->indexName()]);

		return $result;
	}

	public function setupMappings()
	{
		// Set the index and type
		$params['index'] = $this->indexName();
		$params['type']  = 'pages';

		// Adding a new type to an existing index
		$pagesMapping = [
		    '_source' => [
		        'enabled' => true
		    ],
		    'properties' => [
		        'visible_to' => [
		            'type' => 'date',
		            'format' => 'yyyy-MM-dd HH:mm:ss'
		        ]
		    ]
		];

		$params['body']['pages'] = $pagesMapping;

		// Update the index mapping
		$result = $this->client->indices()->putMapping($params);

		return $result;
	}

    /**
     * @param $module
     * @param $module_id
     * @param $url
     * @param $search_data
     */
    public function register($module, $module_id, $url, $search_data)
	{
		$this->initClient();

		$index_params = [];
		$index_params['index'] = $this->indexName();
		$index_params['type'] = $module;
		$index_params['id'] = $module_id;

		$search_data['coanda_url'] = $url;

		// If we don't have visibilty dates, then set a pretty generous default!
		if (!isset($search_data['visible_from']))
		{
			$search_data['visible_from'] = '2000-01-01 12:00:00';
		}

		if (!isset($search_data['visible_to']))
		{
			$search_data['visible_to'] = '3000-01-01 12:00:00';
		}

		$index_params['body'] = $search_data;

		$index_result = $this->client->index($index_params);

		Log::info('Elastic search indexed: ' . $module . ' -> ' . $module_id);
		Log::info($index_result);
	}

    /**
     * @param $module
     * @param $module_id
     */
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

			Log::info('Elastic search removed: ' . $module . ' -> ' . $module_id);
			Log::info($delete_result);			
		}
		catch (\Elasticsearch\Common\Exceptions\Missing404Exception $exception)
		{
			Log::info('Elastic search, failed to remove: ' . $module . ' -> ' . $module_id);
		}
	}

    /**
     * @return mixed
     */
    public function handleSearch()
	{
		$query = Input::has('q') ? Input::get('q') : false;

		// $spelling_suggestion = $this->orthograph($query);
		// dd($spelling_suggestion);

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

			$offset = ($page - 1) * $per_page;

			$query_params['from'] = $offset;
			$query_params['size'] = $per_page;

			$filter = array();

			$filter['and']['filters'][0]['range']['visible_from']['lt'] = date('Y-m-d H:m:s', time());
			$filter['and']['filters'][1]['range']['visible_to']['gt'] = date('Y-m-d H:m:s', time());

			$_query = array();
			$_query['match']['_all'] = $query;

			$query_params['body']['query']['filtered'] = array(
			    "filter" => $filter,
			    "query"  => $_query
			);

			try
			{
				$search_results = $this->client->search($query_params);

				$results = $search_results['hits']['hits'];
				$total = $search_results['hits']['total'];
			}
			catch (\Elasticsearch\Common\Exceptions\Missing404Exception $exception)
			{
				$results = [];
			}
			catch (\Elasticsearch\ Common\Exceptions\Curl\CouldNotConnectToHost $exception)
			{
				$results = [];
			}
		}

		$paginated_results = Paginator::make($results, $total, $per_page);

		$layout = Coanda::module('layout')->layoutFor('search:results');

		$layout_data = [
			'content' => View::make($results_template, [ 'results' => $paginated_results, 'query' => $query ]),
			'meta' => [
				'title' => 'Search' . ($query ? ' for "' . $query . '"' : ''),
				'description' => ''
			],
			'layout' => $layout,
			'module' => 'search',
			'module_identifier' => 'results'
		];

		return View::make($layout->template(), $layout_data);
	}
}