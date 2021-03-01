<?php

namespace Drupal\diplo_delete_nodes\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use \Consolidation\OutputFormatters\StructuredData\UnstruturedData;
use Drush\Commands\DrushCommands;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

//for deleting metatag field
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

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
 * drush ddn:fields|grep meta > before.txt
 * drush ddn:mm
 * drush ddn:fields|grep meta > after.txt
 * vimdiff before.txt after.txt
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
  public function processNode($id, $nid, $operation_details, &$context) {

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
            $nid,
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
   *   field_type: Field Type
   *   content_type: Content Type
   *
   * @default-fields field,field_type,content_type
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

//     field_session_report entity reference field

    $all = $this->entityFieldManager->getFieldMap();

    foreach ($all['node'] as $field => $field_conf) {

      foreach ($field_conf['bundles'] as $key => $content_type) {
        $rows[] = [
          'field' => $field,
          'field_type' => $field_conf['type'],
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


  /**
   * Move wrong field_meta_tags to field_metatag
   * and delete field_meta_tags and field_meta_test
   *
   * @command diplo_delete_nodes:move_meta
   * @aliases ddn:mm
   *
   */
  public function moveMeta() {
    $entity_type = 'node';

    $database = \Drupal::database();
    $query = $database->query("SELECT entity_id, bundle FROM {node__field_meta_tags}");
    $result = $query->fetchAllKeyed();

    print_r($result);

    // moving data form field_meta_tags to field_metatag
    foreach($result as $nid => $node_type) {
        $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load($nid);
        $this->logger()->notice($this->t('Node @nid loaded', ['@nid' => $nid,]));
        $field_meta_tags = $node->field_meta_tags->value;

        $field_metatag = $node->field_metatag->value;

        $node->field_metatag->value = $node->field_meta_tags->value;
        $node->save();
    }

    // Deleting field storage.
    FieldStorageConfig::loadByName('node', 'field_meta_tags')->delete();
    $this->logger()->notice($this->t('field_meta_tags deleted', ['@nid' => $nid,]));

    FieldStorageConfig::loadByName('node', 'field_meta_test')->delete();
    $this->logger()->notice($this->t('field_meta_tags deleted', ['@nid' => $nid,]));

    // Deleting field. not needed, since FieldConfig has a dependency on the FieldStorageConfigfield
//     FieldConfig::loadByName('node', 'blog', 'field_meta_tags')->delete();
//     FieldConfig::loadByName('node', 'event', 'field_meta_tags')->delete();
//     FieldConfig::loadByName('node', 'page', 'field_meta_tags')->delete();

//   field_meta_tags                   metatag                      blog
//   field_meta_tags                   metatag                      course
//   field_meta_tags                   metatag                      diplo_news
//   field_meta_tags                   metatag                      event
//   field_meta_tags                   metatag                      page
//   field_meta_tags                   metatag                      topic
//   field_meta_tags                   metatag                      book_reviews


  }


  /**
   * Move wrong field_meta_tags to field_metatag
   * and delete field_meta_tags and field_meta_test
   * drush ddn:etc --format=csv > nodes_tags.csv
   *
   * @command diplo_delete_nodes:export_to_csv
   * @aliases ddn:etc
   *
   */
  public function exportToCsv($options = ['format' => 'table']) {
    $entity_type = 'node';

    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $nids = $query->condition('status', '1')
                   ->condition('field_tags.entity.name', '', '<>')
                  ->exists('field_tags')
                  ->execute();
//    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);


//    $query = \Drupal::entityQuery('node')
//      ->condition('status', 1)
//      ->condition('field_tags.entity.name', '');

//    $database = \Drupal::database();
//    $query = $database->query("SELECT entity_id, bundle FROM {node__field_meta_tags}");
//    $result = $query->fetchAll();


    foreach($nids as $nid ) {
//      print_r($nid);
      $node = \Drupal::entityTypeManager()->getStorage($entity_type)->load($nid);

//      print_r($node);
//      drush_print($node->nid->value);
//      drush_print($node->getType());
//      print_r($node->get('field_tags')->getValue());

//      $node->field_tag->value;

      $node_type = $node->getType();
      $node_nid = $node->nid->value;
      $node_url = 'https://diplomacy.edu' . \Drupal::service('path_alias.manager')->getAliasByPath('/node/'. $node_nid, NULL);
      $term_ids = $node->get('field_tags')->getValue();

      $tags = '';
      foreach ($term_ids as $key => $value) {
//        drush_print($value['target_id']);
        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($value['target_id']);
//        print_r($term->get('name')->getValue()[0]['value']);
        if ($term) {
          $tags .= $term->get('name')->getValue()[0]['value'] . ', ';
        }
      }

      $rows[] = [
        'content_type' => $node_type,
        'nid' => $node_nid,
        'url' => $node_url,
        'tags' => $tags
      ];
    }

//    print_r(count($nids));

    return new RowsOfFields($rows);

  }
}


