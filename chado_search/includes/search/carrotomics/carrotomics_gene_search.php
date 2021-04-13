<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_gene_search_form ($form) {

  $form->addBaseSQL("SELECT * FROM {chado_search_gene_search}");

  $form->addSelectFilter(
      Set::selectFilter()
      ->id('genus')
      ->title('Genus')
      ->column('genus')
      ->table('chado_search_gene_search')
      ->cache(TRUE)
      ->labelWidth(120)
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('species')
      ->title('Species')
      ->dependOnId('genus')
      ->callback('chado_search_gene_search_ajax_species')
      ->labelWidth(80)
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('infraspecific_name')
      ->title('Infraspecific name')
      ->dependOnId('species')
      ->callback('chado_search_gene_search_ajax_infraspecific')
      ->labelWidth(100)
      ->newLine()
  );
  $icon = '/' . drupal_get_path('module', 'chado_search') . '/theme/images/question.gif';
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('analysis')
      ->title('Dataset <a href="/sequence_dataset_description"><img src="' . $icon . '"></a>')
      ->column('analysis')
      ->table('chado_search_gene_search')
      ->multiple(TRUE)
      ->optGroupByPattern(array('Genbank Genes' => 'NCBI', 'Predicted Genes' => 'Genome|genome', 'Unigene' => 'Unigene|unigene', 'RefTrans' => 'RefTrans'))
      ->cache(TRUE)
      ->labelWidth(163)
      ->size(5)
      ->newLine()
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('location')
      ->title('Genome Location')
      ->dependOnId('analysis')
      ->callback('chado_search_gene_search_ajax_location')
      ->labelWidth(163)
  );
  $form->addBetweenFilter(
      Set::betweenFilter()
      ->id('fmin')
      ->title("between")
      ->id2('fmax')
      ->title2("and")
      ->size(15)
      ->labelWidth(70)
      ->labelWidth2(40)
      ->newLine()
  );
  //$form->addTextFilter('feature_name', 'Gene/Transcript Name', FALSE, 60);
  $form->addTextFilter(
      Set::textFilter()
      ->id('feature_name')
      ->title('Gene/Transcript Name')
      ->labelWidth(163)
  );
  //$form->addMarkup('feature_name_example', '(e.g. adh)');
  $form->addFile(
      Set::file()
      ->id('feature_name_file_inline')
      ->labelWidth(1)
      ->newLine()
  );

  $form->addTextFilter(
      Set::textFilter()
      ->id('keyword')
      ->title('Keyword')
      ->labelWidth(163)
  );
  $form->addMarkup(
      Set::markup()
      ->id('keyword_example')
      ->text('(eg. polygalacturonase, resistance, EC:1.4.1.3, cell cycle, ATP binding, zinc finger)')
      ->newLine()
  );
   $customizables = array(
    'organism' => 'Organism',
    'feature_type' => 'Type',
    'analysis' => 'Source',
    'location' => 'Location',
     'blast_value' => 'BLAST',
     'interpro_value' => 'InterPro',
     'kegg_value' => 'KEGG',
     'go_term' => 'GO Term',
     'gb_keyword' => 'GenBank'
  );
  $form->addCustomOutput (
      Set::customOutput()
      ->id('custom_output')
      ->options($customizables)
      ->defaults(array('organism', 'feature_type', 'analysis', 'location'))
  );
  $form->addSubmit();
  $form->addReset();
  $desc =
    'Search genes and transcripts by species, dataset, genome location, name and/or keyword. 
      For keyword, enter any protein name of homologs, KEGG term/EC number, GO term, or InterPro term.  
     <b>| ' . l('Short video tutorial', 'https://youtu.be/P-Rw8i9Iz5E', array('attributes' => array('target' => '_blank'))) . ' | ' . l('Text tutorial', 'tutorial/gene_search') . ' | ' . 
    l('Email us with problems and suggestions', 'contact') . '</b>';
  $form->addFieldset(
      Set::fieldset()
      ->id('gene_search_fields')
      ->startWidget('genus')
      ->endWidget('reset')
      ->description($desc)
  );

  return $form;
}

// Submit the form
function chado_search_gene_search_form_submit ($form, &$form_state) {
  // Add conditions
  $where = array();
  $where [] = Sql::textFilterOnMultipleColumns('feature_name', $form_state, array('uniquename', 'name'));
  $where [] = Sql::selectFilter('analysis', $form_state, 'analysis');
  $where [] = Sql::selectFilter('genus', $form_state, 'genus');
  $where [] = Sql::selectFilter('species', $form_state, 'species');
  $where [] = Sql::selectFilter('infraspecific_name', $form_state, 'infraspecific_name');
  $where [] = Sql::fileOnMultipleColumns('feature_name_file_inline', array('uniquename', 'name'));
  $where [] = Sql::selectFilter('location', $form_state, 'landmark');
  $where [] = Sql::betweenFilter('fmin', 'fmax', $form_state, 'fmin', 'fmax');
  $where [] = Sql::textFilterOnMultipleColumns('keyword', $form_state, array('go_term', 'blast_value', 'kegg_value', 'interpro_value', 'gb_keyword'));

  Set::result()
    ->where($where)
    ->tableDefinitionCallback('chado_search_gene_search_table_definition')
    ->fastaDownload(TRUE)
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_gene_search_table_definition () {
  $headers = array(
    'name:s:chado_search_link_feature:feature_id' => 'Name',
    'organism:s' => 'Organism',
    'feature_type:s' => 'Type',
    'analysis:s' => 'Source',
    'location:s:chado_search_link_jbrowse:srcfeature_id,location' => 'Location',
    'blast_value:s' => 'BLAST',
    'interpro_value:s' => 'InterPro',
    'kegg_value:s' => 'KEGG',
    'go_term:s' => 'GO',
    'gb_keyword:s' => 'GenBank'
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
function chado_search_gene_search_ajax_location ($val) {
  $sql = "SELECT distinct landmark FROM {chado_search_gene_search} WHERE analysis IN (:analysis) ORDER BY landmark";
  $list = chado_search_bind_dynamic_select(array(':analysis' => $val), 'landmark', $sql);

  # custom sort order only for DCARv2
  if (array_key_exists("DCARv2_B1", $list)) {
    uasort($list, "dcar_cmp");
  }

  return($list);
}

function chado_search_gene_search_ajax_species ($val) {
  $sql = "SELECT distinct species FROM {chado_search_gene_search} WHERE genus = :genus ORDER BY species";
  return chado_search_bind_dynamic_select(array(':genus' => $val), 'species', $sql);
}

function chado_search_gene_search_ajax_infraspecific ($val) {
  $sql = "SELECT distinct infraspecific_name FROM {chado_search_gene_search} WHERE species = :species ORDER BY infraspecific_name";
  return chado_search_bind_dynamic_select(array(':species' => $val), 'infraspecific_name', $sql);
}
