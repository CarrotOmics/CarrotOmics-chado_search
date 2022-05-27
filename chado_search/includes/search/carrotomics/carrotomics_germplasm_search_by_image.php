<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_germplasm_search_by_image_form ($form) {
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
  $form->addTextFilter(
      Set::textFilter()
      ->id('stock_uniquename')
      ->title('Name')
  );
  $form->addMarkup(
      Set::markup()
      ->id('stock_uniquename_example')
      ->text('(e.g. PI 652329)')
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('legend')
      ->title('Legend')
  );
  $form->addMarkup(
      Set::markup()
      ->id('legend_example')
      ->text('(e.g. leaves, umbel, plant, seed)')
      ->newLine()
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('organism')
      ->title('Organism')
      ->column('organism')
      ->table('chado_search_germplasm_search_by_image')
      ->multiple(TRUE)
      ->newLine()
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('year')
      ->title('Year')
      ->column('year')
      ->table('chado_search_germplasm_search_by_image')
      ->multiple(TRUE)
  );
  $form->addMarkup(
      Set::markup()
      ->id('latest_year_example')
      ->text('Year when image was obtained')
  );
  $form->addSubmit();
  $form->addReset();
  $form->addFieldset(
      Set::fieldset()
      ->id('germplasm_search_by_image')
      ->startWidget('stock_uniquename')
      ->endWidget('reset')
      ->description("Search germplasm with images. Wild card (*) can be used to match the germplasm name.")
  );
  return $form;
}

// Submit the form
function chado_search_germplasm_search_by_image_form_submit ($form, &$form_state) {
  // Get base sql
  $icons = url('sites/default/files/bulk_data/carrotomics/germplasm/icon/');
  $imgs = url('sites/default/files/bulk_data/carrotomics/germplasm/image/');
  $sql = "SELECT *, '<a href=' || '$imgs' || image_uri || ' target=_blank><img src=' || '$icons' || image_uri || '></a>' AS image FROM {chado_search_germplasm_search_by_image}";
  // Add conditions
  $where = array();
  $where [0] = Sql::textFilterOnMultipleColumns('stock_uniquename', $form_state, array('uniquename', 'alias'), FALSE, 'stock_id:chado_search_germplasm_search');
  if ($form_state['values']['stock_uniquename_op'] != 'exactly') {
    $where [0] = str_replace('*', '%', $where[0]);
  }
  $where [] = Sql::textFilter('legend', $form_state, 'legend');
  $where [] = Sql::selectFilter('year', $form_state, 'year');
  $where [] = Sql::selectFilter('organism', $form_state, 'organism');
  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_germplasm_search_by_image_table_definition')
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_germplasm_search_by_image_table_definition () {
  $headers = array(
    'uniquename:s:chado_search_link_stock:stock_id' => 'Germplasm',
    'organism:s:chado_search_link_organism:organism_id' => 'Species',
    'legend:s' => 'Legend',
    'image' => 'Image'
  );
  return $headers;
}
function chado_search_germplasm_search_by_image_download_definition () {
  $headers = array(
    'uniquename' => 'Germplasm',
    'organism' => 'Species',
    'legend' => 'Legend',
    'image_uri' => 'Image'
  );
  return $headers;
}
