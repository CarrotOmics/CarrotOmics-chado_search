<?php

/*************************************************************
 *
 * Custom sort for DCARv2 assembly
 *
 * example of usage
 *
 * require_once 'carrotomics_custom_sort.php';
 * 
 * // custom sort order only for DCARv2
 * if (array_key_exists("DCARv2_B1", $list)) {
 *   uasort($list, "dcar_cmp");
 * }
 *
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
