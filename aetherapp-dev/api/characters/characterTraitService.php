<?php
declare(strict_types=1);

require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/traitUtils.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterTraitRepository.php';

final class AetherCharacterTraitException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message) { parent::__construct($message); }
    public function getHttpStatus(): int { return $this->httpStatus; }
}

/** @param array<string, mixed> $trait */
function aetherCanCurrentUserManageTrait(array $trait, string $role): bool
{
    return isPrivilegedUserRole($role) || !traitHasFlag($trait, 'secret');
}

/** @return array<string, string> */
function aetherUpdateCharacterTrait(PDO $pdo, array $currentUser, int $characterId, int $traitId, int $currentTraitId, string $action): array
{
    $character = aetherFetchTraitCharacter($pdo, $characterId);
    if ($character === null) {
        throw new AetherCharacterTraitException(404, 'Personage niet gevonden.');
    }
    if (!aetherCanEditDraftCharacter($currentUser, $character)) {
        throw new AetherCharacterTraitException(403, 'Je hebt geen rechten om traits van dit personage aan te passen.');
    }

    $role = (string) $currentUser['role'];
    $canOverspend = isPrivilegedUserRole($role);
    $trait = getTraitDefinition($pdo, $traitId);
    if ($trait === null) {
        throw new AetherCharacterTraitException(404, 'Trait niet gevonden.');
    }
    if (!aetherCanCurrentUserManageTrait($trait, $role)) {
        throw new AetherCharacterTraitException(403, 'Je hebt geen rechten om deze trait aan te passen.');
    }
    if ((string) $trait['class'] !== 'all' && (string) $trait['class'] !== (string) $character['class']) {
        throw new AetherCharacterTraitException(400, 'Deze trait is niet beschikbaar voor deze klasse.');
    }

    $currentLink = null;
    $currentTrait = null;
    if ($currentTraitId > 0) {
        $currentLink = aetherFetchCharacterTraitLink($pdo, $characterId, $currentTraitId);
        if ($currentLink !== null) {
            $currentTrait = getTraitDefinition($pdo, $currentTraitId);
            if ($currentTrait !== null && !aetherCanCurrentUserManageTrait($currentTrait, $role)) {
                throw new AetherCharacterTraitException(403, 'Je hebt geen rechten om deze trait aan te passen.');
            }
        }
    }

    $link = aetherFetchCharacterTraitLink($pdo, $characterId, $traitId);
    $rankType = (string) $trait['rankType'];
    $currentRank = $link !== null ? (int) $link['rankValue'] : 0;
    if ($link !== null && isCompanyShareTrait($trait)) {
        $currentRank = getCompanyShareBaseRank([
            'name' => (string) ($trait['name'] ?? ''), 'baseRank' => $currentRank,
            'isCompanyShare' => true, 'shareDraftStep' => $trait['shareDraftStep'] ?? null,
        ]);
    }
    $points = getCharacterPointSummary($pdo, [
        'id' => $character['id'], 'type' => $character['type'], 'idUser' => $character['idUser'],
        'experienceToTrait' => $character['experienceToTrait'],
    ]);
    $currentCost = $link !== null ? calculateTraitPointCost($trait, $currentRank) : 0;

    if ($action === 'add') {
        if ($link !== null) return ['error' => 'Trait is al gekoppeld.'];
        if (isGroupedTrait($trait)) {
            foreach (getCharacterTraitLinks($pdo, $characterId, [(string) ($trait['type'] ?? '')]) as $candidate) {
                if (areTraitsInSameSelectionGroup($candidate, $trait)) {
                    return ['error' => 'Je kan maar 1 trait kiezen binnen deze categorie.'];
                }
            }
        }
        if ((int) $trait['isUnique'] === 1 && (string) $trait['type'] === 'profession'
            && aetherCharacterHasProfessionTrait($pdo, $characterId)) {
            return ['error' => 'Er is al een beroep gekoppeld aan dit personage.'];
        }
        $newRank = isCompanyShareTrait($trait) ? getCompanyShareDraftStep($trait) : 1;
        if (!$canOverspend && calculateTraitPointCost($trait, $newRank) > $points['availableStatusPoints']) {
            return ['error' => 'Onvoldoende statuspunten.'];
        }
        aetherInsertCharacterTraitLink($pdo, $characterId, $traitId, $newRank);
        return ['status' => 'ok'];
    }

    if ($action === 'change') {
        if ($currentLink === null || $currentTrait === null) return ['error' => 'Bestaande trait-link niet gevonden.'];
        if ($link !== null && $currentTraitId !== $traitId) return ['error' => 'Deze trait is al gekoppeld.'];
        if (!isGroupedTrait($currentTrait) || !isGroupedTrait($trait)) return ['error' => 'Alleen gegroepeerde traits kunnen gewisseld worden.'];
        if (!areTraitsInSameSelectionGroup($currentTrait, $trait)) return ['error' => 'Je kan alleen wisselen binnen dezelfde traitgroep.'];
        if ($currentTraitId === $traitId) return ['status' => 'ok'];
        $rank = (int) $currentLink['rankValue'];
        if (isCompanyShareTrait($currentTrait)) {
            $rank = getCompanyShareBaseRank([
                'name' => (string) ($currentTrait['name'] ?? ''), 'baseRank' => $rank,
                'isCompanyShare' => true, 'shareDraftStep' => $currentTrait['shareDraftStep'] ?? null,
            ]);
        }
        $delta = calculateTraitPointCost($trait, $rank) - calculateTraitPointCost($currentTrait, $rank);
        if (!$canOverspend && $delta > $points['availableStatusPoints']) return ['error' => 'Onvoldoende statuspunten voor deze wijziging.'];
        aetherChangeCharacterTraitLink($pdo, (int) $currentLink['id'], $traitId);
        return ['status' => 'ok'];
    }

    if ($action === 'remove') {
        if ($link === null) return ['error' => 'Trait-link niet gevonden.'];
        aetherDeleteCharacterTraitLink($pdo, (int) $link['id']);
        return ['status' => 'ok'];
    }

    if ($link === null) return ['error' => 'Trait-link niet gevonden.'];
    if ($rankType === 'singular') return ['error' => 'Deze trait heeft geen rang.'];
    if (isCompanyShareTrait($trait) && (string) ($character['state'] ?? '') !== 'draft') {
        return ['error' => 'Aandelen verhoog je na draft via de economie-tab.'];
    }
    $step = isCompanyShareTrait($trait) ? getCompanyShareDraftStep($trait) : 1;
    if ($action === 'rank_up') {
        $newRank = $currentRank + $step;
    } else {
        $minimum = isCompanyShareTrait($trait) ? getCompanyShareDraftStep($trait) : 1;
        if ($rankType === 'range_positive' && $currentRank <= $minimum) {
            return ['error' => 'De rang kan niet lager dan ' . (isCompanyShareTrait($trait) ? (string) $minimum . '%.' : '1.')];
        }
        $newRank = $currentRank - $step;
    }
    if (!$canOverspend && calculateTraitPointCost($trait, $newRank) - $currentCost > $points['availableStatusPoints']) {
        return ['error' => 'Onvoldoende statuspunten.'];
    }

    if (isCompanyShareTrait($trait) && $action === 'rank_up') {
        try {
            $shareLink = aetherFetchCharacterTraitCompanyLink($pdo, (int) $link['id']);
            $companyId = (int) ($shareLink['idCompany'] ?? 0);
            if ($companyId > 0) {
                $allocated = 0;
                foreach (aetherFetchCompanyTraitShareLinks($pdo, $companyId) as $row) {
                    $other = getTraitDefinition($pdo, (int) ($row['idTrait'] ?? 0));
                    if ($other === null || !isCompanyShareTrait($other) || (int) ($row['id'] ?? 0) === (int) $link['id']) continue;
                    $allocated += max(0, (int) ($row['rankValue'] ?? 0) + (int) ($row['extraPercentage'] ?? 0));
                }
                if ($allocated + $newRank + (int) ($shareLink['extraPercentage'] ?? 0) > 100) {
                    return ['error' => 'Dit bedrijf heeft niet genoeg vrije aandelen voor deze verhoging.'];
                }
            }
        } catch (Throwable $shareCapacityException) {
            // Preserve support for installations where share-link storage is not migrated yet.
        }
    }

    aetherUpdateCharacterTraitRank($pdo, (int) $link['id'], $newRank);
    return ['status' => 'ok'];
}
