<?php
// Create 'germplasm_search' MView
function chado_search_create_germplasm_search_mview() {
  $view_name = 'chado_search_germplasm_search';
  chado_search_drop_mview($view_name);
  $schema = array (
  'table' => $view_name,
  'fields' => array (
    'stock_id' => array (
      'type' => 'int',
    ),
    'name' => array (
      'type' => 'varchar',
      'length' => '255',
    ),
    'uniquename' => array (
      'type' => 'text',
    ),
    'organism_id' => array (
      'type' => 'int',
    ),
    'organism' => array (
      'type' => 'varchar',
      'length' => 510,
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
    'description' => array (
      'type' => 'text',
    ),
    'genome' => array (
      'type' => 'text',
    ),
    'db' => array (
      'type' => 'varchar',
      'length' => '255',
    ),
    'accession' => array (
      'type' => 'varchar',
      'length' => '255',
    ),
    'urlprefix' => array (
      'type' => 'varchar',
      'length' => '255',
    ),
    'alias' => array (
      'type' => 'text',
    ),
  ),
);
  $sql = "SELECT DISTINCT
  S.stock_id,
  S.name,
  S.uniquename,
  O.organism_id,
  concat_ws(' ', O.genus, O.species, REPLACE (
    (SELECT name FROM cvterm CVTT WHERE CVTT.cvterm_id = O.type_id
      AND CVTT.cv_id = (SELECT cv_id FROM cv WHERE name='taxonomic_rank')),
    'no_rank', ''), O.infraspecific_name) AS organism,
  (SELECT OP.value FROM organismprop OP WHERE OP.organism_id = O.organism_id AND OP.type_id =
    (SELECT F.cvterm_id FROM cvterm F WHERE F.name = 'superclass' AND F.cv_id =
      (SELECT FF.cv_id FROM cv FF WHERE FF.name = 'taxonomic_rank'))) AS family,
  O.genus,
  O.species,
  REPLACE (
    (SELECT name FROM cvterm CVTT WHERE CVTT.cvterm_id = O.type_id
      AND CVTT.cv_id = (SELECT cv_id FROM cv WHERE name='taxonomic_rank')),
    'no_rank', '')
    as infraspecific_type,
  O.infraspecific_name,
  S.description,
  GENOME.value AS genome,
  DATA.name AS db,
  DATA.accession,
  DATA.urlprefix,
  ALIAS.value AS alias
FROM stock S
INNER JOIN organism O ON S.organism_id = O.organism_id
LEFT JOIN (
  SELECT organism_id, value
  FROM organismprop OP
  WHERE type_id = (
    SELECT cvterm_id
    FROM cvterm
    WHERE name = 'genome_group'
    AND cv_id = (
      SELECT cv_id
      FROM cv
      WHERE name = 'MAIN'
    )
  )
) GENOME ON GENOME.organism_id = O.organism_id
LEFT JOIN (
  SELECT stock_id, accession, name, urlprefix
  FROM dbxref X
  INNER JOIN db ON X.db_id = db.db_id
  INNER JOIN stock_dbxref SX ON X.dbxref_id = SX.dbxref_id
) DATA ON DATA.stock_id = S.stock_id
LEFT JOIN (
  SELECT stock_id, value
  FROM stockprop
  WHERE type_id = (
    SELECT cvterm_id
    FROM cvterm
    WHERE name = 'alias'
    AND cv_id = (
      SELECT cv_id
      FROM cv
      WHERE name = 'MAIN'
    )
  )
) ALIAS ON ALIAS.stock_id = S.stock_id
WHERE S.type_id <> (SELECT cvterm_id FROM cvterm WHERE name = 'sample' AND cv_id =(SELECT cv_id FROM cv WHERE name = 'MAIN'))";
  tripal_add_mview($view_name, 'chado_search', $schema, $sql, '');
}
