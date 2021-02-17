<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_qualitative_traits_form ($form) {
  $form->addTabs(
      Set::tab()
      ->id('trait_search_tabs')
      ->items(array('/search/qualitative_traits' => 'Qualitative Trait', '/search/quantitative_traits' => 'Quantitative Trait'))
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('trait1')
      ->title('Trait 1')
      ->column('trait_descriptor')
      ->table('chado_search_qualitative_traits')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('value1')
      ->title('Value 1')
      ->dependOnId('trait1')
      ->callback('chado_search_qualitative_traits_ajax_values')
  );
    $form->addSelectOptionFilter(
        Set::selectOptionFilter()
        ->id('op1')
        ->options(array('and' => 'AND', 'or' => 'OR'))
        ->required(TRUE)
        ->newLine()
    );

    $form->addSelectFilter(
        Set::selectFilter()
        ->id('trait2')
        ->title('Trait 2')
        ->column('trait_descriptor')
        ->table('chado_search_qualitative_traits')
    );
    $form->addDynamicSelectFilter(
        Set::dynamicSelectFilter()
        ->id('value2')
        ->title('Value 2')
        ->dependOnId('trait2')
        ->callback('chado_search_qualitative_traits_ajax_values')
    );
    $form->addSelectOptionFilter(
        Set::selectOptionFilter()
        ->id('op2')
        ->options(array('and' => 'AND', 'or' => 'OR'))
        ->required(TRUE)
        ->newLine()
    );

    $form->addSelectFilter(
        Set::selectFilter()
        ->id('trait3')
        ->title('Trait 3')
        ->column('trait_descriptor')
        ->table('chado_search_qualitative_traits')
    );
    $form->addDynamicSelectFilter(
        Set::dynamicSelectFilter()
        ->id('value3')
        ->title('Value 3')
        ->dependOnId('trait3')
        ->callback('chado_search_qualitative_traits_ajax_values')
    );
    $form->addFieldset(
        Set::fieldset()
        ->id('topLevel')
        ->startWidget('trait1')
        ->endWidget('value3')
    );

  $form->addSubmit();
  $form->addReset();

  return $form;
}

// Submit the form
function chado_search_qualitative_traits_form_submit ($form, &$form_state) {
  $t = array();
  $v = array();
  $t[0] = Sql::selectFilter('trait1', $form_state, 'trait_descriptor');
  $v[0] = Sql::selectFilter('value1', $form_state, 'trait_value');
  $t[1] = Sql::selectFilter('trait2', $form_state, 'trait_descriptor');
  $v[1] = Sql::selectFilter('value2', $form_state, 'trait_value');
  $t[2] = Sql::selectFilter('trait3', $form_state, 'trait_descriptor');
  $v[2] = Sql::selectFilter('value3', $form_state, 'trait_value');
  $op = array();
  $op[1] = $form_state['values']['op1'];
  $op[2] = $form_state['values']['op2'];
  $conditions =Sql::pairConditions($t, $v);
    // Read from $conditions and generate the SQL
    $first_con = true;
    $append = "";
    $changeHeaders = "";
    foreach ($conditions AS $index => $c) {
       if ($first_con) {
         $sql = "SELECT * FROM (SELECT stock_id, variety_name, organism_id, organism, trait_descriptor AS trait$index, trait_value AS value$index, environment AS env$index, project_id AS pid$index, project_name AS prj$index FROM {chado_search_qualitative_traits}";
         $append .= "WHERE $c) T$index";
         $first_con = false;
         $first_table = $index;
       } else {
         if ($op[$index] == 'OR') {
           $append .= " FULL OUTER JOIN (SELECT  stock_id, variety_name, organism_id, organism, trait_descriptor AS trait$index, trait_value AS value$index, environment AS env$index, project_id AS pid$index, project_name AS prj$index FROM {chado_search_qualitative_traits} WHERE $c) T$index USING(stock_id, variety_name, organism_id, organism)";
         }
         else {
           $append .= " INNER JOIN (SELECT variety_name AS variety_name$index, trait_descriptor AS trait$index, trait_value AS value$index, environment AS env$index, project_id AS pid$index, project_name AS prj$index FROM {chado_search_qualitative_traits} WHERE $c) T$index ON (variety_name$index = T$first_table.variety_name";
           if ($op[1] == 'OR' && $index = 2) {
             $append .= " OR T1.variety_name = T$index.variety_name$index)";
           }
           else {
             $append .= ")";
           }
         }
       }
    }
    // If there is no $condition, use a different SQL to group stocks
    if (!$conditions) {
      $disabledCols = "value0;value1;value2;prj0;prj1;prj2";
      $sql = "SELECT first(stock_id) AS stock_id, variety_name, first(organism_id) AS organism_id, first(organism) AS organism, string_agg(trait_descriptor || ' = ' || trait_value, '; ') AS all_traits FROM {chado_search_qualitative_traits} GROUP BY variety_name";
    } else { // If there is $condition, dynamically determine which columns to show
      $disabledCols = "all_traits";
      foreach ($t AS $idx => $enabled) {
        if (!$enabled) {
          $disabledCols .= ";value$idx;prj$idx";
        } else {
          $title = explode('=', $enabled);
          $t = trim($title[1], ' \'');
          $changeHeaders .= "value$idx=$t;";
        }
      }
    }
  Set::result()
    ->sql($sql)
    ->tableDefinitionCallback('chado_search_qualitative_traits_table_definition')
    ->append($append)
    ->disableCols($disabledCols)
    ->changeHeaders($changeHeaders)
    ->rewriteCols('stock_id=chado_search_qualitative_traits_rewrite_stock_id')
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_qualitative_traits_table_definition () {
  $headers = array(
    'variety_name:s:chado_search_link_stock:stock_id' => 'Germplasm',
    'organism:s:chado_search_link_organism:organism_id' => 'Species',
    'all_traits:s' => 'All Traits',
    'value0:s' => 'Trait1',
    'prj0:s:chado_search_link_project:pid0' => 'Project1',
    'value1:s' => 'Trait2',
    'prj1:s:chado_search_link_project:pid1' => 'Project2',
    'value2:s' => 'Trait3',
    'prj2:s:chado_search_link_project:pid2' => 'Project3',
    'stock_id' => 'Image'
  );
  return $headers;
}

function chado_search_qualitative_traits_download_definition () {
  $headers = array(
    'variety_name' => 'Germplasm',
    'organism' => 'Species',
    'all_traits' => 'All Traits',
    'value0' => 'Trait1',
    'prj0' => 'Project1',
    'value1' => 'Trait2',
    'prj1' => 'Project2',
    'value2' => 'Trait3',
    'prj2' => 'Project3',
  );
  return $headers;
}

/*************************************************************
 * AJAX callbacks
*/
function chado_search_qualitative_traits_ajax_values ($val) {
  $sql = "SELECT distinct trait_value FROM {chado_search_qualitative_traits} WHERE trait_descriptor = :descriptor ORDER BY trait_value";
  return chado_search_bind_dynamic_select(array(':descriptor' => $val), 'trait_value', $sql);
}

function chado_search_qualitative_traits_rewrite_stock_id($stock_id) {
  $sql = "SELECT count(eimage_id) FROM chado.stock_image WHERE stock_id = :stock_id";
  $num_image = db_query($sql, array(':stock_id' => $stock_id))->fetchField();
  if ($num_image) {
    $link = chado_search_link_entity('stock', $stock_id);
    return "<a href=\"$link?pane=image\">View $num_image images</a>";
  } else {
    return '';
  }
}
