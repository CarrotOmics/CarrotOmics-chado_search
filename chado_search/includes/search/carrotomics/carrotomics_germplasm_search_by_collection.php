<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_germplasm_search_by_collection_form ($form) {
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
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('collection')
      ->title('Collection')
      ->column('collection')
      ->table('chado_search_germplasm_search_by_collection')
      ->multiple(TRUE)
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('accession')
      ->title('Accession')
  );
  $form->addSubmit();
  $form->addReset();
  $form->addFieldset(
      Set::fieldset()
      ->id('germplasm_search_by_collection')
      ->startWidget('collection')
      ->endWidget('reset')
      ->description(t('Search germplasm by collection. See the <a href="@url">Collection List</a> for details about each of the collections.',
                      ['@url' => '/collections']))
  );
  return $form;
}

// Submit the form
function chado_search_germplasm_search_by_collection_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = "SELECT * FROM {chado_search_germplasm_search_by_collection}";
  // Add conditions
  $where = array();
  $where [] = Sql::selectFilter('collection', $form_state, 'collection');
  $where [] = Sql::textFilter('accession', $form_state, 'version');
  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_germplasm_search_by_collection_table_definition')
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_germplasm_search_by_collection_table_definition () {
  $headers = array(
    'uniquename:s:chado_search_link_stock:stock_id' => 'Germplasm',
    'collection:s' => 'Collection',
    'db:s' => 'Database',
    'organism:s:chado_search_link_organism:organism_id' => 'Species',
    'genome:s' => 'Genome',
    //'version:s:chado_search_germplasm_search_by_collection_link_accession:db,accession,urlprefix' => 'Accession'
  );
  return $headers;
}
//// Define call back to link the accession
//function chado_search_germplasm_search_by_collection_link_accession ($params = NULL) {
//  $db = $params[0];
//  $acc = $params[1];
//  $urlprefix = $params[2];
//  if ($urlprefix && $acc) {
//    if ($db == 'GRIN_PVP') {
//      $acc = str_replace('PVP ', '', $acc);
//    }
//    return "$urlprefix$acc";
//  } else {
//    return NULL;
//  }
//}
