<?php
declare(strict_types=1);

require_once __DIR__ . '/gossipKnowledgeUtils.php';

const AETHER_SKILL_ACTION_CODE_PSI = 'psi';
const AETHER_SKILL_ACTION_STATE_BURN = 'burn';
const AETHER_PSI_CLEAR_BURN_MODIFIER = 5;
const AETHER_PSI_MIN_ROLL = 1;
const AETHER_PSI_MAX_ROLL = 15;
const AETHER_PSI_MAX_RESULT = 20;

function getCharacterSkillActionStateNumericValue(
    PDO $pdo,
    int $idCharacter,
    string $actionCode,
    string $stateCode,
    int $default = 0
): int {
    if ($idCharacter <= 0 || trim($actionCode) === '' || trim($stateCode) === '') {
        return $default;
    }

    $row = dbOne(
        $pdo,
        'SELECT numericValue
           FROM tblCharacterSkillActionState
          WHERE idCharacter = :idCharacter
            AND actionCode = :actionCode
            AND stateCode = :stateCode',
        [
            'idCharacter' => $idCharacter,
            'actionCode' => $actionCode,
            'stateCode' => $stateCode,
        ]
    );

    if ($row === null) {
        return $default;
    }

    return (int) ($row['numericValue'] ?? $default);
}

function saveCharacterSkillActionStateNumericValue(
    PDO $pdo,
    int $idCharacter,
    string $actionCode,
    string $stateCode,
    int $numericValue
): void {
    $pdo->prepare(
        'INSERT INTO tblCharacterSkillActionState (
            idCharacter,
            actionCode,
            stateCode,
            numericValue,
            updatedAt
         ) VALUES (
            :idCharacter,
            :actionCode,
            :stateCode,
            :numericValue,
            NOW()
         )
         ON DUPLICATE KEY UPDATE
            numericValue = VALUES(numericValue),
            updatedAt = VALUES(updatedAt)'
    )->execute([
        'idCharacter' => $idCharacter,
        'actionCode' => $actionCode,
        'stateCode' => $stateCode,
        'numericValue' => $numericValue,
    ]);
}

