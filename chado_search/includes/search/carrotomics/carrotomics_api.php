<?php

/*
 * Extensions to the chado search api
*/

/*
 * Link generic using format of "table:record" (used for stock + biomaterial)
 */
function chado_search_link_generic ($tablerecord) {
    $parts = explode(':', $tablerecord);
    return chado_search_link_entity($parts[0], $parts[1]);
}

/*
 * JBrowse link builder
 *
 * Determine if a location can be viewed in JBrowse, and if so
 * generate an appropriate url from location of e.g. "DCARv2_Chr5:8614051..8615973"
 * This requred modifications to api/result/Table.php to remove l() processing if
 * there is a question mark, otherwise the link is escaped and does not work
 * 191c191,197
 * <               $table .= "<td>" . l ($value, $link) . "</td>";
 * ---
 * >               // JBrowse links have a "?" and l() will escape that, so don't do these
 * >               if (preg_match("/\?/", $link)) {
 * >                 $table .= "<td><a href=\"" . $link . "\">$value</a></td>";
 * >               }
 * >               else {
 * >                 $table .= "<td>" . l ($value, $link) . "</td>";
 * >               }
 */
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
            $jbrowseurl = urldecode($obj->urlprefix) . '/data&loc=' . $urlsuffix . '&highlight=' . $location;
            if (!preg_match("/^\//", $jbrowseurl)) {
              $jbrowseurl = '/' . $jbrowseurl;
            }

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
