<?php
declare(strict_types=1);

if (defined('AETHER_CHARACTER_READ_TEST_BOOTSTRAP')) {
    if (!function_exists('mb_strtolower')) {
        function mb_strtolower(string $value): string
        {
            return strtolower($value);
        }
    }

    final class CharacterReadTestStatement extends PDOStatement
    {
        private array $parameters = [];

        public function __construct(
            private CharacterReadTestPdo $testPdo,
            private string $query
        ) {
        }

        public function execute(?array $params = null): bool
        {
            $this->parameters = $params ?? [];
            $trimmed = ltrim($this->query);
            if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE)\b/i', $trimmed)) {
                $this->testPdo->writeAttempts++;
                throw new LogicException('Een leesroute probeerde data te wijzigen.');
            }

            if ($this->testPdo->scenario === 'server_error'
                && ($this->testPdo->route === 'character' && str_contains($this->query, 'FROM tblLinkCharacterSkill AS lcs')
                    || $this->testPdo->route === 'diary' && str_contains($this->query, 'SELECT d.*')
                    || $this->testPdo->route === 'sections' && str_contains($this->query, 'SELECT section, content'))) {
                throw new PDOException('SQLSTATE[42S02]: secret_read_table technical detail');
            }

            return true;
        }

        public function fetch(
            int $mode = PDO::FETCH_DEFAULT,
            int $cursorOrientation = PDO::FETCH_ORI_NEXT,
            int $cursorOffset = 0
        ): mixed {
            if (str_contains($this->query, 'FROM tblUser')) {
                $id = (int) ($this->parameters['id'] ?? $this->parameters[0] ?? 0);
                $user = $this->testPdo->users[$id] ?? null;
                if ($user === null) {
                    return false;
                }
                if (str_contains($this->query, 'SELECT firstName, lastName')) {
                    return ['firstName' => $user['firstName'], 'lastName' => $user['lastName']];
                }
                return $user;
            }

            if (str_contains($this->query, 'FROM tblCharacter')) {
                $id = (int) ($this->parameters['id'] ?? $this->parameters['idCharacter'] ?? $this->parameters[0] ?? 0);
                return $this->testPdo->characters[$id] ?? false;
            }

            return false;
        }

        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            if (str_contains($this->query, 'FROM tblLinkCharacterSkill AS lcs')
                && str_contains($this->query, 's.visibility')) {
                $skills = [
                    [
                        'id' => '5', 'name' => 'Public skill', 'description' => 'Visible',
                        'beginner' => 'B', 'professional' => 'P', 'master' => 'M', 'level' => '2',
                    ],
                ];
                if ((int) ($this->parameters[1] ?? 0) === 1) {
                    $skills[] = [
                        'id' => '9', 'name' => 'Secret skill', 'description' => 'Director only',
                        'beginner' => 'SB', 'professional' => 'SP', 'master' => 'SM', 'level' => '1',
                    ];
                }
                return $skills;
            }
            if (str_contains($this->query, 'FROM tblCharacterSpecialisation AS cs')) {
                return [
                    ['idSkill' => '5', 'id' => '50', 'name' => 'Public spec', 'kind' => null],
                    ['idSkill' => '9', 'id' => '90', 'name' => 'Secret spec', 'kind' => 'discipline'],
                ];
            }
            if (str_contains($this->query, 'FROM tblLinkSkillType AS lst')) {
                return [
                    ['idSkill' => '5', 'idSkillType' => '2', 'code' => 'public', 'name' => 'Public type', 'description' => 'Visible'],
                    ['idSkill' => '9', 'idSkillType' => '3', 'code' => 'secret', 'name' => 'Secret type', 'description' => 'Hidden'],
                ];
            }
            if (str_contains($this->query, 'SELECT d.*')) {
                return [[
                    'id' => '7',
                    'idCharacter' => '1',
                    'idEvent' => '11',
                    'goals' => '<p><strong>Goal</strong><script>alert(1)</script></p>',
                    'achievements' => '<div><span style="color:red"><em>Won</em></span></div>',
                    'gossip1' => '<b>plain gossip</b>',
                    'gossip2' => 'Second',
                    'gossip3' => '',
                    'eventTitle' => 'Test event',
                    'dateStart' => '2026-09-01',
                    'dateEnd' => null,
                ]];
            }
            if (str_contains($this->query, 'SELECT e.id, e.title, e.dateStart')) {
                return [['id' => '12', 'title' => 'Available event', 'dateStart' => '2026-10-01']];
            }
            if (str_contains($this->query, 'SELECT section, content')) {
                return [
                    ['section' => 'personal_background', 'content' => '<div onclick="evil()"><strong>History</strong></div>'],
                    ['section' => 'knowledge', 'content' => '<p>Knowledge<script>alert(1)</script></p>'],
                    ['section' => 'nature', 'content' => '<span style="color:red"><em>Calm</em></span>'],
                    ['section' => 'admin_notes', 'content' => '<p>Must not leak</p>'],
                ];
            }

            return [];
        }

        public function fetchColumn(int $column = 0): mixed
        {
            return 0;
        }
    }

    final class CharacterReadTestPdo extends PDO
    {
        public int $writeAttempts = 0;

        public function __construct(
            public string $route,
            public string $scenario,
            public array $users,
            public array $characters
        ) {
        }

        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            return new CharacterReadTestStatement($this, $query);
        }
    }

    function dbAll(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function dbOne(PDO $pdo, string $sql, array $params = []): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    function getPDO(): PDO
    {
        global $pdo;
        return $pdo;
    }

    $route = (string) ($argv[1] ?? 'character');
    $scenario = (string) ($argv[2] ?? 'participant');
    $users = [
        10 => ['id' => 10, 'username' => 'participant', 'firstName' => 'Part', 'lastName' => 'Icipant', 'role' => 'participant'],
        20 => ['id' => 20, 'username' => 'director', 'firstName' => 'Di', 'lastName' => 'Rector', 'role' => 'director'],
        30 => ['id' => 30, 'username' => 'administrator', 'firstName' => 'Ad', 'lastName' => 'Min', 'role' => 'administrator'],
    ];
    $characters = [
        1 => [
            'id' => 1, 'idUser' => 10, 'type' => 'player', 'state' => 'active', 'class' => 'upper class',
            'firstName' => '<b>Plain name</b>', 'lastName' => 'Character', 'experienceToTrait' => 0,
            'physicalHealth' => 0, 'mentalHealth' => 0, 'physicalHealthFree' => 0, 'mentalHealthFree' => 0,
            'bankaccount' => 100, 'securitiesaccount' => 25, 'securitiesManagerType' => 'self',
            'securitiesRiskProfile' => 3, 'securitiesManagerCharacterId' => null,
        ],
        2 => [
            'id' => 2, 'idUser' => 99, 'type' => 'player', 'state' => 'active', 'class' => 'middle class',
            'firstName' => 'Other', 'lastName' => 'Character', 'experienceToTrait' => 0,
            'physicalHealth' => 0, 'mentalHealth' => 0, 'physicalHealthFree' => 0, 'mentalHealthFree' => 0,
            'bankaccount' => 50, 'securitiesaccount' => 0, 'securitiesManagerType' => 'self',
            'securitiesRiskProfile' => 3, 'securitiesManagerCharacterId' => null,
        ],
    ];
    $pdo = new CharacterReadTestPdo($route, $scenario, $users, $characters);

    $sessionUserId = match ($scenario) {
        'unauthenticated' => 999,
        'director' => 20,
        'administrator' => 30,
        default => 10,
    };
    session_start();
    $_SESSION = ['user' => ['id' => $sessionUserId, 'role' => 'administrator']];

    register_shutdown_function(static function () use ($pdo): void {
        $status = http_response_code();
        fwrite(STDERR, '__AETHER_READ_STATE__:' . json_encode([
            'status' => $status === false ? 200 : $status,
            'writeAttempts' => $pdo->writeAttempts,
        ]) . "\n");
    });
    return;
}

