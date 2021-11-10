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
    form_set_error('', t('The distance as entered is not valid, it must be a positive number.'));
  }
}

// Submit the form
function chado_search_germplasm_search_by_geolocation_form_submit ($form, &$form_state) {

  // three values from the form are needed to construct the distance filter
  $querylat = geoconvert( $form_state['values']['latitude'] );
  $querylong = geoconvert( $form_state['values']['longitude'] );
  $querydistance = $form_state['values']['distance'];

  // The formula in the subquery calculates the great-circle distance between two
  // points using the Haversine formula, distance units are km
  $sql = "SELECT * FROM (SELECT *,"
       . " ROUND( CAST( 6378.137 * acos( cos( radians($querylat) ) * cos( radians( latitude ) )"
       . " * cos( radians( longitude ) - radians($querylong) ) + sin( radians($querylat) )"
       . " * sin( radians(latitude))) AS NUMERIC), 1)"
       . " AS distance from {chado_search_germplasm_search_by_geolocation}) CALC"
       . " WHERE latitude IS NOT NULL AND longitude IS NOT NULL"
       . " AND distance <= $querydistance"
       . " ORDER BY distance";

  // Add conditions, none added here because we are using Haversine filter above
  $where = [];

  Set::result()
    ->sql($sql)
    ->where($where)
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
 * notation, to decimal degree format
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
  return($value);
}
