
<?php

require_once __DIR__ . '/champions_qualification.php';

// ============================================
// EUROPA - Domestic League Qualification Candidates
// ============================================

function getEuropaDomesticCandidates(int $currentSeason): array
{
    if ($currentSeason <= 1) {
        return [];
    }

    // ============================================
    // EUROPA - Load Previous Domestic Standings
    // ============================================

    $standings = loadPreviousLeagueStandings(
        $currentSeason
    );

    // ============================================
    // EUROPA - Load Current Champions League Pots
    // ============================================

    $championsProgress = loadTournamentProgress(
        $currentSeason,
        'champions'
    );

    $championsPots = $championsProgress['pots'] ?? null;

    // Wait until Champions League pots are saved.
    if (!is_array($championsPots)) {
        return [];
    }

    $championsTeamIds = [];

    for ($pot = 1; $pot <= 4; $pot++) {
        $ids = $championsPots[(string)$pot] ?? null;

        if (
            !is_array($ids) ||
            count($ids) !== 8
        ) {
            throw new RuntimeException(
                "Invalid Champions League Pot {$pot}."
            );
        }

        foreach ($ids as $teamId) {
            if (
                !is_int($teamId) ||
                $teamId <= 0 ||
                isset($championsTeamIds[$teamId])
            ) {
                throw new RuntimeException(
                    'Invalid or duplicated Champions League team.'
                );
            }

            $championsTeamIds[$teamId] = true;
        }
    }

    // ============================================
    // EUROPA - Best Non-Champions Team Per League
    // ============================================

    $candidates = [];

    foreach ($standings as $league => $teamIds) {
        $selectedTeamId = null;

        foreach ($teamIds as $teamId) {
            if (!isset($championsTeamIds[$teamId])) {
                $selectedTeamId = $teamId;
                break;
            }
        }

        if ($selectedTeamId === null) {
            throw new RuntimeException(
                "No eligible Europa League team in {$league}."
            );
        }

        $candidates[$league] = $selectedTeamId;
    }

    if (count($candidates) !== 9) {
        throw new RuntimeException(
            'Expected nine domestic league candidates.'
        );
    }

    return $candidates;
}
