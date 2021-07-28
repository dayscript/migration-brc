<?php

namespace Drupal\brc_elasticsearch_custom;

interface ElasticsearchManagerInterface {
	public function mappingCategory($category, $type = null);
	
	public function mappingFields($type);
	
	public function indexingApi($category, $type, $operation);
	
	public function indexingProcessDoc($category, $operation, $nid, $data = null);
	
	public function clientElasticsearch($operation, $index, $type, $body = null);
	
	public function clientOaipmh($operation, $endpoint, $set = null, $token = null);
	
	public function processResult($data, $type, $parameters = null);
	
	public function processFieldImage($tid);
	
	public function checkedType($t);
	
	public function clearType($t);
	
	public function clearObject($node);
}
