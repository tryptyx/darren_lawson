<?php
/**
 * @file
 * Hooks provided by the Discography framework.
 *
 * These hooks should be implemented by someone who is developing a Discography
 * Provider Adapter module.
 */

/**
 * Provide information about the Discography Provider.
 *
 * This hook provides information to the Discography Mediator about this
 * module, and minimal information about the third-party API that this module
 * is adapting. This information will be displayed on the forms generated by
 * the Discography Mediator module.
 *
 * @return array of information. Array must have this structure:
 * - name: short, human-readable name of the provider.
 * - description: (optional) brief description of what the user does with the
 *   provider.
 * - search_types: an array of search types. The keys are the
 *   machine-readable version of the search type, and the values are the human-
 *   readable version of the search type.
 * - returns_releases: An array specifying which search types
 *   immediately return releases (and not, say, artists or labels).
 *   The array should be a list of machine-readable search types from the
 *   search_types array.
 * - search_filters: (optional) array of search filters. If the user selects a
 *   filter, the search will only return results matching the filter type.
 *   Each key of this array is a search_type. Each value is an array of
 *   filters for that particular search type, where the key is the machine-
 *   readable name for the filter, and the value is the human-readable name for
 *   the filter. If a particular search type has no filters, leave that type
 *   out of the array.
 *   If you do not include this, you must implement results filtering yourself
 *   using the search types. For example, if the search_type is "artist," that
 *   search should only return artists.
 *   Search filters are completely optional. Providing filters is supported
 *   for third parties that have advanced search capabilities.
 */
function hook_discog_provider_info() {
  return array(
    'name' => 'Example',
    'description' => 'Search and import releases from api.example.com',
    'search_types' => array(
      'release' => 'Releases',
      'artist'  => 'Artists',
      'label'   => 'Labels',
      'genre'   => 'Genres',
      'catno'   => 'Catalog Number',
      'hat'     => 'Hat Size',
    ),
    'returns_releases' => array('release', 'catno'),
    'search_filters' => array(
      // Filter releases by release type
      'release' => array(
        'all'      => 'All Releases',
        'full'     => 'Full-length albums',
        'single'   => 'Singles',
        'ep'       => 'EP\'s',
        'download' => 'Download-only releases',
      ),
      // Filter hat sizes by hat type
      'hat' => array(
        'all'       => 'All Hats',
        'banjo'     => 'Banjo Paterson Hat',
        'cattleman' => 'Cattleman Hat',
        'fedora'    => 'Panama Fedora',
        'outback'   => 'Tilley Outback Hat',
      ),
      // Because they don't have array keys, the artist, label, genre, and
      // catno search types don't have filters
    ),
  );
}

/**
 * Handle a search query to the third-party API.
 *
 * This hook should be implemented by someone who is developing a Discography
 * Provider Adapter module.
 *
 * @param $term Search term
 * @param $type Search type (defined in hook_discog_provider_info)
 * @param $filter Search filter (defined in hook_discog_provider_info)
 * @param $per_page Number of results to return (for pagination)
 * @param $offset Skip this number of results (for pagination)
 * @return Array, with these keys:
 * - total_results [REQUIRED]: The total number of results, as an integer
 * - results: An array containing these keys:
 *   - id: The ID of the result (e.g. artist ID or label ID)
 *   - title: The title of the result (e.g. artist or label name)
 *   - thumb_url: (optional) link to thumbnail image on provider's website
 *   - provider_url: (optional) link to result on provider's website
 *   - release_artist: (optional) release artist (if returning releases)
 *   - release_format: (optional) release format (if returning releases)
 *   - release_label: (optional) record label (if returning releases)
 *   - release_catno: (optional) catalog number (if returning releases)
 *   If you include any other fields, you must override
 *   theme_discog_option_text().
 */
