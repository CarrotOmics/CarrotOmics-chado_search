<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;
use ChadoSearch\sql\ColumnCond;

require_once 'carrotomics_api.php';

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_primer_search_form ($form) {
  $form->addTextFilter(
      Set::textFilter()
      ->id('feature_name')
      ->title('Name')
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('feature_uniquename')
      ->title('Identifier')
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('feature_residues')
      ->title('Sequence')
  );
  $form->addSelect(
      Set::select()
      ->id('orientation')
      ->options(['Forward', 'Reverse Complement', 'Both'])
      ->newLine()
  );

  $form->addSubmit();
  $form->addReset();
  $desc = 'Search for primers by entering full or partial names or full or partial sequence in the fields below.<br>'
        . ' <a href="/node/172">IUPAC Degenerate base codes</a> can be used in the sequence, e.g. ACGM matches both ACGA and ACGC.';
  $form->addFieldset(
      Set::fieldset()
      ->id('primer_search')
      ->startWidget('feature_name')
      ->endWidget('reset')
      ->description($desc)
  );
  return $form;
}

// Submit the form
function chado_search_primer_search_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = "SELECT * FROM {feature}";
  // Add conditions
  $where = [];
  $where[] = "type_id = (SELECT cvterm_id FROM {cvterm} WHERE name = 'primer' AND cv_id=(SELECT cv_id FROM {cv} WHERE name='sequence'))";
  $where[] = Sql::textFilter('feature_name', $form_state, 'name');
  $where[] = Sql::textFilter('feature_uniquename', $form_state, 'uniquename');

  // Here implement both reverse complement and degenerate searching,
  // $sequences is passed as a reference and returns the "degeneratized"
  // sequence array, e.g. 'ACGM' from the form becomes ['acga', 'acgc']
  // The orientation from the form is encoded as F=0 R=1 both=2
  $residues_query = $form_state["input"]["feature_residues"];
  if ($residues_query) {
    $orientation = $form_state["input"]["orientation"];
    $sequences=[];
    if ($orientation != 1) {  // Forward or Both
      primer_search_undegenerate($residues_query, $sequences);
    }
    if ($orientation != 0 ) { // Reverse or Both
      primer_search_undegenerate(primer_search_revcomp($residues_query), $sequences);
    }
    $op = $form_state["input"]["feature_residues_op"];
    $casesensitive = false;
    $delimiter = '|';
    $logic = ($op == 'not_contain')?'AND':'OR';
    $residues_cond = new ColumnCond('residues', $op, implode($delimiter, $sequences),
                                    $casesensitive, $delimiter, $logic);
    $where[] = $residues_cond->getStatement();
  }

  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_primer_search_table_definition')
    ->fastaDownload(TRUE)
    ->execute($form, $form_state);
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_primer_search_table_definition () {
  $headers = array(
      'name:s:chado_search_link_feature:feature_id' => 'Name',
      'uniquename:s:chado_search_link_feature:feature_id' => 'Identifier',
      'residues:s' => 'Sequence',
  );
  return $headers;
}

/*************************************************************
 * Convert a DNA sequence to its reverse complement
 * Supports IUPAC degenerate base codes
*/
function primer_search_revcomp ($seq) {
  $revcomp = strrev($seq);
  $revcomp = strtr($revcomp, 'AaCcTtUuGgMmRrYyKkVvHhDdBb', 'TtGgAaAaCcKkYyRrMmBbDdHhVv');
  return $revcomp;
}

/*************************************************************
 * Expand degenerate bases to an array of every possible sequence
 * This is a recursive function, $results passed by reference
*/
function primer_search_undegenerate ($seq, &$results = [], $startpos = 0) {
static $deg =
       [ '.' => ['%'],
         '-' => ['%'],
         'u' => ['t'],
         'm' => ['a', 'c'],
         'r' => ['a', 'g'],
         'w' => ['a', 't'],
         's' => ['c', 'g'],
         'y' => ['c', 't'],
         'k' => ['g', 't'],
         'v' => ['a', 'c', 'g'],
         'h' => ['a', 'c', 't'],
         'd' => ['a', 'g', 't'],
         'b' => ['c', 'g', 't'],
         'n' => ['a', 'c', 'g', 't'],
         '?' => ['a', 'c', 'g', 't'],
       ];
  $anyexpanded = false;
  for ($i = $startpos; $i < strlen($seq); $i++) {
    if (!$anyexpanded) {
      $base = strtolower(substr($seq, $i, 1));
      if (array_key_exists($base, $deg)) {
        foreach ($deg[$base] as $newbase) {
          $aseq = $seq;
          $aseq[$i] = $newbase;
          // recursive call will either process degenerate bases later in
          // the string, or store the string in $results if no more exist
          primer_search_undegenerate($aseq, $results, $i+1);
        }
        $anyexpanded = true;
      }
    }
  }
  if (!$anyexpanded) {
    $results[] = strtolower($seq);
  }
}
