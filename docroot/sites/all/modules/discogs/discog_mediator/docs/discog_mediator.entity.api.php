<?php
/**
 * @file
 * Hooks provided by the Discography framework.
 *
 * These hooks should be implemented by someone who is developing a Discography
 * Entity Adapter module.
 *
 * Also included are two helper functions; they provide examples of how to
 * save images and taxonomy terms to a node. You do not need to implement them,
 * though of course you may use them if you want.
 */

/**
 * Provide information about the content type (bundle or node) into which you
 * will save third-party data.
 *
 * This information will be displayed on the form generated by the Discography
 * Mediator module.
 *
 * @return array of information. Array must have this structure:
 * - name: short, human-readable name of the entity or node type.
 * - description: (optional) brief description of the entity or node type.
 *
 */
function hook_discog_type_info() {
  return array(
   'name' => t('My Album'),
   'description' => t('My album content type.'),
  );
}

/**
 * Take a structured array of release data and save it to an entity.
 *
 * The entity is usually a node type, but could theoretically be any bundle, or
 * even multiple bundles if you so desire.
 *
 * @param $release Array of release data.
 * The $release array should have fields with the following keys:
 * - title: release title (named for consistency with node title)
 * - release_artist: primary artist on the release (may be e.g. "Various")
 * - release_label: (optional) record label
 * - release_catno: (optional) catalog number
 * - release_format: (optional) release format (e.g. "CD")
 * - release_country: (optional) release country
 * - release_date: (optional) release date
 * - release_credits: (optional) release credits (producer, guest artists, etc.)
 * - release_notes: (optional) release notes
 * - release_images: (optional) array of URL's to images on provider's site:
 *   - image_urls: zero-indexed array of image URL's
 *   - primary: index of primary image URL (for types with one image per album)
 * - release_categories: (optional) array of release categories (genres,
 *   styles, etc). These are usually turned into taxonomy terms.
 * - release_tracks: an associative array of track values. Each track should be
 *   a 0-indexed array, and each index should be an array with these fields:
 *   - track_position: track position (track number)
 *     Note that this should be a field; you should NOT use the key as the
 *     track position.
 *   - track_title: the name of the track
 *   - track_duration: (optional) duration (in MM:SS format)
 *   - track_artist: (optional) track artist (for e.g. compilations)
 *   - track_notes: (optional) track notes
 *   - track_lyrics: (optional) lyrics
 *
 * @return Array containing information about the creation process.
 * The array should have these fields:
 * - success: boolean
 * - message: (optional) Success/failure message, e.g. "Release X was created
 *   successfully"
 * - nid: (optional) The ID of the inserted node (or other bundle)
 */
function hook_discog_save_release($release) {
  // Define and initialize variables
  $ok         = FALSE;
  $id         = 0;
  $message    = '';
  $title      = '';
  $values     = array();
  $lang       = LANGUAGE_NONE;

  // We need to include node.pages.inc to have access to node hooks
  module_load_include('inc', 'node', 'node.pages');

  // Convert $release data to something we can put into $form_state['values']
  // I set up the system so that the fields match those in the Discography
  // Release content type; YMMV
  foreach ($release as $key => $value) {
    switch ($key) {
      case 'title':
        // Unlike other fields, node title is scalar and non-localized
        $values['title'] = check_plain($value);
        break;
      case 'release_categories':
        // See the helper function below
        $genres = _hook_genres($value);
        if (!empty($genres)) {
          $values['release_genres'][$lang] = $genres;
        }
        break;
      case 'release_images':
        // See the helper function below
        $file = _hook_images($value);
        if (!empty($file)) {
          $values['release_image'][$lang][0] = $file;
        }
        break;
      case 'release_tracks':
        // If you are using Discography Track Fields, this is simple:
        $values[$key][$lang] = $value;
        // If you have your own track type, implement it accordingly
        break;
      case 'release_notes':
        // Deliberate fallthrough - assumes both accept HTML markup
      case 'release_credits':
        $values[$key][$lang][0]['value'] = check_markup($value);
        break;
      default:
        $values[$key][$lang][0]['value'] = check_plain($value);
    }
  }
  // Save meta-info
  $values['language'] = $lang;
  $values['op']       = t('Save');

  // Create a node out of the values, prepare and save it
  $node = (object) $values;
  $node->type = 'YOUR CONTENT TYPE';
  node_object_prepare($node);
  node_save($node);

  // See if there were any errors, and if so, set variables
  if ($node->nid) {
    $ok = TRUE;
    $id = $node->nid;
    $message = t('Release "@title" was created successfully.',
        array('@title' => $values['title']));
  }
  else {
    $message = t('Discography Release Adapter error importing "@title".',
        array('@title' => $values['title']));
  }

  return array(
    'success' => $ok,
    'message' => $message,
    'nid'     => $id,
  );
}

/**
 * Helper function to handle categories. The categories are turned into
 * taxonomy terms and saved with the node.
 *
 * Note that you must have already defined a taxonomy vocabulary for this to
 * work. Put the vocabulary name and ID into the code below, or alter the
 * helper function's signature to accept them if you like.
 *
 * @param $categories Array of categories (genres or styles)
 * @return Array of taxonomy information to insert into the node. If there
 * was a problem saving the taxonomy terms, or if the taxonomy module is not
 * enabled, an empty array is returned.
 */
function _hook_genres($categories) {
  // Sanity check
  if (!module_exists('taxonomy')) {
    return array();
  }
  // Define and initialize variables
  $term_ids  = array();
  $terms     = array();
  $term      = '';

  foreach ($categories as $delta => $category) {
    // See if there is already a taxonomy term for this category
    $terms = taxonomy_get_term_by_name($category, 'YOUR VOCABULARY NAME');
    if (!empty($terms)) {
      // Get the term
      $term = reset($terms);
    }
    else {
      // Create the term
      $term = (object) array(
        'vid' => 'YOUR VOCABULARY ID',
        'name' => $category,
      );
      if (!taxonomy_term_save($term)) {
        continue;
      }
    }
    // Add the term ID to the list
    $term_ids[$delta]['tid'] = $term->tid;
    // Add the actual taxonomy object to the list
    $term_ids[$delta]['taxonomy_term'] = $term;
  }
  // Return term ID's to $form_state
  return $term_ids;
}

/**
 * Helper function to handle images.
 *
 * @param $data Array of image data, passed directly from
 *   $release['release_images']
 * @return Array representing a file object. If there was a problem retreiving
 *   or saving the file, or if the image module is not enabled, an empty array
 *   is returned.
 */
function _hook_images($data) {
  // Sanity check
  if (!module_exists('image')) {
    return array();
  }
  if (empty($data['primary'])) {
    $primary = 0;
  }
  else {
    $primary = $data['primary'];
  }
  $url      = $data['image_urls'][$primary];
  $filename = basename($url);
  $image    = file_get_contents($url);
  if ($image) {
    $file = file_save_data($image, 'public://' . $filename, FILE_EXISTS_REPLACE);
     return (array) $file;
  }
  // If image failed, return blank array
  return array();
}