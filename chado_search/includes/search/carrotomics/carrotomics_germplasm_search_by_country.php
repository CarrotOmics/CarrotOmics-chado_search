<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_germplasm_search_by_country_form ($form) {
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
      ->id('country')
      ->title('Country')
      ->column('country')
      ->table('chado_search_germplasm_search_by_country')
      ->multiple(TRUE)
  );
  $form->addSubmit();
  $form->addReset();
  $form->addFieldset(
      Set::fieldset()
      ->id('germplasm_search_by_country')
      ->startWidget('country')
      ->endWidget('reset')
      ->description("Search germplasm by country. More than one country can be selected. Note that not all germplasm is assigned to a country.")
  );
  return $form;
}

// Submit the form
function chado_search_germplasm_search_by_country_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = "SELECT * FROM {chado_search_germplasm_search_by_country}";
  // Add conditions
  $where = [];
  $where [] = Sql::selectFilter('country', $form_state, 'country');

  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_germplasm_search_by_country_table_definition')
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_germplasm_search_by_country_table_definition () {
  $headers = [
    'uniquename:s:chado_search_link_stock:stock_id' => 'Germplasm',
    'organism:s:chado_search_link_organism:organism_id' => 'Species',
    'stock_type:s' => 'Stock Type',
    'country:s' => 'Country',
    'description:s' => 'Description',
  ];
  return $headers;
}
