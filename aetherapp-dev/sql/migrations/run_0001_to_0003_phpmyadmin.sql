-- GEBLOKKEERD VOOR ONE.COM / DBADMIN
--
-- De databasegebruiker op deze hosting kan information_schema niet betrouwbaar
-- lezen. Daardoor kunnen bestaande equivalente indexes en fout gedefinieerde
-- indexnamen niet veilig automatisch worden gecontroleerd.
--
-- Dit bestand voert bewust geen DDL en geen datamutaties uit. Gebruik eerst:
--   inspect_0001_to_0003_onecom_readonly.sql
--
-- De eerdere implementatie is alleen ter referentie bewaard als:
--   run_0001_to_0003_requires_information_schema.sql.reference

SELECT
  'GEEN ACTIE: migraties 0001-0003 zijn op oneiros_beaetherdev toegepast en gecontroleerd; dit bestand voert niets uit.'
    AS migrationBlocked;