$projectRoot = dirname(__DIR__);
$fixtureRoot = sys_get_temp_dir() . '/aether-character-read-' . bin2hex(random_bytes(6));

function copyCharacterReadFixtureTree(string $source, string $target): void
{
    if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
        throw new RuntimeException("Kon fixturemap {$target} niet maken.");
    }
    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot()) {
            continue;
        }
        $targetPath = $target . '/' . $item->getFilename();
        if ($item->isDir()) {
            copyCharacterReadFixtureTree($item->getPathname(), $targetPath);
        } elseif (!copy($item->getPathname(), $targetPath)) {
            throw new RuntimeException("Kon fixturebestand {$item->getPathname()} niet kopieren.");
        }
    }
}

function removeCharacterReadFixtureTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

copyCharacterReadFixtureTree($projectRoot . '/api', $fixtureRoot . '/api');
copy($projectRoot . '/sessionUserBootstrap.php', $fixtureRoot . '/sessionUserBootstrap.php');
mkdir($fixtureRoot . '/vendor', 0777, true);
file_put_contents(
    $fixtureRoot . '/vendor/autoload.php',
    '<?php require ' . var_export($projectRoot . '/vendor/autoload.php', true) . ';'
);
file_put_contents(
    $fixtureRoot . '/db.php',
    "<?php\ndefine('AETHER_CHARACTER_READ_TEST_BOOTSTRAP', true);\nrequire "
        . var_export(__FILE__, true) . ";\n"
);

