<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../characters/characterPointUtils.php';
require_once __DIR__ . '/../characters/economyUtils.php';

function requirePrivilegedCompanyAccess(PDO $pdo): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user']['id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Geen gebruiker in sessie.']);
        exit;
    }

    $currentUserRole = getCurrentUserRole($pdo);
    if (!isPrivilegedUserRole($currentUserRole)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om bedrijven te beheren.']);
        exit;
    }
}

function normalizeCompanySliderValue(mixed $value): int
{
    return max(-7, min(7, (int) $value));
}

function normalizeCompanyValue(mixed $value): float
{
    $normalized = round((float) $value, 2);
    return max(0, $normalized);
}

function getDefaultCompanyFoundationDate(): string
{
    return (new DateTimeImmutable('today'))->sub(new DateInterval('P100Y'))->format('Y-m-d');
}

function getCompanyTypeDefinitions(): array
{
    return [
        'micro' => [
            'key' => 'micro',
            'label' => 'Micro-onderneming',
            'min' => 0.0,
            'max' => 30000.0,
            'description' => 'Dit type onderneming is klein, persoonlijk en volledig verweven met het leven van de eigenaar. De zaak is vaak gevestigd in of naast de woning, en draait op vakmanschap, reputatie en vaste klanten. Groei is beperkt, maar stabiliteit kan hoog zijn zolang de eigenaar gezond blijft. Innovatie gebeurt traag en meestal uit noodzaak, niet uit strategie. Dit zijn de stille radertjes van de stad: onmisbaar, maar zelden zichtbaar in het grote economische spel. Voorbeelden: bakkerij, kleermakerij, schoenmaker, kleine herberg, horlogemaker.',
        ],
        'family' => [
            'key' => 'family',
            'label' => 'Familiebedrijf',
            'min' => 30000.0,
            'max' => 450000.0,
            'description' => 'Een familiebedrijf vormt een eerste echte economische entiteit los van één individu. Meerdere familieleden of werknemers dragen bij aan de werking, en er is vaak sprake van specialisatie en eenvoudige organisatie. Investeringen in materiaal of infrastructuur zijn merkbaar, en het bedrijf kan lokale concurrentie aangaan of zelfs domineren. De continuïteit ligt in de familie: generaties bouwen verder op wat eerder is opgebouwd. Voorbeelden: drukkerij, brouwerij, transportbedrijf met paarden en vroege stoomwagens, kleine bouwonderneming, industriële wasserij.',
        ],
        'national' => [
            'key' => 'national',
            'label' => 'Nationale onderneming',
            'min' => 450000.0,
            'max' => 6750000.0,
            'description' => 'Dit type bedrijf overstijgt de lokale markt en opereert op nationaal niveau, vaak met meerdere vestigingen of distributiepunten. Er ontstaat een duidelijke hiërarchie met management, administratie en gespecialiseerde arbeiders. De eigenaar is niet langer dagelijks betrokken bij de uitvoering, maar stuurt op afstand via leidinggevenden. Deze bedrijven zijn zichtbaar in het straatbeeld en beginnen een merkidentiteit op te bouwen. Ze beïnvloeden prijzen, werkgelegenheid en soms zelfs lokale politiek. Voorbeelden: warenhuisketen, nationale brouwerijgroep, spoorwegmaatschappij binnen één land, grote textielfabriek, gas- of elektriciteitsmaatschappij in opkomst.',
        ],
        'small_international' => [
            'key' => 'small_international',
            'label' => 'Kleine internationale groep',
            'min' => 6750000.0,
            'max' => 101250000.0,
            'description' => 'Deze bedrijven opereren over landsgrenzen heen en combineren productie, handel en financiën. Ze hebben dochterondernemingen in meerdere regio’s en werken vaak met complexe eigendomsstructuren. Beslissingen worden strategisch genomen en hebben impact op hele sectoren. Innovatie speelt een grotere rol, zeker in een deze moderne tijd waar technologie een concurrentievoordeel biedt. Ze onderhouden banden met banken, adel en overheden. Voorbeelden: internationale staalproducent, chemisch bedrijf, spoorwegnetwerk tussen meerdere landen, fabrikant van industriële machines, telegraaf- of communicatienetwerk.',
        ],
        'large_international' => [
            'key' => 'large_international',
            'label' => 'Grote internationale groep',
            'min' => 101250000.0,
            'max' => null,
            'description' => 'Dit zijn economische grootmachten die functioneren als staten binnen staten. Ze controleren volledige productieketens, van grondstof tot eindproduct, en hebben invloed op internationale handel en politiek. De leiding bestaat uit elites: industriëlen, bankiers en adel. Hun beslissingen kunnen oorlogen beïnvloeden, steden doen groeien of instorten, en technologische revoluties versnellen. In een steampunkwereld zijn dit de bedrijven die experimenteren met grensverleggende en soms gevaarlijke technologieën. Voorbeelden: continentaal spoorwegimperium, megaconglomeraat in staal en wapens, energiebedrijf met stoom- en elektrische netwerken, internationale bankholding, fabrikant van geavanceerde lucht- of oorlogsmachines.',
        ],
    ];
}

function getCompanyTypeByValue(mixed $value): array
{
    $companyValue = normalizeCompanyValue($value);
    $definitions = getCompanyTypeDefinitions();

    if ($companyValue <= 30000.0) {
        return $definitions['micro'];
    }

    if ($companyValue <= 450000.0) {
        return $definitions['family'];
    }

    if ($companyValue <= 6750000.0) {
        return $definitions['national'];
    }

    if ($companyValue <= 101250000.0) {
        return $definitions['small_international'];
    }

    return $definitions['large_international'];
}

function enrichCompanyWithType(array $company): array
{
    $type = getCompanyTypeByValue($company['companyValue'] ?? 0);
    $company['companyTypeKey'] = $type['key'];
    $company['companyTypeLabel'] = $type['label'];
    $company['companyTypeDescription'] = $type['description'];

    return $company;
}

