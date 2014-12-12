<?php namespace CoandaCMS\ElasticSearch;

use CoandaCMS\Coanda\Search\CoandaSearchProvider;

use Elasticsearch, Log, Config, Input, View, Paginator, Coanda;
use Elasticsearch\Common\Exceptions\Curl\CouldNotConnectToHost;
use Elasticsearch\Common\Exceptions\Missing404Exception;

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

	/**
	 * @return mixed
     */
	public function addPagesIndex()
	{
		$this->initClient();

		$result = $this->client->indices()->create(['index' => $this->indexName()]);

		return $result;
	}

	/**
	 * @return mixed
     */
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
		        'visible_from' => [
		            'type' => 'date',
		            'format' => 'yyyy-MM-dd HH:mm:ss'
		        ],
		        'visible_to' => [
		            'type' => 'date',
		            'format' => 'yyyy-MM-dd HH:mm:ss'
		        ],
		        'date' => [
		            'type' => 'string'
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
	 * @return mixed|void
	 */
	public function register($module, $module_id, $url, $search_data)
	{
		$this->initClient();

		$index_params = [];
		$index_params['index'] = $this->indexName();
		$index_params['type'] = $module;
		$index_params['id'] = $module_id;

		$search_data['coanda_url'] = $url;

		// If we don't have visibility dates, then set a pretty generous default!
		if (!isset($search_data['visible_from']))
		{
			$search_data['visible_from'] = '2000-01-01 12:00:00';
		}

		if (!isset($search_data['visible_to']))
		{
			$search_data['visible_to'] = '3000-01-01 12:00:00';
		}

		$index_params['body'] = $search_data;

		$this->client->index($index_params);
	}

	/**
	 * @param $module
	 * @param $module_id
	 * @return mixed|void
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

			$this->client->delete($delete_params);
		}
		catch (Missing404Exception $exception)
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
		$page = Input::has('page') ? Input::get('page') : 1;
		$per_page = 10;
		$results_template = Config::get('coanda-elastic-search::elastic.results_template');

		$total = false;
		$results = [];

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

			$filter = [
				'and' => [
					'filters' => [
						[
							'range' => [
								'visible_from' => [
									'lt' => date('Y-m-d H:m:s', time())
								]
							]
						],
						[
							'range' => [
								'visible_to' => [
									'gt' => date('Y-m-d H:m:s', time())
								]
							]
						],
					]
				]
			];

			$page_type_string = Input::has('type') ? Input::get('type') : '';
			$page_types = [];

			if ($page_type_string != '')
			{
				$page_types = explode(',', $page_type_string);
			}

			if (count($page_types) > 0)
			{
				$filter['and']['filters'][] = [
					'terms' => [
							'page_type' => $page_types
						]
				];
			}

			$query_params['body']['query']['filtered'] = array(
				'filter' => $filter,
				'query'  => [
					'match' => [
						'_all' => $query
					]
				]
			);

			try
			{
				$search_results = $this->client->search($query_params);

				$results = $search_results['hits']['hits'];
				$total = $search_results['hits']['total'];
			}
			catch (Missing404Exception $exception)
 			{
				// empty results
			}
			catch (CouldNotConnectToHost $exception)
			{
				// empty results
			}
		}

		$paginated_results = Paginator::make($results, $total, $per_page);

		if (Input::get('format') == 'json')
		{
			return $paginated_results;
		}

		$layout = Coanda::module('layout')->layoutFor('search:results');

		$layout_data = [
			'content' => View::make($results_template, [ 'results' => $paginated_results, 'query' => $query ]),
			'meta' => [
				'title' => 'Search' . (htmlentities($query) ? ' for "' . htmlentities($query) . '"' : ''),
				'description' => ''
			],
			'layout' => $layout,
			'breadcrumb' => [
				'search' => 'Search'
			],
			'module' => 'search',
			'module_identifier' => 'results'
		];

		return View::make($layout->template(), $layout_data);
	}
}