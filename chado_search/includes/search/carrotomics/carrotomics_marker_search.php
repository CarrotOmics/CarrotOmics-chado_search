<?php

use ChadoSearch\Set;
use ChadoSearch\Sql;

/*************************************************************
 * Search form, form validation, and submit function
 */
// Search form
function chado_search_marker_search_form ($form) {
  $form->addTabs(
      Set::tab()
      ->id('nearby_marker_tabs')
      ->items(['/search/markers' => 'Marker Search', '/search/nearby_markers' => 'Search Nearby Markers'])
  );
  // Search by Name
  $form->addTextFilter(
      Set::textFilter()
      ->id('marker_uniquename')
      ->title('Marker Name')
      ->labelWidth(120)
  );
  $form->addMarkup(
      Set::markup()
      ->id('marker_example')
      ->text("(e.g. OP-Y07/300, EAAC/MCAA-110, GSSR-125, K0531, 7_5002412)")
  );
  $form->addFile(
      Set::file()
      ->id('feature_name_file_inline')
      ->labelWidth(1)
      ->newLine()
  );
  // Restricted by Features
  $icon = '/' . drupal_get_path('module', 'chado_search') . '/theme/images/question.gif';
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('marker_type')
      ->title('Marker Type <a href="/marker_type"><img src="' . $icon . '"></a>')
      ->column('marker_type')
      ->table('chado_search_marker_search')
      ->cache(TRUE)
      ->labelWidth(120)
      ->newLine()
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('mapped_organism')
      ->title('Marker Mapped in Species')
      ->column('mapped_organism')
      ->table('chado_search_marker_search')
      ->multiple(TRUE)
      ->labelWidth(220)
      ->cache(TRUE)
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('organism')
      ->title('Marker Developed from Species')
      ->column('organism')
      ->table('chado_search_marker_search')
      ->multiple(TRUE)
      ->cache(TRUE)
      ->labelWidth(260)
      ->newLine()
      ->disable(['N/A N/A'])
  );
  $form->addSelectFilter(
      Set::selectFilter()
      ->id('map_name')
      ->title('Map')
      ->column('map_name')
      ->table('chado_search_marker_search')
      ->labelWidth(120)
      ->newLine()
      ->cache(TRUE)
  );
  $form->addDynamicSelectFilter(
      Set::dynamicSelectFilter()
      ->id('linkage_group')
      ->title('Linkage Group')
      ->dependOnId('map_name')
      ->callback('chado_search_marker_search_ajax_linkage_group')
      ->labelWidth(120)
  );
  $form->addBetweenFilter(
      Set::betweenFilter()
      ->id('start')
      ->title("between")
      ->id2('stop')
      ->title2("and")
      ->labelWidth2(50)
      ->size(10)
  );
  $form->addMarkup(
      Set::markup()
      ->id('linkage_group_unit')
      ->text("<strong>cM</strong>")
      ->newLine()
  );
  $form->addTextFilter(
      Set::textFilter()
      ->id('trait_name')
      ->title('Trait Name')
      ->labelWidth(120)
      );
  $form->addMarkup(
      Set::markup()
      ->id('trait_name_example')
      ->text('(e.g. Alternaria resistance, root color, or top size)')
      ->newLine()
      );
  $form->addSubmit();
  $form->addReset();
  $desc = 'Search for markers in CarrotOmics. In advanced marker search site, users can' .
          ' perform combinatorial queries using categories such as name, type, species,' .
          ' anchored position in genome and/or genetic map position.<br><b>' .
          l('Short video tutorial', 'https://www.youtube.com/watch?v=oqiuSI99mMg', ['attributes' => ['target' => '_blank']]) .
          ' | ' . l('Text tutorial', 'tutorial/marker_search') .
          ' | ' . l('Email us with problems and suggestions', 'contact') . '</b>';
  $form->addFieldset(
      Set::fieldset()
      ->id('top_level')
      ->startWidget('marker_uniquename')
      ->endWidget('reset')
      ->description($desc)
  );
  return $form;
}

// Submit the form
function chado_search_marker_search_form_submit ($form, &$form_state) {
  // Get base sql
  $sql = chado_search_marker_search_base_query();
  // Add conditions
  $where = [];
  $where [] = Sql::textFilterOnMultipleColumns('marker_uniquename', $form_state, ['marker_uniquename', 'marker_name', 'alias', 'synonym']);
  $where [] = Sql::fileOnMultipleColumns('feature_name_file_inline', ['marker_uniquename', 'marker_name', 'alias', 'synonym']);
  $where [] = Sql::selectFilter('marker_type', $form_state, 'marker_type');
  $where [] = Sql::selectFilter('organism', $form_state, 'organism');
  $where [] = Sql::selectFilter('mapped_organism', $form_state, 'mapped_organism');
  $where [] = Sql::selectFilter('map_name', $form_state, 'map_name');
  $where [] = Sql::selectFilter('linkage_group', $form_state, 'lg_uniquename');
  $where [] = Sql::betweenFilter('start', 'stop', $form_state, 'start', 'start', TRUE);
  $where [] = Sql::textFilter('trait_name', $form_state, 'trait_name');
  Set::result()
    ->sql($sql)
    ->where($where)
    ->tableDefinitionCallback('chado_search_marker_search_table_definition')
    ->customDownload(['disable_default' => TRUE, 'chado_search_marker_search_carrotomics_download' => 'Table'])
    ->execute($form, $form_state);
}