function getCompanyLogoDirectory(): string
{
    return dirname(__DIR__, 2) . '/img/bedrijfslogo';
}

function getCompanyLogoAbsolutePath(int $companyId): string
{
    return getCompanyLogoDirectory() . '/' . $companyId . '.png';
}

function getCompanyLogoPublicPath(int $companyId): string
{
    return 'img/bedrijfslogo/' . $companyId . '.png';
}

function getCompanyLogoUrl(int $companyId): ?string
{
    $logoPath = getCompanyLogoAbsolutePath($companyId);
    if (!is_file($logoPath)) {
        return null;
    }

    return getCompanyLogoPublicPath($companyId) . '?v=' . filemtime($logoPath);
}

function inferCompanyShareClassFromTraitName(string $traitName): ?string
{
    $normalizedName = mb_strtolower(trim(preg_replace('/\s+/', ' ', $traitName)));
    if (str_starts_with($normalizedName, 'a-aandelen')) {
        return 'A';
    }

    if (str_starts_with($normalizedName, 'b-aandelen')) {
        return 'B';
    }

    return null;
}

function formatCompanyShareholderDisplayName(array $row): string
{
    $title = trim((string) ($row['title'] ?? ''));
    $firstName = trim((string) ($row['firstName'] ?? ''));
    $lastName = trim((string) ($row['lastName'] ?? ''));
    $name = trim(($title !== '' ? $title . ' ' : '') . $firstName . ' ' . $lastName);

    if ($name !== '') {
        return $name;
    }

    return 'Personage #' . (int) ($row['idCharacter'] ?? 0);
}

function sortCompanyShareholderEntries(array &$entries): void
{
    usort($entries, static function (array $left, array $right): int {
        $leftPercentage = (int) ($left['percentage'] ?? 0);
        $rightPercentage = (int) ($right['percentage'] ?? 0);
        if ($leftPercentage !== $rightPercentage) {
            return $rightPercentage <=> $leftPercentage;
        }

        return strcasecmp((string) ($left['displayName'] ?? ''), (string) ($right['displayName'] ?? ''));
    });
}

function getCompanyShareholderGroups(PDO $pdo, int $idCompany): array
{
    if ($idCompany <= 0) {
        return [
            ['shareClass' => 'A', 'label' => 'A-aandelen', 'shareholders' => []],
            ['shareClass' => 'B', 'label' => 'B-aandelen', 'shareholders' => []],
        ];
    }

    try {
        $rows = dbAll(
            $pdo,
            'SELECT
                c.id AS idCharacter,
                c.title,
                c.firstName,
                c.lastName,
                t.name AS traitName,
                COALESCE(tsd.shareClass, \'\') AS shareClass,
                lct.rankValue,
                COALESCE(lctc.extraPercentage, 0) AS extraPercentage
             FROM tblLinkCharacterTraitCompany AS lctc
             JOIN tblLinkCharacterTrait AS lct
               ON lct.id = lctc.idLinkCharacterTrait
             JOIN tblTrait AS t
               ON t.id = lct.idTrait
             JOIN tblCharacter AS c
               ON c.id = lct.idCharacter
             LEFT JOIN tblTraitShareDefinition AS tsd
               ON tsd.idTrait = t.id
             WHERE lctc.idCompany = :idCompany',
            ['idCompany' => $idCompany]
        );
    } catch (Throwable $e) {
        $rows = dbAll(
            $pdo,
            'SELECT
                c.id AS idCharacter,
                c.title,
                c.firstName,
                c.lastName,
                t.name AS traitName,
                lct.rankValue,
                COALESCE(lctc.extraPercentage, 0) AS extraPercentage
             FROM tblLinkCharacterTraitCompany AS lctc
             JOIN tblLinkCharacterTrait AS lct
               ON lct.id = lctc.idLinkCharacterTrait
             JOIN tblTrait AS t
               ON t.id = lct.idTrait
             JOIN tblCharacter AS c
               ON c.id = lct.idCharacter
             WHERE lctc.idCompany = :idCompany',
            ['idCompany' => $idCompany]
        );
    }

    $grouped = [
        'A' => [],
        'B' => [],
    ];

    foreach ($rows as $row) {
        $shareClass = trim((string) ($row['shareClass'] ?? ''));
        if ($shareClass === '') {
            $shareClass = inferCompanyShareClassFromTraitName((string) ($row['traitName'] ?? '')) ?? '';
        }

        if ($shareClass !== 'A' && $shareClass !== 'B') {
            continue;
        }

        $percentage = max(0, (int) ($row['rankValue'] ?? 0) + (int) ($row['extraPercentage'] ?? 0));
        if ($percentage <= 0) {
            continue;
        }

        $idCharacter = (int) ($row['idCharacter'] ?? 0);
        if ($idCharacter <= 0) {
            continue;
        }

        if (!isset($grouped[$shareClass][$idCharacter])) {
            $grouped[$shareClass][$idCharacter] = [
                'idCharacter' => $idCharacter,
                'displayName' => formatCompanyShareholderDisplayName($row),
                'percentage' => 0,
            ];
        }

        $grouped[$shareClass][$idCharacter]['percentage'] += $percentage;
    }

    $aShareholders = array_values($grouped['A']);
    $bShareholders = array_values($grouped['B']);
    sortCompanyShareholderEntries($aShareholders);
    sortCompanyShareholderEntries($bShareholders);

    return [
        ['shareClass' => 'A', 'label' => 'A-aandelen', 'shareholders' => $aShareholders],
        ['shareClass' => 'B', 'label' => 'B-aandelen', 'shareholders' => $bShareholders],
    ];
}

function getCompanyShareholderPayoutEntries(PDO $pdo, int $idCompany): array
{
    $groups = getCompanyShareholderGroups($pdo, $idCompany);
    $entries = [];

    foreach ($groups as $group) {
        foreach ((array) ($group['shareholders'] ?? []) as $shareholder) {
            $idCharacter = (int) ($shareholder['idCharacter'] ?? 0);
            $percentage = max(0, (int) ($shareholder['percentage'] ?? 0));
            if ($idCharacter <= 0 || $percentage <= 0) {
                continue;
            }

            if (!isset($entries[$idCharacter])) {
                $entries[$idCharacter] = [
                    'idCharacter' => $idCharacter,
                    'displayName' => (string) ($shareholder['displayName'] ?? ('Personage #' . $idCharacter)),
                    'percentage' => 0,
                ];
            }

            $entries[$idCharacter]['percentage'] += $percentage;
        }
    }

    return array_values($entries);
}

function getCompanyAllocatedSharePercentage(PDO $pdo, int $idCompany): int
{
    if ($idCompany <= 0) {
        return 0;
    }

    try {
        $rows = dbAll(
            $pdo,
            'SELECT
                lct.idTrait,
                lct.rankValue,
                COALESCE(lctc.extraPercentage, 0) AS extraPercentage
             FROM tblLinkCharacterTraitCompany AS lctc
             JOIN tblLinkCharacterTrait AS lct
               ON lct.id = lctc.idLinkCharacterTrait
             WHERE lctc.idCompany = :idCompany',
            ['idCompany' => $idCompany]
        );
    } catch (Throwable $e) {
        return 0;
    }

    $allocated = 0;
    foreach ($rows as $row) {
        try {
            require_once __DIR__ . '/../characters/traitUtils.php';
            require_once __DIR__ . '/../characters/companyShareUtils.php';
            $trait = getTraitDefinition($pdo, (int) ($row['idTrait'] ?? 0));
            if (!$trait || !isCompanyShareTrait($trait)) {
                continue;
            }
        } catch (Throwable $e) {
            continue;
        }

        $allocated += max(0, (int) ($row['rankValue'] ?? 0) + (int) ($row['extraPercentage'] ?? 0));
    }

    return max(0, min(100, $allocated));
}

function getCompanyAvailableSharePercentage(PDO $pdo, int $idCompany): int
{
    return max(0, 100 - getCompanyAllocatedSharePercentage($pdo, $idCompany));
}

function getCompanySnapshotEventOptions(PDO $pdo): array
{
    try {
        $rows = dbAll(
            $pdo,
            'SELECT id, title, dateStart, dateEnd
               FROM tblEvent
              ORDER BY dateStart ASC, id ASC'
        );
    } catch (Throwable $e) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'idEvent' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'dateStart' => (string) ($row['dateStart'] ?? ''),
            'dateEnd' => (string) ($row['dateEnd'] ?? ''),
        ];
    }, $rows);
}