function hook_discog_search($term, $type, $filter, $per_page, $offset) {
  // Define and initialize variables
  $data    = array();
  $req     = NULL;
  $json    = array();
  $options = array();
  $url     = '';

  // Build the query
  $url     = 'http://api.example.com/search';
  $options = array(
    'q'        => $term,
    'type'     => $type,
    'filter'   => $filter,
    'page'     => $page,
    'per_page' => $per_page,
  );

  // Send the API request via HTTP. You may have to do more than this,
  // depending upon the third-party API.
  $req  = drupal_http_request($url, $options);
  $json = drupal_json_decode($req);

  /* The fields below are just examples. The actual JSON fields would depend
   * upon the third party API; they may even be arrays themselves.
   * Also, for simplicity, I am omitting validation, which you should not do.
   */

  // Set total results
  if (isset($json['total'])) {
    $data['total_results'] = $json['total'];
  }

  // Build results sub-array
  if (isset($json[$results])) {
    foreach ($json[$results] as $delta => $result) {
      // Required by the Discography Mediator
      $data['results'][$delta]['title'] = $result['title'];
      $data['results'][$delta]['id']    = $result['id'];
      // Not required, but exist in most Discogs API queries
      if (isset($result['thumb'])) {
        $data['results'][$delta]['thumb_url'] = $result['thumb'];
      }
      if(isset($result['url'])) {
        $data['results'][$delta]['provider_url'] = $result['url'];
      }
      if (isset($result['artist'])) {
        $data['results'][$delta]['release_artist'] = $result['artist'];
      }
      if (isset($result['format'])) {
        $data['results'][$delta]['release_format'] = $result['format'];
      }
      if (isset($result['label'])) {
        $data['results'][$delta]['release_label'] = $result['label'];
      }
      if (isset($result['catno'])) {
        $data['results'][$delta]['release_catno'] = $result['catno'];
      }
    }
  }
  return $data;
}

/**
 * Handle a query of a particular artist, label, etc. for their releases.
 *
 * This hook should be implemented by someone who is developing a Discography
 * Provider Adapter module.
 *
 * @param $type Search type (defined in hook_discog_provider_info)
 * @param $id ID of entity type to return
 * @param $per_page Number of releases to return (for pagination)
 * @param $offset Skip this number of releases (for pagination)
 * @return Array of results, with these keys:
 * - total_results: The total number of results, as an integer
 * - info: (optional) array of information about this particular artist,
 *   label, etc. to be displayed before the releases returned. If you decide to
 *   include it, these keys are supported by the Mediator's default theme:
 *   - name: (optional) name of the artist/label/etc.
 *   - description: (optional) description of artist/label/etc.
 *   - provider_url: (optional) link back to information on provider's website
 *   - image_url: (optional) URL to image on provider's website
 *   If you include any other fields, you must override
 *   theme_discog_info_text().
 * - results: An array containing these keys:
 *   - id: The ID of the result (e.g. artist ID or label ID)
 *   - title: The title of the result (e.g. artist or label name)
 *   - thumb_url: (optional) link to thumbnail image on provider's website
 *   - provider_url: (optional) link to result on provider's website
 *   - release_artist: (optional) release artist (if returning releases)
 *   - release_format: (optional) release format (if returning releases)
 *   - release_label: (optional) record label (if returning releases)
 *   - release_catno: (optional) catalog number (if returning releases)
 *   If you include any other fields, you must override
 *   theme_discog_option_text().
 */
function hook_discog_fetch_releases($type, $id, $per_page, $offset) {
  // Define and initialize variables
  $data    = array();
  $req     = NULL;
  $json    = array();
  $url     = '';

  /* You'll notice that the beginning of this function contains almost exactly
   * the same code as hook_discog_search(). It might be a good idea to put it
   * into its own helper function.
   */

  // Build the query data array
  $url = 'http://api.example.com/' . $type . '/' . $id . '/releases';

  // Send the API request via HTTP.
  $req  = drupal_http_request($url, $options);
  $json = drupal_json_decode($req);

  // Set total results
  if (isset($json['total'])) {
    $data['total_results'] = $json['total'];
  }

  // Build results sub-array
  if (isset($json[$results])) {
    foreach ($json[$results] as $delta => $result) {
      $data['results'][$delta]['title'] = $result['title'];
      $data['results'][$delta]['id']    = $result['id'];
      if (isset($result['thumb'])) {
        $data['results'][$delta]['thumb_url'] = $result['thumb'];
      }
      if(isset($result['url'])) {
        $data['results'][$delta]['provider_url'] = $result['url'];
      }
      if (isset($result['artist'])) {
        $data['results'][$delta]['release_artist'] = $result['artist'];
      }
      if (isset($result['format'])) {
        $data['results'][$delta]['release_format'] = $result['format'];
      }
      if (isset($result['label'])) {
        $data['results'][$delta]['release_label'] = $result['label'];
      }
      if (isset($result['catno'])) {
        $data['results'][$delta]['release_catno'] = $result['catno'];
      }
    }
  }

  /* The Discography Mediator form can display information before the list of
   * releases. Let's add that now, by performing a separate query.
   * (Again, JSON fields will depend upon the third party API.)
   */
  $url = 'http://api.example.com/' . $type . '/' . $id;
  $req  = drupal_http_request($url, $options);
  $json = drupal_json_decode($req);

  if (isset($json[$type])) {
    $data['info']['name'] = $json[$type];
  }
  if (isset($json['profile'])) {
    $data['info']['description'] = $json['profile'];
  }
  if (isset($json['url'])) {
    $data['info']['provider_url'] = $json['url'];
  }
  if (isset($json['image'])) {
    $data['info']['image_url'] = $json['image'];
  }

  return $data;
}

