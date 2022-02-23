<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;
use ChadoSearch\sql\ColumnCond;

/*************************************************************
 * Definition for the Search form
 */
function chado_search_file_search_form ($form) {
  $form->addTextFilter(
      Set::textFilter()
      ->id('name')
      ->title('File Name')
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('description')
      ->title('Description')
      ->newLine()
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('type')
      ->title('File Type')
      ->column('type')
      ->table('chado_search_file_search')
      ->cache(TRUE)
      ->labelWidth(120)
      ->newLine()
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('license')
      ->title('File License')
      ->column('license')
      ->table('chado_search_file_search')
      ->cache(TRUE)
      ->labelWidth(120)
      ->newLine()
  );

  $form->addSubmit();
  $form->addReset();

  $desc = 'Search for files by entering full or partial names or descriptions in the fields below.'
    . ' If you are unsure of the name, you can limit search results by file type.';
  $form->addFieldset(
      Set::fieldset()
      ->id('file_search')
      ->startWidget('name')
      ->endWidget('reset')
      ->description($desc)
  );
  return $form;
}

/*************************************************************
 * Submit the form
 */
function chado_search_file_search_form_submit ($form, &$form_state) {
  // Create base sql
  $sql = "SELECT * FROM {chado_search_file_search}";
  // Add conditions from search form
  $where = [];
  $where[] = Sql::textFilter('name', $form_state, 'name');
  $where[] = Sql::textFilter('description', $form_state, 'description');
  $where[] = Sql::selectFilter('type', $form_state, 'type');
  $where[] = Sql::selectFilter('license', $form_state, 'license');

  Set::result()
    ->sql($sql)
    ->where($where)
    ->defaultOrder('name')
    ->tableDefinitionCallback('chado_search_file_search_table_definition')
    ->execute($form, $form_state);
}

/*************************************************************
 * Definition for layout of the search result table
 */
function chado_search_file_search_table_definition () {
  $headers = array(
      'type:s' => 'File Type',
      'name:s:chado_search_link_file:id' => 'File Name',
      'description:s' => 'Description',
      'license:s' => 'License',
  );
  return $headers;
}

/*************************************************************
 * Link file to entity
 *
 * This replaces a call to chado_search_link_entity(), which cannot be used
 * because that function first tries to make a Tripal v2 link, and that throws
 * an error because the table chado_file does not exist
 */
function chado_search_link_file ($file_id) {
  $base_table = 'file';
  $link = NULL;
  if (function_exists('chado_get_record_entity_by_table') && $file_id) {
    $entity_id = chado_get_record_entity_by_table ($base_table, $file_id);
    if ($entity_id) {
      $link = "/bio_data/$entity_id";
    }
  }
  return $link;
}
