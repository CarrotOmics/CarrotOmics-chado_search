<?php
// Create 'germplasm_search' MView
function chado_search_create_germplasm_search_mview() {
  $view_name = 'chado_search_germplasm_search';
  chado_search_drop_mview($view_name);
  $schema = array (
  'table' => $view_name,
  'fields' => array (
    'record_id' => array (
      'type' => 'varchar',
      'length' => '32',
    ),
    'accession_type' => array (
      'type' => 'varchar',
      'length' => 64,
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
  CONCAT('stock:', S.stock_id) AS record_id,
  INITCAP(REGEXP_REPLACE(REPLACE(REPLACE(SCV.name, '_', ' '), '414 ', ''), '^accession$', 'germplasm accession')) AS accession_type,
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
LEFT JOIN cvterm SCV ON S.type_id = SCV.cvterm_id
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

WHERE S.type_id <> (SELECT cvterm_id FROM cvterm WHERE name = 'sample' AND cv_id =(SELECT cv_id FROM cv WHERE name = 'MAIN'))

UNION

SELECT
  CONCAT('biomaterial:', B.biomaterial_id) AS record_id,
  'BioSample' AS accession_type,
  B.name AS name,
  B.name AS uniquename,
  B.taxon_id AS organism_id,
  concat_ws(' ', O2.genus, O2.species, REPLACE (
    (SELECT name FROM cvterm CVTT WHERE CVTT.cvterm_id = O2.type_id
      AND CVTT.cv_id = (SELECT cv_id FROM cv WHERE name='taxonomic_rank')),
    'no_rank', ''), O2.infraspecific_name) AS organism,
  (SELECT OP.value FROM organismprop OP WHERE OP.organism_id = O2.organism_id AND OP.type_id =
    (SELECT F.cvterm_id FROM cvterm F WHERE F.name = 'superclass' AND F.cv_id =
      (SELECT FF.cv_id FROM cv FF WHERE FF.name = 'taxonomic_rank'))) AS family,
  O2.genus,
  O2.species,
  REPLACE (
    (SELECT name FROM cvterm CVTT WHERE CVTT.cvterm_id = O2.type_id
      AND CVTT.cv_id = (SELECT cv_id FROM cv WHERE name='taxonomic_rank')),
    'no_rank', '')
    as infraspecific_type,
  O2.infraspecific_name,
  B.description,
  '' AS genome,
  DATA2.name AS db,
  DATA2.accession AS accession,
  DATA2.urlprefix AS urlprefix,
  ALIAS2.value AS alias
FROM biomaterial B
INNER JOIN organism O2 ON B.taxon_id = O2.organism_id
LEFT JOIN (
  SELECT biomaterial_id, accession, name, urlprefix
  FROM dbxref X2
  INNER JOIN db ON X2.db_id = db.db_id
  INNER JOIN biomaterial_dbxref BX2 ON X2.dbxref_id = BX2.dbxref_id
) DATA2 ON DATA2.biomaterial_id = B.biomaterial_id
LEFT JOIN (
  SELECT biomaterial_id, value
  FROM biomaterialprop
  WHERE type_id = (
    SELECT cvterm_id
    FROM cvterm
    WHERE name = 'submitter_provided_accession'
    AND cv_id = (
      SELECT cv_id
      FROM cv
      WHERE name = 'ncbi_properties' OR name = 'NCBI_BioSample_Attributes'
    )
  )
) ALIAS2 ON ALIAS2.biomaterial_id = B.biomaterial_id";
  tripal_add_mview($view_name, 'chado_search', $schema, $sql, '');
}
