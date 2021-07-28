<?php

namespace Drupal\brc_elasticsearch_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\brc_elasticsearch_custom\ElasticsearchManagerInterface;
/**
 * Declaration of class ElasticsearchIndexingDrupalForm.
 */
class ElasticsearchIndexingDrupalForm extends ConfigFormBase {

  protected $commonFunction;

  public function __construct(ElasticsearchManagerInterface $interface) {
    $this->commonFunction = $interface;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('brc_elasticsearch_custom.manager')
    );
  }

  public function getFormId() {
    return 'brc_elasticsearch_custom_indexing_form';
  }

  protected function getEditableConfigNames() {
    return [
      'brc_elasticsearch_custom.settings',
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('brc_elasticsearch_custom.settings');
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#tree'] = TRUE;    
    $form['settings'] = ['#type' => 'vertical_tabs'];
    $form['drupal'] = [
      '#type' => 'details',
      '#title' => $this->t('Drupal'),
      '#group' => 'settings',
    ];
    $form['drupal']['container'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'drupal-container'],
    ];

    $form['drupal']['container']['node'] = [
      '#type' => 'fieldset',
      '#title' => 'Nodos a indexar',
    ];
    foreach ($this->commonFunction->checkedType($config->get('categories')['node']['mapping']) as $mk => $m) {
      $form['drupal']['container']['node'][$mk] = [
        '#type' => 'checkbox',
        '#title' => $m,
        '#default_value' => '',
      ];
    }
    $form['drupal']['container']['taxonomy'] = [
      '#type' => 'fieldset',
      '#title' => 'Taxonomía a indexar',
    ];
    foreach ($this->commonFunction->checkedType($config->get('categories')['taxonomy']['mapping']) as $mk => $m) {
      $form['drupal']['container']['taxonomy'][$mk] = [
        '#type' => 'checkbox',
        '#title' => $m,
        '#default_value' => '',
      ];
    }
    $form['drupal']['container']['actions'] = [
      '#type' => 'actions',
    ];
    $form['drupal']['container']['actions']['add_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Indexar'),
      '#submit' => ['::indicesDrupalCreate'],
      '#ajax'   => [
        'callback' => [ $this, 'indicesDrupalCallback'],
        'wrapper'  => 'drupal-container',
      ],
    ];
    $form['drupal']['container']['actions']['remove_item'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Eliminar'),
      '#submit' => ['::indicesDrupalDelete'],
      '#limit_validation_errors' => [],
      '#ajax'                    => [
        'callback' => [ $this, 'indicesDrupalCallback'],
        'wrapper'  => 'drupal-container',
      ],
    ];
    $form_state->setCached(FALSE);
    return $form;
  }

  public function indicesDrupalCreate(array $form, FormStateInterface $form_state) {
    $i = $form_state->getValue('drupal');
    foreach ($i['container']['node'] as $k => $v) {
      if($v == 1) {
        $this->commonFunction->indexingApi('node', $k, 'create');
      }
    }
    foreach ($i['container']['taxonomy'] as $k => $v) { 
      if($v == 1) {
        $this->commonFunction->indexingApi('taxonomy', $k, 'create');
      }
    }
    $form_state->setRebuild();
  }

  public function indicesDrupalDelete(array $form, FormStateInterface $form_state) {
    $i = $form_state->getValue('indices');
    
    foreach ($i['container']['node'] as $k => $v) {
      if($v == 1) {
        $this->commonFunction->indexingApi('node', $k, 'delete');
      }
    }
    foreach ($i['container']['taxonomy'] as $k => $v) { 
      if($v == 1) {
        $this->commonFunction->indexingApi('taxonomy', $k, 'delete');
      }
    }
    $form_state->setRebuild();
  }

  public function indicesDrupalCallback(array $form, FormStateInterface $form_state) {
    return $form['drupal']['container'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    return parent::submitForm($form, $form_state);
  }
}
