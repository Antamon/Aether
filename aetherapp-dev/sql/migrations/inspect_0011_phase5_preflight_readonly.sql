-- Alleen-lezen controle vóór fase 5. Voer uit in de geselecteerde databank.
SELECT DATABASE() AS selectedDatabase;

-- Vereist: precies deze vijf regels. Voer dit pas uit als tblSchemaMigration bestaat.
SELECT migrationCode
FROM tblSchemaMigration
WHERE migrationCode IN (
  '0006_share_reform_schema',
  '0007_share_reform_backfill',
  '0008_share_domain_codes',
  '0009_share_status_initialization',
  '0010_holder_sale_offer',
  '0011_company_leadership'
)
ORDER BY migrationCode;
