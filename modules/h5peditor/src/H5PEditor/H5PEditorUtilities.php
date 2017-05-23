<?php

namespace Drupal\h5peditor\H5PEditor;

use Drupal\H5PEditor;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\Core\Url;

class H5PEditorUtilities {

  /**
   * Keeps track of our editor instance, saving valuable resources.
   *
   * @return \H5peditor
   */
  public static function getInstance() {
    static $instance;

    if (!$instance) {

      $core     = H5PDrupal::getInstance('core');
      $instance = new \H5peditor(
        $core,
        new H5PEditorDrupalStorage(),
        new H5PEditorDrupalAjax()
      );
    }

    return $instance;
  }

  /**
   * Get editor settings needed for JS front-end
   *
   * @param int $contentId Currently editing content. 0 for new content.
   *
   * @return array Settings needed for view
   */
  public static function getEditorSettings($contentId = 0) {
    $contentValidator = H5PDrupal::getInstance('contentvalidator');
    $module_path      = drupal_get_path('module', 'h5p');

    $settings = [
      'h5peditor' => [
        'filesPath'          => self::getFilePathForContent($contentId),
        'fileIcon'           => [
          'path'   => base_path() . 'vendor/h5p/h5p-editor/images/binary-file.png',
          'width'  => 50,
          'height' => 50,
        ],
        'ajaxPath'           => self::getAjaxPath($contentId),
        'modulePath'         => 'vendor/h5p',
        'libraryPath'        => $module_path . '/libraries/',
        'copyrightSemantics' => $contentValidator->getCopyrightSemantics(),
        'assets'             => self::getEditorAssets(),
        'contentRelUrl'      => '../h5p/content/',
        'editorRelUrl'       => '../../../vendor/h5p/h5p-editor',
        'apiVersion'         => \H5PCore::$coreApi,
      ],
    ];

    return $settings;
  }

  /**
   * Get assets needed to display editor. These are fetched from core.
   *
   * @return array Js and css for showing the editor
   */
  private static function getEditorAssets() {
    $corePath   = base_path() . "vendor/h5p/h5p-core/";
    $editorPath = base_path() . "vendor/h5p/h5p-editor/";

    $css  = array_merge(
      self::getAssets(\H5PCore::$styles, $corePath),
      self::getAssets(\H5PEditor::$styles, $editorPath)
    );
    $js   = array_merge(
      self::getAssets(\H5PCore::$scripts, $corePath),
      self::getAssets(\H5PEditor::$scripts, $editorPath, ['scripts/h5peditor-editor.js'])
    );
    $js[] = self::getTranslationFilePath();

    return ['css' => $css, 'js' => $js];
  }

  /**
   * Extracts assets from a collection of assets
   *
   * @param array $collection Collection of assets
   * @param string $prefix Prefix needed for constructing the file-path of the assets
   * @param null|array $exceptions Exceptions from the collection that should be skipped
   *
   * @return array Extracted assets from the source collection
   */
  private static function getAssets($collection, $prefix, $exceptions = NULL) {
    $assets      = [];
    $cacheBuster = self::getCacheBuster();

    foreach ($collection as $item) {
      // Skip exceptions
      if ($exceptions && in_array($item, $exceptions)) {
        continue;
      }
      $assets[] = "{$prefix}{$item}{$cacheBuster}";
    }
    return $assets;
  }

  /**
   * Get cache buster
   *
   * @return string A cache buster that may be applied to resources
   */
  private static function getCacheBuster() {
    $cache_buster = \Drupal::state()->get('css_js_query_string');
    return $cache_buster ? "?{$cache_buster}" : '';
  }

  /**
   * Translation file path for the editor. Defaults to English if chosen
   * language is not available.
   *
   * @return string Path to translation file for editor
   */
  private static function getTranslationFilePath() {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $languageFolder = base_path() . 'vendor/h5p/h5p-editor/language';
    $chosenLanguage = "{$languageFolder}/{$language}.js";
    $cacheBuster    = self::getCacheBuster();

    if (file_exists($chosenLanguage)) {
      // Use set language file
      return "$chosenLanguage{$cacheBuster}";
    }
    else {
      // Default to english
      return "{$languageFolder}/en.js{$cacheBuster}";
    }
  }

  /**
   * File path that can be used for saving files that should be bundled with
   * the content.
   *
   * @param int $contentId Id of content that is being edited. 0 means new
   * content.
   *
   * @return string Path to directory where content may be stored
   */
  private static function getFilePathForContent($contentId = 0) {
    $filesBaseFolder = base_path() . H5PDrupal::getRelativeH5PPath();
    if ($contentId) {
      // Files stored in content
      return "{$filesBaseFolder}/content/{$contentId}";
    }
    else {
      // Files stored in editor
      return "{$filesBaseFolder}/editor";
    }
  }

  /**
   * Create URI for ajax the client may send to the server
   *
   * @param int $contentId Id of content that is being edited. 0 is new content.
   *
   * @return \Drupal\Core\GeneratedUrl|string Uri for AJAX
   */
  private static function getAjaxPath($contentId = 0) {
    $securityToken = \H5PCore::createToken('editorajax');
    return Url::fromUri(
      "internal:/h5peditor/{$securityToken}/{$contentId}/"
    )->toString();
  }

  /**
   * Extract library information from library string
   *
   * @param string $library Library string with versioning, e.g. H5P.MultiChoice 1.9
   * @param string $property May be used to only extract certain information
   * about library. Available values are 'all', 'libraryId' and specific property
   *
   * @return int|bool|array One or more properties, or false if invalid.
   */
  public static function getLibraryProperty($library, $property = 'all') {
    $matches = [];
    preg_match_all('/(.+)\s(\d+)\.(\d+)$/', $library, $matches);
    if (count($matches) == 4) {
      $libraryData = [
        'machineName'  => $matches[1][0],
        'majorVersion' => $matches[2][0],
        'minorVersion' => $matches[3][0],
      ];
      switch ($property) {
        case 'all':
          $libraryData['libraryId'] = self::getLibraryId($libraryData);
          return $libraryData;
        case 'libraryId':
          $libraryId = self::getLibraryId($libraryData);
          return $libraryId;
        default:
          return $libraryData[$property];
      }
    }
    else {
      return FALSE;
    }
  }

  /**
   * Library ID from unique library data
   *
   * @param array $libraryData Library data which must contain
   * 'machineName', 'majorVersion' and 'minorVersion'
   *
   * @return null|int Library id
   */
  private static function getLibraryId($libraryData) {
    $select = \Drupal::database()->select('h5p_libraries');
    $select->fields('h5p_libraries', array('library_id'))
           ->condition('machine_name', $libraryData['machineName'])
           ->condition('major_version', $libraryData['majorVersion'])
           ->condition('minor_version', $libraryData['minorVersion']);
    return $select->execute()->fetchField();
  }
}
