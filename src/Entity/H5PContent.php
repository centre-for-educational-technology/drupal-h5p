<?php

namespace Drupal\h5p\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\h5p\H5PDrupal\H5PDrupal;

/**
 * Defines the h5p content entity.
 *
 * @ContentEntityType(
 *   id = "h5p_content",
 *   label = @Translation("H5P Content"),
 *   base_table = "h5p_content",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 * )
 */
class H5PContent extends ContentEntityBase implements ContentEntityInterface {

  protected $library;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    // Standard field, used as unique if primary index.
    $fields['id']->setDescription(t('The ID of the H5P Content entity.'));

    $fields['library_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Library ID'))
      ->setDescription(t('The ID of the library we instanciate using our parameters.'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'normal')
      ->setRequired(TRUE);

    $fields['parameters'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Parameters'))
      ->setDescription(t('The raw/unsafe parameters.'))
      ->setSetting('size', 'big')
      ->setRequired(TRUE);

    $fields['filtered_parameters'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Filtered Parameters'))
      ->setDescription(t('The filtered parameters that are safe to use'))
      ->setSetting('size', 'big')
      ->setDefaultValue('');

    $fields['disabled_features'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Disabled Features'))
      ->setDescription(t('Keeps track of which features has been disabled for the content.'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'small')
      ->setDefaultValue(0);

    return $fields;
  }

  /**
   * Load library used by content
   */
  protected function loadLibrary() {
    $this->library = db_query(
        "SELECT machine_name AS name,
                major_version AS major,
                minor_version AS minor,
                embed_types,
                fullscreen
           FROM {h5p_libraries}
          WHERE library_id = :id",
        array(
          ':id' => $this->get('library_id')->value
        ))
        ->fetchObject();
  }

  /**
   *
   */
  public function isDivEmbeddable() {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    return (strpos($this->library->embed_type, 'iframe') === FALSE);
  }

  /**
   *
   */
  protected function getExportURL() {
    if (empty(\Drupal::state()->get('h5p_export'))) {
      return '';
    }

    $h5p_path = \Drupal::state()->get('h5p_default_path') ?: 'h5p';
    return file_create_url("public://{$h5p_path}/exports/interactive-content-" . $this->id() . '.h5p');
  }

  /**
   *
   */
  public function getH5PIntegrationSettings() {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    $content = [
      'id' => $this->id(),
      'slug' => 'interactive-content', // TODO: Add a title or something?
      'library' => (array) $this->library,
      'params' => $this->get('parameters')->value,
      'filtered' => $this->get('filtered_parameters')->value,
    ];

    $core = H5PDrupal::getInstance('core');
    $filtered_parameters = $core->filterParameters($content);
    $display_options = $core->getDisplayOptionsForEdit($this->get('disabled_features')->value);

    // TODO: Implement hook filtered_params ?
    //   \Drupal::moduleHandler()->alter('h5p_params', $files['scripts'], $library_list, $embed_type);

    // TODO: Get preloaded user data
/*    $results = db_query("SELECT sub_content_id, data_id, data
                          FROM {h5p_content_user_data}
                          WHERE user_id = :user_id
                          AND content_main_id = :content_id
                          AND preloaded = 1",
      array(
        ':user_id' => \Drupal::currentUser()->id(),
        ':content_id' => $content['mainId']
      ));

    $content_user_data = array(
      0 => array(
        'state' => '{}'
      )
    );
    foreach ($results as $result) {
      $content_user_data[$result->sub_content_id][$result->data_id] = $result->data;
    }
    */

    $embed_url = Url::fromUri('internal:/h5p/embed/' . $this->id(), ['absolute' => TRUE])->toString();
    $resizer_url = Url::fromUri('internal:/vendor/h5p/h5p-core/js/h5p-resizer.js', ['absolute' => TRUE, 'language' => FALSE])->toString();

    return array(
      'library' => "{$this->library->name} {$this->library->major}.{$this->library->minor}",
      'jsonContent' => $filtered_parameters,
      'fullScreen' => $this->library->fullscreen,
      'exportUrl' => $this->getExportURL(),
      'embedCode' => '<iframe src="' . $embed_url . '" width=":w" height=":h" frameborder="0" allowfullscreen="allowfullscreen"></iframe>',
      'resizeCode' => '<script src="' . $resizer_url . '" charset="UTF-8"></script>',
      'url' => $embed_url,
      'title' => 'Not Available', // TODO: Add title?
//      'contentUserData' => $content_user_data,
      'displayOptions' => $display_options,
    );
  }
}