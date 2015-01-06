<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Hosts - here you can specify the hosts used for Elasticsearch
	|--------------------------------------------------------------------------
	|
	*/
	'hosts' => [
		'http://localhost:9200',
	],

	'index_name' => 'site',

	'results_template' => 'coanda-elastic-search::results',

	'defined_filters' => [
//		'example' => [
//			'page_types' => ['page', 'news_article'],
//			'paths' => ['location-in-your-site']
//		]
	]

);
