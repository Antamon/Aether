<?php
declare(strict_types=1);

if (defined('AETHER_CHARACTER_SKILLS_ACTIONS_TEST_BOOTSTRAP')) {
    if (!function_exists('mb_strtolower')) {
        function mb_strtolower(string $value, ?string $encoding = null): string
        {
            return strtolower($value);
        }
    }

    final class CharacterSkillsActionsTestStatement extends PDOStatement
    {
        private array $params = [];
        private array $rows = [];
        private int $cursor = 0;
        private int $affectedRows = 0;

        public function __construct(private CharacterSkillsActionsTestPdo $pdo, private string $query)
        {
        }

        private function sql(): string
        {
            return preg_replace('/\s+/', ' ', trim($this->query)) ?? trim($this->query);
        }

        public function execute(?array $params = null): bool
        {
            $this->params = $params ?? [];
            $this->rows = [];
            $this->cursor = 0;
            $this->affectedRows = 0;
            $sql = $this->sql();

            if ($this->pdo->scenario === 'server_error' && str_contains($sql, 'FROM tblEvent')) {
                throw new PDOException('SQLSTATE[42S02]: secret_action_table');
            }

            if (str_starts_with($sql, 'INSERT INTO tblApiIdempotency')) {
                $this->pdo->write($sql, $this->params);
                $key = $this->params['idUser'] . ':' . $this->params['operation'] . ':' . $this->params['requestKey'];
                if (!isset($this->pdo->idempotency[$key])) {
                    $this->pdo->idempotency[$key] = [
                        'payloadHash' => $this->params['payloadHash'],
                        'status' => 'processing',
                        'responseStatus' => null,
                        'responseJson' => null,
                    ];
                }
            } elseif (str_starts_with($sql, 'UPDATE tblApiIdempotency')) {
                $this->pdo->write($sql, $this->params);
                $key = $this->params['idUser'] . ':' . $this->params['operation'] . ':' . $this->params['requestKey'];
                if (isset($this->pdo->idempotency[$key])) {
                    $this->pdo->idempotency[$key]['status'] = 'completed';
                    $this->pdo->idempotency[$key]['responseStatus'] = 200;
                    $this->pdo->idempotency[$key]['responseJson'] = $this->params['responseJson'];
                    $this->affectedRows = 1;
                }
            } elseif (str_starts_with($sql, 'UPDATE tblLinkCharacterSkill')) {
                $this->pdo->write($sql, $this->params);
                $idCharacter = (int) $this->params[1];
                $idSkill = (int) $this->params[2];
                $this->pdo->skillLinks[$idCharacter][$idSkill]['level'] = (int) $this->params[0];
            } elseif (str_starts_with($sql, 'DELETE cs FROM tblCharacterSpecialisation')) {
                $this->pdo->write($sql, $this->params);
                [$idCharacter, $idSkill] = array_map('intval', $this->params);
                foreach ($this->pdo->characterSpecialisations as $key => $link) {
                    $definition = $this->pdo->specialisations[(int) $link['idSkillSpecialisation']] ?? null;
                    if ((int) $link['idCharacter'] === $idCharacter && (int) $link['idSkill'] === $idSkill
                        && ($definition['kind'] ?? '') === 'discipline') {
                        unset($this->pdo->characterSpecialisations[$key]);
                    }
                }
            } elseif (str_starts_with($sql, 'DELETE FROM tblCharacterSpecialisation')) {
                $this->pdo->write($sql, $this->params);
                [$idCharacter, $idSkill] = array_map('intval', $this->params);
                foreach ($this->pdo->characterSpecialisations as $key => $link) {
                    if ((int) $link['idCharacter'] === $idCharacter && (int) $link['idSkill'] === $idSkill) {
                        unset($this->pdo->characterSpecialisations[$key]);
                    }
                }
            } elseif (str_starts_with($sql, 'DELETE FROM tblLinkCharacterSkill')) {
                $this->pdo->write($sql, $this->params);
                if ($this->pdo->scenario === 'skill_delete_error') {
                    throw new PDOException('SQLSTATE[HY000]: secret_skill_delete');
                }
                unset($this->pdo->skillLinks[(int) $this->params[0]][(int) $this->params[1]]);
            } elseif (str_starts_with($sql, 'INSERT INTO tblSkillSpecialisation')) {
                $this->pdo->write($sql, $this->params);
                $id = $this->pdo->nextSpecialisationId++;
                $this->pdo->lastInsertIdValue = $id;
                $this->pdo->specialisations[$id] = [
                    'id' => $id, 'idSkill' => (int) $this->params[0],
                    'name' => (string) $this->params[1], 'kind' => (string) $this->params[2],
                ];
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterSpecialisation')) {
                $this->pdo->write($sql, $this->params);
                if ($this->pdo->scenario === 'specialisation_link_error') {
                    throw new PDOException('SQLSTATE[HY000]: secret_specialisation_link');
                }
                $link = [
                    'idCharacter' => (int) $this->params[0],
                    'idSkill' => (int) $this->params[1],
                    'idSkillSpecialisation' => (int) $this->params[2],
                ];
                if ($this->pdo->scenario === 'specialisation_unique_conflict') {
                    if (!str_contains($sql, 'ON DUPLICATE KEY UPDATE')) {
                        $exception = new PDOException('SQLSTATE[23000]: duplicate specialisation link', 23000);
                        $exception->errorInfo = ['23000', 1062, 'duplicate specialisation link'];
                        throw $exception;
                    }
                    // Simuleer dat een gelijktijdige transactie dezelfde link na de voorafgaande SELECT invoegde.
                    $this->pdo->characterSpecialisations[] = $link;
                } else {
                    $this->pdo->characterSpecialisations[] = $link;
                }
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterEventGossipAttempt')) {
                $this->pdo->write($sql, $this->params);
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'];
                if ($this->pdo->scenario === 'gossip_concurrent_increment' && str_contains($sql, 'id = id')) {
                    // Een tweede transactie verhoogde dezelfde beginwaarde vlak vóór deze SQL-write.
                    $this->pdo->attempts[$key] = (int) ($this->pdo->attempts[$key] ?? 0) + 1;
                }
                if (str_contains($sql, 'attemptCount = attemptCount + 1')) {
                    $this->pdo->attempts[$key] = isset($this->pdo->attempts[$key])
                        ? (int) $this->pdo->attempts[$key] + 1
                        : 1;
                } elseif (str_contains($sql, 'id = id')) {
                    $this->pdo->attempts[$key] = (int) ($this->pdo->attempts[$key] ?? 0);
                } else {
                    $this->pdo->attempts[$key] = (int) ($this->params['attemptCount'] ?? 0);
                }
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterEventGossipUnlock')) {
                $this->pdo->write($sql, $this->params);
                if ($this->pdo->scenario === 'knowledge_unlock_error') {
                    throw new PDOException('SQLSTATE[HY000]: secret_unlock');
                }
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'] . ':' . $this->params['idSourceCharacter'];
                if (str_contains($sql, 'id = id')) {
                    $this->pdo->unlocks[$key] ??= [
                        'unlockGossip1' => 0, 'unlockGossip2' => 0, 'unlockGossip3' => 0,
                    ];
                } else {
                    $previous = $this->pdo->unlocks[$key] ?? [
                        'unlockGossip1' => 0, 'unlockGossip2' => 0, 'unlockGossip3' => 0,
                    ];
                    $this->pdo->unlocks[$key] = [
                        'unlockGossip1' => max((int) $previous['unlockGossip1'], (int) $this->params['unlockGossip1']),
                        'unlockGossip2' => max((int) $previous['unlockGossip2'], (int) $this->params['unlockGossip2']),
                        'unlockGossip3' => max((int) $previous['unlockGossip3'], (int) $this->params['unlockGossip3']),
                    ];
                }
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterSkillActionState')) {
                $this->pdo->write($sql, $this->params);
                $idCharacter = (int) $this->params['idCharacter'];
                if (str_contains($sql, 'id = id')) {
                    $this->pdo->burn[$idCharacter] ??= (int) $this->params['numericValue'];
                } else {
                    $this->pdo->burn[$idCharacter] = (int) $this->params['numericValue'];
                }
            } elseif (str_starts_with($sql, 'INSERT INTO tblCharacterSkillActionUse')) {
                $this->pdo->write($sql, $this->params);
                if ($this->pdo->scenario === 'action_use_error') {
                    throw new PDOException('SQLSTATE[HY000]: secret_action_use');
                }
                $id = $this->pdo->nextActionUseId++;
                $this->pdo->lastInsertIdValue = $id;
                $this->pdo->actionUses[$id] = $this->params;
            }

            return true;
        }

        public function rowCount(): int
        {
            return $this->affectedRows;
        }

        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblApiIdempotency')) {
                $key = $this->params['idUser'] . ':' . $this->params['operation'] . ':' . $this->params['requestKey'];
                return $this->pdo->idempotency[$key] ?? false;
            }
            if (str_contains($sql, 'FROM tblUser')) {
                return $this->pdo->users[(int) ($this->params['id'] ?? 0)] ?? false;
            }
            if (str_contains($sql, 'FROM tblCharacter WHERE') && !str_contains($sql, 'JOIN')) {
                $id = (int) ($this->params['id'] ?? $this->params[':id'] ?? $this->params[':idCharacter'] ?? $this->params[0] ?? 0);
                return $this->pdo->characters[$id] ?? false;
            }
            if (str_contains($sql, 'FROM tblLinkCharacterSkill') && str_contains($sql, 'SELECT level')) {
                $idCharacter = (int) ($this->params['idCharacter'] ?? $this->params[0] ?? 0);
                $idSkill = (int) ($this->params['idSkill'] ?? $this->params[1] ?? 0);
                return $this->pdo->skillLinks[$idCharacter][$idSkill] ?? false;
            }
            if (str_contains($sql, 'FROM tblSkillSpecialisation') && str_contains($sql, 'WHERE id = ? AND idSkill = ?')) {
                $row = $this->pdo->specialisations[(int) $this->params[0]] ?? null;
                return $row !== null && (int) $row['idSkill'] === (int) $this->params[1] ? $row : false;
            }
            if (str_contains($sql, 'FROM tblSkillSpecialisation') && str_contains($sql, 'LOWER(name) = LOWER(?)')) {
                foreach ($this->pdo->specialisations as $row) {
                    if ((int) $row['idSkill'] === (int) $this->params[0]
                        && strtolower((string) $row['name']) === strtolower((string) $this->params[1])) {
                        return $row;
                    }
                }
                return false;
            }
            if (str_contains($sql, 'FROM tblCharacterSkillActionState')) {
                $id = (int) $this->params['idCharacter'];
                return isset($this->pdo->burn[$id]) ? ['numericValue' => $this->pdo->burn[$id]] : false;
            }
            if (str_contains($sql, 'FROM tblCharacterEventGossipAttempt')) {
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'];
                return isset($this->pdo->attempts[$key]) ? ['attemptCount' => $this->pdo->attempts[$key]] : false;
            }
            if (str_contains($sql, 'FROM tblCharacterEventGossipUnlock') && !str_contains($sql, 'JOIN')) {
                $key = $this->params['idViewerCharacter'] . ':' . $this->params['idEvent'] . ':' . $this->params['idSourceCharacter'];
                return $this->pdo->unlocks[$key] ?? false;
            }
            if (str_contains($sql, 'FROM tblCharacterDiary AS d') && str_contains($sql, 'd.idCharacter = :idSourceCharacter')) {
                $key = $this->params['idEvent'] . ':' . $this->params['idSourceCharacter'];
                $diary = $this->pdo->diaries[$key] ?? null;
                if ($diary === null) {
                    return false;
                }
                return $diary + $this->pdo->characters[(int) $this->params['idSourceCharacter']];
            }
            if (str_contains($sql, 'FROM tblCharacterDiaryVisibility')) {
                $key = $this->params['idEvent'] . ':' . $this->params['idCharacter'];
                return isset($this->pdo->visibility[$key]) ? ['isVisible' => $this->pdo->visibility[$key]] : false;
            }
            if (str_contains($sql, 'FROM tblEvent') && str_contains($sql, 'WHERE id = :idEvent')) {
                return $this->pdo->events[(int) $this->params['idEvent']] ?? false;
            }
            if (isset($this->rows[$this->cursor])) {
                return $this->rows[$this->cursor++];
            }
            return false;
        }

        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            $sql = $this->sql();
            if (str_contains($sql, 'FROM tblSkill s') && str_contains($sql, 'cs.level')) {
                $result = [];
                foreach ($this->pdo->skillLinks[(int) $this->params[0]] ?? [] as $skillId => $link) {
                    $result[] = $this->pdo->skills[$skillId] + ['level' => $link['level']];
                }
                usort($result, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
                return $result;
            }
            if (str_contains($sql, 'FROM tblEvent') && str_contains($sql, 'ORDER BY dateStart')) {
                return array_values($this->pdo->events);
            }
            if (str_contains($sql, 'FROM tblLinkCharacterSkill AS lcs')) {
                $result = [];
                foreach ($this->pdo->skillLinks[(int) $this->params['idCharacter']] ?? [] as $skillId => $link) {
                    foreach ($this->pdo->skillTypes[$skillId] ?? [] as $type) {
                        $result[] = [
                            'idSkill' => $skillId, 'level' => $link['level'],
                            'skillName' => $this->pdo->skills[$skillId]['name'],
                            'idSkillType' => $type['id'], 'skillTypeCode' => $type['code'], 'skillTypeName' => $type['name'],
                        ];
                    }
                }
                return $result;
            }
            if (str_contains($sql, 'FROM tblCharacterDiary AS d') && str_contains($sql, 'LEFT JOIN tblCharacterDiaryVisibility')) {
                $result = [];
                $viewer = (int) $this->params['idViewerCharacterFilter'];
                $event = (int) $this->params['idEvent'];
                foreach ($this->pdo->diaries as $key => $diary) {
                    if ((int) $diary['idEvent'] !== $event || (int) $diary['idCharacter'] === $viewer) {
                        continue;
                    }
                    $character = $this->pdo->characters[(int) $diary['idCharacter']];
                    $visibilityKey = $event . ':' . $diary['idCharacter'];
                    $unlockKey = $viewer . ':' . $event . ':' . $diary['idCharacter'];
                    $result[] = $diary + $character + [
                        'isVisible' => $this->pdo->visibility[$visibilityKey] ?? null,
                    ] + ($this->pdo->unlocks[$unlockKey] ?? [
                        'unlockGossip1' => 0, 'unlockGossip2' => 0, 'unlockGossip3' => 0,
                    ]);
                }
                return $result;
            }
            return [];
        }

        public function fetchColumn(int $column = 0): mixed
        {
            $sql = $this->sql();
            if (str_contains($sql, 'SELECT visibility FROM tblSkill')) {
                return $this->pdo->skills[(int) ($this->params['id'] ?? 0)]['visibility'] ?? false;
            }
            if (str_contains($sql, 'FROM tblLinkSkillType')) {
                foreach ($this->pdo->skillTypes[(int) $this->params[0]] ?? [] as $type) {
                    if ($type['code'] === 'discipline') {
                        return 1;
                    }
                }
                return 0;
            }
            if (str_contains($sql, 'FROM tblCharacterSpecialisation')) {
                foreach ($this->pdo->characterSpecialisations as $link) {
                    if ((int) $link['idCharacter'] === (int) $this->params[0]
                        && (int) $link['idSkill'] === (int) $this->params[1]
                        && (int) $link['idSkillSpecialisation'] === (int) $this->params[2]) {
                        return 1;
                    }
                }
                return 0;
            }
            return 0;
        }
    }

    final class CharacterSkillsActionsTestPdo extends PDO
    {
        public array $users;
        public array $characters;
        public array $skills;
        public array $skillLinks;
        public array $skillTypes;
        public array $specialisations;
        public array $characterSpecialisations;
        public array $events;
        public array $diaries;
        public array $visibility;
        public array $attempts;
        public array $unlocks;
        public array $burn;
        public array $actionUses;
        public array $idempotency;
        public int $nextSpecialisationId;
        public int $nextActionUseId;
        public int $lastInsertIdValue = 0;
        public int $writeAttempts = 0;
        public int $committedWrites = 0;
        public array $writes = [];
        private bool $transactionActive = false;
        private ?array $snapshot = null;
        private int $transactionWrites = 0;

        public function __construct(public string $scenario, array $state)
        {
            foreach ($state as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
        }

        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            return new CharacterSkillsActionsTestStatement($this, $query);
        }

        public function lastInsertId(?string $name = null): string|false
        {
            return (string) $this->lastInsertIdValue;
        }

        public function beginTransaction(): bool
        {
            $this->transactionActive = true;
            $this->transactionWrites = 0;
            $this->snapshot = $this->exportState();
            return true;
        }

        public function commit(): bool
        {
            $this->committedWrites += $this->transactionWrites;
            $this->transactionActive = false;
            $this->snapshot = null;
            return true;
        }

        public function rollBack(): bool
        {
            if ($this->snapshot !== null) {
                foreach ($this->snapshot as $key => $value) {
                    if (property_exists($this, $key)) {
                        $this->{$key} = $value;
                    }
                }
            }
            $this->transactionActive = false;
            $this->transactionWrites = 0;
            $this->snapshot = null;
            return true;
        }

        public function inTransaction(): bool
        {
            return $this->transactionActive;
        }

        public function write(string $sql, array $params): void
        {
            $this->writeAttempts++;
            $this->writes[] = ['sql' => $sql, 'parameters' => $params];
            if ($this->transactionActive) {
                $this->transactionWrites++;
            } else {
                $this->committedWrites++;
            }
        }

        public function skillExperienceCost(int $characterId): int
        {
            $cost = 0;
            foreach ($this->skillLinks[$characterId] ?? [] as $link) {
                $cost += [0 => 0, 1 => 1, 2 => 3, 3 => 6][(int) $link['level']] ?? 0;
            }
            foreach ($this->characterSpecialisations as $link) {
                if ((int) $link['idCharacter'] !== $characterId) {
                    continue;
                }
                if (($this->specialisations[(int) $link['idSkillSpecialisation']]['kind'] ?? '') !== 'discipline') {
                    $cost += 2;
                }
            }
            return $cost;
        }

        public function exportState(): array
        {
            return [
                'users' => $this->users, 'characters' => $this->characters, 'skills' => $this->skills,
                'skillLinks' => $this->skillLinks, 'skillTypes' => $this->skillTypes,
                'specialisations' => $this->specialisations,
                'characterSpecialisations' => $this->characterSpecialisations,
                'events' => $this->events, 'diaries' => $this->diaries, 'visibility' => $this->visibility,
                'attempts' => $this->attempts, 'unlocks' => $this->unlocks, 'burn' => $this->burn,
                'actionUses' => $this->actionUses, 'nextSpecialisationId' => $this->nextSpecialisationId,
                'nextActionUseId' => $this->nextActionUseId, 'idempotency' => $this->idempotency,
            ];
        }
    }

    function dbAll(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    function dbOne(PDO $pdo, string $sql, array $params = []): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    function getPDO(): PDO
    {
        global $pdo;
        return $pdo;
    }

    $route = (string) ($argv[1] ?? 'events');
    $scenario = (string) ($argv[2] ?? 'participant');
    $stateFile = (string) ($argv[3] ?? '');
    $requestKey = (string) ($argv[4] ?? 'test-request-key-0000000000000000');
    $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
    $pdo = new CharacterSkillsActionsTestPdo($scenario, $state);

    $userId = match ($scenario) {
        'unauthenticated' => 999,
        'director' => 20,
        'administrator' => 30,
        default => 10,
    };
    session_start();
    $_SESSION = ['user' => ['id' => $userId, 'role' => 'administrator'], 'aetherCsrfToken' => 'expected-token'];
    if ($scenario !== 'missing_csrf') {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $scenario === 'invalid_csrf' ? 'forged-token' : 'expected-token';
    }
    if (in_array($route, ['reveal', 'useAction'], true) && $scenario !== 'missing_idempotency') {
        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $requestKey;
    }
    register_shutdown_function(static function () use ($pdo, $stateFile): void {
        file_put_contents($stateFile, json_encode($pdo->exportState(), JSON_THROW_ON_ERROR));
        $status = http_response_code();
        fwrite(STDERR, '__AETHER_SKILL_ACTION_STATE__:' . json_encode([
            'status' => $status === false ? 200 : $status,
            'writeAttempts' => $pdo->writeAttempts,
            'committedWrites' => $pdo->committedWrites,
            'writes' => $pdo->writes,
        ], JSON_THROW_ON_ERROR) . "\n");
    });
    return;
}

