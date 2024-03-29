<?php
// Create 'marker_search' MView
function chado_search_create_qualitative_traits_mview() {
  $view_name = 'chado_search_qualitative_traits';
  chado_search_drop_mview($view_name);
  $schema = array(
  'table' => $view_name,
  'fields' => array (
    'stock_id' => array (
      'type' => 'int',
    ),
    'variety_name' => array (
      'type' => 'text',
    ),
    'trait_descriptor' => array (
      'type' => 'text',
    ),
    'trait_value' => array (
      'type' => 'text',
    ),
    'project_id' => array (
      'type' => 'int',
    ),
    'project_name' => array (
      'type' => 'text',
    ),
    'organism_id' => array (
      'type' => 'int',
    ),
    'organism' => array (
      'type' => 'varchar',
      'length' => '510',
    ),
    'environment' => array (
      'type' => 'varchar',
      'length' => '255',
    ),
  ),
  'indexes' => array (
    'qual_traits_variety_name' => array (
      0 => 'variety_name',
    ),
  ),
);

  $sql = "SELECT DISTINCT
      STK.stock_id,
      STK.uniquename AS variety_name,
      V.name AS trait_descriptor,
      P.value AS trait_value,
      PRJ.project_id,
      PRJ.name AS project,
      SMP.organism_id,
      SMP.organism,
      G.description AS environment
    FROM nd_experiment_phenotype NEP
    INNER JOIN nd_experiment NE ON NE.nd_experiment_id = NEP.nd_experiment_id
    INNER JOIN nd_geolocation G ON G.nd_geolocation_id = NE.nd_geolocation_id 
    INNER JOIN phenotype P ON NEP.phenotype_id = P.phenotype_id
    INNER JOIN cvterm V ON P.attr_id = V.cvterm_id
    INNER JOIN (SELECT S.stock_id, uniquename, nd_experiment_id, S.organism_id,
      concat_ws (' ', O.genus, O.species, REPLACE (
           (SELECT name FROM cvterm CVTT WHERE CVTT.cvterm_id = O.type_id
           AND CVTT.cv_id = (SELECT cv_id FROM cv WHERE name='taxonomic_rank')),
           'no_rank', ''), O.infraspecific_name) AS organism FROM stock S
      INNER JOIN nd_experiment_stock NES ON S.stock_id = NES.stock_id
      INNER JOIN organism O ON O.organism_id = S.organism_id)
      SMP ON SMP.nd_experiment_id = NEP.nd_experiment_id
    INNER JOIN stock_relationship SR ON SR.subject_id = SMP.stock_id AND SR.type_id = (SELECT cvterm_id FROM cvterm WHERE name = 'sample_of' AND cv_id = (SELECT cv_id FROM cv WHERE name = 'MAIN'))
    INNER JOIN stock STK ON STK.stock_id = SR.object_id
    INNER JOIN (SELECT P.project_id, name, nd_experiment_id FROM project P
      INNER JOIN nd_experiment_project NEJ ON P.project_id = NEJ.project_id)
      PRJ ON PRJ.nd_experiment_id = NEP.nd_experiment_id
    WHERE P.value !~ '^[\d\.]+$'";

  tripal_add_mview($view_name, 'chado_search', $schema, $sql, '');
}
