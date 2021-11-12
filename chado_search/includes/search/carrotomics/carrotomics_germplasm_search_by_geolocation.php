<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_germplasm_search_by_geolocation_form ($form) {
  $form->addTabs(
      Set::tab()
      ->id('germplasm_search_tabs')
      ->items(['/search/germplasm' => 'Name',
               '/search/germplasm/collection' => 'Collection',
               '/search/germplasm/pedigree' => 'Pedigree',
               '/search/germplasm/country' => 'Country',
               '/search/germplasm/geolocation' => 'Geolocation',
               '/search/germplasm/image' => 'Image'])
  );
  $form->addLabeledFilter(
      Set::labeledFilter()
      ->id('latitude')
      ->title('Latitude')
      );
  $form->addMarkup(
      Set::markup()
      ->id('latitude_example')
      ->text('For example, -12.345 or 12° 20\' 42" W')
      ->newLine()
  );
  $form->addLabeledFilter(
      Set::labeledFilter()
      ->id('longitude')
      ->title('Longitude')
      ->newLine()
      );
  $form->addLabeledFilter(
      Set::labeledFilter()
      ->id('distance')
      ->title('Distance')
      );
  $form->addMarkup(
      Set::markup()
      ->id('distance_example')
      ->text('Enter a maximum distance in kilometers from your reference point')
      ->newLine()
  );

  $form->addSelectFilter(
      Set::selectFilter()
      ->id('family')
      ->title('Familiy')
      ->column('family')
      ->table('chado_search_species')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('genus')
      ->title('Genus')
      ->dependOnId('family')
      ->callback('chado_search_geolocation_ajax_genus')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('species')
      ->title('Species')
      ->dependOnId('genus')
      ->callback('chado_search_geolocation_ajax_species')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('infraspecific_type')
      ->title('Infraspecific type')
      ->dependOnId('species')
      ->callback('chado_search_geolocation_ajax_infraspecific_type')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('infraspecific_name')
      ->title('Infraspecific name')
      ->dependOnId('infraspecific_type')
      ->callback('chado_search_geolocation_ajax_infraspecific_name')
      ->newLine()
  );

  $form->addSubmit();
  $form->addReset();
  $form->addFieldset(
      Set::fieldset()
      ->id('germplasm_search_by_geolocation')
      ->startWidget('latitude')
      ->endWidget('reset')
      ->description("Search germplasm by geolocation coordinates. All germplasm"
                  . " accessions within your selected distance in kilometers from"
                  . " the specified reference point will be returned, sorted by"
                  . " distance. All three values are required to perform a search.")
  );
  return $form;
}

// Validate the form
function chado_search_germplasm_search_by_geolocation_form_validate ($form, &$form_state) {

  // Validate latitude
  $querylat = trim($form_state['values']['latitude']);
  if (!$querylat) {
    form_set_error('', t('Latitude is required.'));
  }
  $querylatval = geoconvert( $querylat );
  if (!$querylatval) {
    form_set_error('', t('Latitude as entered is not a valid latitude.'));
  }
  if (($querylatval < -90.0) or ($querylatval > 90.0)) {
    form_set_error('', t('Latitude must be between -90 and 90.'));
  }

  // Validate longitude
  $querylong = trim($form_state['values']['longitude']);
  if (!$querylong) {
    form_set_error('', t('Longitude is required.'));
  }
  $querylongval = geoconvert( $querylong );
  if (!$querylongval) {
    form_set_error('', t('longitude as entered is not a valid longitude.'));
  }
  if (($querylongval < -180.0) or ($querylongval > 180.0)) {
    form_set_error('', t('Longitude must be between -180 and 180.'));
  }

  // Validate distance
  $querydistance = trim($form_state['values']['distance']);
  if (!$querydistance) {
    form_set_error('', t('A positive distance value in kilomenters is required.'));
  }
  if (!(preg_match('/^[\d\.]+$/', $querydistance))) {
    form_set_error('', t('The distance as entered is not valid, it must be a positive number without "+".'));
  }
  if (preg_match('/\..*\./', $querydistance)) {
    form_set_error('', t('The distance as entered is not valid, it has more than one period character.'));
  }
}