$projectRoot = dirname(__DIR__);
$fixtureRoot = sys_get_temp_dir() . '/aether-skills-actions-' . bin2hex(random_bytes(6));
$failures = [];
$stateFiles = [];

function assertSkillsActions(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function copySkillsActionsTree(string $source, string $target): void
{
    if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
        throw new RuntimeException("Kon fixturemap {$target} niet maken.");
    }
    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot()) {
            continue;
        }
        $targetPath = $target . '/' . $item->getFilename();
        $item->isDir() ? copySkillsActionsTree($item->getPathname(), $targetPath) : copy($item->getPathname(), $targetPath);
    }
}

function removeSkillsActionsTree(string $path): void
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

function createSkillsActionsState(array $state): string
{
    $path = tempnam(sys_get_temp_dir(), 'aether-skill-action-state-');
    if ($path === false) {
        throw new RuntimeException('Kon geen statebestand maken.');
    }
    file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
    return $path;
}

/** @return array<string, mixed> */
function runSkillsActions(
    string $path,
    string $route,
    string $scenario,
    array|string $request,
    string $stateFile,
    ?string $requestKey = null
): array
{
    $requestKey ??= 'test-request-' . bin2hex(random_bytes(12));
    $process = proc_open(
        [PHP_BINARY, $path, $route, $scenario, $stateFile, $requestKey],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException("Kon {$route}/{$scenario} niet starten.");
    }
    fwrite($pipes[0], is_string($request) ? $request : json_encode($request, JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $body = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if (!preg_match('/__AETHER_SKILL_ACTION_STATE__:(\{.*\})/', $stderr, $matches)) {
        throw new RuntimeException("Geen status voor {$route}/{$scenario}: {$stderr}");
    }
    $meta = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    return $meta + [
        'body' => $body, 'decoded' => json_decode($body, true), 'stderr' => $stderr,
        'exitCode' => $exitCode,
        'state' => json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR),
    ];
}

function assertSkillsActionsError(array $result, int $status, int $writes, string $label): void
{
    assertSkillsActions((int) $result['status'] === $status, "{$label}: HTTP {$result['status']} in plaats van {$status}");
    assertSkillsActions(isset($result['decoded']['error']), "{$label}: foutresponse ontbreekt");
    assertSkillsActions((int) $result['writeAttempts'] === $writes, "{$label}: onverwachte writepogingen");
    assertSkillsActions((int) $result['committedWrites'] === 0, "{$label}: geweigerd request committe writes");
}

copySkillsActionsTree($projectRoot . '/api', $fixtureRoot . '/api');
copy($projectRoot . '/sessionUserBootstrap.php', $fixtureRoot . '/sessionUserBootstrap.php');
file_put_contents(
    $fixtureRoot . '/db.php',
    "<?php\ndefine('AETHER_CHARACTER_SKILLS_ACTIONS_TEST_BOOTSTRAP', true);\nrequire " . var_export(__FILE__, true) . ";\n"
);
file_put_contents(
    $fixtureRoot . '/api/characters/characterPointUtils.php',
    <<<'PHP'
<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/accessControl.php';
function getCharacterSkillExperienceCost(PDO $pdo, int $idCharacter): int { return $pdo->skillExperienceCost($idCharacter); }
function getCharacterPointSummary(PDO $pdo, array $character): array {
    $isPlayer = ($character['type'] ?? '') === 'player';
    return [
        'isPlayer' => $isPlayer,
        'experienceBudget' => $isPlayer ? (int) ($character['experienceBudget'] ?? 20) : null,
    ];
}
function isPrivilegedUserRole(string $role): bool { return aetherIsPrivilegedRole($role); }
PHP
);

$routes = [
    'updateSkill' => $fixtureRoot . '/api/characters/updateSkill.php',
    'addSpec' => $fixtureRoot . '/api/characters/addSkillSpecialisation.php',
    'events' => $fixtureRoot . '/api/characters/getCharacterActionEvents.php',
    'targets' => $fixtureRoot . '/api/characters/getCharacterActionKnowledgeTargets.php',
    'reveal' => $fixtureRoot . '/api/characters/revealCharacterActionKnowledge.php',
    'useAction' => $fixtureRoot . '/api/characters/useCharacterSkillAction.php',
];
$initialState = [
    'users' => [
        10 => ['id' => 10, 'username' => 'participant', 'firstName' => 'Part', 'lastName' => 'Icipant', 'role' => 'participant'],
        20 => ['id' => 20, 'username' => 'director', 'firstName' => 'Di', 'lastName' => 'Rector', 'role' => 'director'],
        30 => ['id' => 30, 'username' => 'administrator', 'firstName' => 'Ad', 'lastName' => 'Min', 'role' => 'administrator'],
    ],
    'characters' => [
        1 => ['id' => 1, 'idUser' => 10, 'type' => 'player', 'state' => 'active', 'class' => 'middle class', 'experienceBudget' => 20, 'firstName' => 'Own', 'lastName' => 'Player'],
        2 => ['id' => 2, 'idUser' => 99, 'type' => 'player', 'state' => 'active', 'class' => 'middle class', 'experienceBudget' => 20, 'firstName' => 'Other', 'lastName' => 'Player'],
        3 => ['id' => 3, 'idUser' => 99, 'type' => 'player', 'state' => 'active', 'class' => 'middle class', 'experienceBudget' => 20, 'firstName' => 'Source', 'lastName' => 'Visible'],
        4 => ['id' => 4, 'idUser' => 99, 'type' => 'extra', 'state' => 'active', 'class' => 'lower class', 'experienceBudget' => 0, 'firstName' => 'Hidden', 'lastName' => 'Extra'],
        5 => ['id' => 5, 'idUser' => 10, 'type' => 'extra', 'state' => 'active', 'class' => 'lower class', 'experienceBudget' => 0, 'firstName' => 'Own', 'lastName' => 'Extra'],
    ],
    'skills' => [
        32 => ['id' => 32, 'name' => 'Wereldwijs', 'description' => '', 'beginner' => '', 'professional' => '', 'master' => '', 'visibility' => 'public'],
        40 => ['id' => 40, 'name' => 'Zien', 'description' => '', 'beginner' => '', 'professional' => '', 'master' => '', 'visibility' => 'public'],
        41 => ['id' => 41, 'name' => 'Geheime gave', 'description' => '', 'beginner' => '', 'professional' => '', 'master' => '', 'visibility' => 'secret'],
        42 => ['id' => 42, 'name' => 'Kunde', 'description' => '', 'beginner' => '', 'professional' => '', 'master' => '', 'visibility' => 'public'],
    ],
    'skillLinks' => [
        1 => [32 => ['level' => 2], 40 => ['level' => 1], 41 => ['level' => 1], 42 => ['level' => 1]],
        2 => [40 => ['level' => 1]],
        5 => [40 => ['level' => 1]],
    ],
    'skillTypes' => [
        40 => [['id' => 1, 'code' => 'zintuiglijke_gave', 'name' => 'Zintuiglijke gave']],
        41 => [['id' => 2, 'code' => 'somatische_gave', 'name' => 'Somatische gave']],
    ],
    'specialisations' => [
        100 => ['id' => 100, 'idSkill' => 42, 'name' => 'Historicus', 'kind' => 'specialisation'],
        101 => ['id' => 101, 'idSkill' => 40, 'name' => 'Verkeerde skill', 'kind' => 'specialisation'],
    ],
    'characterSpecialisations' => [],
    'events' => [7 => ['id' => 7, 'title' => 'Aether Cut', 'dateStart' => '2026-09-01', 'dateEnd' => '2026-09-02']],
    'diaries' => [
        '7:3' => ['idCharacter' => 3, 'idEvent' => 7, 'gossip1' => 'Publiek geheim', 'gossip2' => 'Dieper geheim', 'gossip3' => ''],
        '7:4' => ['idCharacter' => 4, 'idEvent' => 7, 'gossip1' => 'Verborgen extra', 'gossip2' => '', 'gossip3' => ''],
    ],
    'visibility' => [], 'attempts' => [], 'unlocks' => [], 'burn' => [1 => 0, 2 => 0, 5 => 0],
    'actionUses' => [], 'nextSpecialisationId' => 102, 'nextActionUseId' => 500, 'idempotency' => [],
];

try {
    $state = $stateFiles[] = createSkillsActionsState($initialState);
    $update = runSkillsActions($routes['updateSkill'], 'updateSkill', 'participant', ['action' => 'up', 'idSkill' => 42, 'idCharacter' => 1], $state);
    assertSkillsActions($update['status'] === 200 && array_keys($update['decoded'] ?? []) === ['skills', 'usedExperience', 'maxExperience'], 'Skillupdate-response is niet compatibel');
    assertSkillsActions(($update['state']['skillLinks'][1][42]['level'] ?? 0) === 2, 'Geldige eigen skillwijziging bleef niet bewaard');
    assertSkillsActions(($update['writes'][0]['parameters'] ?? []) === [2, 1, 42], 'Skillupdate gebruikte niet de exacte prepared parameters');

    foreach (['director', 'administrator'] as $role) {
        $roleState = $stateFiles[] = createSkillsActionsState($initialState);
        $result = runSkillsActions($routes['updateSkill'], 'updateSkill', $role, ['action' => 'up', 'idSkill' => 40, 'idCharacter' => 2], $roleState);
        assertSkillsActions($result['status'] === 200 && $result['committedWrites'] === 1, "{$role} verloor skillrechten");
    }
    $secretSkillState = $stateFiles[] = createSkillsActionsState($initialState);
    $directorSecretSkill = runSkillsActions($routes['updateSkill'], 'updateSkill', 'director', [
        'action' => 'up', 'idSkill' => 41, 'idCharacter' => 1,
    ], $secretSkillState);
    assertSkillsActions($directorSecretSkill['status'] === 200 && $directorSecretSkill['committedWrites'] === 1,
        'Director verloor beheer van een geheime skill');

    $specState = $stateFiles[] = createSkillsActionsState($initialState);
    $addSpec = runSkillsActions($routes['addSpec'], 'addSpec', 'participant', [
        'idSkill' => 42, 'idCharacter' => 1, 'idSkillSpecialisation' => 100, 'name' => '',
    ], $specState);
    assertSkillsActions($addSpec['decoded'] === ['success' => true], 'Specialisatie-successresponse wijzigde');
    assertSkillsActions(count($addSpec['state']['characterSpecialisations']) === 1, 'Specialisatie bleef niet bewaard bij opnieuw lezen');
    assertSkillsActions(($addSpec['writes'][0]['parameters'] ?? []) === [1, 42, 100], 'Specialisatielink gebruikte niet de exacte parameters');
    $repeatSpec = runSkillsActions($routes['addSpec'], 'addSpec', 'participant', [
        'idSkill' => 42, 'idCharacter' => 1, 'idSkillSpecialisation' => 100, 'name' => '',
    ], $specState);
    assertSkillsActions($repeatSpec['decoded'] === ['success' => true] && $repeatSpec['writeAttempts'] === 0
        && count($repeatSpec['state']['characterSpecialisations']) === 1,
        'Opnieuw lezen/toevoegen maakte een dubbele specialisatielink');

    $newSpecState = $stateFiles[] = createSkillsActionsState($initialState);
    $newSpec = runSkillsActions($routes['addSpec'], 'addSpec', 'director', [
        'idSkill' => 42, 'idCharacter' => 1, 'idSkillSpecialisation' => 0, 'name' => ' Nieuwe discipline ', 'kind' => 'discipline',
    ], $newSpecState);
    assertSkillsActions($newSpec['status'] === 200 && $newSpec['committedWrites'] === 2, 'Definitie en link committen niet samen');
    assertSkillsActions(($newSpec['state']['specialisations'][102]['kind'] ?? '') === 'discipline', 'Beheerdiscipline verloor haar bestaande uitzondering');
    $specRaceState = $stateFiles[] = createSkillsActionsState($initialState);
    $specRace = runSkillsActions($routes['addSpec'], 'addSpec', 'specialisation_unique_conflict', [
        'idSkill' => 42, 'idCharacter' => 1, 'idSkillSpecialisation' => 100, 'name' => '',
    ], $specRaceState);
    assertSkillsActions($specRace['status'] === 200 && $specRace['decoded'] === ['success' => true]
        && count($specRace['state']['characterSpecialisations']) === 1,
        'Gelijktijdige identieke specialisatielink werd niet gecontroleerd als succes afgehandeld');

    $events = runSkillsActions($routes['events'], 'events', 'participant', ['idCharacter' => 1], $state);
    assertSkillsActions(array_keys($events['decoded'] ?? []) === ['events', 'worldKnowledgeLevel', 'psiBurn', 'actions'], 'Actioncatalogusresponse wijzigde');
    assertSkillsActions(($events['decoded']['worldKnowledgeLevel'] ?? 0) === 2, 'Wereldwijsniveau wijzigde');
    $listedSkillIds = [];
    foreach ($events['decoded']['actions'] ?? [] as $group) {
        array_push($listedSkillIds, ...array_column($group['skills'] ?? [], 'idSkill'));
    }
    assertSkillsActions(in_array(40, $listedSkillIds, true) && !in_array(41, $listedSkillIds, true), 'Publieke/geheime skillfilter voor participant is fout');
    $adminEvents = runSkillsActions($routes['events'], 'events', 'administrator', ['idCharacter' => 1], $state);
    $adminSkillIds = [];
    foreach ($adminEvents['decoded']['actions'] ?? [] as $group) {
        array_push($adminSkillIds, ...array_column($group['skills'] ?? [], 'idSkill'));
    }
    assertSkillsActions(in_array(41, $adminSkillIds, true), 'Administrator verloor geheime actionskill');
    $ownExtraEvents = runSkillsActions($routes['events'], 'events', 'participant', ['idCharacter' => 5], $state);
    assertSkillsActions($ownExtraEvents['status'] === 200, 'Bestaande action-uitzondering voor een eigen extra ging verloren');
    $directorEvents = runSkillsActions($routes['events'], 'events', 'director', ['idCharacter' => 2], $state);
    assertSkillsActions($directorEvents['status'] === 200, 'Director verloor action-leestoegang op een ander character');

    $targets = runSkillsActions($routes['targets'], 'targets', 'participant', ['idCharacter' => 1, 'idEvent' => 7], $state);
    assertSkillsActions(array_keys($targets['decoded'] ?? []) === ['worldKnowledgeLevel', 'attemptCount', 'targets'], 'Kennistargetresponse wijzigde');
    assertSkillsActions(array_column($targets['decoded']['targets'] ?? [], 'idCharacter') === [3], 'Zichtbare/verborgen kennistargets zijn verkeerd gefilterd');

    $revealState = $stateFiles[] = createSkillsActionsState($initialState);
    $reveal = runSkillsActions($routes['reveal'], 'reveal', 'participant', ['idCharacter' => 1, 'idEvent' => 7, 'idSourceCharacter' => 3], $revealState);
    assertSkillsActions($reveal['status'] === 200 && array_keys($reveal['decoded'] ?? []) === [
        'attemptCount', 'newlyUnlockedLevels', 'unlockedGossips', 'unlockedGossipLevel',
        'displayName', 'portraitUrl', 'type', 'isFullyUnlocked',
    ], 'Reveal-response wijzigde');
    assertSkillsActions($reveal['committedWrites'] === 6 && ($reveal['state']['attempts']['1:7'] ?? 0) === 1, 'Gossipwrites en idempotentieresponse committen niet samen');
    $concurrentRevealState = $stateFiles[] = createSkillsActionsState($initialState);
    $concurrentReveal = runSkillsActions($routes['reveal'], 'reveal', 'gossip_concurrent_increment', [
        'idCharacter' => 1, 'idEvent' => 7, 'idSourceCharacter' => 3,
    ], $concurrentRevealState);
    assertSkillsActions($concurrentReveal['status'] === 200
        && ($concurrentReveal['decoded']['attemptCount'] ?? 0) === 2
        && ($concurrentReveal['state']['attempts']['1:7'] ?? 0) === 2,
        'Twee gesimuleerde gossipincrements leverden geen eindwaarde 2 op: '
            . json_encode(['response' => $concurrentReveal['decoded'], 'attempts' => $concurrentReveal['state']['attempts']]));
    $attemptWrite = array_values(array_filter(
        $concurrentReveal['writes'],
        static fn(array $write): bool => str_contains((string) ($write['sql'] ?? ''), 'attemptCount = attemptCount + 1')
    ));
    assertSkillsActions($attemptWrite !== [], 'Gossipincrement gebruikt geen atomaire database-increment');

    $actionState = $stateFiles[] = createSkillsActionsState($initialState);
    $use = runSkillsActions($routes['useAction'], 'useAction', 'participant', [
        'idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 40,
        'actionCode' => 'psi', 'actionSubtype' => 'zintuiglijke_gave', 'clearBurn' => false,
    ], $actionState);
    assertSkillsActions($use['status'] === 200 && array_keys($use['decoded'] ?? []) === [
        'usageId', 'actionCode', 'categoryCode', 'categoryLabel', 'skill', 'event', 'roll', 'burn', 'result',
    ], 'Skillaction-response wijzigde');
    assertSkillsActions($use['committedWrites'] === 5 && count($use['state']['actionUses']) === 1, 'Action state, use en idempotentieresponse committen niet samen');
    $actionUseWrites = array_values(array_filter(
        $use['writes'],
        static fn(array $write): bool => str_starts_with((string) ($write['sql'] ?? ''), 'INSERT INTO tblCharacterSkillActionUse')
    ));
    $useParams = $actionUseWrites[0]['parameters'] ?? [];
    assertSkillsActions(($useParams['idCharacter'] ?? 0) === 1 && ($useParams['idEvent'] ?? 0) === 7
        && ($useParams['idSkill'] ?? 0) === 40 && ($useParams['createdBy'] ?? 0) === 10,
        'Action use prepared parameters bevatten niet de vertrouwde ids');
    $repeatUse = runSkillsActions($routes['useAction'], 'useAction', 'participant', [
        'idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 40,
        'actionCode' => 'psi', 'actionSubtype' => 'zintuiglijke_gave', 'clearBurn' => false,
    ], $actionState);
    assertSkillsActions($repeatUse['status'] === 200 && count($repeatUse['state']['actionUses']) === 2,
        'Bestaand gedrag zonder action-use-limiet wijzigde');

    $idempotentState = $stateFiles[] = createSkillsActionsState($initialState);
    $idempotentPayload = [
        'idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 40,
        'actionCode' => 'psi', 'actionSubtype' => 'zintuiglijke_gave', 'clearBurn' => false,
    ];
    $idempotencyKey = 'test-action-idempotency-00000001';
    $firstIdempotentUse = runSkillsActions(
        $routes['useAction'], 'useAction', 'participant', $idempotentPayload, $idempotentState, $idempotencyKey
    );
    $replayedUse = runSkillsActions(
        $routes['useAction'], 'useAction', 'participant', $idempotentPayload, $idempotentState, $idempotencyKey
    );
    assertSkillsActions($replayedUse['status'] === 200
        && $replayedUse['decoded'] === $firstIdempotentUse['decoded']
        && count($replayedUse['state']['actionUses']) === 1,
        'Action-idempotentiereplay voerde een tweede actie uit of wijzigde de response');
    $conflictingUse = runSkillsActions(
        $routes['useAction'],
        'useAction',
        'participant',
        array_replace($idempotentPayload, ['clearBurn' => true]),
        $idempotentState,
        $idempotencyKey
    );
    assertSkillsActions($conflictingUse['status'] === 409
        && count($conflictingUse['state']['actionUses']) === 1,
        'Dezelfde action-key met een andere payload gaf geen conflict zonder tweede actie');

    $revokedStateData = $replayedUse['state'];
    $revokedStateData['characters'][1]['idUser'] = 99;
    file_put_contents($idempotentState, json_encode($revokedStateData, JSON_THROW_ON_ERROR));
    $revokedReplay = runSkillsActions(
        $routes['useAction'], 'useAction', 'participant', $idempotentPayload, $idempotentState, $idempotencyKey
    );
    assertSkillsActions($revokedReplay['status'] === 403
        && count($revokedReplay['state']['actionUses']) === 1
        && $revokedReplay['committedWrites'] === 0,
        'Ingetrokken characterrechten blokkeerden een opgeslagen actionreplay niet');

    $revealReplayState = $stateFiles[] = createSkillsActionsState($initialState);
    $revealPayload = ['idCharacter' => 1, 'idEvent' => 7, 'idSourceCharacter' => 3];
    $revealKey = 'test-reveal-idempotency-0000001';
    $firstReveal = runSkillsActions(
        $routes['reveal'], 'reveal', 'participant', $revealPayload, $revealReplayState, $revealKey
    );
    $replayedReveal = runSkillsActions(
        $routes['reveal'], 'reveal', 'participant', $revealPayload, $revealReplayState, $revealKey
    );
    assertSkillsActions($replayedReveal['status'] === 200
        && $replayedReveal['decoded'] === $firstReveal['decoded']
        && ($replayedReveal['state']['attempts']['1:7'] ?? 0) === 1,
        'Gossipreplay gebruikte een tweede poging of wijzigde de opgeslagen response');

    foreach (['reveal' => $revealPayload, 'useAction' => $idempotentPayload] as $route => $request) {
        $missingKeyState = $stateFiles[] = createSkillsActionsState($initialState);
        assertSkillsActionsError(
            runSkillsActions($routes[$route], $route, 'missing_idempotency', $request, $missingKeyState),
            400,
            0,
            "{$route} zonder idempotentiesleutel"
        );
    }
    $directorActionState = $stateFiles[] = createSkillsActionsState($initialState);
    $directorUse = runSkillsActions($routes['useAction'], 'useAction', 'director', [
        'idCharacter' => 2, 'idEvent' => 7, 'idSkill' => 40,
        'actionCode' => 'psi', 'actionSubtype' => 'zintuiglijke_gave', 'clearBurn' => false,
    ], $directorActionState);
    assertSkillsActions($directorUse['status'] === 200 && $directorUse['committedWrites'] === 5,
        'Director verloor action-schrijfrechten op een ander character');
    $administratorActionState = $stateFiles[] = createSkillsActionsState($initialState);
    $administratorSecretUse = runSkillsActions($routes['useAction'], 'useAction', 'administrator', [
        'idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 41,
        'actionCode' => 'psi', 'actionSubtype' => 'somatische_gave', 'clearBurn' => false,
    ], $administratorActionState);
    assertSkillsActions($administratorSecretUse['status'] === 200 && $administratorSecretUse['committedWrites'] === 5,
        'Administrator verloor gebruik van een geheime actionskill');

    $rejections = [
        ['updateSkill', 'participant', ['action' => 'up', 'idSkill' => 40, 'idCharacter' => 2], 403, 'skill op andermans character'],
        ['updateSkill', 'participant', ['action' => 'up', 'idSkill' => 40, 'idCharacter' => 5], 403, 'skill op eigen extra'],
        ['updateSkill', 'participant', ['action' => 'up', 'idSkill' => 41, 'idCharacter' => 1], 403, 'geheime skill participant'],
        ['addSpec', 'participant', ['idSkill' => 42, 'idCharacter' => 1, 'idSkillSpecialisation' => 101, 'name' => ''], 500, 'specialisatie van verkeerde skill'],
        ['events', 'participant', ['idCharacter' => 2], 403, 'acties van ander character'],
        ['events', 'participant', ['idCharacter' => 999], 404, 'onbekend actioncharacter'],
        ['reveal', 'participant', ['idCharacter' => 1, 'idEvent' => 999, 'idSourceCharacter' => 3], 400, 'ongeldige event/targetcombinatie'],
        ['reveal', 'participant', ['idCharacter' => 1, 'idEvent' => 7, 'idSourceCharacter' => 4], 400, 'verborgen kennistarget'],
        ['useAction', 'participant', ['idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 41, 'actionCode' => 'psi', 'actionSubtype' => 'somatische_gave'], 403, 'gebruik geheime skill'],
        ['useAction', 'participant', ['idCharacter' => 1, 'idEvent' => 999, 'idSkill' => 40, 'actionCode' => 'psi', 'actionSubtype' => 'zintuiglijke_gave'], 400, 'onbekend actionevent'],
        ['useAction', 'participant', ['idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 42, 'actionCode' => 'psi', 'actionSubtype' => 'zintuiglijke_gave'], 400, 'skill/actioncombinatie'],
    ];
    foreach ($rejections as [$route, $scenario, $request, $status, $label]) {
        $rejectState = $stateFiles[] = createSkillsActionsState($initialState);
        $rejection = runSkillsActions($routes[$route], $route, $scenario, $request, $rejectState);
        $expectedWrites = in_array($route, ['reveal', 'useAction'], true) ? 1 : 0;
        assertSkillsActionsError($rejection, $status, $expectedWrites, $label);
        if (in_array($route, ['reveal', 'useAction'], true) && $status === 400) {
            assertSkillsActions(array_keys($rejection['decoded'] ?? []) === ['error', 'details']
                && $rejection['decoded']['details'] === null,
                "{$label}: bestaand action-foutcontract met details:null wijzigde");
        }
    }

    $xpStateData = $initialState;
    $xpStateData['characters'][1]['experienceBudget'] = 6;
    $xpState = $stateFiles[] = createSkillsActionsState($xpStateData);
    $xp = runSkillsActions($routes['updateSkill'], 'updateSkill', 'participant', ['action' => 'up', 'idSkill' => 42, 'idCharacter' => 1], $xpState);
    assertSkillsActions($xp['decoded'] === ['error' => 'Onvoldoende ervaringspunten.'] && $xp['writeAttempts'] === 0, 'XP-grens wijzigde of schreef data');

    foreach ([[3, 'up', 'Maximum vaardigheidsniveau bereikt.'], [0, 'down', 'Niveau is al 0.']] as [$level, $action, $message]) {
        $levelStateData = $initialState;
        $levelStateData['skillLinks'][1][42]['level'] = $level;
        $levelState = $stateFiles[] = createSkillsActionsState($levelStateData);
        $levelResult = runSkillsActions($routes['updateSkill'], 'updateSkill', 'participant', [
            'action' => $action, 'idSkill' => 42, 'idCharacter' => 1,
        ], $levelState);
        assertSkillsActions($levelResult['decoded'] === ['error' => $message] && $levelResult['writeAttempts'] === 0,
            "Skillniveaugrens {$level}/{$action} wijzigde");
    }

    foreach (['updateSkill', 'addSpec', 'reveal', 'useAction'] as $route) {
        $request = match ($route) {
            'updateSkill' => ['action' => 'up', 'idSkill' => 42, 'idCharacter' => 1],
            'addSpec' => ['idSkill' => 42, 'idCharacter' => 1, 'idSkillSpecialisation' => 100, 'name' => ''],
            'reveal' => ['idCharacter' => 1, 'idEvent' => 7, 'idSourceCharacter' => 3],
            default => ['idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 40, 'actionCode' => 'psi', 'actionSubtype' => 'zintuiglijke_gave'],
        };
        foreach (['missing_csrf', 'invalid_csrf'] as $scenario) {
            $csrfState = $stateFiles[] = createSkillsActionsState($initialState);
            assertSkillsActionsError(runSkillsActions($routes[$route], $route, $scenario, $request, $csrfState), 403, 0, "{$route} {$scenario}");
        }
    }

    foreach (['updateSkill', 'addSpec', 'events', 'targets', 'reveal', 'useAction'] as $route) {
        $base = match ($route) {
            'updateSkill' => ['action' => 'up', 'idSkill' => 42, 'idCharacter' => 1],
            'addSpec' => ['idSkill' => 42, 'idCharacter' => 1, 'idSkillSpecialisation' => 100, 'name' => ''],
            'events' => ['idCharacter' => 1],
            'targets' => ['idCharacter' => 1, 'idEvent' => 7],
            'reveal' => ['idCharacter' => 1, 'idEvent' => 7, 'idSourceCharacter' => 3],
            default => ['idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 40, 'actionCode' => 'psi', 'actionSubtype' => 'zintuiglijke_gave'],
        };
        foreach ([['request' => $base + ['unexpected' => true], 'status' => 422, 'label' => 'onverwacht veld'], ['request' => '{}', 'status' => 422, 'label' => 'ontbrekende velden']] as $case) {
            $invalidState = $stateFiles[] = createSkillsActionsState($initialState);
            assertSkillsActionsError(runSkillsActions($routes[$route], $route, 'participant', $case['request'], $invalidState), $case['status'], 0, "{$route} {$case['label']}");
        }
        $invalidState = $stateFiles[] = createSkillsActionsState($initialState);
        assertSkillsActionsError(runSkillsActions($routes[$route], $route, 'participant', '{bad-json', $invalidState), 400, 0, "{$route} ongeldige JSON");

        $unauthenticatedState = $stateFiles[] = createSkillsActionsState($initialState);
        assertSkillsActionsError(runSkillsActions($routes[$route], $route, 'unauthenticated', $base, $unauthenticatedState), 401, 0, "{$route} niet aangemeld");
    }

    $validationCases = [
        ['updateSkill', ['action' => 'sideways', 'idSkill' => 42, 'idCharacter' => 1], 'ongeldige skillactie-enum'],
        ['addSpec', ['idSkill' => ['42'], 'idCharacter' => 1, 'idSkillSpecialisation' => 100], 'ongeldig skill-idtype'],
        ['targets', ['idCharacter' => 1, 'idEvent' => 0], 'event-id buiten grens'],
        ['useAction', ['idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 40, 'actionCode' => 'psi', 'actionSubtype' => str_repeat('x', 65)], 'te lange actionsubtype'],
        ['useAction', ['idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 40, 'actionCode' => 'sql_column', 'actionSubtype' => 'zintuiglijke_gave'], 'gemanipuleerde actioncode'],
    ];
    foreach ($validationCases as [$route, $request, $label]) {
        $validationState = $stateFiles[] = createSkillsActionsState($initialState);
        assertSkillsActionsError(runSkillsActions($routes[$route], $route, 'participant', $request, $validationState), 422, 0, $label);
    }

    $rollbackCases = [
        ['updateSkill', 'skill_delete_error', ['action' => 'delete', 'idSkill' => 42, 'idCharacter' => 1], 2],
        ['addSpec', 'specialisation_link_error', ['idSkill' => 42, 'idCharacter' => 1, 'idSkillSpecialisation' => 0, 'name' => 'Rollback spec'], 2],
        ['reveal', 'knowledge_unlock_error', ['idCharacter' => 1, 'idEvent' => 7, 'idSourceCharacter' => 3], 3],
        ['useAction', 'action_use_error', ['idCharacter' => 1, 'idEvent' => 7, 'idSkill' => 40, 'actionCode' => 'psi', 'actionSubtype' => 'zintuiglijke_gave'], 4],
    ];
    foreach ($rollbackCases as [$route, $scenario, $request, $writeAttempts]) {
        $rollbackState = $stateFiles[] = createSkillsActionsState($initialState);
        $result = runSkillsActions($routes[$route], $route, $scenario, $request, $rollbackState);
        assertSkillsActionsError($result, 500, $writeAttempts, "{$route} rollback");
        assertSkillsActions(!str_contains($result['body'], 'SQLSTATE') && !str_contains($result['body'], 'secret_'), "{$route}: technische details lekten");
        assertSkillsActions($result['state'] === $initialState, "{$route}: rollback herstelde de volledige state niet");
        $expectedMessage = match ($route) {
            'updateSkill' => 'Fout bij updaten van skill.',
            'addSpec' => 'Kon specialisatie niet opslaan.',
            'reveal' => 'Kon de wereldwijsroddels niet vrijspelen.',
            default => 'Kon deze vaardigheidsactie niet registreren.',
        };
        assertSkillsActions($result['decoded'] === ['error' => $expectedMessage], "{$route}: generieke 500-response wijzigde");
    }

    $serverState = $stateFiles[] = createSkillsActionsState($initialState);
    $serverError = runSkillsActions($routes['events'], 'events', 'server_error', ['idCharacter' => 1], $serverState);
    assertSkillsActionsError($serverError, 500, 0, 'Actioncatalogus serverfout');
    assertSkillsActions($serverError['decoded'] === ['error' => 'Kon de actiedata niet laden.'], 'Generieke action-500 wijzigde');
    assertSkillsActions(!str_contains($serverError['body'], 'SQLSTATE') && !str_contains($serverError['body'], 'secret_'), 'Action-500 lekte technische details');
} finally {
    foreach ($stateFiles as $stateFile) {
        if (is_file($stateFile)) {
            unlink($stateFile);
        }
    }
    removeSkillsActionsTree($fixtureRoot);
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Character skill/action endpoint tests passed." . PHP_EOL;
