<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_germplasm_search_form ($form) {
  $form->addTabs(
      Set::tab()
      ->id('germplasm_search_tabs')
      ->items(array('/search/germplasm' => 'Name', '/search/germplasm/collection' => 'Collection', '/search/germplasm/pedigree' => 'Pedigree', '/search/germplasm/country' => 'Country', '/search/germplasm/image' => 'Image'))
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('stock_uniquename')
      ->title('Name')
      ->newLine()
  );
  $form->addFile(
      Set::file()
      ->id('stock_uniquename_file')
      ->title("File Upload")
      ->description("Provide germplasm names in a file. Separate each name by a new line.")
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
      ->id('infraspecific_type')
      ->title('Infraspecific type')
      ->dependOnId('species')
      ->callback('chado_search_species_ajax_infraspecific_type')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('infraspecific_name')
      ->title('Infraspecific name')
      ->dependOnId('infraspecific_type')
      ->callback('chado_search_species_ajax_infraspecific_name')
      ->newLine()
  );
  $form->addLabeledFilter(
      Set::labeledFilter()
      ->id('stock_description')
      ->title('Keywords')
      );
  $form->addMarkup(
      Set::markup()
      ->id('stock_description_example')
      ->text('Provide words in germplasm description (e.g. USDA, resistant, yellow)')
      ->newLine()
      );
  $form->addSubmit();
  $form->addReset();
  $form->addFieldset(
      Set::fieldset()
      ->id('germplasm_search')
      ->startWidget('stock_uniquename')
      ->endWidget('reset')
      ->description("Search germplasm by name or alias. Wild card (*) can be used to match any word.")
  );
  return $form;
}

// Submit the form
function chado_search_germplasm_search_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = chado_search_germplasm_search_base_query();
  // Add conditions
  $where = array();
  $where [0] = Sql::textFilterOnMultipleColumns('stock_uniquename', $form_state, array('uniquename', 'alias'), FALSE, 'stock_id:chado_search_germplasm_search');
  if ($form_state['values']['stock_uniquename_op'] != 'exactly') {
    $where [0] = str_replace('*', '%', $where[0]);
  }
  $where [1] = Sql::fileOnMultipleColumns('stock_uniquename_file', array('uniquename', 'alias'), FALSE, FALSE, 'stock_id:chado_search_germplasm_search');
  $where [1] = str_replace('*', '%', $where[1]);
  $where [2] = Sql::selectFilter('family', $form_state, 'family');
  $where [3] = Sql::selectFilter('genus', $form_state, 'genus');
  $where [4] = Sql::selectFilter('species', $form_state, 'species');
  $where [5] = Sql::selectFilter('infraspecific_type', $form_state, 'infraspecific_type');
  $where [6] = Sql::selectFilter('infraspecific_name', $form_state, 'infraspecific_name');
  $where [7] = Sql::labeledFilter('stock_description', $form_state, 'description', FALSE, 'contains');
  $groupby = "stock_id:chado_search_germplasm_search";
  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_germplasm_search_table_definition')
    ->groupby($groupby)
    ->execute($form, $form_state);
}

/*************************************************************
 * SQL
*/
// Define query for the base table. Do not include the WHERE clause
function chado_search_germplasm_search_base_query() {
// The urlprefix has a placeholder for accession value of '{accession}'.
// This can't be passed directly in the replace function, otherwise it is
// assumed to be the name of a chado table and the curly braces are removed,
// so use a double-escaped value \\173 and \\175 to pass them in unharmed
  $query =
    "SELECT
       stock_id,
       string_agg(
         distinct (
           CASE
             WHEN urlprefix <> '' AND urlprefix IS NOT NULL
             THEN
               '<a href=\"' || REGEXP_REPLACE(urlprefix, '\\173accession\\175', accession) || '\">' || accession || '</a>&nbsp;&nbsp; '
             ELSE
               ''
             END
         ), ''
       )
      as links,
      string_agg(
         distinct (
           CASE
             WHEN urlprefix <> '' AND urlprefix IS NOT NULL
             THEN
               accession || '. '
             ELSE
               ''
             END
         ), ''
       )
      as links_for_download,
      *
     FROM {chado_search_germplasm_search}";
  return $query;
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_germplasm_search_table_definition () {
  $headers = array(
    'uniquename:s:chado_search_link_stock:stock_id' => 'Germplasm',
    'organism:s:chado_search_link_organism:organism_id' => 'Species',
    'links:s' => 'External Link',
    'alias:s' => 'Aliases',
    'description:s' => 'Description',
    'genome:s' => 'Genome',
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
function chado_search_species_ajax_infraspecific_type ($val) {
  $sql = "SELECT distinct infraspecific_type FROM {chado_search_species} WHERE species = :species ORDER BY infraspecific_type";
  return chado_search_bind_dynamic_select(array(':species' => $val), 'infraspecific_type', $sql);
}
function chado_search_species_ajax_infraspecific_name ($val) {
  $sql = "SELECT distinct infraspecific_name FROM {chado_search_species} WHERE infraspecific_type=:infraspecific_type ORDER BY infraspecific_name";
  return chado_search_bind_dynamic_select(array(':infraspecific_type' => $val), 'infraspecific_name', $sql);
}