// Submit the form
function chado_search_germplasm_search_by_geolocation_form_submit ($form, &$form_state) {

  // Here the base SQL is rather involved, as we need to calculate distance
  // between two points on the surface of the earth. We use the Haversine
  // formula in a sub query to generate a distance value that we can then
  // use in a WHERE clause. This is then itself embedded in another sub query
  // so that we can use the usual chado query tools for the other taxonomy filters.
  // Three values from the form are needed to construct the distance filter.
  // geoconvert() and form validation will sanitize the user input that is
  // embedded in this SQL.
  $querylat = geoconvert( $form_state['values']['latitude'] );
  $querylong = geoconvert( $form_state['values']['longitude'] );
  $querydistance = $form_state['values']['distance'];

  // The constant 6378.137 is the earth's radius in km
  // The distance calculation is rounded to the nearest 0.1 km
  $sql = "SELECT * FROM (SELECT * FROM (SELECT *,"
       . " ROUND( CAST( 6378.137 * acos( cos( radians($querylat) ) * cos( radians(latitude) )"
       . " * cos( radians(longitude) - radians($querylong) ) + sin( radians($querylat) )"
       . " * sin( radians(latitude))) AS NUMERIC), 1) AS distance"
       . " FROM {chado_search_germplasm_search_by_geolocation}) SUB2"
       . " WHERE latitude IS NOT NULL"
       . " AND longitude IS NOT NULL"
       . " AND distance <= $querydistance) SUB1";

  // Filter by taxonomy values if selected
  $where = [];
  $where[] = Sql::selectFilter('family', $form_state, 'family');
  $where[] = Sql::selectFilter('genus', $form_state, 'genus');
  $where[] = Sql::selectFilter('species', $form_state, 'species');
  $where[] = Sql::selectFilter('infraspecific_type', $form_state, 'infraspecific_type');
  $where[] = Sql::selectFilter('infraspecific_name', $form_state, 'infraspecific_name');

  Set::result()
    ->sql($sql)
    ->where($where)
    ->defaultOrder('distance')
    ->tableDefinitionCallback('chado_search_germplasm_search_by_geolocation_table_definition')
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_germplasm_search_by_geolocation_table_definition () {
  $headers = array(      
    'uniquename:s:chado_search_link_stock:stock_id' => 'Germplasm',
    'organism:s:chado_search_link_organism:organism_id' => 'Species',
    'stock_type:s' => 'Stock Type',
    'country:s' => 'Country',
    'distance:s' => 'Distance (km)',
    'latitude:s' => 'Latitude',
    'latitude_dev:s' => 'Lat. ±',
    'longitude:s' => 'Longitude',
    'longitude_dev:s' => 'Long. ±',
    'altitude:s' => 'Altitude',
    'altitude_dev:s' => 'Alt. ±',
  );
  return $headers;
}

/**
 * Converts an entered latitude or longitude into decimal form
 * from either degrees and decimal minutes, or degree minute second
 * notation, to decimal degree format.
 *
 * @param float $value 
 * @return float decimalized version or null string if error
 */
function geoconvert ( $value ) {
  $value = strtoupper(trim($value));
  // test if already is decimal format e.g. +12.56789
  if (preg_match('/^([\+\-]?\d+[\.\d]*)°?$/u', $value, $matches)) {
    $value = $matches[1];
  }
  // test for degree and decimal minute format e.g. +12 34.0734
  elseif (preg_match('/([+-]?\d+)[°\s]+(\d+[\.\d]*)\'?$/u', $value, $matches)) {
    $value = $matches[1] + $matches[2]/60;
  }
  // test for degree minute second format e.g. 12° 34' 56.78" N
  elseif (preg_match('/(\d+)[°\s]+(\d+)[\'\s]+([\d\.]*)["\s]*([NSEW])/u', $value, $matches)) {
    $sign = 1;
    if (($matches[4] == 'S') or ($matches[4] == 'W')) {
      $sign = -1;
    }
    $value = $sign * ($matches[1] + $matches[2]/60 + $matches[3]/3600);
  }
  // error parsing value, return null string
  else {
    $value = '';
  }
  // final check for more than one decimal place
  if (preg_match('/\..*\./', $value)) {
    $value = '';
  }
  return($value);
}

/*************************************************************
 * AJAX callbacks
 */
function chado_search_geolocation_ajax_genus ($val) {
  $sql = "SELECT distinct genus FROM {chado_search_species} WHERE family = :family ORDER BY genus";
  return chado_search_bind_dynamic_select(array(':family' => $val), 'genus', $sql);
}
function chado_search_geolocation_ajax_species ($val) {
  $sql = "SELECT distinct species FROM {chado_search_species} WHERE genus = :genus ORDER BY species";
  return chado_search_bind_dynamic_select(array(':genus' => $val), 'species', $sql);
}
function chado_search_geolocation_ajax_infraspecific_type ($val) {
  $sql = "SELECT distinct infraspecific_type FROM {chado_search_species} WHERE species = :species ORDER BY infraspecific_type";
  return chado_search_bind_dynamic_select(array(':species' => $val), 'infraspecific_type', $sql);
}
function chado_search_geolocation_ajax_infraspecific_name ($val) {
  $sql = "SELECT distinct infraspecific_name FROM {chado_search_species} WHERE infraspecific_type=:infraspecific_type ORDER BY infraspecific_name";
  return chado_search_bind_dynamic_select(array(':infraspecific_type' => $val), 'infraspecific_name', $sql);
}