/*************************************************************
 * SQL
*/
// Define query for the base table. Do not include the WHERE clause
function chado_search_marker_search_base_query() {
  $query = "SELECT * FROM {chado_search_marker_search}";
  return $query;
}

/*************************************************************
 * JBrowse link builder
*/
// Determine if location can be viewed in JBrowse and if so
// generate an appropriate url from location of e.g. "DCARv2_Chr5:8614051..8615973"
// This requred modifications to api/result/Table.php to remove l() processing if
// there is a question mark, otherwise the link is escaped and does not work
function chado_search_marker_search_jbrowse_link ($location) {

  // Some markers are on more than one map, so the same location may be found on
  // multiple table lines. Create a local short-lived cache to avoid duplicate queries
  static $jbrowselookup = [];

  if ($location) {
    // see if this location was cached from an earlier table line
    if (array_key_exists($location, $jbrowselookup)) {
      return $jbrowselookup[$location];
    }
    else {
      # parse location, split on punctuation
      $parts = preg_split("/[:\.]+/", $location);
      if (count($parts) >= 3) {
        $srcfeature = $parts[0];
        $fmin = $parts[1];
        $fmax = $parts[2];

        // when displayed in JBrowse, add 100 b.p. margin on each side for better appearance
        $margin = 100;
        $fminmargin = $fmin>$margin?$fmin - $margin:1;
        $fmaxmargin = $fmax + $margin;
        $urlsuffix = $srcfeature . ':' . $fminmargin . '..' . $fmaxmargin;

        // determine if present in JBrowse. The feature table has had the dbxref value
        // set if present in JBrowse, see notebook page 78 July 11, 2020
        $sql = "SELECT urlprefix from {db} WHERE db_id=(SELECT db_id FROM {dbxref}"
          . " WHERE dbxref_id=(SELECT dbxref_id from {feature} WHERE type_id=(SELECT cvterm_id FROM {cvterm}"
          . " WHERE name=:chromosome AND cv_id=(SELECT cv_id FROM {cv} WHERE name=:sequence)) AND uniquename=:uniquename))";
        $results = chado_query($sql, [':chromosome' => 'chromosome', ':sequence' => 'sequence', ':uniquename' => $srcfeature]);
        if ($results) {
          $obj = $results->fetchObject();
          if ($obj) {
            // because the urlprefix from the db table is already encoded when Tripal
            // JBrowse created it, we need to unencode it first, and add the leading /
            // to make url not a relative url, and /data suffix as Tripal JBrowse uses
            $jbrowseurl = '/' . urldecode($obj->urlprefix) . '/data&loc=' . $urlsuffix . '&highlight=' . $location;

            // store in local cache and then return the url
            $jbrowselookup[$location] = $jbrowseurl;
            return $jbrowseurl;
          }
        }
      }
    }
  }
  // if anything fails, null is returned, which means no hyperlinks
  // will be generated for the location
  return NULL;
}

/*************************************************************
 * Build the search result table
*/
// Define the result table
function chado_search_marker_search_table_definition () {
  $headers = [
    'marker_name:s:chado_search_link_feature:marker_feature_id' => 'Name',
    'alias:s' => 'Alias',
    'marker_type:s' => 'Type',
    'organism:s' => 'Species',
    'map_name:s:chado_search_link_featuremap:featuremap_id' => 'Map',
    'lg_uniquename:s' => 'Linkage Group',
    'start:s' => 'Start',
    'stop:s' => 'Stop',
    'location:s:chado_search_marker_search_jbrowse_link:location' => 'Location'
  ];
  return $headers;
}

/*************************************************************
 * AJAX callbacks
*/
// Downloading file ajax callback
function chado_search_marker_search_download_fasta_definition () {
  return 'marker_feature_id';
}

// User defined: Populating the landmark for selected organism
function chado_search_marker_search_ajax_location ($val) {
  $sql = "SELECT distinct landmark, CASE WHEN regexp_replace(landmark, E'\\\D','','g') = '' THEN 999999 ELSE regexp_replace(landmark, E'\\\D','','g')::numeric END AS lnumber FROM {chado_search_marker_search} WHERE genome = :genome ORDER BY lnumber";
  return chado_search_bind_dynamic_select([':genome' => $val], 'landmark', $sql);
}

// User defined: Populating the linkage group for selected map
function chado_search_marker_search_ajax_linkage_group ($val) {
  $sql = "SELECT distinct lg_uniquename FROM {chado_search_marker_search} WHERE map_name = :map_name ORDER BY lg_uniquename";
  return chado_search_bind_dynamic_select([':map_name' => $val], 'lg_uniquename', $sql);
}