function getCompanySnapshotStabilityRangePercentage(int $stability): float
{
    return max(1.0, min(15.0, 8.0 - $stability));
}

function getCompanySnapshotProfitabilityPercentage(int $profitability): float
{
    return max(-11.0, min(17.0, 3.0 + (2.0 * $profitability)));
}

function generateRandomCompanySnapshotAdjustment(float $companyValue, float $stabilityRangePercentage): float
{
    $rangeAmountInCents = (int) round(max(0.0, $companyValue) * (max(0.0, $stabilityRangePercentage) / 100) * 100);
    if ($rangeAmountInCents <= 0) {
        return 0.0;
    }

    return round(random_int(-$rangeAmountInCents, $rangeAmountInCents) / 100, 2);
}

function generateRandomCompanySnapshotAdjustmentFromBounds(
    float $companyValue,
    float $lowerBoundPercentage,
    float $upperBoundPercentage
): float {
    $normalizedCompanyValue = normalizeCompanyValue($companyValue);
    $lowerAmountInCents = (int) round($normalizedCompanyValue * ($lowerBoundPercentage / 100) * 100);
    $upperAmountInCents = (int) round($normalizedCompanyValue * ($upperBoundPercentage / 100) * 100);

    if ($upperAmountInCents < $lowerAmountInCents) {
        [$lowerAmountInCents, $upperAmountInCents] = [$upperAmountInCents, $lowerAmountInCents];
    }

    if ($lowerAmountInCents === $upperAmountInCents) {
        return round($lowerAmountInCents / 100, 2);
    }

    return round(random_int($lowerAmountInCents, $upperAmountInCents) / 100, 2);
}

function getCompanyPersonnelImportanceMultipliers(): array
{
    return [
        'Negligible' => 1.0,
        'Low' => 2.0,
        'Moderate' => 4.0,
        'High' => 6.0,
        'Critical' => 8.0,
    ];
}

function getCompanyPersonnelMatchPercentage(int $effectiveLevel, int $requiredLevel): float
{
    $normalizedEffectiveLevel = max(0, min(4, $effectiveLevel));
    $normalizedRequiredLevel = max(1, min(3, $requiredLevel));
    $matchMatrix = [
        0 => [1 => -2.0, 2 => -3.0, 3 => -4.0],
        1 => [1 => 2.0, 2 => -2.0, 3 => -3.0],
        2 => [1 => 3.0, 2 => 2.0, 3 => -2.0],
        3 => [1 => 4.0, 2 => 3.0, 3 => 2.0],
        4 => [1 => 5.0, 2 => 4.0, 3 => 3.0],
    ];

    return $matchMatrix[$normalizedEffectiveLevel][$normalizedRequiredLevel] ?? 0.0;
}

