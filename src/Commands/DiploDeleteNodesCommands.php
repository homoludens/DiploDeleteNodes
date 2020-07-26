<?php

namespace Drupal\diplo_delete_nodes\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class DiploDeleteNodesCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * @var array
   */
  protected $batch;

  public $diplo_config;

   /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The alias type manager.
   *
   * @var \Drupal\pathauto\AliasTypeManager
   */
  protected $aliasTypeManager;

  /**
   * The alias storage helper.
   *
   * @var \Drupal\pathauto\AliasStorageHelperInterface
   */
  protected $aliasStorageHelper;

  /**
   * Constructs a new PathautoCommands object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration object factory.
   * @param \Drupal\pathauto\AliasTypeManager $aliasTypeManager
   *   The alias type manager.
   * @param \Drupal\pathauto\AliasStorageHelperInterface $aliasStorageHelper
   *   The alias storage helper.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AliasTypeManager $aliasTypeManager, AliasStorageHelperInterface $aliasStorageHelper) {
    $this->configFactory = $configFactory;
    $this->aliasTypeManager = $aliasTypeManager;
    $this->aliasStorageHelper = $aliasStorageHelper;
    
    $this->batch = [
      'title' => $this->t('Resources upgrade'),
      'init_message' => $this->t('Starting migration batch operations at Drupal 8 destination'),
      'error_message' => $this->t('An error occured'),
      'progress_message' => $this->t('Running migration batch operations at Drupal 8 destination'),
      'operations' => [],
      'finished' => [__CLASS__, 'finished'],
    ];
  }
   
  /**
   * Adds an operation to the batch.
   *
   * @param array $operations
   * @param class|null $class
   */
  public function addOperations(array $operations, $class) {
    $parent = $class ? get_class($this->{$class}) : __CLASS__;
    foreach ($operations as $operation => $params) {
      if (is_array($params[0]) && isset($params[0]['callback'])) {
        $operation = $params[0]['callback'];
      }
      $this->batch['operations'][] = [
         // __CLASS__ . '::' . $operation, $params,
         $parent . '::' . $operation, $params,
      ];
    }
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public function finished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
     //\Drupal::logger('Val')->notice('<pre>'. print_r($results, 1) .'</pre>');
     // Here we could do something meaningful with the results.
      // We just display the number of nodes we processed...
      $messenger->addMessage(t('@count results processed.', ['@count' => count($results)]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

  /**
   * First group of pre-migrate operations

   * @command diplo_delete_nodes:custom-alias
   * @aliases ddn:ca
   * @options dry-run
   */
  public function customAliases($type, $field, $options = ['dry-run' => FALSE]) {
    
    $this->output()->writeln('Let\'s bring it on, show starts now!');

    $ops = [
      'callback' => 'customAliasesCallback',
      //'diplo_config' => $diplo_config->get($to_type . '.' . $from_type),
      'options' => $options,
      'type' => $type,
      'needle' => "%/sessions/%", //"%&quot;%", &#039;
      'comparison' => 'LIKE',
      'field' => $field,
      //'message' => t('Migrating nodes of @from_type into new nodes of @to_type', ['@from_type' => $from_type, '@to_type' => $to_type]),
    ];

    $operations[] = [$ops];

    $this->addOperations($operations, FALSE);

    // Start drush batch process.
    batch_set($this->batch);

    drush_backend_batch_process();    
    
    // Show some information at the end
    $this->logger()->notice("Migration done.");
  }


  public static function customAliasesCallback(array $params = [], &$context) {
    $node_storage = \Drupal::service('entity_type.manager')->getStorage('node');
    $nodes = $node_storage->loadByProperties(['type' => $params['type'], 'status' => NODE_PUBLISHED]);
    $pids = [];

    foreach ($nodes as $nid => $node) {
      $path = $node->get('path')->getValue();
      if (!empty($path) && isset($path[0]['pid'])) {
            $pids[] = $path[0]['pid'];
      }
    }
  
    if (!empty($pids)) {
      \Drupal::service('pathauto.alias_storage_helper')->deleteMultiple($pids);
      \Drupal::logger('Val')->notice('<pre>'. print_r($pids, 1) .'</pre>');    
    }
  }


  /**
   * Command description here.
   *
   * @param $arg1
   *   Argument description.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   * @option option-name
   *   Description
   * @usage diplo_delete_nodes-commandName foo
   *   Usage description
   *
   * @command diplo_delete_nodes:commandName
   * @aliases ddn:command
   */
  public function commandName($arg1, $options = ['option-name' => 'default']) {
    $this->logger()->success(dt('Achievement unlocked.'));
  }

  /**
   * An example of the table output format.
   *
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   *
   * @field-labels
   *   field: FieldID
   *   content_type: Content Type
   * @default-fields field,content_type
   *
   * @command diplo_delete_nodes:fields
   * @aliases ddn:fields
   *
   * @filter-default-field name
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function fields($options = ['format' => 'table']) {
    $all = \Drupal::entityManager()->getFieldMap();
    foreach ($all['node'] as $field => $field_conf) {
      foreach ($field_conf['bundles'] as $key => $content_type) {
        $rows[] = [
          'field' => $field,
          'content_type' => $content_type,
          'content_type' => $content_type,
        ];
      }
    }
    
    return new RowsOfFields($rows);
  }
}