$routePaths = [
    'character' => $fixtureRoot . '/api/characters/getCharacter.php',
    'diary' => $fixtureRoot . '/api/characters/getCharacterDiary.php',
    'sections' => $fixtureRoot . '/api/characters/getCharacterSections.php',
];
$failures = [];

function assertCharacterRead(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

/** @return array{body: string, decoded: mixed, status: int, writes: int, stderr: string, exitCode: int} */
function runCharacterReadScenario(string $path, string $route, string $scenario, array|string $request): array
{
    $process = proc_open(
        [PHP_BINARY, $path, $route, $scenario],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException("Kon {$route}/{$scenario} niet starten.");
    }
    fwrite($pipes[0], is_string($request) ? $request : json_encode($request));
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if (!preg_match('/__AETHER_READ_STATE__:(\{.*\})/', $stderr, $matches)) {
        throw new RuntimeException("Geen teststatus voor {$route}/{$scenario}: {$stderr}");
    }
    $state = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    return [
        'body' => $body,
        'decoded' => json_decode($body, true),
        'status' => (int) $state['status'],
        'writes' => (int) $state['writeAttempts'],
        'stderr' => $stderr,
        'exitCode' => $exitCode,
    ];
}

function assertCharacterReadError(array $result, int $status, string $message): void
{
    assertCharacterRead($result['status'] === $status, "{$message}: HTTP {$result['status']} in plaats van {$status}.");
    assertCharacterRead(is_array($result['decoded']) && isset($result['decoded']['error']), "{$message}: error-response ontbreekt.");
    assertCharacterRead($result['writes'] === 0, "{$message}: geweigerd verzoek deed een write.");
    assertCharacterRead($result['exitCode'] === 0, "{$message}: processtatus {$result['exitCode']}; {$result['stderr']}");
}

try {
    $character = runCharacterReadScenario($routePaths['character'], 'character', 'participant', ['id' => 1]);
    assertCharacterRead($character['status'] === 200, 'Participant kon eigen character niet lezen: ' . $character['body'] . ' / ' . $character['stderr']);
    assertCharacterRead(array_keys($character['decoded'] ?? []) === [
        'id', 'idUser', 'type', 'state', 'class', 'firstName', 'lastName', 'experienceToTrait',
        'physicalHealth', 'mentalHealth', 'physicalHealthFree', 'mentalHealthFree', 'bankaccount',
        'securitiesaccount', 'securitiesManagerType', 'securitiesRiskProfile', 'securitiesManagerCharacterId',
        'skills', 'nameParticipant', 'traitGroups', 'professionGroups', 'experience', 'maxExperience',
        'baseStatusPoints', 'usedStatusPoints', 'maxStatusPoints', 'availableStatusPoints', 'languages',
        'languageSummary', 'canManageLanguages', 'portraitUrl', 'canManagePortrait', 'canEditBankAccount',
        'canEditSecuritiesAccount', 'canTransferMoney', 'canDeleteBankTransactions', 'canManageSecurities',
        'canApproveSecuritiesSnapshots', 'defaultBankTransferDate', 'bankTransferTargets',
        'baseRecurringIncome', 'salaryIncreaseBaseIncome', 'salaryIncreasePercentage', 'salaryIncreaseAmount',
        'grossRecurringIncome', 'householdStaffExpenseAmount', 'recurringIncomeTotal',
        'middleClassLivingStandardIncome', 'virtualCompanyShareLivingStandardIncome',
        'draftBankAccountAmount', 'securitiesManagerSkillLevel', 'securitiesManagerDisplayName',
        'securitiesRiskProfileOptions', 'securitiesManagerOptions', 'bankTransactions',
        'canCreateEconomySnapshots', 'economySnapshotEventOptions', 'economySnapshots', 'companyShares',
        'companySharePurchaseOptions',
    ], 'getCharacter-responsevelden of veldvolgorde wijzigden');
    assertCharacterRead(($character['decoded']['firstName'] ?? null) === '<b>Plain name</b>', 'Gewone charactertekst veranderde onverwacht');
    assertCharacterRead(count($character['decoded']['skills'] ?? []) === 1, 'Participant kreeg niet exact de publieke skill');
    assertCharacterRead(($character['decoded']['skills'][0]['name'] ?? '') === 'Public skill', 'Publieke skillresponse wijzigde');
    assertCharacterRead(!str_contains($character['body'], 'Secret skill'), 'Geheime skill lekte naar participant');
    assertCharacterRead(($character['decoded']['nameParticipant'] ?? '') === 'Part Icipant', 'Participantnaam ontbreekt');
    assertCharacterRead($character['writes'] === 0, 'getCharacter deed een write');

    foreach (['director', 'administrator'] as $roleScenario) {
        $result = runCharacterReadScenario($routePaths['character'], 'character', $roleScenario, ['id' => 2]);
        assertCharacterRead($result['status'] === 200, "{$roleScenario} verloor charactertoegang");
        assertCharacterRead(count($result['decoded']['skills'] ?? []) === 2, "{$roleScenario} verloor toegang tot geheime skills");
    }
    assertCharacterReadError(
        runCharacterReadScenario($routePaths['character'], 'character', 'participant', ['id' => 2]),
        403,
        'Participant op character van een ander'
    );

    $diary = runCharacterReadScenario($routePaths['diary'], 'diary', 'participant', ['idCharacter' => 1]);
    assertCharacterRead($diary['status'] === 200, 'Diary van eigen character kon niet worden gelezen');
    assertCharacterRead(array_keys($diary['decoded']) === ['entries', 'availableEvents'], 'Diary top-level contract wijzigde');
    assertCharacterRead(array_keys($diary['decoded']['entries'][0] ?? []) === [
        'id', 'idCharacter', 'idEvent', 'goals', 'achievements', 'gossip1', 'gossip2', 'gossip3',
        'eventTitle', 'dateStart', 'dateEnd',
    ], 'Diary entry-contract of veldvolgorde wijzigde');
    assertCharacterRead(($diary['decoded']['entries'][0]['id'] ?? null) === 7, 'Diary-id is niet langer een integer');
    assertCharacterRead(str_contains($diary['decoded']['entries'][0]['goals'] ?? '', '<strong>Goal</strong>'), 'Toegestane diary-opmaak verdween');
    assertCharacterRead(!str_contains($diary['body'], '<script'), 'Script bleef in diaryresponse staan');
    assertCharacterRead(($diary['decoded']['entries'][0]['gossip1'] ?? '') === '<b>plain gossip</b>', 'Gewone gossiptekst werd als rich text behandeld');
    assertCharacterRead($diary['decoded']['availableEvents'] === [[
        'id' => 12, 'title' => 'Available event', 'dateStart' => '2026-10-01',
    ]], 'Available-events contract wijzigde');

    $sections = runCharacterReadScenario($routePaths['sections'], 'sections', 'participant', ['idCharacter' => 1]);
    assertCharacterRead($sections['status'] === 200, 'Sections van eigen character konden niet worden gelezen');
    assertCharacterRead(array_keys($sections['decoded'] ?? []) === [
        'personal_background', 'knowledge', 'nature', 'demeanour',
    ], 'Sections-contract of veldvolgorde wijzigde');
    assertCharacterRead(str_contains($sections['decoded']['personal_background'] ?? '', '<strong>History</strong>'), 'Toegestane section-opmaak verdween');
    assertCharacterRead(str_contains($sections['decoded']['nature'] ?? '', '<em>Calm</em>'), 'Rich text uit legacy span bleef niet leesbaar');
    assertCharacterRead(!str_contains($sections['body'], 'onclick'), 'Eventhandler bleef in sectionsresponse staan');
    assertCharacterRead(!str_contains($sections['body'], '<script'), 'Script bleef in sectionsresponse staan');
    assertCharacterRead(!str_contains($sections['body'], 'Must not leak'), 'Onbekende beheersection lekte in response');
    assertCharacterRead(($sections['decoded']['demeanour'] ?? null) === '', 'Ontbrekende section is niet meer een lege string');

    foreach (['diary', 'sections'] as $route) {
        $field = $route === 'diary' ? 'idCharacter' : 'idCharacter';
        foreach (['director', 'administrator'] as $roleScenario) {
            $result = runCharacterReadScenario($routePaths[$route], $route, $roleScenario, [$field => 2]);
            assertCharacterRead($result['status'] === 200, "{$route}: {$roleScenario} verloor toegang");
        }
        assertCharacterReadError(
            runCharacterReadScenario($routePaths[$route], $route, 'participant', [$field => 2]),
            403,
            "{$route}: participant op character van een ander"
        );
    }

    foreach (['character' => 'id', 'diary' => 'idCharacter', 'sections' => 'idCharacter'] as $route => $idField) {
        assertCharacterReadError(
            runCharacterReadScenario($routePaths[$route], $route, 'unauthenticated', [$idField => 1]),
            401,
            "{$route}: niet aangemeld"
        );
        assertCharacterReadError(
            runCharacterReadScenario($routePaths[$route], $route, 'participant', [$idField => 999]),
            404,
            "{$route}: onbekend character"
        );
        assertCharacterReadError(
            runCharacterReadScenario($routePaths[$route], $route, 'participant', '{}'),
            422,
            "{$route}: ontbrekend id"
        );
        assertCharacterReadError(
            runCharacterReadScenario($routePaths[$route], $route, 'participant', '[]'),
            400,
            "{$route}: JSON-lijst in plaats van object"
        );
        assertCharacterReadError(
            runCharacterReadScenario($routePaths[$route], $route, 'participant', [$idField => 'fout']),
            422,
            "{$route}: ongeldig id"
        );
        assertCharacterReadError(
            runCharacterReadScenario($routePaths[$route], $route, 'participant', [$idField => 1, 'unexpected' => true]),
            422,
            "{$route}: onverwacht veld"
        );
        $serverError = runCharacterReadScenario($routePaths[$route], $route, 'server_error', [$idField => 1]);
        assertCharacterReadError($serverError, 500, "{$route}: databasefout");
        assertCharacterRead(!str_contains($serverError['body'], 'SQLSTATE'), "{$route}: SQL-detail lekte");
        assertCharacterRead(!str_contains($serverError['body'], 'secret_read_table'), "{$route}: tabeldetail lekte");
    }
} finally {
    removeCharacterReadFixtureTree($fixtureRoot);
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Character read endpoint tests passed." . PHP_EOL;