function getCompanyPersonnelImpactSummary(PDO $pdo, int $idCompany, int $stability): array
{
    if ($idCompany <= 0) {
        $baseRange = getCompanySnapshotStabilityRangePercentage($stability);
        return [
            'totalPercentage' => 0.0,
            'lowerBoundPercentage' => -$baseRange,
            'upperBoundPercentage' => $baseRange,
        ];
    }

    try {
        $rows = dbAll(
            $pdo,
            'SELECT
                cp.id AS idCompanyPersonnel,
                cp.idCharacter,
                cp.importance,
                cps.idSkill,
                cps.level AS requiredLevel,
                cpss.idSkillSpecialisation
             FROM tblCompanyPersonnel AS cp
             LEFT JOIN tblCompanyPersonnelSkill AS cps
               ON cps.idCompanyPersonnel = cp.id
             LEFT JOIN tblCompanyPersonnelSkillSpecialisation AS cpss
               ON cpss.idCompanyPersonnelSkill = cps.id
             WHERE cp.idCompany = :idCompany
             ORDER BY cp.id ASC, cps.id ASC, cpss.id ASC',
            ['idCompany' => $idCompany]
        );
    } catch (Throwable $e) {
        $rows = [];
    }

    $entries = [];
    foreach ($rows as $row) {
        $idCompanyPersonnel = (int) ($row['idCompanyPersonnel'] ?? 0);
        if ($idCompanyPersonnel <= 0) {
            continue;
        }

        if (!isset($entries[$idCompanyPersonnel])) {
            $entries[$idCompanyPersonnel] = [
                'idCharacter' => (int) ($row['idCharacter'] ?? 0),
                'importance' => normalizeCompanyPersonnelImportance($row['importance'] ?? 'Moderate'),
                'idSkill' => (int) ($row['idSkill'] ?? 0),
                'requiredLevel' => normalizeCompanyPersonnelSkillLevel($row['requiredLevel'] ?? 1),
                'requiredSpecialisationIds' => [],
            ];
        }

        $idSkillSpecialisation = (int) ($row['idSkillSpecialisation'] ?? 0);
        if ($idSkillSpecialisation > 0) {
            $entries[$idCompanyPersonnel]['requiredSpecialisationIds'][$idSkillSpecialisation] = true;
        }
    }

    $characterIds = array_values(array_unique(array_filter(array_map(
        static fn(array $entry): int => (int) ($entry['idCharacter'] ?? 0),
        array_values($entries)
    ))));
    $skillIds = array_values(array_unique(array_filter(array_map(
        static fn(array $entry): int => (int) ($entry['idSkill'] ?? 0),
        array_values($entries)
    ))));

    $characterSkillLevels = [];
    if (count($characterIds) > 0 && count($skillIds) > 0) {
        $charIn = buildCompanyPersonnelInClause($characterIds, 'personnelSkillChar');
        $skillIn = buildCompanyPersonnelInClause($skillIds, 'personnelSkillId');
        $params = array_merge($charIn['params'], $skillIn['params']);

        try {
            $skillRows = dbAll(
                $pdo,
                "SELECT idCharacter, idSkill, level
                   FROM tblLinkCharacterSkill
                  WHERE idCharacter IN ({$charIn['clause']})
                    AND idSkill IN ({$skillIn['clause']})",
                $params
            );

            foreach ($skillRows as $skillRow) {
                $characterSkillLevels[(int) $skillRow['idCharacter']][(int) $skillRow['idSkill']] = (int) ($skillRow['level'] ?? 0);
            }
        } catch (Throwable $e) {
            $characterSkillLevels = [];
        }
    }

    $characterSpecialisations = [];
    if (count($characterIds) > 0 && count($skillIds) > 0) {
        $charIn = buildCompanyPersonnelInClause($characterIds, 'personnelSpecChar');
        $skillIn = buildCompanyPersonnelInClause($skillIds, 'personnelSpecId');
        $params = array_merge($charIn['params'], $skillIn['params']);

        try {
            $specRows = dbAll(
                $pdo,
                "SELECT idCharacter, idSkill, idSkillSpecialisation
                   FROM tblCharacterSpecialisation
                  WHERE idCharacter IN ({$charIn['clause']})
                    AND idSkill IN ({$skillIn['clause']})",
                $params
            );

            foreach ($specRows as $specRow) {
                $characterSpecialisations[(int) $specRow['idCharacter']][(int) $specRow['idSkill']][(int) $specRow['idSkillSpecialisation']] = true;
            }
        } catch (Throwable $e) {
            $characterSpecialisations = [];
        }
    }

    $importanceMultipliers = getCompanyPersonnelImportanceMultipliers();
    $totalPercentage = 0.0;

    foreach ($entries as $entry) {
        $idCharacter = (int) ($entry['idCharacter'] ?? 0);
        $idSkill = (int) ($entry['idSkill'] ?? 0);
        if ($idCharacter <= 0 || $idSkill <= 0) {
            continue;
        }

        $actualLevel = (int) ($characterSkillLevels[$idCharacter][$idSkill] ?? 0);
        $requiredLevel = (int) ($entry['requiredLevel'] ?? 1);
        $requiredSpecialisationIds = array_keys($entry['requiredSpecialisationIds'] ?? []);
        $actualSpecialisations = $characterSpecialisations[$idCharacter][$idSkill] ?? [];
        $hasMatchingSpecialisation = false;

        foreach ($requiredSpecialisationIds as $idSkillSpecialisation) {
            if (!empty($actualSpecialisations[(int) $idSkillSpecialisation])) {
                $hasMatchingSpecialisation = true;
                break;
            }
        }

        $effectiveLevel = $actualLevel;
        if ($hasMatchingSpecialisation && $effectiveLevel > 0) {
            $effectiveLevel = min(4, $effectiveLevel + 1);
        }

        $matchPercentage = getCompanyPersonnelMatchPercentage($effectiveLevel, $requiredLevel);
        $importance = (string) ($entry['importance'] ?? 'Moderate');
        $importanceMultiplier = $importanceMultipliers[$importance] ?? 1.0;
        $totalPercentage += $matchPercentage * $importanceMultiplier;
    }

    $baseRange = getCompanySnapshotStabilityRangePercentage($stability);
    return [
        'totalPercentage' => round($totalPercentage, 2),
        'lowerBoundPercentage' => round(-$baseRange, 2),
        'upperBoundPercentage' => round($baseRange, 2),
    ];
}

