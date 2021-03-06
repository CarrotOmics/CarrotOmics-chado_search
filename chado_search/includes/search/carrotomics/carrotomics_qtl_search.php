<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_qtl_search_form ($form) {
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('type')
      ->title('Type')
      ->column('type')
      ->table('chado_search_qtl_search')
      ->multiple(TRUE)
      ->labelWidth(130)
      ->newLine()
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('species')
      ->title('Species')
      ->column('organism')
      ->table('chado_search_qtl_search')
      ->multiple(TRUE)
      ->labelWidth(130)
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('trait_name')
      ->title('Trait Name')
      ->labelWidth(130)
  );
  $form->addFile(
      Set::file()
      ->id('trait_name_file_inline')
      ->labelWidth(1)
      );
  $form->addMarkup(
      Set::markup()
      ->id('trait_name_example')
      ->text('(e.g. Carotene content, resistance, shoot height)')
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('published_symbol')
      ->title('Published Symbol')
      ->labelWidth(130)
  );
  $form->addFile(
      Set::file()
      ->id('published_symbol_file_inline')
      ->labelWidth(1)
      );
  $form->addMarkup(
      Set::markup()
      ->id('published_symbol_example')
      ->text('(not used)')
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('qtl_label')
      ->title('QTL/MTL Label')
      ->labelWidth(130)
  );
  $form->addFile(
      Set::file()
      ->id('qtl_label_file_inline')
      ->labelWidth(1)
      );
  $form->addMarkup(
      Set::markup()
      ->id('qtl_label_example')
      ->text('(e.g. REST, VERN)')
      ->newLine()
  );
  $form->addSubmit();
  $form->addReset();
  $desc =
  'Search QTLs and/or MTLs (Mendelian Trait Loci) by any combination of species, trait category, trait name, published symbol or label.
     <b>| ' . l('Short video tutorial', 'https://www.youtube.com/watch?v=Cu42oH_PXvc', array('attributes' => array('target' => '_blank'))) . ' | ' . l('Text tutorial', 'tutorial/QTL_search') . ' | ' .
       l('Email us with problems and suggestions', 'contact') . '</b>';
  $form->addFieldset(
      Set::fieldset()
      ->id('qtl_search')
      ->startWidget('type')
      ->endWidget('reset')
      ->description($desc)
  );
  return $form;
}

// Submit the form
function chado_search_qtl_search_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = chado_search_qtl_search_base_query();
  // Add conditions
  $where = array();
  $where[] = Sql::selectFilter('type', $form_state, 'type');
  $where[] = Sql::selectFilter('species', $form_state, 'organism');
  //$where[] = Sql::selectFilter('trait_category', $form_state, 'category');
  $where[] = Sql::textFilter('trait_name', $form_state, 'trait');
  $where [] = Sql::file('trait_name_file_inline', 'trait');
  $where[] = Sql::textFilter('published_symbol', $form_state, 'symbol');
  $where [] = Sql::file('published_symbol_file_inline', 'symbol');
  $where[] = Sql::textFilter('qtl_label', $form_state, 'qtl');
  $where [] = Sql::file('qtl_label_file_inline', 'qtl');
  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_qtl_search_table_definition')
    ->execute($form, $form_state);
}

/*************************************************************
 * SQL
*/
// Define query for the base table. Do not include the WHERE clause
function chado_search_qtl_search_base_query() {
  $query = 
    "SELECT * FROM {chado_search_qtl_search}";
  return $query;
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_qtl_search_table_definition () {
  $headers = array(      
    'type:s' => 'Type',
    'qtl:s:chado_search_link_feature:feature_id' => 'Label',
    'trait:s' => 'Trait Name',
    'symbol:s' => 'Published Symbol',
    'map:s:chado_search_link_featuremap:featuremap_id' => 'Map',
    'organism:s:chado_search_link_organism:organism_id' => 'Species'
  );
  return $headers;
}

// Define the download table
function chado_search_qtl_search_download_definition () {
  $headers = array(
      'type' => 'Type',
      'qtl' => 'Label',
      'symbol' => 'Published Symbol',
      'trait' => 'Trait Name',
      'category' => 'Trait Category',
      'study' => 'Study',
      'population' => 'Population',
      'col_marker_uniquename' => 'Colocalizing marker',
      'neighbor_marker_uniquename' => 'Neighboring marker',
      'map' => 'Map',
      'ad_ratio' => 'AD ratio',
      'r2' => 'R2',      
      'organism' => 'Species',
      'reference' => 'Publication'
  );
  return $headers;
}
