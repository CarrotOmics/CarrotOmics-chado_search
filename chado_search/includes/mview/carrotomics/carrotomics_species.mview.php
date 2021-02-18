<?php
// Create 'germplasm_search' MView
function chado_search_create_species_mview() {
  $view_name = 'chado_search_species';
  chado_search_drop_mview($view_name);
  $schema = array(
  'table' => $view_name,
  'fields' => array (
    'organism_id' => array (
      'type' => 'int',
      'not null' => true,
    ),
    'family' => array (
      'type' => 'varchar',
      'length' => 255,
    ),
    'genus' => array (
      'type' => 'varchar',
      'length' => 255,
    ),
    'species' => array (
      'type' => 'varchar',
      'length' => 255,
      'not null' => true,
    ),
    'infraspecific_type' => array (
      'type' => 'varchar',
      'length' => 255,
      'not null' => false,
    ),
    'infraspecific_name' => array (
      'type' => 'varchar',
      'length' => 255,
      'not null' => false,
    ),
    'organism' => array (
      'type' => 'varchar',
      'length' => 510,
      'not null' => true,
    ),
    'common_name' => array (
      'type' => 'varchar',
      'length' => 255,
      'not null' => false,
    ),
    'grin' => array (
      'type' => 'varchar',
      'length' => 255,
      'not null' => false,
    ),
    'haploid_chromosome_number' => array (
      'type' => 'text',
      'not null' => false,
    ),
    'ploidy' => array (
      'type' => 'text',
      'not null' => false,
    ),
    'geographic_origin' => array (
      'type' => 'text',
      'not null' => false,
    ),
    'num_germplasm' => array (
      'type' => 'int',
      'not null' => false,
    ),
    'num_sequences' => array (
      'type' => 'int',
      'not null' => false,
    ),
    'num_libraries' => array (
      'type' => 'int',
      'not null' => false,
    ),
    'num_biomaterial' => array (
      'type' => 'int',
      'not null' => false,
    ),
  ),
  'indexes' => array (
    'species_summary_idx0' => array (
      0 => 'organism_id',
    ),
  ),
  'foreign keys' => array (
    'organism' => array (
      'table' => 'organism',
      'columns' => array (
        'organism_id' => 'organism_id',
      ),
    ),
  ),
);

  $sql = "SELECT
  organism_id,
  (SELECT OP.value FROM organismprop OP WHERE OP.organism_id = O.organism_id AND OP.type_id =
    (SELECT F.cvterm_id FROM cvterm F WHERE F.name = 'superclass' AND F.cv_id =
      (SELECT FF.cv_id FROM cv FF WHERE FF.name = 'taxonomic_rank'))) AS family,
  genus,
  species,
  REPLACE (
    (SELECT name FROM cvterm CVTT WHERE CVTT.cvterm_id = O.type_id
      AND CVTT.cv_id = (SELECT cv_id FROM cv WHERE name='taxonomic_rank')),
    'no_rank', '')
    as infraspecific_type,
  infraspecific_name,
  concat_ws(' ', O.genus, O.species, REPLACE (
    (SELECT name FROM cvterm CVTT WHERE CVTT.cvterm_id = O.type_id
      AND CVTT.cv_id = (SELECT cv_id FROM cv WHERE name='taxonomic_rank')),
    'no_rank', ''), O.infraspecific_name) AS organism,
  common_name,
  (SELECT accession FROM dbxref X INNER JOIN db ON db.db_id = X.db_id INNER JOIN organism_dbxref OD ON X.dbxref_id = OD.dbxref_id WHERE OD.organism_id = O.organism_id AND db.name = 'GRIN Taxonomy') AS grin,
  (SELECT OP.value
   FROM organismprop OP
     INNER JOIN cvterm CVT_OP on CVT_OP.cvterm_id = OP.type_id
   WHERE CVT_OP.name = 'haploid_chromosome_number' AND OP.organism_id = O.organism_id) as haploid_chromosome_number,
  (SELECT OP.value
   FROM organismprop OP
     INNER JOIN cvterm CVT_OP on CVT_OP.cvterm_id = OP.type_id
   WHERE CVT_OP.name = 'ploidy' AND OP.organism_id = O.organism_id) as ploidy,
  (SELECT OP.value
   FROM organismprop OP
     INNER JOIN cvterm CVT_OP on CVT_OP.cvterm_id = OP.type_id
   WHERE CVT_OP.name = 'geographic_origin' AND OP.organism_id = O.organism_id) as geographic_origin,
  (SELECT count(*)
   FROM stock S
   WHERE S.organism_id = O.organism_id AND S.type_id <> (SELECT cvterm_id FROM cvterm WHERE name = 'sample' AND cv_id = (SELECT cv_id FROM cv WHERE name = 'MAIN'))) as num_germplasm,
  (SELECT count(*)
   FROM feature F
   WHERE F.organism_id = O.organism_id AND residues IS NOT NULL) as num_sequences,
  (SELECT count(*)
   FROM library L
   WHERE L.organism_id = O.organism_id) as num_libraries,
  (SELECT count(*)
   FROM biomaterial BM
   WHERE BM.taxon_id = O.organism_id) as num_biomaterial
FROM organism O
WHERE genus NOT IN ('N/A')
ORDER BY genus, species, infraspecific_type, infraspecific_name";

  tripal_add_mview($view_name, 'chado_search', $schema, $sql, '');
}