function getCompanyPersonnelSalaryIncreaseExpenseAmount(PDO $pdo, int $idCompany): float
{
    if ($idCompany <= 0) {
        return 0.0;
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT
                cp.idCharacter,
                c.`class`,
                cp.salaryIncreasePercentage
             FROM tblCompanyPersonnel AS cp
             JOIN tblCharacter AS c
               ON c.id = cp.idCharacter
             WHERE cp.idCompany = :idCompany
               AND cp.salaryIncreasePercentage > 0",
            ['idCompany' => $idCompany]
        );
    } catch (Throwable $e) {
        return 0.0;
    }

    $monthlyExtraSalaryTotal = 0.0;
    foreach ($rows as $row) {
        $idCharacter = (int) ($row['idCharacter'] ?? 0);
        if ($idCharacter <= 0) {
            continue;
        }

        $monthlyExtraSalaryTotal += getCharacterCompanySalaryIncreaseAmount(
            $pdo,
            [
                'id' => $idCharacter,
                'class' => (string) ($row['class'] ?? ''),
            ],
            $idCompany
        );
    }

    return round($monthlyExtraSalaryTotal * 12, 2);
}

function applyCompanyPersonnelImpactToSnapshotAdjustment(
    float $rawAdjustmentAmount,
    float $personnelImpactPercentage
): array {
    $normalizedRawAdjustmentAmount = round($rawAdjustmentAmount, 2);
    $normalizedPersonnelImpactPercentage = round($personnelImpactPercentage, 2);
    $personnelAdjustmentAmount = 0.0;

    if ($normalizedPersonnelImpactPercentage > 0 && $normalizedRawAdjustmentAmount < 0) {
        $personnelAdjustmentAmount = round(
            abs($normalizedRawAdjustmentAmount) * ($normalizedPersonnelImpactPercentage / 100),
            2
        );
    } elseif ($normalizedPersonnelImpactPercentage < 0 && $normalizedRawAdjustmentAmount > 0) {
        $personnelAdjustmentAmount = round(
            $normalizedRawAdjustmentAmount * ($normalizedPersonnelImpactPercentage / 100),
            2
        );
    }

    return [
        'rawAdjustmentAmount' => $normalizedRawAdjustmentAmount,
        'personnelAdjustmentAmount' => $personnelAdjustmentAmount,
        'finalAdjustmentAmount' => round($normalizedRawAdjustmentAmount + $personnelAdjustmentAmount, 2),
    ];
}

function calculateCompanySnapshotFinancials(
    float $companyValue,
    int $stability,
    int $profitability,
    float $personnelImpactPercentage = 0.0,
    float $personnelSalaryIncreaseExpenseAmount = 0.0,
    ?float $lowerBoundPercentage = null,
    ?float $upperBoundPercentage = null
): array
{
    $normalizedCompanyValue = normalizeCompanyValue($companyValue);
    $normalizedStability = normalizeCompanySliderValue($stability);
    $normalizedProfitability = normalizeCompanySliderValue($profitability);

    $profitabilityPercentage = getCompanySnapshotProfitabilityPercentage($normalizedProfitability);
    $stabilityRangePercentage = getCompanySnapshotStabilityRangePercentage($normalizedStability);
    $resolvedLowerBoundPercentage = $lowerBoundPercentage ?? -$stabilityRangePercentage;
    $resolvedUpperBoundPercentage = $upperBoundPercentage ?? $stabilityRangePercentage;

    $baseProfitAmountBeforePersonnel = round($normalizedCompanyValue * ($profitabilityPercentage / 100), 2);
    $normalizedPersonnelSalaryIncreaseExpenseAmount = round(max(0.0, $personnelSalaryIncreaseExpenseAmount), 2);
    $baseProfitAmount = round($baseProfitAmountBeforePersonnel - $normalizedPersonnelSalaryIncreaseExpenseAmount, 2);
    $rawStabilityAdjustmentAmount = generateRandomCompanySnapshotAdjustmentFromBounds(
        $normalizedCompanyValue,
        (float) $resolvedLowerBoundPercentage,
        (float) $resolvedUpperBoundPercentage
    );
    $personnelAdjustedStability = applyCompanyPersonnelImpactToSnapshotAdjustment(
        $rawStabilityAdjustmentAmount,
        $personnelImpactPercentage
    );
    $stabilityAdjustmentAmount = $personnelAdjustedStability['finalAdjustmentAmount'];
    $profitAmount = round($baseProfitAmount + $stabilityAdjustmentAmount, 2);

    return [
        'companyValue' => $normalizedCompanyValue,
        'profitabilityPercentage' => $profitabilityPercentage,
        'stabilityRangePercentage' => $stabilityRangePercentage,
        'personnelImpactPercentage' => round($personnelImpactPercentage, 2),
        'stabilityLowerBoundPercentage' => round((float) $resolvedLowerBoundPercentage, 2),
        'stabilityUpperBoundPercentage' => round((float) $resolvedUpperBoundPercentage, 2),
        'personnelSalaryIncreaseExpenseAmount' => $normalizedPersonnelSalaryIncreaseExpenseAmount,
        'baseProfitAmountBeforePersonnel' => $baseProfitAmountBeforePersonnel,
        'baseProfitAmount' => $baseProfitAmount,
        'rawStabilityAdjustmentAmount' => $personnelAdjustedStability['rawAdjustmentAmount'],
        'personnelAdjustmentAmount' => $personnelAdjustedStability['personnelAdjustmentAmount'],
        'stabilityAdjustmentAmount' => $stabilityAdjustmentAmount,
        'profitAmount' => $profitAmount,
    ];
}

