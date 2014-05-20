<?php namespace CoandaCMS\ElasticSearch;

use CoandaCMS\Coanda\Search\CoandaSearchProvider;

use Elasticsearch, Log;

class CoandaElasticSearchProvider implements CoandaSearchProvider {

	private $client;

	private function initClient()
	{
		$params = [];
		$params['hosts'] = [
			'http://localhost:9200'
		];

		$this->client = new Elasticsearch\Client($params);
	}

	public function register($index, $type, $id, $search_data)
	{
		$this->initClient();

		$index_params = [];
		$index_params['body'] = $search_data;
		$index_params['index'] = $index;
		$index_params['type'] = $type;
		$index_params['id'] = $id;

		$index_result = $this->client->index($index_params);

		Log::info('Elastic search indexed: ' . $index . ' -> ' . $type . ' -> ' . $id);
		Log::info($index_result);
	}

	public function unRegister($index, $type, $id)
	{
		try
		{
			$this->initClient();

			$delete_params = [];
			$delete_params['index'] = $index;
			$delete_params['type'] = $type;
			$delete_params['id'] = $id;
		
			$delete_result = $this->client->delete($delete_params);

			Log::info('Elastic search removed: ' . $index . ' -> ' . $type . ' -> ' . $id);
			Log::info($delete_result);			
		}
		catch (\Elasticsearch\Common\Exceptions\Missing404Exception $exception)
		{
			Log::info('Elastic search, failed to remove: ' . $index . ' -> ' . $type . ' -> ' . $id);
		}
	}

}