<?php

namespace Drupal\diplo_delete_nodes\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;

//use Drush\Commands\core\CacheCommands;
//use Drush\Sql\SqlBase;
use Drupal\Core\Config\ConfigFactoryInterface;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Utility\Html;

use Drupal\pathauto\AliasStorageHelperInterface;
use Drupal\pathauto\PathautoState;

//use Drupal\pathauto\AliasTypeBatchUpdateInterface;
//use Drupal\pathauto\AliasTypeManager;
use Drupal\node\Entity\Node;

//use Drupal\field\Entity\FieldStorageConfig;
//use Drupal\field\Entity\FieldConfig;


use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;


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
   * Entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;
  
  
  
  /**
   * Constructs a new UpdateVideosStatsController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerChannelFactory) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }


  /**
   * 
   */
//   public function __construct() {
//     $this->batch = [
//       'title' => $this->t('Node and Contet Type deletion'),
//       'init_message' => $this->t('Starting deletion batch operations'),
//       'error_message' => $this->t('An error occured'),
//       'progress_message' => $this->t('Running  deletion batch operations'),
//       'operations' => [],
//       'finished' => [__CLASS__, 'finished'],
//     ];
//     
//     $diplo_config = \Drupal::config('diplo.settings');
//     $this->diplo_config = $diplo_config->get('migration.' . $website);
//   }
  
  
  /**
   * Batch process callback.
   * https://git.drupalcode.org/project/drush9_batch_processing/-/blob/8.x-1.x/src/BatchService.php
   *
   * @param int $id
   *   Id of the batch.
   * @param string $operation_details
   *   Details of the operation.
   * @param object $context
   *   Context for operations.
   */
  public function processMyNode($id, $operation_details, &$context) {
    // Simulate long process by waiting 100 microseconds.
    usleep(100);
    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function (in this example, batch_example_finished()).
    $context['results'][] = $id;
    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@id" @details', [
      '@id' => $id,
      '@details' => $operation_details,
    ]);
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
//       $this->logger()->notice($this->t('@count results processed.', ['@count' => count($results)]));
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

   * @command diplo_delete_nodes:delete-nodes
   * @aliases ddn:dn
   * @options dry-run
   */
  public function deleteNodes($type, $options = ['dry-run' => FALSE]) {
    
    $this->output()->writeln('Let\'s bring it on, show starts now!');

    $ops = [
      'callback' => 'deleteNodesCallback',
      //'diplo_config' => $diplo_config->get($to_type . '.' . $from_type),
      'options' => $options,
      'type' => $type,
      'needle' => "%/sessions/%", //"%&quot;%", &#039;
      'comparison' => 'LIKE',
//       'field' => $field,
      //'message' => t('Migrating nodes of @from_type into new nodes of @to_type', ['@from_type' => $from_type, '@to_type' => $to_type]),
    ];

    $operations[] = [$ops];

    $this->addOperations($operations, FALSE);

    // Start drush batch process.
    batch_set($this->batch);

    drush_backend_batch_process();    
    
    // Show some information at the end
    $this->logger()->notice("Deletition done.");
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
  
  
  public static function deleteNodesCallback(array $params = [], &$context) {

    $node_storage = \Drupal::service('entity_type.manager')->getStorage('node');
    $nodes = $node_storage->loadByProperties(['type' => $params['type']]);
    
//     $node_storage->delete($nodes);
    
    $nids = [];

    foreach ($nodes as $nid => $node) {
      $nids[] = $nid;
    }
  
    if (!empty($nids)) {
      \Drupal::service('pathauto.alias_storage_helper')->deleteMultiple($pids);
      \Drupal::logger('Val')->notice('<pre>'. print_r($pids, 1) .'</pre>');    
    }
  }


  
  /**
   * Delete Nodes.
   *
   * @param string $type
   *   Type of node to update
   *   Argument provided to the drush command.
   *
   * @command delete:allnodes
   * @aliases delete-allnodes
   *
   * @usage delete:allnodes foo
   *   foo is the type of node to update
   */
  public function deleteAllNodes($type = '') {
    // 1. Log the start of the script.
    $this->output()->writeln('Delete nodes batch operations start');
//     $this->loggerChannelFactory->get('drush9_batch_processing')->info($this->t('Delete nodes batch operations start'));
    // Check the type of node given as argument, if not, set article as default.
    if (strlen($type) == 0) {
      $this->output()->writeln('No Content Type selected');
      return;
//       $this->loggerChannelFactory->get('drush9_batch_processing')->info($this->t('No Content Type selected'));
    }
    // 2. Retrieve all nodes of this type.
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $query = $storage->getQuery()
        ->condition('type', $type);
      $nids = $query->execute();
    }
    catch (\Exception $e) {
      $this->output()->writeln($e);
//       $this->loggerChannelFactory->get('/*drush9_batch_processing*/')->warning($this->t('Error found @e', ['@e' => $e,]));
    }
    // 3. Create the operations array for the batch.
    $operations = [];
    $numOperations = 0;
    $batchId = 1;
    if (!empty($nids)) {
      foreach ($nids as $nid) {
        // Prepare the operation. Here we could do other operations on nodes.
        $this->output()->writeln($this->t('Preparing batch: ') . $batchId);
        $operations[] = [
          __CLASS__ . '::processMyNode', [
            $batchId,
            $this->t('Updating node @nid', ['@nid' => $nid,]),
          ],
        ];
        $batchId++;
        $numOperations++;
      }
    }
    else {
      $this->logger()->warning($this->t('No nodes of this type @type', ['@type' => $type,]));
    }
    // 4. Create the batch.
    $batch = [
      'title' => $this->t('Updating @num node(s)', ['@num' => $numOperations,]),
      'operations' => $operations,
      'finished' => __CLASS__ . '::finished',
    ];
    // 5. Add batch operations as new batch sets.
    batch_set($batch);
    // 6. Process the batch sets.
    drush_backend_batch_process();
    // 6. Show some information.
    $this->logger()->notice($this->t('Batch operations end.'));
    // 7. Log some information.
//     $this->loggerChannelFactory->get('drush9_batch_processing')->info($this->t('Update batch operations end.'));
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
    \Drupal::logger('Val')->notice('<pre>'. print_r($arg1, 1) .'</pre>');
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