/**
 * Handle the retrieval of a release with a given ID.
 *
 * This hook should be implemented by someone who is developing a Discography
 * Provider Adapter module.
 *
 * @return Array with the following keys:
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
 *     Note that this should be a field; you should NOT use the track position
 *     as the key.
 *   - track_title: the name of the track
 *   - track_duration: (optional) duration (in MM:SS format)
 *   - track_artist: (optional) track artist (for e.g. compilations)
 *   - track_notes: (optional) track notes
 *   - track_lyrics: (optional) lyrics
 *
 */
function hook_discog_fetch_release($id) {
  // Define and initialize variables
  $data = array();
  $json = array();
  $url  = '';

  // Build the query data array
  $url = 'http://api.example.com/releases/' . $id;

  // Send the API request via HTTP.
  $req  = drupal_http_request($url, $options);
  $json = drupal_json_decode($req);

  // Set up the $data array's required fields, so that if the JSON object
  // didn't return usable values, at least required fields won't be unset
  $data = array(
    'title'          => '',
    'release_artist' => '',
    'release_tracks' => array(),
  );
  // Parse JSON data for the release
  if (isset($json['title'])) {
    $data['title'] = $json['title'];
  }
  if (isset($json['artists'])) {
    $data['release_artist'] = $json['artist'];
  }
  if (isset($json['labels'])) {
    $data['release_label'] = $json['label'];
  }
  if (isset($json['catno'])) {
    $data['release_catno'] = $json['catno'];
  }
  if (isset($json['format'])) {
    $data['release_format'] = $json['format'];
  }
  if (isset($json['country'])) {
    $data['release_country'] = $json['country'];
  }
  if (isset($json['released'])) {
    $data['release_date'] = $json['released'];
  }
  if (isset($json['extraartists'])) {
    $data['release_date'] = $json['extraartists'];
  }
  if (isset($json['notes'])) {
    $data['release_notes'] = $json['notes'];
  }

  // Merge styles and genres into categories
  $data['release_categories'] = array();
  if (isset($json['styles'])) {
    $data['release_categories'] = $json['styles'];
  }
  if (isset($json['genres'])) {
    $data['release_categories'] = array_merge(
        (array) $data['release_categories'],
        (array) $json['genres']);
  }

  // Parse JSON data for tracks
  if (isset($json['tracklist'])) {
    foreach ($json['tracklist'] as $delta => $track) {
      if (isset($track['position'])) {
        $data['release_tracks'][$delta]['track_position'] = $track['position'];
      }
      else {
        // Required, so use $delta as track position
        $data['release_tracks'][$delta]['track_position'] = $delta;
      }
      if (isset($track['title'])) {
        $data['release_tracks'][$delta]['track_title'] = $track['title'];
      }
      else {
        // Required - use some sort of default, or possibly skip track
        $data['release_tracks'][$delta]['track_title'] = 'DEFAULT';
      }
      if (isset($track['duration'])) {
        $data['release_tracks'][$delta]['track_duration'] = $track['duration'];
      }
      if (isset($track['artists'])) {
        $data['release_tracks'][$delta]['track_artist'] = $track['artists'];
      }
      if (isset($track['extraartists'])) {
        $data['release_tracks'][$delta]['track_notes'] = $track['extraartists'];
      }
      if (isset($track['lyrics'])) {
        $data['release_tracks'][$delta]['lyrics'] = $track['lyrics'];
      }
    }
  }
  // This assumes the first image is the primary image
  if (isset($json['images'])) {
    $data['release_images']['image_urls'] = $json['images'];
    $data['release_images']['primary']    = 0;
  }

  return $data;
}