function chado_search_marker_search_download_definition () {
  $headers = [
    'marker_feature_id' => 'Feature_id',
    'marker_name' => 'Name',
    'alias' => 'Alias',
    'marker_type' => 'Type',
    'organism_id' => 'Organism_id',
    'organism' => 'Species',
    'map_name' => 'Map',
    'lg_uniquename' => 'Linkage Group',
    'start' => 'Start',
    'stop' => 'Stop',
    'location' => 'Location'
  ];
  return $headers;
}

// Custom download for CarrotOmics
function chado_search_marker_search_carrotomics_download ($handle, $result, $sql, $total_items, $progress_var) {
  global $base_url;
  // Get max no of primers
  $primer_count = "
    SELECT count(*)
    FROM {feature} F
    INNER JOIN {feature_relationship} FR ON F.feature_id = FR.subject_id
    INNER JOIN {feature} P ON P.feature_id = FR.object_id
    WHERE
    F.type_id = (SELECT cvterm_id FROM {cvterm} WHERE name = 'genetic_marker' AND cv_id = (SELECT cv_id FROM {cv} WHERE name = 'sequence'))
    AND
    P.type_id = (SELECT cvterm_id FROM {cvterm} WHERE name = 'primer' AND cv_id = (SELECT cv_id FROM {cv} WHERE name = 'sequence'))
    AND
    FR.type_id = (SELECT cvterm_id FROM {cvterm} WHERE name = 'adjacent_to' AND cv_id = (SELECT cv_id FROM {cv} WHERE name = 'relationship'))
    AND
    F.feature_id = Marker.marker_feature_id
    GROUP BY F.feature_id";
  $max_sql = "SELECT max(count) FROM (SELECT ($primer_count) AS count FROM ($sql) Marker) T";
  $max_no = chado_search_query($max_sql)->fetchField();
  // Write header
  fwrite($handle, "\"Name\",\"Alias\",\"Type\",\"Species\",\"Map\",\"Linkage Group\",\"Start\",\"Stop\",\"Location\",\"Citation\"");
  for ($i = 1; $i <= $max_no; $i ++) {
    fwrite($handle, ",\"Primer$i name\",\"Primer$i sequence\"");
  }
  fwrite($handle, "\n");
  // Get result with primers and feature/organism nid
  $sql_primers = "
    SELECT string_agg(P.name || '::' || P.residues, '||')
    FROM {feature} F
    INNER JOIN {feature_relationship} FR ON F.feature_id = FR.subject_id
    INNER JOIN {feature} P ON P.feature_id = FR.object_id
    WHERE
    F.type_id = (SELECT cvterm_id FROM {cvterm} WHERE name = 'genetic_marker' AND cv_id = (SELECT cv_id FROM {cv} WHERE name = 'sequence'))
    AND
    P.type_id = (SELECT cvterm_id FROM {cvterm} WHERE name = 'primer' AND cv_id = (SELECT cv_id FROM {cv} WHERE name = 'sequence'))
    AND
    FR.type_id = (SELECT cvterm_id FROM {cvterm} WHERE name = 'adjacent_to' AND cv_id = (SELECT cv_id FROM {cv} WHERE name = 'relationship'))
    AND
    F.feature_id = Marker.marker_feature_id
    GROUP BY F.feature_id";
  $sql_citation = "
    SELECT string_agg(value, ';') AS citation
    FROM {pubprop} PP
    INNER JOIN {feature_pub} FP ON FP.pub_id = PP.pub_id
    WHERE PP.type_id = (SELECT cvterm_id FROM {cvterm} WHERE name = 'Citation' AND cv_id = (SELECT cv_id FROM {cv} WHERE name = 'tripal_pub'))
    AND FP.feature_id = marker_feature_id
    GROUP BY FP.feature_id";
  $sql = "SELECT *, ($sql_primers) AS primers, ($sql_citation) AS citation, (SELECT nid FROM chado_feature WHERE feature_id = marker_feature_id) AS feature_nid, (SELECT nid FROM chado_organism WHERE organism_id = Marker.organism_id) AS organism_nid FROM ($sql) Marker";
  $result = chado_search_query($sql);
  $progress = 0;
  $counter = 1;
  // Write reults
  while ($obj = $result->fetchObject()) {
    $current = round ($counter / $total_items * 100);
    if ($current != $progress) {
      $progress = $current;
      variable_set($progress_var, $progress);
    }
    fwrite($handle, "\"=HYPERLINK(\"\"$base_url/node/$obj->feature_nid\"\",\"\"$obj->marker_name\"\")\",\"$obj->alias\",\"$obj->marker_type\",\"=HYPERLINK(\"\"$base_url/node/$obj->organism_nid\"\",\"\"$obj->organism\"\")\",\"$obj->map_name\",\"$obj->lg_uniquename\",\"$obj->start\",\"$obj->stop\",\"$obj->location\",\"$obj->citation\"");
    $primers = explode('||', $obj->primers);
    foreach ($primers AS $primer) {
      $primer_info = explode('::', $primer);
      $pname = $primer_info[0];
      $pseq = $primer_info[1];
      fwrite($handle, ",\"$pname\",\"$pseq\"");
    }
    fwrite($handle, "\n");
    $counter ++;
  }
  // Reset progress bar
  variable_del($progress_var);
}