function getCompanySnapshots(PDO $pdo, int $idCompany): array
{
    if ($idCompany <= 0) {
        return [];
    }

    try {
        $rows = dbAll(
            $pdo,
            'SELECT
                cs.id,
                cs.idEvent,
                cs.companyValue,
                cs.stability,
                cs.profitability,
                cs.appliedAction,
                cs.companyValueDelta,
                cs.personnelImpactPercentage,
                cs.stabilityLowerBoundPercentage,
                cs.stabilityUpperBoundPercentage,
                cs.profitAmount,
                cs.baseProfitAmount,
                cs.stabilityAdjustmentAmount,
                e.title,
                e.dateStart,
                e.dateEnd
             FROM tblCompanySnapshot AS cs
             JOIN tblEvent AS e
               ON e.id = cs.idEvent
             WHERE cs.idCompany = :idCompany
             ORDER BY e.dateStart ASC, cs.id ASC',
            ['idCompany' => $idCompany]
        );
    } catch (Throwable $e) {
        return [];
    }

    return array_map(static function (array $row): array {
        return [
            'idCompanySnapshot' => (int) ($row['id'] ?? 0),
            'idEvent' => (int) ($row['idEvent'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'dateStart' => (string) ($row['dateStart'] ?? ''),
            'dateEnd' => (string) ($row['dateEnd'] ?? ''),
            'companyValue' => round((float) ($row['companyValue'] ?? 0), 2),
            'stability' => normalizeCompanySliderValue($row['stability'] ?? 0),
            'profitability' => normalizeCompanySliderValue($row['profitability'] ?? 0),
            'appliedAction' => (string) ($row['appliedAction'] ?? 'none'),
            'companyValueDelta' => round((float) ($row['companyValueDelta'] ?? 0), 2),
            'personnelImpactPercentage' => round((float) ($row['personnelImpactPercentage'] ?? 0), 2),
            'stabilityLowerBoundPercentage' => round((float) ($row['stabilityLowerBoundPercentage'] ?? 0), 2),
            'stabilityUpperBoundPercentage' => round((float) ($row['stabilityUpperBoundPercentage'] ?? 0), 2),
            'profitAmount' => round((float) ($row['profitAmount'] ?? 0), 2),
            'baseProfitAmount' => round((float) ($row['baseProfitAmount'] ?? 0), 2),
            'stabilityAdjustmentAmount' => round((float) ($row['stabilityAdjustmentAmount'] ?? 0), 2),
        ];
    }, $rows);
}

function getCompanyDetailData(PDO $pdo, int $idCompany): ?array
{
    if ($idCompany <= 0) {
        return null;
    }

    $company = dbOne(
        $pdo,
        'SELECT id, companyName, description, foundationDate, companyValue, stability, profitability
           FROM tblCompany
          WHERE id = :id',
        ['id' => $idCompany]
    );

    if ($company === null) {
        return null;
    }

    $company['companyValue'] = round((float) ($company['companyValue'] ?? 0), 2);
    $company['stability'] = normalizeCompanySliderValue($company['stability'] ?? 0);
    $company['profitability'] = normalizeCompanySliderValue($company['profitability'] ?? 0);
    $company = enrichCompanyWithType($company);
    $company['logoUrl'] = getCompanyLogoUrl((int) $company['id']);
    $company['shareholderGroups'] = getCompanyShareholderGroups($pdo, (int) $company['id']);
    $company['availableSharePercentage'] = getCompanyAvailableSharePercentage($pdo, (int) $company['id']);
    $company['snapshotEventOptions'] = getCompanySnapshotEventOptions($pdo);
    $company['snapshots'] = getCompanySnapshots($pdo, (int) $company['id']);
    $company['personnelImportanceOptions'] = getCompanyPersonnelImportanceOptions();
    $company['personnelCharacterOptions'] = getCompanyPersonnelCharacterOptions($pdo);
    $company['personnelSkillOptions'] = getCompanyPersonnelSkillOptions($pdo);
    $company['personnelEntries'] = getCompanyPersonnelEntries($pdo, (int) $company['id']);

    return $company;
}

function refreshCompanySnapshotsForCurrentPersonnel(PDO $pdo, int $idCompany): void
{
    if ($idCompany <= 0) {
        return;
    }

    try {
        $snapshots = dbAll(
            $pdo,
            'SELECT id, companyValue, stability, profitability, appliedAction
               FROM tblCompanySnapshot
              WHERE idCompany = :idCompany',
            ['idCompany' => $idCompany]
        );
    } catch (Throwable $e) {
        $snapshots = dbAll(
            $pdo,
            'SELECT id, companyValue, stability, profitability
               FROM tblCompanySnapshot
              WHERE idCompany = :idCompany',
            ['idCompany' => $idCompany]
        );
    }

    if (count($snapshots) === 0) {
        return;
    }

    $updateStmt = $pdo->prepare(
        'UPDATE tblCompanySnapshot
            SET personnelImpactPercentage = :personnelImpactPercentage,
                stabilityLowerBoundPercentage = :stabilityLowerBoundPercentage,
                stabilityUpperBoundPercentage = :stabilityUpperBoundPercentage,
                profitAmount = :profitAmount,
                baseProfitAmount = :baseProfitAmount,
                stabilityAdjustmentAmount = :stabilityAdjustmentAmount,
                updatedAt = CURRENT_TIMESTAMP
          WHERE id = :idCompanySnapshot
            AND idCompany = :idCompany'
    );

    foreach ($snapshots as $snapshot) {
        $appliedAction = trim((string) ($snapshot['appliedAction'] ?? 'none'));
        if ($appliedAction !== '' && $appliedAction !== 'none') {
            continue;
        }

        $stability = normalizeCompanySliderValue($snapshot['stability'] ?? 0);
        $profitability = normalizeCompanySliderValue($snapshot['profitability'] ?? 0);
        $personnelImpactSummary = getCompanyPersonnelImpactSummary($pdo, $idCompany, $stability);
        $personnelSalaryIncreaseExpenseAmount = getCompanyPersonnelSalaryIncreaseExpenseAmount($pdo, $idCompany);
        $financials = calculateCompanySnapshotFinancials(
            (float) ($snapshot['companyValue'] ?? 0),
            $stability,
            $profitability,
            (float) ($personnelImpactSummary['totalPercentage'] ?? 0),
            $personnelSalaryIncreaseExpenseAmount,
            (float) ($personnelImpactSummary['lowerBoundPercentage'] ?? 0),
            (float) ($personnelImpactSummary['upperBoundPercentage'] ?? 0)
        );

        $updateStmt->execute([
            'personnelImpactPercentage' => $financials['personnelImpactPercentage'],
            'stabilityLowerBoundPercentage' => $financials['stabilityLowerBoundPercentage'],
            'stabilityUpperBoundPercentage' => $financials['stabilityUpperBoundPercentage'],
            'profitAmount' => $financials['profitAmount'],
            'baseProfitAmount' => $financials['baseProfitAmount'],
            'stabilityAdjustmentAmount' => $financials['stabilityAdjustmentAmount'],
            'idCompanySnapshot' => (int) ($snapshot['id'] ?? 0),
            'idCompany' => $idCompany,
        ]);
    }
}

function getCompanyPersonnelImportanceOptions(): array
{
    return ['Negligible', 'Low', 'Moderate', 'High', 'Critical'];
}

function normalizeCompanyPersonnelImportance(mixed $value): string
{
    $normalized = trim((string) $value);
    $allowed = getCompanyPersonnelImportanceOptions();

    return in_array($normalized, $allowed, true) ? $normalized : 'Moderate';
}

function normalizeCompanyPersonnelSalaryIncreasePercentage(mixed $value): float
{
    $normalized = round((float) $value, 2);
    if (!is_finite($normalized)) {
        return 0.0;
    }

    return max(0.0, $normalized);
}

function normalizeCompanyPersonnelSkillLevel(mixed $value): int
{
    return max(1, min(3, (int) $value));
}

function buildCompanyPersonnelInClause(array $ids, string $prefix = 'companyPersonnel'): array
{
    $placeholders = [];
    $params = [];

    foreach (array_values($ids) as $index => $id) {
        $key = $prefix . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = (int) $id;
    }

    return [
        'clause' => implode(', ', $placeholders),
        'params' => $params,
    ];
}

function getCompanyPersonnelProfessionNamesByCharacter(PDO $pdo, array $characterIds): array
{
    $characterIds = array_values(array_unique(array_filter(array_map('intval', $characterIds), static fn(int $id): bool => $id > 0)));
    if (count($characterIds) === 0) {
        return [];
    }

    try {
        $in = buildCompanyPersonnelInClause($characterIds, 'personnelProfession');
        $rows = dbAll(
            $pdo,
            "SELECT
                lct.idCharacter,
                t.name
             FROM tblLinkCharacterTrait AS lct
             JOIN tblTrait AS t
               ON t.id = lct.idTrait
             WHERE lct.idCharacter IN ({$in['clause']})
               AND t.`type` = 'profession'
             ORDER BY t.name ASC",
            $in['params']
        );
    } catch (Throwable $e) {
        return [];
    }

    $namesByCharacter = [];
    foreach ($rows as $row) {
        $idCharacter = (int) ($row['idCharacter'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));
        if ($idCharacter <= 0 || $name === '') {
            continue;
        }

        if (!isset($namesByCharacter[$idCharacter])) {
            $namesByCharacter[$idCharacter] = [];
        }

        if (!in_array($name, $namesByCharacter[$idCharacter], true)) {
            $namesByCharacter[$idCharacter][] = $name;
        }
    }

    return $namesByCharacter;
}

function buildCompanyPersonnelPresentation(array $row, array $professionNames = []): array
{
    $idCharacter = (int) ($row['idCharacter'] ?? $row['id'] ?? 0);
    $class = trim((string) ($row['class'] ?? ''));
    $title = trim((string) ($row['title'] ?? ''));
    $firstName = trim((string) ($row['firstName'] ?? ''));
    $lastName = trim((string) ($row['lastName'] ?? ''));
    $baseName = trim($firstName . ' ' . $lastName);
    $professionLabel = implode(', ', array_values(array_filter(array_map(
        static fn($name): string => trim((string) $name),
        $professionNames
    ))));

    if ($class === 'upper class') {
        $displayName = trim(($title !== '' ? $title . ' ' : '') . $baseName);
        return [
            'displayName' => $displayName !== '' ? $displayName : ('Personage #' . $idCharacter),
            'nameLabel' => $displayName !== '' ? $displayName : ('Personage #' . $idCharacter),
            'professionLabel' => '',
        ];
    }

    if ($baseName === '') {
        $baseName = 'Personage #' . $idCharacter;
    }

    return [
        'displayName' => $professionLabel !== '' ? ($baseName . ' (' . $professionLabel . ')') : $baseName,
        'nameLabel' => $baseName,
        'professionLabel' => $professionLabel,
    ];
}

function getCompanyPersonnelCharacterOptions(PDO $pdo): array
{
    try {
        $rows = dbAll(
            $pdo,
            "SELECT
                c.id,
                c.firstName,
                c.lastName,
                c.title,
                c.`class`,
                c.`type`,
                c.`state`
             FROM tblCharacter AS c
             WHERE c.`state` <> 'draft'
             ORDER BY c.lastName ASC, c.firstName ASC, c.id ASC"
        );
    } catch (Throwable $e) {
        return [];
    }

    $professionNamesByCharacter = getCompanyPersonnelProfessionNamesByCharacter(
        $pdo,
        array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows)
    );

    $options = [];
    foreach ($rows as $row) {
        $idCharacter = (int) ($row['id'] ?? 0);
        if ($idCharacter <= 0) {
            continue;
        }

        $presentation = buildCompanyPersonnelPresentation(
            [
                'idCharacter' => $idCharacter,
                'class' => (string) ($row['class'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'firstName' => (string) ($row['firstName'] ?? ''),
                'lastName' => (string) ($row['lastName'] ?? ''),
            ],
            $professionNamesByCharacter[$idCharacter] ?? []
        );

        $options[] = [
            'idCharacter' => $idCharacter,
            'displayName' => $presentation['displayName'],
            'nameLabel' => $presentation['nameLabel'],
            'professionLabel' => $presentation['professionLabel'],
            'class' => (string) ($row['class'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'state' => (string) ($row['state'] ?? ''),
        ];
    }

    return $options;
}

function getCompanyPersonnelSkillOptions(PDO $pdo): array
{
    try {
        $skills = dbAll(
            $pdo,
            'SELECT id, name
               FROM tblSkill
              ORDER BY name ASC'
        );
    } catch (Throwable $e) {
        return [];
    }

    $specialisationsBySkill = [];
    try {
        $specialisationRows = dbAll(
            $pdo,
            'SELECT id, idSkill, name, kind
               FROM tblSkillSpecialisation
              ORDER BY name ASC'
        );

        foreach ($specialisationRows as $row) {
            $idSkill = (int) ($row['idSkill'] ?? 0);
            if ($idSkill <= 0) {
                continue;
            }

            if (!isset($specialisationsBySkill[$idSkill])) {
                $specialisationsBySkill[$idSkill] = [];
            }

            $specialisationsBySkill[$idSkill][] = [
                'idSkillSpecialisation' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'kind' => (string) ($row['kind'] ?? 'specialisation'),
            ];
        }
    } catch (Throwable $e) {
        $specialisationsBySkill = [];
    }

    $options = [];
    foreach ($skills as $row) {
        $idSkill = (int) ($row['id'] ?? 0);
        if ($idSkill <= 0) {
            continue;
        }

        $options[] = [
            'idSkill' => $idSkill,
            'name' => (string) ($row['name'] ?? ''),
            'specialisations' => $specialisationsBySkill[$idSkill] ?? [],
        ];
    }

    return $options;
}

function getCompanyPersonnelEntries(PDO $pdo, int $idCompany): array
{
    if ($idCompany <= 0) {
        return [];
    }

    try {
        $rows = dbAll(
            $pdo,
            "SELECT
                cp.id AS idCompanyPersonnel,
                cp.idCharacter,
                cp.importance,
                cp.salaryIncreasePercentage,
                c.firstName,
                c.lastName,
                c.title,
                c.`class`,
                cps.id AS idCompanyPersonnelSkill,
                cps.idSkill,
                cps.level,
                s.name AS skillName,
                cpss.idSkillSpecialisation,
                ss.name AS specialisationName,
                ss.kind AS specialisationKind
             FROM tblCompanyPersonnel AS cp
             JOIN tblCharacter AS c
               ON c.id = cp.idCharacter
             LEFT JOIN tblCompanyPersonnelSkill AS cps
               ON cps.idCompanyPersonnel = cp.id
             LEFT JOIN tblSkill AS s
               ON s.id = cps.idSkill
             LEFT JOIN tblCompanyPersonnelSkillSpecialisation AS cpss
               ON cpss.idCompanyPersonnelSkill = cps.id
             LEFT JOIN tblSkillSpecialisation AS ss
               ON ss.id = cpss.idSkillSpecialisation
             WHERE cp.idCompany = :idCompany
             ORDER BY c.lastName ASC, c.firstName ASC, cp.id ASC, s.name ASC, cps.id ASC, ss.name ASC",
            ['idCompany' => $idCompany]
        );
    } catch (Throwable $e) {
        return [];
    }

    $characterIds = array_values(array_unique(array_map(
        static fn(array $row): int => (int) ($row['idCharacter'] ?? 0),
        $rows
    )));
    $professionNamesByCharacter = getCompanyPersonnelProfessionNamesByCharacter($pdo, $characterIds);

    $entries = [];
    foreach ($rows as $row) {
        $idCharacter = (int) ($row['idCharacter'] ?? 0);
        if ($idCharacter <= 0) {
            continue;
        }

        $entryKey = (int) ($row['idCompanyPersonnel'] ?? 0);
        if (!isset($entries[$entryKey])) {
            $presentation = buildCompanyPersonnelPresentation(
                [
                    'idCharacter' => $idCharacter,
                    'class' => (string) ($row['class'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'firstName' => (string) ($row['firstName'] ?? ''),
                    'lastName' => (string) ($row['lastName'] ?? ''),
                ],
                $professionNamesByCharacter[$idCharacter] ?? []
            );

            $entries[$entryKey] = [
                'idCompanyPersonnel' => $entryKey,
                'idCharacter' => $idCharacter,
                'displayName' => $presentation['displayName'],
                'nameLabel' => $presentation['nameLabel'],
                'professionLabel' => $presentation['professionLabel'],
                'importance' => normalizeCompanyPersonnelImportance($row['importance'] ?? 'Moderate'),
                'salaryIncreasePercentage' => normalizeCompanyPersonnelSalaryIncreasePercentage($row['salaryIncreasePercentage'] ?? 0),
                'skills' => [],
            ];
        }

        $idCompanyPersonnelSkill = (int) ($row['idCompanyPersonnelSkill'] ?? 0);
        if ($idCompanyPersonnelSkill <= 0) {
            continue;
        }

        if (!isset($entries[$entryKey]['skills'][$idCompanyPersonnelSkill])) {
            $entries[$entryKey]['skills'][$idCompanyPersonnelSkill] = [
                'idCompanyPersonnelSkill' => $idCompanyPersonnelSkill,
                'idSkill' => (int) ($row['idSkill'] ?? 0),
                'skillName' => (string) ($row['skillName'] ?? ''),
                'level' => normalizeCompanyPersonnelSkillLevel($row['level'] ?? 1),
                'specialisations' => [],
            ];
        }

        $idSkillSpecialisation = (int) ($row['idSkillSpecialisation'] ?? 0);
        if ($idSkillSpecialisation <= 0) {
            continue;
        }

        $entries[$entryKey]['skills'][$idCompanyPersonnelSkill]['specialisations'][$idSkillSpecialisation] = [
            'idSkillSpecialisation' => $idSkillSpecialisation,
            'name' => (string) ($row['specialisationName'] ?? ''),
            'kind' => (string) ($row['specialisationKind'] ?? 'specialisation'),
        ];
    }

    $normalizedEntries = array_values(array_map(static function (array $entry): array {
        $entry['skills'] = array_values(array_map(static function (array $skill): array {
            $skill['specialisations'] = array_values($skill['specialisations']);
            return $skill;
        }, $entry['skills']));
        return $entry;
    }, $entries));

    return $normalizedEntries;
}
