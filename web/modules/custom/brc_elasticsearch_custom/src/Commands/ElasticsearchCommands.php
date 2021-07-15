<?php
/**
 * A drush command file.
 * @package Drupal\brc_elasticsearch_custom\Commands.
 */

namespace Drupal\brc_elasticsearch_custom\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\brc_elasticsearch_custom\ElasticsearchManagerInterface;

class ElasticsearchCommands extends DrushCommands {
  /**
   * Drush command module custom Elasticsearch.
   *
   * @param string $type
   *   Content type of sources
   * @command elasticsearch:elastic
   * @aliases elasticsearch elastic
   * @option category
   *   node,taxonomy,babelojs,makemake
   * @option operation
   *   create,delete.
   * @usage elasticsearch:elastic --category --operation
   */
  public function elasticsearch($type = '', $options = ['category' => 'node', 'operation' => 'create']) {
    $commonFunction = \Drupal::service('brc_elasticsearch_custom.manager');
    switch ($options['category']) {
      case 'node':
      case 'taxonomy':
      case 'makemake':
        if(empty($type)){
          $this->output()->writeln("The type field can't be empty.");
        }else {
          $response = $commonFunction->indexingApi($options['category'], $type, $options['operation'], true);
          $this->output()->writeln( $response );
        }
        break;
      case 'babelojs':
        $config = \Drupal::config('brc_elasticsearch_custom.settings');
        $e = [];
        foreach ($config->get('categories')['babel']['endpoint'] as $key => $index) {
          $e[] = $index['endpoint'];
        }
        foreach ($config->get('categories')['ojs']['endpoint'] as $key => $index) {
          $e[] = $index['endpoint'];
        }
        $sets = [];
        foreach ($e as $key => $endpoint) {
          foreach ($commonFunction->clientOaipmh('sets', $endpoint) as $key => $set) {
            $sets[$set->setSpec] = $endpoint;
          }
        }

        $log = ['total' => 0, 'successful' => 0, 'failed' => 0, 'error' => []];
        $set = $type;
        $endpoint = $sets[$set];
        $no_token = 1;
        $token = '';

        while ($no_token == 1) {
          if(isset($token) && !empty($token)){
            $response = $commonFunction->clientOaipmh('next', $endpoint, $set, $token);
          }else {
            $response = $commonFunction->clientOaipmh('recordsperset', $endpoint, $set);
            if(isset($response->resumptionToken)){
              $token = $response->resumptionToken;
            }
            if(empty($token)){ $no_token = 0; }
            foreach ($response->record as $key => $record) {
              $response = $commonFunction->indexingProcessDoc('babelojs', $options['operation'], $set, $record);
              $log = $commonFunction->translateLog($response, $log);
            }
          }
        }
        $this->output()->writeln('Documentos procesados: ' . $log['total'] . ', exitosos: ' . $log['successful'] . ', han fallado: ' .  $log['failed']);
        break;
    }
  }
}