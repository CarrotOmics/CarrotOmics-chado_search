<?php 

// Link to node by nid. If nid is unavailable return NULL
function chado_search_link_node ($nid) {
    if ($nid) {
        return "/node/$nid";
    } else {
        return NULL;
    }
}

function chado_search_link_entity ($base_table, $record_id) {
    $link = NULL;
    // tripal v2 link (node)
    $nid = chado_get_nid_from_id ($base_table, $record_id);
    if ($nid) {
        $link = "/node/$nid";
    }
    // tripal v3 link (entity)
    if (function_exists('chado_get_record_entity_by_table') && $record_id) {
        $entity_id = chado_get_record_entity_by_table ($base_table, $record_id);
        if ($entity_id) {
            $link = "/bio_data/$entity_id";
        }
    }
    return $link;
}

// Link to node by nid. If nid is unavailable return NULL
function chado_search_link_url ($url) {
    if ($url) {
        return $url;
    } else {
        return NULL;
    }
}

// Link feature
function chado_search_link_feature ($feature_id) {
    return chado_search_link_entity('feature', $feature_id);
}

// Link genetic_marker feature
function chado_search_link_genetic_marker ($locus_feature_id) {
    // Convert the feature_id of marker_locus to genetic_marker
    $fid = chado_search_query("SELECT object_id FROM {feature_relationship} FR WHERE subject_id = $locus_feature_id AND type_id = (SELECT cvterm_id FROM {cvterm} WHERE name = 'instance_of' AND cv_id = (SELECT cv_id FROM {cv} WHERE name = 'relationship'))")->fetchField();
    return chado_search_link_entity('feature', $fid);
}

// Link organism
function chado_search_link_organism ($organism_id) {
    return chado_search_link_entity('organism', $organism_id);
}

// Link featuremap
function chado_search_link_featuremap ($featuremap_id) {
    return chado_search_link_entity('featuremap', $featuremap_id);
}

// Link library
function chado_search_link_library ($library_id) {
    return chado_search_link_entity('library', $library_id);
}

// Link analysis
function chado_search_link_analysis ($analysis_id) {
    return chado_search_link_entity('analysis', $analysis_id);
}

// Link project
function chado_search_link_project ($project_id) {
    return chado_search_link_entity('project', $project_id);
}

// Link pub
function chado_search_link_pub ($pub_id) {
    return chado_search_link_entity('pub', $pub_id);
}

// Link stock
function chado_search_link_stock ($stock_id) {
    return chado_search_link_entity('stock', $stock_id);
}

// Link contact
function chado_search_link_contact ($contact_id) {
    return chado_search_link_entity('contact', $contact_id);
}

// Link nd_geolocation
function chado_search_link_nd_geolocation ($nd_geolocation_id) {
    return chado_search_link_entity('nd_geolocation', $nd_geolocation_id);
}

// Link jbrowse
function chado_search_link_jbrowse ($paras) {
    $srcfeature_id = $paras [0];
    $loc = $paras[1];
    $sql =
    "SELECT value
    FROM {feature} F
    INNER JOIN {analysisfeature} AF ON F.feature_id = AF.feature_id
    INNER JOIN {analysis} A ON A.analysis_id = AF.analysis_id
    INNER JOIN {analysisprop} AP ON AP.analysis_id = A.analysis_id
    INNER JOIN {cvterm} V ON V.cvterm_id = AP.type_id
    WHERE
    V.name = 'JBrowse URL' AND
    F.feature_id = :srcfeature_id";
    $jbrowse = $srcfeature_id ? chado_search_query($sql, array('srcfeature_id' => $srcfeature_id))->fetchField() : NULL;
    if ($jbrowse) {
        return chado_search_link_url ($jbrowse . $loc);
    }
    else {
        return NULL;
    }
}

// Link GRIN
function chado_search_link_grin ($grin) {
    return 'http://www.ars-grin.gov/cgi-bin/npgs/html/taxon.pl?' . $grin;
}

/*************************************************************
 * JBrowse link builder
*/
// Determine if location can be viewed in JBrowse and if so
// generate an appropriate url from location of e.g. "DCARv2_Chr5:8614051..8615973"
// This requred modifications to api/result/Table.php to remove l() processing if
// there is a question mark, otherwise the link is escaped and does not work
function chado_search_jbrowse_link ($location) {

  // Some tables have the same sequence location more than once.
  // Create a local short-lived cache to avoid duplicate queries
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
