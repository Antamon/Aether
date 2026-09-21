# Aether-databasemigraties

Deze bestanden zijn bedoeld voor handmatige uitvoering via phpMyAdmin of een gecontroleerde MariaDB-CLI. Er bestaat geen publiek PHP-endpoint voor migraties.

## one.com-status

Migraties 0001–0003 zijn op 21 september 2026 op `oneiros_beaetherdev` uitgevoerd en via de alleen-lezen one.com-inspectie gecontroleerd. Er is geen verdere apply- of rollbackactie nodig.

De controle toont:

- MariaDB 10.11.18 en `FOREIGN_KEY_CHECKS = 1`;
- de unieke `(idCharacter, idSkill)`-index;
- de unieke `(idCharacter, idSkill, idSkillSpecialisation)`-index;
- geen duplicaten of onverwachte nullwaarden;
- geen verweesde of verkeerd gekoppelde specialisatierecords;
- de drie migratieregistraties met `appliedAt = 2026-09-21 07:53:56`;
- vier reeds bekende verweesde character-skilllinks als waarschuwing: IDs 70, 71, 72 en 508.

De aangeleverde export `oneiros_be_mysql_service_one_com.sql` is om 07:43 UTC gegenereerd, ruim vóór de registratietijd. Hij is daardoor een pre-migratieback-up en bevat de nieuwe indexes en `tblSchemaMigration` nog niet.

Nieuwe automatische uitvoering in één bestand blijft op de huidige one.com/dbadmin-verbinding geblokkeerd. De databasegebruiker krijgt fout `#1044` bij toegang tot `information_schema`. Zonder die metadata kan het script niet betrouwbaar:

- een equivalente unieke index onder een andere naam herkennen;
- een bestaande bedoelde indexnaam met een verkeerde definitie blokkeren;
- de indexdefinitie na impliciet committende DDL verifiëren.

`run_0001_to_0003_phpmyadmin.sql` voert daarom bewust geen DDL of datamutaties meer uit. De eerdere implementatie staat alleen voor onderzoek in `run_0001_to_0003_requires_information_schema.sql.reference` en mag niet op one.com worden geïmporteerd. Dit voorkomt ook dat de reeds voltooide migraties opnieuw worden aangeraakt.

## Veilige volgende stap

Voer op de geselecteerde `oneiros_beaetherdev`-database uitsluitend uit:

`inspect_0001_to_0003_onecom_readonly.sql`

Dit bestand gebruikt alleen `SELECT` en `SHOW`, leest `information_schema` niet en wijzigt niets. Bewaar of kopieer alle resultaatsets. Op basis daarvan kan per actuele tabel worden vastgesteld of de migraties al geheel of gedeeltelijk zijn uitgevoerd en welke gerichte applystap nog veilig is.

## Afzonderlijke bestanden

De bestanden onder `preflight/`, `apply/` en `verify/` blijven de oorspronkelijke inspecteerbare migratiefasen. Hun metadataqueries gebruiken `information_schema` en zijn daardoor niet zonder meer bruikbaar met het beperkte one.com-account.

De bestanden onder `rollback/` worden alleen bewust en afzonderlijk gebruikt. `ALTER TABLE` kan impliciet committen; de bestanden claimen daarom geen transactionele DDL-rollback. Een rollback mag nooit worden uitgevoerd voordat de actuele indexdefinities handmatig zijn vastgesteld.

De volledige bevindingen staan in `docs/database-migrations-concurrency.md`.
