// TODO: Why can't h5pintegration.js just hook into the H5P namespace instead of creating its own?
var H5PIntegration = H5PIntegration || {};
var H5P = H5P || {};

$(document).ready(function () {
  H5P.loadedJs = Drupal.settings.h5p !== undefined && Drupal.settings.h5p.loadedJs !== undefined ? Drupal.settings.h5p.loadedJs : [];
  H5P.loadedCss = Drupal.settings.h5p !== undefined && Drupal.settings.h5p.loadedCss !== undefined ? Drupal.settings.h5p.loadedCss : [];
});

H5PIntegration.getJsonContent = function (contentId) {
  return Drupal.settings.h5p.content['cid-' + contentId].jsonContent;
};

H5PIntegration.getContentPath = function (contentId) {
  if (Drupal.settings.h5p !== undefined && contentId !== undefined) {
    return Drupal.settings.h5p.jsonContentPath + contentId + '/';
  }
  else if (Drupal.settings.h5peditor !== undefined)  {
    return Drupal.settings.h5peditor.filesPath + '/h5peditor/';
  }
};

/**
 * Get the path to the library
 *
 * TODO: Make this use machineName instead of machineName-majorVersion-minorVersion
 *
 * @param {string} library
 *  The library identifier as string, for instance 'downloadify-1.0'
 * @returns {string} The full path to the library
 */
H5PIntegration.getLibraryPath = function (library) {
  // TODO: This is silly and needs to be changed, why does the h5peditor have its own namespace for these things?
  var libraryPath = Drupal.settings.h5p !== undefined ? Drupal.settings.h5p.libraryPath : Drupal.settings.h5peditor.libraryPath

  return Drupal.settings.basePath + libraryPath + library;
};

H5PIntegration.getFullscreen = function (contentId) {
  return Drupal.settings.h5p.content['cid-' + contentId].fullScreen === '1';
};

H5PIntegration.fullscreenText = Drupal.t('Fullscreen');