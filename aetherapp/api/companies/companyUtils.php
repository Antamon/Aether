<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../characters/characterPointUtils.php';

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