function recordCharacterSkillActionUse(PDO $pdo, array $payload): int
{
    $encode = static function ($value): ?string {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };

    $pdo->prepare(
        'INSERT INTO tblCharacterSkillActionUse (
            idCharacter,
            idEvent,
            idSkill,
            actionCode,
            actionSubtype,
            rollBase,
            rollModifier,
            rollFinal,
            resultCode,
            resultTitle,
            resultText,
            stateBefore,
            stateAfter,
            metadata,
            createdAt,
            createdBy
         ) VALUES (
            :idCharacter,
            :idEvent,
            :idSkill,
            :actionCode,
            :actionSubtype,
            :rollBase,
            :rollModifier,
            :rollFinal,
            :resultCode,
            :resultTitle,
            :resultText,
            :stateBefore,
            :stateAfter,
            :metadata,
            NOW(),
            :createdBy
         )'
    )->execute([
        'idCharacter' => (int) ($payload['idCharacter'] ?? 0),
        'idEvent' => (int) ($payload['idEvent'] ?? 0),
        'idSkill' => ($payload['idSkill'] ?? null) !== null ? (int) $payload['idSkill'] : null,
        'actionCode' => (string) ($payload['actionCode'] ?? ''),
        'actionSubtype' => trim((string) ($payload['actionSubtype'] ?? '')) !== ''
            ? (string) $payload['actionSubtype']
            : null,
        'rollBase' => ($payload['rollBase'] ?? null) !== null ? (int) $payload['rollBase'] : null,
        'rollModifier' => (int) ($payload['rollModifier'] ?? 0),
        'rollFinal' => ($payload['rollFinal'] ?? null) !== null ? (int) $payload['rollFinal'] : null,
        'resultCode' => trim((string) ($payload['resultCode'] ?? '')) !== ''
            ? (string) $payload['resultCode']
            : null,
        'resultTitle' => trim((string) ($payload['resultTitle'] ?? '')) !== ''
            ? (string) $payload['resultTitle']
            : null,
        'resultText' => trim((string) ($payload['resultText'] ?? '')) !== ''
            ? (string) $payload['resultText']
            : null,
        'stateBefore' => $encode($payload['stateBefore'] ?? null),
        'stateAfter' => $encode($payload['stateAfter'] ?? null),
        'metadata' => $encode($payload['metadata'] ?? null),
        'createdBy' => ($payload['createdBy'] ?? null) !== null ? (int) $payload['createdBy'] : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function getCharacterPsiBurn(PDO $pdo, int $idCharacter): int
{
    return max(
        0,
        getCharacterSkillActionStateNumericValue(
            $pdo,
            $idCharacter,
            AETHER_SKILL_ACTION_CODE_PSI,
            AETHER_SKILL_ACTION_STATE_BURN,
            0
        )
    );
}

function setCharacterPsiBurn(PDO $pdo, int $idCharacter, int $burn): void
{
    saveCharacterSkillActionStateNumericValue(
        $pdo,
        $idCharacter,
        AETHER_SKILL_ACTION_CODE_PSI,
        AETHER_SKILL_ACTION_STATE_BURN,
        max(0, $burn)
    );
}

function getPsiCategoryDefinitions(): array
{
    return [
        'zintuiglijke_gave' => [
            'label' => 'Zintuiglijke gave',
            'aliases' => ['zintuiglijke_gave', 'zintuiglijke gave', 'sensory_gift'],
        ],
        'somatische_gave' => [
            'label' => 'Somatische gave',
            'aliases' => ['somatische_gave', 'somatische gave', 'somatic_gift'],
        ],
        'sferische_gave' => [
            'label' => 'Sferische gave',
            'aliases' => ['sferische_gave', 'sferische gave', 'sphere_gift', 'spherical_gift'],
        ],
        'manipulatieve_gave' => [
            'label' => 'Manipulatieve gave',
            'aliases' => ['manipulatieve_gave', 'manipulatieve gave', 'manipulative_gift'],
        ],
    ];
}

function normalizeSkillActionCode(string $value): string
{
    $normalized = str_replace(["\u{03A8}", "\u{03C8}"], 'psi', trim($value));
    $normalized = mb_strtolower($normalized, 'UTF-8');
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
    if (is_string($transliterated) && $transliterated !== '') {
        $normalized = $transliterated;
    }

    $normalized = str_replace(['-', ' '], '_', $normalized);
    $normalized = preg_replace('/[^a-z0-9_]+/', '', $normalized) ?? '';
    $normalized = preg_replace('/_+/', '_', $normalized) ?? '';
    return trim($normalized, '_');
}

function buildCharacterPsiActionGroups(PDO $pdo, int $idCharacter): array
{
    if ($idCharacter <= 0) {
        return [];
    }

    $rows = dbAll(
        $pdo,
        'SELECT
            lcs.idSkill,
            lcs.level,
            s.name AS skillName,
            st.id AS idSkillType,
            st.code AS skillTypeCode,
            st.name AS skillTypeName
           FROM tblLinkCharacterSkill AS lcs
           JOIN tblSkill AS s
             ON s.id = lcs.idSkill
           LEFT JOIN tblLinkSkillType AS lst
             ON lst.idSkill = lcs.idSkill
           LEFT JOIN tblSkillType AS st
             ON st.id = lst.idSkillType
          WHERE lcs.idCharacter = :idCharacter
          ORDER BY s.name ASC, st.name ASC, st.id ASC',
        ['idCharacter' => $idCharacter]
    );

    $skills = [];
    foreach ($rows as $row) {
        $idSkill = (int) ($row['idSkill'] ?? 0);
        if ($idSkill <= 0) {
            continue;
        }

        if (!isset($skills[$idSkill])) {
            $skills[$idSkill] = [
                'idSkill' => $idSkill,
                'name' => (string) ($row['skillName'] ?? ''),
                'level' => max(0, min(3, (int) ($row['level'] ?? 0))),
                'typeTokens' => [],
            ];
        }

        $code = trim((string) ($row['skillTypeCode'] ?? ''));
        $name = trim((string) ($row['skillTypeName'] ?? ''));
        if ($code !== '') {
            $skills[$idSkill]['typeTokens'][] = normalizeSkillActionCode($code);
        }
        if ($name !== '') {
            $skills[$idSkill]['typeTokens'][] = normalizeSkillActionCode($name);
        }
    }

    $definitions = getPsiCategoryDefinitions();
    $groups = [];

    foreach ($definitions as $categoryCode => $definition) {
        $groups[$categoryCode] = [
            'actionCode' => 'psi:' . $categoryCode,
            'actionKind' => AETHER_SKILL_ACTION_CODE_PSI,
            'buttonAction' => 'actionPsi',
            'categoryCode' => $categoryCode,
            'label' => (string) ($definition['label'] ?? $categoryCode),
            'imageSrc' => 'img/actionPsy.png',
            'skills' => [],
        ];
    }

    foreach ($skills as $skill) {
        $tokens = array_values(array_unique(array_filter($skill['typeTokens'])));
        if (count($tokens) < 1) {
            continue;
        }

        foreach ($definitions as $categoryCode => $definition) {
            $categoryMatched = false;
            foreach ((array) ($definition['aliases'] ?? []) as $alias) {
                if (in_array(normalizeSkillActionCode((string) $alias), $tokens, true)) {
                    $categoryMatched = true;
                    break;
                }
            }

            if (!$categoryMatched) {
                continue;
            }

            $groups[$categoryCode]['skills'][] = [
                'idSkill' => (int) $skill['idSkill'],
                'name' => (string) $skill['name'],
                'level' => (int) $skill['level'],
            ];
        }
    }

    $result = [];
    foreach ($definitions as $categoryCode => $definition) {
        $skillsForCategory = $groups[$categoryCode]['skills'];
        if (count($skillsForCategory) < 1) {
            continue;
        }

        usort($skillsForCategory, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        $groups[$categoryCode]['skills'] = $skillsForCategory;
        $result[] = $groups[$categoryCode];
    }

    return $result;
}

function getPsiOutcomeDefinition(string $categoryCode, int $resultValue): array
{
    $categoryCode = array_key_exists($categoryCode, getPsiCategoryDefinitions())
        ? $categoryCode
        : 'zintuiglijke_gave';

    $shared = [
        [
            'min' => 1,
            'max' => 1,
            'burnIncrease' => 0,
            'text' => 'Je gave werkt feilloos. Je zelfvertrouwen krijgt een boost. Je krijgt al je mentale ringen terug.',
        ],
        [
            'min' => 2,
            'max' => 4,
            'burnIncrease' => 0,
            'text' => 'Je gave werkt feilloos. Je zelfvertrouwen krijgt een boost. Je krijgt 1 mentale ring terug.',
        ],
        [
            'min' => 5,
            'max' => 6,
            'burnIncrease' => 0,
            'text' => 'Je gave werkt, maar door de mentale stress verlies je 1 mentale ring.',
        ],
        [
            'min' => 7,
            'max' => 8,
            'burnIncrease' => 0,
            'text' => 'Je gave werkt, maar door de mentale stress verlies je 2 mentale ringen.',
        ],
        [
            'min' => 9,
            'max' => 9,
            'burnIncrease' => 1,
            'text' => 'De psychische energie raast door je lichaam. Je gave staat op het punt om extra goed te werken, maar het kost je 3 mentale ringen. Je kan dat voorkomen door je gave te blokkeren. Dan kan je ze een dagdeel lang niet meer gebruiken.',
        ],
        [
            'min' => 10,
            'max' => 11,
            'burnIncrease' => 0,
            'text' => 'Je slaagt er niet in om je geest te kalmeren en je gave te activeren. Doe een half uur iets kalmerends en probeer het daarna nog eens.',
        ],
        [
            'min' => 12,
            'max' => 12,
            'burnIncrease' => 1,
            'text' => 'Neurale kortsluiting. Je valt op de grond en krijgt enkele minuten een zware epilepsieaanval. Als je tijdens de aanval geen medische hulp krijgt, verlies je 1 fysieke ring. Het gebruik van je gave mislukt.',
        ],
        [
            'min' => 13,
            'max' => 14,
            'burnIncrease' => 1,
            'text' => 'Kosmische openbaringen dringen zich op in je hoofd. Het gebruik van je gave mislukt en je verliest 2 mentale ringen. Het komende uur wil je je verstoppen en vertrouw je niemand.',
        ],
        [
            'min' => 15,
            'max' => 15,
            'burnIncrease' => 1,
            'text' => 'Kosmische openbaringen dringen zich op in je hoofd, waardoor de zinloosheid van je bestaan pijnlijk duidelijk wordt en je suïcidale gedachten krijgt. Het gebruik van je gave mislukt en je verliest 2 mentale ringen.',
        ],
    ];

    $perCategory = [
        'zintuiglijke_gave' => [
            [
                'min' => 16,
                'max' => 17,
                'burnIncrease' => 2,
                'text' => 'Je gave activeert, maar je verliest de controle. Een tijd lang wordt het ondraaglijk om in de buurt van andere mensen te zijn. Elke indruk of emotie van buitenaf voelt als een messteek in je geest. Verlies 3 mentale ringen. Je haalt geen nuttige informatie uit je gave.',
            ],
            [
                'min' => 18,
                'max' => 19,
                'burnIncrease' => 2,
                'text' => 'Je gave activeert, maar je verliest de controle. Echo\'s uit het verleden manifesteren zich in je geest. Een of meerdere personen uit je familiegeschiedenis nemen een tijd lang je geest over. Nadien verlies je 3 mentale ringen. Je haalt geen nuttige informatie uit je gave.',
            ],
            [
                'min' => 20,
                'max' => 20,
                'burnIncrease' => 2,
                'text' => 'Je krijgt gruwelijke visioenen over heden en verleden die je geest breken. Je ontwikkelt een mentale aandoening en zal jezelf van het leven beroven tenzij iemand je tegenhoudt.',
            ],
        ],
        'sferische_gave' => [
            [
                'min' => 16,
                'max' => 17,
                'burnIncrease' => 2,
                'text' => 'Je gave activeert, maar je verliest de controle. Een tijd lang zal je, telkens wanneer je iemand aanraakt, iemand ongewild 1 fysieke ring schade toebrengen. Verlies 3 mentale ringen.',
            ],
            [
                'min' => 18,
                'max' => 19,
                'burnIncrease' => 2,
                'text' => 'Je gave activeert, maar je verliest de controle. Een tijd lang word je omringd door een oncontroleerbare vortex die bestaat uit de energie van je gebruikte gave. Iedereen die bij je in de buurt komt, krijgt elke 3 seconden 2 punten schade. Verlies 3 mentale ringen.',
            ],
            [
                'min' => 20,
                'max' => 20,
                'burnIncrease' => 2,
                'text' => 'Je lichaam wordt helemaal overspoeld door het element van je gebruikte gave. Je belandt in een coma. Een bekwame dokter kan je er weer uithalen, maar dat is niet eenvoudig vanwege de geweldige energiestromen van je gave die als een cocon je lichaam beschermen. Je ontwikkelt een mentale aandoening.',
            ],
        ],
        'somatische_gave' => [
            [
                'min' => 16,
                'max' => 17,
                'burnIncrease' => 2,
                'text' => 'Je gave activeert, maar je verliest de controle. Een tijd lang zal je ongecontroleerd naar willekeurige plekken binnen gezichtsafstand teleporteren. Je activeert ook hyperkracht, maar kan die niet controleren, waardoor je gemakkelijk zaken in je omgeving breekt. Je wordt ook erg onhandig, loopt tegen of door obstakels en laat voorwerpen gemakkelijk vallen. Verlies 3 mentale ringen.',
            ],
            [
                'min' => 18,
                'max' => 19,
                'burnIncrease' => 2,
                'text' => 'Een tijd lang scheid je feromonen af die gevoelens van extreme haat tegenover jou opwekken bij de mensen rondom je. Ze zullen tegen je beginnen schreeuwen en roepen. Mensen die gewapend zijn, zullen je aanvallen. Verlies 3 mentale ringen.',
            ],
            [
                'min' => 20,
                'max' => 20,
                'burnIncrease' => 2,
                'text' => 'De neurale druk veroorzaakt een tijd lang totale zinsverbijstering. Je zal alles en iedereen aanvallen. Al je aanvallen doen 2 punten schade en je beschikt over 8 extra levenspunten. Mocht je toch gewond raken, dan genees je jezelf door binnen 5 meter de levensenergie van iemand te stelen. Je ontwikkelt een mentale aandoening.',
            ],
        ],
        'manipulatieve_gave' => [
            [
                'min' => 16,
                'max' => 17,
                'burnIncrease' => 2,
                'text' => 'Je gave activeert, maar je verliest de controle. Een tijd lang zal je elke gedachte die in je opkomt ongewild telepathisch projecteren naar de mensen rondom je. Dit leidt tot erg genante situaties. Verlies 3 mentale ringen.',
            ],
            [
                'min' => 18,
                'max' => 19,
                'burnIncrease' => 2,
                'text' => 'Een tijd lang projecteer je ongewild een overweldigend gevoel van haat tegenover jou in de mensen rondom je. Ze zullen tegen je beginnen schreeuwen en roepen. Mensen die gewapend zijn, zullen je aanvallen. Verlies 3 mentale ringen.',
            ],
            [
                'min' => 20,
                'max' => 20,
                'burnIncrease' => 2,
                'text' => 'Je geest breekt onder de psychische stress en verstopt zich ergens diep in je onderbewustzijn. Je vergeet wie je bent. Langdurige sessies met een psychiater kunnen je geheugen weer bovenbrengen. Je ontwikkelt een mentale aandoening.',
            ],
        ],
    ];

    $resultValue = max(1, min(AETHER_PSI_MAX_RESULT, $resultValue));
    $entries = array_merge($shared, $perCategory[$categoryCode] ?? []);

    foreach ($entries as $entry) {
        $min = (int) ($entry['min'] ?? 0);
        $max = (int) ($entry['max'] ?? 0);
        if ($resultValue < $min || $resultValue > $max) {
            continue;
        }

        return [
            'rangeMin' => $min,
            'rangeMax' => $max,
            'rangeLabel' => $min === $max ? (string) $min : ($min . '-' . $max),
            'burnIncrease' => (int) ($entry['burnIncrease'] ?? 0),
            'text' => (string) ($entry['text'] ?? ''),
        ];
    }

    return [
        'rangeMin' => AETHER_PSI_MAX_RESULT,
        'rangeMax' => AETHER_PSI_MAX_RESULT,
        'rangeLabel' => (string) AETHER_PSI_MAX_RESULT,
        'burnIncrease' => 2,
        'text' => 'Je geest breekt onder de psychische stress en verstopt zich ergens diep in je onderbewustzijn. Je vergeet wie je bent. Langdurige sessies met een psychiater kunnen je geheugen weer boven brengen. Je ontwikkelt een mentale aandoening.',
    ];
}

function validateCharacterPsiSkill(PDO $pdo, int $idCharacter, int $idSkill, string $psiCategoryCode): ?array
{
    if ($idCharacter <= 0 || $idSkill <= 0 || trim($psiCategoryCode) === '') {
        return null;
    }

    foreach (buildCharacterPsiActionGroups($pdo, $idCharacter) as $group) {
        if ((string) ($group['categoryCode'] ?? '') !== $psiCategoryCode) {
            continue;
        }

        foreach ((array) ($group['skills'] ?? []) as $skill) {
            if ((int) ($skill['idSkill'] ?? 0) === $idSkill) {
                return $skill;
            }
        }
    }

    return null;
}

function executeCharacterPsiSkillUse(
    PDO $pdo,
    array $character,
    int $idEvent,
    int $idSkill,
    string $psiCategoryCode,
    bool $clearBurn,
    int $usedBy
): array {
    $idCharacter = (int) ($character['id'] ?? 0);
    if ($idCharacter <= 0 || $idEvent <= 0 || $idSkill <= 0) {
        throw new RuntimeException('Personage, event en vaardigheid zijn verplicht.');
    }

    $event = dbOne($pdo, 'SELECT id, title FROM tblEvent WHERE id = :idEvent', ['idEvent' => $idEvent]);
    if ($event === null) {
        throw new RuntimeException('Event niet gevonden.');
    }

    $skill = validateCharacterPsiSkill($pdo, $idCharacter, $idSkill, $psiCategoryCode);
    if ($skill === null) {
        throw new RuntimeException('Deze psi-vaardigheid hoort niet bij dit personage of dit gave-type.');
    }

    $definitions = getPsiCategoryDefinitions();
    $categoryLabel = (string) (($definitions[$psiCategoryCode]['label'] ?? $psiCategoryCode));
    $burnBefore = getCharacterPsiBurn($pdo, $idCharacter);
    $rollBase = random_int(AETHER_PSI_MIN_ROLL, AETHER_PSI_MAX_ROLL);
    $rollModifier = $clearBurn ? AETHER_PSI_CLEAR_BURN_MODIFIER : 0;
    $effectiveValue = max(
        1,
        min(AETHER_PSI_MAX_RESULT, $rollBase + $burnBefore + $rollModifier)
    );

    $outcome = getPsiOutcomeDefinition($psiCategoryCode, $effectiveValue);
    $burnAfter = $clearBurn
        ? 0
        : max(0, $burnBefore + (int) ($outcome['burnIncrease'] ?? 0));

    setCharacterPsiBurn($pdo, $idCharacter, $burnAfter);

    $resultText = (string) ($outcome['text'] ?? '');
    $usageId = recordCharacterSkillActionUse($pdo, [
        'idCharacter' => $idCharacter,
        'idEvent' => $idEvent,
        'idSkill' => $idSkill,
        'actionCode' => AETHER_SKILL_ACTION_CODE_PSI,
        'actionSubtype' => $psiCategoryCode,
        'rollBase' => $rollBase,
        'rollModifier' => $burnBefore + $rollModifier,
        'rollFinal' => $effectiveValue,
        'resultCode' => 'psi_' . str_replace('-', '_', (string) ($outcome['rangeLabel'] ?? $effectiveValue)),
        'resultTitle' => $categoryLabel,
        'resultText' => $resultText,
        'stateBefore' => [
            'psiBurn' => $burnBefore,
        ],
        'stateAfter' => [
            'psiBurn' => $burnAfter,
        ],
        'metadata' => [
            'clearBurn' => $clearBurn,
            'burnCleared' => $clearBurn,
            'psiCategoryCode' => $psiCategoryCode,
            'psiCategoryLabel' => $categoryLabel,
            'eventTitle' => (string) ($event['title'] ?? ''),
            'skillName' => (string) ($skill['name'] ?? ''),
            'skillLevel' => (int) ($skill['level'] ?? 0),
            'rollBurnBefore' => $burnBefore,
            'rollClearBurnModifier' => $rollModifier,
            'burnIncrease' => (int) ($outcome['burnIncrease'] ?? 0),
        ],
        'createdBy' => $usedBy > 0 ? $usedBy : null,
    ]);

    return [
        'usageId' => $usageId,
        'actionCode' => AETHER_SKILL_ACTION_CODE_PSI,
        'categoryCode' => $psiCategoryCode,
        'categoryLabel' => $categoryLabel,
        'skill' => [
            'idSkill' => (int) ($skill['idSkill'] ?? 0),
            'name' => (string) ($skill['name'] ?? ''),
            'level' => (int) ($skill['level'] ?? 0),
        ],
        'event' => [
            'idEvent' => (int) ($event['id'] ?? 0),
            'title' => (string) ($event['title'] ?? ''),
        ],
        'roll' => [
            'base' => $rollBase,
            'burnBefore' => $burnBefore,
            'clearBurnModifier' => $rollModifier,
            'final' => $effectiveValue,
            'rangeLabel' => (string) ($outcome['rangeLabel'] ?? ''),
        ],
        'burn' => [
            'before' => $burnBefore,
            'increase' => (int) ($outcome['burnIncrease'] ?? 0),
            'after' => $burnAfter,
            'cleared' => $clearBurn,
        ],
        'result' => [
            'title' => $categoryLabel,
            'text' => $resultText,
        ],
    ];
}
