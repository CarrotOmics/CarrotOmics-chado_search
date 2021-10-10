<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_species_form ($form) {
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('family')
      ->title('Family')
      ->column('family')
      ->table('chado_search_species')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('genus')
      ->title('Genus')
      ->dependOnId('family')
      ->callback('chado_search_species_ajax_genus')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('species')
      ->title('Species')
      ->dependOnId('genus')
      ->callback('chado_search_species_ajax_species')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('infraspecific_name')
      ->title('Infraspecific name')
      ->dependOnId('species')
      ->callback('chado_search_species_ajax_infraspecific')
  );
  $form->addSubmit();
  $form->addReset();
  $form->addFieldset(
      Set::fieldset()
      ->id('species_summary')
      ->startWidget('family')
      ->endWidget('reset')
  );
  return $form;
}

// Submit the form
function chado_search_species_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = "SELECT * FROM {chado_search_species}";
  $where = array();
  $where [0] = Sql::selectFilter('family', $form_state, 'family');
  $where [1] = Sql::selectFilter('genus', $form_state, 'genus');
  $where [2] = Sql::selectFilter('species', $form_state, 'species');
  $where [3] = Sql::selectFilter('infraspecific_name', $form_state, 'infraspecific_name');
  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_species_table_definition')
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_species_table_definition () {
  $headers = array(
      'organism:s:chado_search_link_organism:organism_id' => 'Species',
//      'infraspecific_type:s' => 'Infraspecific Type',
//      'infraspecific_name:s' => 'Infraspecific Name',
      'num_germplasm:s' => 'Num Germplasm',
      'num_biomaterial:s' => 'Num Biomaterial',
      'num_sequences:s' => 'Num Sequences',
//      'num_libraries:s' => 'Num Libraries'
  );
  return $headers;
}

/*************************************************************
 * AJAX callbacks
 */
// User defined: Populating the landmark for selected organism
function chado_search_species_ajax_genus ($val) {
  $sql = "SELECT distinct genus FROM {chado_search_species} WHERE family = :family ORDER BY genus";
  return chado_search_bind_dynamic_select(array(':family' => $val), 'genus', $sql);
}
function chado_search_species_ajax_species ($val) {
  $sql = "SELECT distinct species FROM {chado_search_species} WHERE genus = :genus ORDER BY species";
  return chado_search_bind_dynamic_select(array(':genus' => $val), 'species', $sql);
}
function chado_search_species_ajax_infraspecific ($val) {
  $sql = "SELECT distinct infraspecific_name FROM {chado_search_species} WHERE species = :species ORDER BY infraspecific_name";
  return chado_search_bind_dynamic_select(array(':species' => $val), 'infraspecific_name', $sql);
}
