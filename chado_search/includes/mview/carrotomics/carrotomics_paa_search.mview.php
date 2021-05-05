<?php
// Create 'germplasm_search' MView
function chado_search_create_paa_search_mview() {
  $view_name = 'chado_search_paa_search';
  chado_search_drop_mview($view_name);
  // name is text in project and assay, but is character varying(255) in analysis
  // description is text in all
  $schema = array (
  'table' => 'chado_search_paa_search',
  'fields' => array (
    'type' => array (
      'type' => 'text',
      'not null' => false,
    ),
    'id' => array (
      'type' => 'int',
      'not null' => true,
    ),
    'name' => array (
      'type' => 'text',
      'not null' => false,
    ),
    'description' => array (
      'type' => 'text',
      'not null' => false,
    ),
  ),
  'indexes' => array (
    'paa_search_indx0' => array (
      0 => 'type',
    ),
  ),
);

  $sql = "
SELECT 'Analysis' AS type, AN.analysis_id AS id, AN.name, AN.description
  FROM analysis AN
UNION ALL
SELECT 'Project' AS type, PR.project_id AS id, PR.name, PR.description
  FROM project PR
UNION ALL
SELECT 'Assay' AS type, AY.assay_id AS id, AY.name, AY.description
  FROM assay AY
ORDER BY name";

  tripal_add_mview($view_name, 'chado_search', $schema, $sql, '');
}
