<?php
// Create 'chado_search_file_search' MView
function chado_search_create_file_search_mview() {
  $view_name = 'chado_search_file_search';
  chado_search_drop_mview($view_name);
  $schema = array (
  'table' => 'chado_search_file_search',
  'fields' => array (
    'id' => array (
      'type' => 'int',
      'not null' => true,
    ),
    'name' => array (
      'type' => 'text',
      'not null' => false,
    ),
    'type' => array (
      'type' => 'text',
      'not null' => true,
    ),
    'description' => array (
      'type' => 'text',
      'not null' => false,
    ),
    'license' => array (
      'type' => 'text',
      'not null' => false,
    ),
  ),
  'indexes' => array (
    'file_search_indx0' => array (
      0 => 'type',
    ),
  ),
);

  $sql = "SELECT F.file_id as id, F.name, CV.name as type, F.description, L.name as license
FROM file F
LEFT JOIN cvterm CV ON F.type_id=CV.cvterm_id
LEFT JOIN file_license FLI ON F.file_id=FLI.file_id
LEFT JOIN license L ON FLI.license_id=L.license_id";

  tripal_add_mview($view_name, 'chado_search', $schema, $sql, '');
}
