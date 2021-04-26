<?php
// Create 'germplasm_search' MView
function chado_search_create_gene_search_mview() {
  $view_name = 'chado_search_gene_search';
  chado_search_drop_mview($view_name);
  $schema = array (
  'table' => $view_name,
  'fields' => array (
    'feature_id' => array (
      'type' => 'int',
    ),
    'name' => array (
      'type' => 'varchar',
      'length' => '255',
    ),
    'uniquename' => array (
      'type' => 'text',
    ),
    'seqlen' => array (
      'type' => 'int',
    ),
    'genus' => array (
      'type' => 'varchar',
      'length' => 255,
    ),
    'species' => array (
      'type' => 'varchar',
      'length' => 255,
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
    ),
    'organism_common_name' => array (
      'type' => 'varchar',
      'length' => 255,
    ),
    'feature_type' => array (
      'type' => 'varchar',
      'length' => '1024',
    ),
    'srcfeature_id' => array (
      'type' => 'int',
    ),
    'landmark' => array (
      'type' => 'varchar',
      'length' => '255',
    ),
    'fmin' => array (
      'type' => 'int',
    ),
    'fmax' => array (
      'type' => 'int',
    ),
    'location' => array (
      'type' => 'varchar',
      'length' => '510',
    ),
    'analysis' => array (
      'type' => 'varchar',
      'length' => '255',
    ),
    'analysis_id' => array (
      'type' => 'int',
    ),
    'blast_value' => array (
      'type' => 'text',
    ),
    'kegg_value' => array (
      'type' => 'text',
    ),
    'interpro_value' => array (
      'type' => 'text',
    ),
    'go_term' => array (
      'type' => 'text',
    ),
    'go_acc' => array (
      'type' => 'varchar',
      'length' => '258',
    ),
    'gb_keyword' => array (
      'type' => 'text',
    ),
  ),
);

  $sql = "SELECT
F.feature_id,
F.name AS feature_name,
F.uniquename AS feature_uniquename,
F.seqlen AS feature_seqlen,
(SELECT genus FROM organism O WHERE O.organism_id = F.organism_id),
(SELECT species FROM organism O WHERE O.organism_id = F.organism_id),
REPLACE ((SELECT name FROM cvterm CVTT WHERE CVTT.cvterm_id = (SELECT type_id FROM organism O where O.organism_id = F.organism_id)
          AND CVTT.cv_id = (SELECT cv_id FROM cv WHERE name='taxonomic_rank')), 'no_rank', '') as infraspecific_type,
(SELECT infraspecific_name FROM organism O WHERE O.organism_id = F.organism_id),
(SELECT concat_ws(' ', O.genus, O.species, REPLACE (
       (SELECT name FROM cvterm CVTT WHERE CVTT.cvterm_id = O.type_id
         AND CVTT.cv_id = (SELECT cv_id FROM cv WHERE name='taxonomic_rank')),
       'no_rank', ''), O.infraspecific_name) FROM organism O WHERE O.organism_id = F.organism_id) AS organism,
(SELECT common_name FROM organism WHERE organism_id = F.organism_id) AS organism_common_name,
(SELECT name FROM cvterm WHERE cvterm_id = F.type_id) AS feature_type,
LOC.srcfeature_id,
      LOC.name AS landmark,
      (fmin + 1) AS fmin,
      fmax,
      LOC.name || ':' || (fmin + 1) || '..' || fmax AS location,
A.name AS analysis,
A.analysis_id,
-- Blast Best Hit
(SELECT string_agg(distinct
   (SELECT array_to_string(regexp_matches(value, '<Hit_def>(.+?)</Hit_def>'), '')
     FROM analysisfeatureprop AFP2 WHERE AFP2.analysisfeatureprop_id = AFP.analysisfeatureprop_id)
    , '; ')
  FROM analysisfeatureprop AFP
  INNER JOIN analysisfeature AF2 ON AF2.analysisfeature_id = AFP.analysisfeature_id
  WHERE
    type_id = (SELECT cvterm_id FROM cvterm WHERE name = 'analysis_blast_output_iteration_hits')
  AND
  AF2.feature_id = F.feature_id
) AS blast_value,
-- KEGG
(SELECT string_agg(distinct
   (SELECT trim(regexp_replace(value, '<.+>', ''))
     FROM analysisfeatureprop AFP2 WHERE AFP2.analysisfeatureprop_id = AFP.analysisfeatureprop_id)
    , '; ')
  FROM analysisfeatureprop AFP
  INNER JOIN analysisfeature AF2 ON AF2.analysisfeature_id = AFP.analysisfeature_id
  WHERE
    type_id IN (SELECT cvterm_id FROM cvterm WHERE cv_id = (SELECT cv_id FROM cv WHERE name = 'KEGG_ORTHOLOGY'  or name = 'KEGG_PATHWAYS'))
  AND
  AF2.feature_id = F.feature_id
) AS kegg_value,
-- Interpro
(
SELECT string_agg(distinct value, '; ')
FROM (
  SELECT
  AF2.feature_id,
  array_to_string (regexp_matches(value, 'name="(.+?)"', 'g'), '') AS value
  FROM analysisfeatureprop AFP2
  INNER JOIN analysisfeature AF2 ON AF2.analysisfeature_id = AFP2.analysisfeature_id
  WHERE AFP2.type_id = (SELECT cvterm_id FROM cvterm WHERE name = 'analysis_interpro_xmloutput_hit')
  AND AF2.feature_id = F.feature_id
) INTERPRO GROUP BY feature_id
) AS interpro_value,
-- GO term
(
SELECT string_agg(distinct name, '; ')
FROM (
  SELECT feature_id,
  V.name
  FROM feature_cvterm FC
  INNER JOIN cvterm V ON V.cvterm_id = FC.cvterm_id
  WHERE FC.feature_id = F.feature_id
  AND cv_id IN (SELECT cv_id FROM cv WHERE name IN ('biological_process', 'cellular_component', 'molecular_function'))
) GOTERM GROUP BY feature_id
) AS go_term,
-- GO accession
(
SELECT string_agg(distinct acc, '; ')
FROM (
  SELECT feature_id,
  'GO:' || (SELECT accession FROM dbxref WHERE dbxref_id = V.dbxref_id) AS acc
  FROM feature_cvterm FC
  INNER JOIN cvterm V ON V.cvterm_id = FC.cvterm_id
  WHERE FC.feature_id = F.feature_id
  AND cv_id IN (SELECT cv_id FROM cv WHERE name IN ('biological_process', 'cellular_component', 'molecular_function'))
) GOTERM GROUP BY feature_id
) AS go_acc,
-- Genbank Keywords
(
SELECT string_agg(value, '; ')
FROM featureprop
WHERE type_id IN
  (SELECT cvterm_id
   FROM cvterm
   WHERE cv_id =
    (SELECT cv_id
     FROM cv
     WHERE name = 'tripal_genbank_parser')
   AND name IN ('genbank_note','genbank_gene','product','function','genbank_detail','genbank_description'))
AND feature_id = F.feature_id
GROUP BY feature_id
) AS gb_keyword
-- Base Table
FROM feature F
INNER JOIN analysisfeature AF ON AF.feature_id = F.feature_id
INNER JOIN analysis A ON A.analysis_id = AF.analysis_id
-- Genome Location
  LEFT JOIN
  ((SELECT
              GENE.feature_id,
              LMARK.srcfeature_id,
              LMARK.fmin,
              LMARK.fmax,
              LMARK.name
              FROM Feature GENE
              INNER JOIN featureloc GMATLOC ON GMATLOC.srcfeature_id = GENE.feature_id
              INNER JOIN (
                SELECT
                  LMATLOC.feature_id,
                  LMATLOC.srcfeature_id,
                  LMATLOC.fmin,
                  LMATLOC.fmax,
                  CHR.name
                FROM Feature CHR
                INNER JOIN featureloc LMATLOC ON LMATLOC.srcfeature_id = CHR.feature_id
                WHERE (SELECT type_id FROM feature F WHERE F.feature_id = LMATLOC.feature_id) = (SELECT cvterm_id FROM cvterm WHERE name = 'match' AND cv_id = (SELECT cv_id FROM cv WHERE name = 'sequence'))
                AND CHR.type_id IN (SELECT cvterm_id FROM cvterm WHERE name IN ('DNA', 'chromosome', 'supercontig') AND cv_id = (SELECT cv_id FROM cv WHERE name = 'sequence'))
                ) LMARK ON LMARK.feature_id = GMATLOC.feature_id
                WHERE GENE.type_id IN (SELECT cvterm_id FROM cvterm WHERE name IN ('gene', 'mRNA', 'contig') AND cv_id = (SELECT cv_id FROM cv WHERE name = 'sequence')))
          UNION
              (SELECT FL.feature_id, srcfeature_id, fmin, fmax, F.name FROM featureloc FL
                INNER JOIN feature F ON F.feature_id = FL.srcfeature_id
               WHERE F.type_id IN (SELECT cvterm_id FROM cvterm WHERE name IN ('DNA', 'chromosome', 'supercontig') AND cv_id = (SELECT cv_id FROM cv WHERE name = 'sequence'))
              )
          ) LOC ON LOC.feature_id = AF.feature_id
WHERE
      (
-- Restrict the sequence type to gene/mRNA/contig for Unigene/RefTrans
      (F.type_id IN (SELECT cvterm_id FROM cvterm WHERE name IN ('contig', 'gene', 'mRNA') AND cv_id = (SELECT cv_id FROM cv WHERE name = 'sequence'))) AND
      (A.analysis_id IN (SELECT analysis_id FROM analysisprop WHERE type_id = (SELECT cvterm_id FROM cvterm WHERE cv_id = (SELECT cv_id FROM cv WHERE name = 'rdfs') AND name = 'type') AND value IN ('reftrans', 'unigene', 'bulk_data', 'ncbi_data', 'gdr_gene_database', 'transcriptome', 'other_transcripts')))
      OR
-- Restrict the sequence type to gene/mRNA for whole genome assembly
      (F.type_id IN (SELECT cvterm_id FROM cvterm WHERE name IN ('gene', 'mRNA') AND cv_id = (SELECT cv_id FROM cv WHERE name = 'sequence'))) AND
      (A.analysis_id IN (SELECT analysis_id FROM analysisprop WHERE type_id = (SELECT cvterm_id FROM cvterm WHERE cv_id = (SELECT cv_id FROM cv WHERE name = 'rdfs') AND name = 'type') AND value IN ('whole_genome', 'genome_assembly', 'genome_annotation')))
      )";

  tripal_add_mview($view_name, 'chado_search', $schema, $sql, '');
}
