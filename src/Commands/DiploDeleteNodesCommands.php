<?php

namespace Drupal\diplo_delete_nodes\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
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
 *
 *
 * Compare all fields before and after nodes and Content Type deletion:
 *
 * drush ddn:fields > fields_before.txt
 * drush ddn:ct > ct_count_before.txt
 *
 * drush delete:allnodes dailies
 *
 * drush ddn:fields > fields_after.txt
 * drush ddn:ct > ct_count_after.txt
 * vimdiff fields_before.txt fields_after.txt
 * vimdiff ct_count_before.txt ct_count_after.txt
 * 
 *
 */
class DiploDeleteNodesCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * @var array
   */
  protected $batch;

  
  /**
   * Entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;
  
  /**
   * Entity type service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity type service.  
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, LoggerChannelFactoryInterface $loggerChannelFactory) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerChannelFactory = $loggerChannelFactory;
    $this->entityFieldManager = $entityFieldManager;
  }
  
  
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
  public function processNode($id, $operation_details, &$context) {
    
    // Delete node.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if ($node) {
      $node->delete();
    }    
    
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
  public function processContentType($type, $operation_details, &$context) {
    
    // Delete content type.
    $content_type = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->load($type);
      
    $content_type->delete();
    
    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function (in this example, batch_example_finished()).
    $context['results'][] = $type;
    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@type" @details', [
      '@type' => $type,
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

    $this->output()->writeln('Delete nodes batch operations start');

    if (strlen($type) == 0) {
      $this->output()->writeln('No Content Type selected');
      return;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $query = $storage->getQuery()
        ->condition('type', $type);
      $nids = $query->execute();
    }
    catch (\Exception $e) {
      $this->output()->writeln($e);
    }

    $operations = [];
    $numOperations = 0;
    $batchId = 1;
    
    if (!empty($nids)) {
      
      // add node deletion to batch
      foreach ($nids as $nid) {

        $this->output()->writeln($this->t('Preparing batch: ') . $batchId);
        $operations[] = [
            __CLASS__ . '::processNode', [
            $batchId,
            $this->t('Deleting node @nid', ['@nid' => $nid,]),
          ],
        ];
        $batchId++;
        $numOperations++;
      }
      
      // add content type deletion to end of the batch
      $this->output()->writeln($this->t('Deleting content type: ') . $type);
      $operations[] = [
          __CLASS__ . '::processContentType', [
          $type,
          $this->t('Deleting Content type @type', ['@type' => $type,]),
        ],
      ];
      $batchId++;
      $numOperations++;
    }
    else {
      $this->logger()->warning($this->t('No nodes of this type @type', ['@type' => $type,]));
    }

    $batch = [
      'title' => $this->t('Deleting @num node(s)', ['@num' => $numOperations,]),
      'operations' => $operations,
      'finished' => __CLASS__ . '::finished',
    ];

    batch_set($batch);

    drush_backend_batch_process();
    
    $this->logger()->notice($this->t('Batch operations end.'));
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
    //\Drupal::logger('Val')->notice('<pre>'. print_r($arg1, 1) .'</pre>');
    //$this->logger()->success(dt('Achievement unlocked.'));
    
    $all = $this->entityFieldManager->getFieldMap();
    
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

  
  /**
   * Show number of nodes per Content Type
   *
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   *
   * @field-labels
   *   content_type: Content Type
   *   count: Count
   * @default-fields content_type,count
   *
   * @command diplo_delete_nodes:content_type
   * @aliases ddn:ct
   *
   * @filter-default-field name
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function contentType($options = ['format' => 'table']) {
    
    $types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();
      
    foreach($types as $name => $value) {
        $query = \Drupal::entityQuery('node')
                ->condition('type', $name);
        $count = $query->count()->execute();
        
        $rows[] = [
          'content_type' => $name,
          'count' => $count,
        ];
    }
    
    return new RowsOfFields($rows);
  }
}
