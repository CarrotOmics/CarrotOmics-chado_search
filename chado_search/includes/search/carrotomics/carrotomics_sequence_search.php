<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_sequence_search_form ($form) {
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('genus')
      ->title('Genus')
      ->column('genus')
      ->table('chado_search_sequence_search')
      ->cache(TRUE)
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('species')
      ->title('Species')
      ->dependOnId('genus')
      ->callback('chado_search_sequence_search_ajax_species')
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('infraspecific_name')
      ->title('Infraspecific name')
      ->dependOnId('species')
      ->callback('chado_search_sequence_search_ajax_infraspecific')
      ->newLine()
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('feature_type')
      ->title('Type')
      ->column('feature_type')
      ->table('chado_search_sequence_search')
      ->multiple(TRUE)
      ->newLine()
      ->cache(TRUE)
  );
  $icon = '/' . drupal_get_path('module', 'chado_search') . '/theme/images/question.gif';
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('analysis')
      ->title('Dataset <a href="/sequence_dataset_description"><img src="' . $icon . '"></a>')
      ->column('analysis_name')
      ->table('chado_search_sequence_search')
      ->multiple(TRUE)
      ->optGroupByPattern(array('Genbank Genes' => 'NCBI', 'Predicted Genes' => 'Genome|genome', 'Unigene' => 'Unigene|unigene', 'RefTrans' => 'RefTrans'))
      ->cache(TRUE)
      ->newLine()
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('location')
      ->title('Location')
      ->dependOnId('analysis')
      ->callback('chado_search_sequence_search_ajax_location')
  );
  $form->addBetweenFilter(
      Set::betweenFilter()
      ->id('fmin')
      ->title("between")
      ->id2('fmax')
      ->title2("and")
      ->labelWidth2(50)
      ->size(15)
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('feature_name')
      ->title('Name')
      ->newLine()
  );
  $form->addFile(
      Set::file()
      ->id('feature_name_file')
      ->title("File Upload")
      ->description("Provide sequence names in a file. Separate each name by a new line.")
      ->newLine()
  );
  $form->addSubmit();
  $form->addReset();
  $desc =
  'Search for sequences by entering names in the field below. Alternatively, you may upload a file of names. 
      You may also filter results by sequence type and the sequence source. To select multiple options click while 
      holding the "ctrl" key. The results can be downloaded in FASTA or CSV tabular format.
     <b>| ' . l('Short video tutorial', 'https://www.youtube.com/watch?v=i0IuE1qQn0s', array('attributes' => array('target' => '_blank'))) . ' | ' . l('Text tutorial', 'tutorial/sequence_search') . ' | ' .
       l('Email us with problems and suggestions', 'contact') . '</b>';
  $form->addFieldset(
      Set::fieldset()
      ->id('sequence_search')
      ->startWidget('genus')
      ->endWidget('reset')
      ->description($desc)
  );
  return $form;
}

// Submit the form
function chado_search_sequence_search_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = "SELECT * FROM {chado_search_sequence_search}";
  // Add conditions
  $where = array();
  $where [] = Sql::textFilterOnMultipleColumns('feature_name', $form_state, array('uniquename', 'name'));
  $where [] = Sql::selectFilter('feature_type', $form_state, 'feature_type');
  $where [] = Sql::selectFilter('analysis', $form_state, 'analysis_name');
  $where [] = Sql::fileOnMultipleColumns('feature_name_file', array('uniquename', 'name'));
  $where [] = Sql::selectFilter('genus', $form_state, 'genus');
  $where [] = Sql::selectFilter('species', $form_state, 'species');
  $where [] = Sql::selectFilter('infraspecific_name', $form_state, 'infraspecific_name');
  $where [] = Sql::selectFilter('location', $form_state, 'landmark');
  $where [] = Sql::betweenFilter('fmin', 'fmax', $form_state, 'fmin', 'fmax');
  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_sequence_search_table_definition')
    ->fastaDownload(TRUE)
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_sequence_search_table_definition () {
  $headers = array(
      'name:s:chado_search_link_feature:feature_id' => 'Name',
      'uniquename:s' => 'Uniquename',
      'feature_type:s' => 'Type',
      'organism:s' => 'Organism',
      'analysis_name:s:chado_search_link_analysis:analysis_id' => 'Source',
      'location:s:chado_search_jbrowse_link:location' => 'Location',
  );
  return $headers;
}


/*************************************************************
 * Custom sort functions for DCARv2 assembly
*/
function dcar_rank($text) {
// this function splits the name into two parts, sequence type (Chr MT PT B S C)
// and the number of that type, ignoring ".1" suffix
  $part2 = 0;
  if ($text == 'Any') {  // Rank to display first
    $part1 = 0;
  }
  else {
    preg_match('/DCARv._(\D+)(\d*)/', $text, $matches);
    $part1 = $matches[1];
    if (array_key_exists(2,$matches)) {  // no number for MT or PT
      $part2 = $matches[2];
    }
    if ($part1 == 'Chr') {
      $part1 = 1;
    }
    elseif ($part1 == 'MT') {
      $part1 = 2;
    }
    elseif ($part1 == 'PT') {
      $part1 = 3;
    }
    elseif ($part1 == 'B') {
      $part1 = 4;
    }
    elseif ($part1 == 'S') {
      $part1 = 5;
    }
    else {  // 'C'
      $part1 = 6;
    }
  }
  return([$part1, $part2]);
}

function cmp($a, $b) {
  if ($a == $b) {
    return 0;
  }
  return ($a < $b) ? -1 : 1;
}

function dcar_cmp($a, $b) {
// rank first by sequence type in custom order (0=Any 1=Chr 2=MT 3=PT 4=B 5=S 6=C),
// which has been encoded into integers, and then numerically in ascending
// order by the number of the sequence
  [$a1, $a2] = dcar_rank($a);
  [$b1, $b2] = dcar_rank($b);
  if ($a1 == $b1) {  // same sequence type
    return(cmp($a2,$b2));
  }
  return(cmp($a1,$b1));
}

/*************************************************************
 * AJAX callbacks
*/
// User defined: Populating the landmark for selected organism

function chado_search_sequence_search_ajax_location ($value) {
  $sql = "SELECT distinct landmark FROM {chado_search_sequence_search} WHERE analysis_name IN (:analysis) ORDER BY landmark";
  $list = chado_search_bind_dynamic_select(array(':analysis' => $value), 'landmark', $sql);

  # custom sort order only for DCARv2
  if (array_key_exists("DCARv2_B1", $list)) {
    uasort($list, "dcar_cmp");
  }

  return($list);
}


function chado_search_sequence_search_ajax_species ($val) {
  $sql = "SELECT distinct species FROM {chado_search_sequence_search} WHERE genus = :genus ORDER BY species";
  return chado_search_bind_dynamic_select(array(':genus' => $val), 'species', $sql);
}


function chado_search_sequence_search_ajax_infraspecific ($val) {
  $sql = "SELECT distinct infraspecific_name FROM {chado_search_sequence_search} WHERE species = :species ORDER BY infraspecific_name";
  return chado_search_bind_dynamic_select(array(':species' => $val), 'infraspecific_name', $sql);
}

