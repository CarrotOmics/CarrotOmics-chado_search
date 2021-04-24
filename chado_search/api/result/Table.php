<?php

namespace ChadoSearch\result;

use ChadoSearch\SessionVar;

require_once 'Source.php';

class Table extends Source {

  public function __construct($search_id, $result, $page, $num_per_page, $headers, $order, $autoscroll) {
    $html = $this->htmlTable($search_id, $result, $page, $num_per_page, $headers, $order, $autoscroll);
    $this->src = $html;
  }

  private function htmlTable($search_id, $result, $page, $num_per_page, $headers, $order, $autoscroll) {
    $header_keys = array_keys($headers);
    // Disable columns on request
    $disabledCols = SessionVar::getSessionVar($search_id, 'disabled-columns');
    $show_counter = TRUE;
    if ($disabledCols) {
      $dcols = explode(';', $disabledCols);
      foreach ($dcols AS $dc) {
        if ($dc == 'row-counter') {
          $show_counter = FALSE;
        }
        foreach($header_keys AS $hk) {
          $pattern = explode(':', $hk);
          if ($pattern[0] == $dc) {
            unset ($headers[$hk]);
          }
        }
      }
    }
    // Change the headers on request
    $changedHeaders = SessionVar::getSessionVar($search_id, 'changed-headers');
    if ($changedHeaders) {
      $cheaders = explode(';', $changedHeaders);
      foreach ($cheaders AS $ch) {
        foreach($header_keys AS $hk) {
          $pattern = explode(':', $hk);
          $h = explode('=', $ch);
          if ($pattern[0] == $h[0]) {
            $headers[$hk] = $h[1];
          }
        }
      }
    }
    // Rewrite columns on request, conver the session variable (i.e. <column1>=<callback1>;) into an associated array (i.e. 'column1' => 'callback1')
    $rewriteCols = SessionVar::getSessionVar($search_id, 'rewrite-columns');
    $rewriteCallback = array();
    $rewriteCallbackPassObj = array();
    if ($rewriteCols) {
      $rwcols = explode(';', $rewriteCols);
      foreach ($rwcols AS $rwc) {
        $rewrite = explode('=', $rwc);
        $func_name = explode('*', $rewrite[1]);
        if(count($func_name) == 2) {
          $rewriteCallbackPassObj[$rewrite[0]] = TRUE;
        }
        else {
          $rewriteCallbackPassObj[$rewrite[0]] = FALSE;
        }
        if (count($rewrite) == 2 && function_exists($func_name[0]) ) {
          $rewriteCallback[$rewrite[0]] = $func_name[0];
        }
      }
    }
    $div_css_id = $search_id . "-result";
    $table_css_id = $search_id . "-result-table";
    $js_function = $search_id . "_change_order";
    $js_scroll = "";

    // Get hstore column settings if there is any
    $hstoreToColumns = SessionVar::getSessionVar($search_id, 'hstore-to-columns');
    $hstoreCol = $hstoreToColumns['column'] ?? NULL;
    // Scroll only if it is enabled and there is no error on the form
    if ($autoscroll) {
      $js_scroll =
        "<script type=\"text/javascript\">
           (function ($) {
             $(document).ready(function(){
               var error = false;
               $('.element-invisible').each(function(index) {
                 if ($(this).text() == 'Error message') {
                   error = true;
                 }
               });
               if (!error) {
                 var target_offset = $('#$div_css_id-summary').offset();
                 var target_top = target_offset.top;
                 $('html, body').animate({scrollTop: target_top}, 500);
               }
             });
           })(jQuery);
         </script>";
    }
    $table = "$js_scroll<div  id=\"$div_css_id\" class=\"chado_search-result\"><table id=\"$table_css_id\" class=\"chado_search-result-table\">";
    $symbol = "▲";
    $orderby = explode(" ", $order);
    if (count ($orderby) == 2) {
      $symbol = "▼";
    }
    // Add symbol to the column header
    if ($order) {
      foreach ($headers AS $k => $v) {
        $key = explode(":", $k);
        if ($key[0] == $orderby[0]) {
          $headers[$k] .= $symbol;
        }
      }
    }
    // Prepare table header
    $table .= "<tr>";
    if ($show_counter) {
        $table .= "<th>#</th>";
    }
    $idx_header = 1;
    foreach ($headers AS $k => $v) {
      // handle the hstore column
      if ($k == $hstoreCol) {
        foreach ($hstoreToColumns['data'] AS $hsk => $hsv) {
          $table .= "<th id=\"chado_search-$search_id-header-$idx_header\">$hsv</th>";
        }
      }
      else {
        $key = explode(":", $k);
        if (key_exists(1, $key) && ($key[1] == 's' || $key[1] == 'sortable')) {
          $table .= "<th id=\"chado_search-$search_id-header-$idx_header\"><a href=\"javascript:void(0)\" onClick=\"$js_function('$key[0]');return false;\">$v</a></th>";
        } else {
          $table .= "<th id=\"chado_search-$search_id-header-$idx_header\">$v</th>";
        }
      }
      $idx_header ++;
    }
    $table .= "</tr>";
    // Prepare table rows
    $offset = $num_per_page * $page;
    $counter = 1;
    $row_class = "";
    while ($obj = $result->fetchObject()) {
      if ($counter % 2 == 0) {
        $row_class = "chado_search-result-table-even-row";
      } else {
        $row_class = "chado_search-result-table-odd-row";
      }
      $item = $counter + $offset;
      $table .= "<tr class=\"$row_class\">";
      if ($show_counter) {
        $table .= "<td>$item</td>";
      }
      foreach ($headers AS $k => $v) {
        // handle the hstore column
        if ($k == $hstoreCol) {
          $value = property_exists($obj, $k) ? $obj->$k : ''; // hstore column value
          $values = chado_search_hstore_to_assoc($value);
          foreach ($hstoreToColumns['data'] AS $hsk => $hsv) {
            $display_val = key_exists($hsk, $values) ? $values[$hsk] : '';
            $table .= "<td>" . $display_val . "</td>";
          }
        }
        else {
          $key = explode(":", $k);
          $col = $key[0]; // column name
          $value = property_exists($obj, $col) ? $obj->$col : ''; // column value
          if (key_exists($col, $rewriteCallback)) {
            $rwfunc = $rewriteCallback[$col];
            if($rewriteCallbackPassObj[$col]) {
              $value = $rwfunc($obj);
            }
            else {
              $value = $rwfunc($value);
            }
          }
          if (key_exists(3, $key) && $key[2] != '') { // If there is a link function callback (key[2]) and passing parameters (key[3],...)
            $callback = $key[2];
            $var = $key[3];
            $vars = explode(',', $var);
            $pass_params = array();
            foreach($vars AS $param) { // if there is more than one passing parameter
              if (trim ($param)) {
                $stored = isset($obj->$param) ? $obj->$param : NULL;
                array_push($pass_params, $stored);
              }
            }
            if (count($pass_params) == 1) {
              $pass_params = $pass_params [0];
            }
            $link = $callback($pass_params);
            if ($link) {
              // JBrowse links have a "?" and l() will escape that, so don't do these
              if (preg_match("/\?/", $link)) {
                $table .= "<td><a href=\"" . $link . "\">$value</a></td>";
              }
              else {
                $table .= "<td>" . l ($value, $link) . "</td>";
              }
            } else {
              $table .= "<td>" . $value . "</td>";
            }
          } else { // If there is no link function, show the value without link
            $table .= "<td>" . $value . "</td>";
          }
        }
      }
      $table .= "</tr>";
      $counter ++;
    }
    $table .= "</table></div>";
    if ($order) {
      $table .= "<input id=\"" . $search_id . "_current_order\" type=\"hidden\" value=\"$order\">";
    }
    return $table;
  }
}
