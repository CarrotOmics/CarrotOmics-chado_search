<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_paa_search_form ($form) {
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('type')
      ->title('Type')
      ->column('type')
      ->table('chado_search_paa_search')
      ->multiple(TRUE)
      ->labelWidth(130)
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('name')
      ->title('Name')
      ->labelWidth(130)
  );
  $form->addFile(
      Set::file()
      ->id('name_file_inline')
      ->labelWidth(1)
      );
  $form->addMarkup(
      Set::markup()
      ->id('name_example')
      ->text('(e.g. Annotation, Assembly)')
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('description')
      ->title('Description')
      ->labelWidth(130)
  );
  $form->addFile(
      Set::file()
      ->id('description_file_inline')
      ->labelWidth(1)
      );
  $form->addMarkup(
      Set::markup()
      ->id('description_label_example')
      ->text('(e.g. Variants, GBS)')
      ->newLine()
  );
  $form->addSubmit();
  $form->addReset();
  $desc = 'Search within any or all of the content types: Project, Analysis, and Assay';
  $form->addFieldset(
      Set::fieldset()
      ->id('paa_search')
      ->startWidget('type')
      ->endWidget('reset')
      ->description($desc)
  );
  return $form;
}

// Submit the form
function chado_search_paa_search_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = chado_search_paa_search_base_query();
  // Add conditions
  $where = array();
  $where[] = Sql::selectFilter('type', $form_state, 'type');
  $where[] = Sql::textFilter('name', $form_state, 'name');
  $where [] = Sql::file('name_file_inline', 'name');
  $where[] = Sql::textFilter('description', $form_state, 'description');
  $where [] = Sql::file('description_file_inline', 'description');
  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_paa_search_table_definition')
    ->execute($form, $form_state);
}

/*************************************************************
 * SQL
*/
// Define query for the base table. Do not include the WHERE clause
function chado_search_paa_search_base_query() {
  $query = 
    "SELECT * FROM {chado_search_paa_search}";
  return $query;
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_paa_search_table_definition () {
  $headers = [
    'name:s:chado_search_link_paa:type,id' => 'Name',
    'type:s' => 'Type',
    'description:s' => 'Description',
  ];
  return $headers;
}

// Define the download table
function chado_search_paa_search_download_definition () {
  $headers = [
      'name' => 'Name',
      'type' => 'Type',
      'description' => 'Description',
  ];
  return $headers;
}

// callback for looking up entity from any of three different entity types
function chado_search_link_paa ($paras) {
  $id_type = $paras[0];
  $id_number = $paras[1];
  $result = chado_search_link_entity(strtolower($id_type), $id_number);
  return $result;
}
