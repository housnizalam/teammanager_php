
<?php
require_once __DIR__ . '/tournament_storage.php';

// ============================================
// CHAMPIONS - Calculate League Qualification Quotas
// ============================================

function calculateChampionsLeagueQuotas(
    array $roundOf16,
    array $quarterFinalists,
    array $semiFinalists,
    int $championId,
    array $europaFinalists,
    array $teamLeagueById,
    array $leagueNames
): array {

    // ============================================
    // CHAMPIONS - Validate Previous Season Results
    // ============================================

    if (
        count($leagueNames) !== 9 ||
        count(array_unique($leagueNames)) !== 9 ||
        count($roundOf16) !== 16 ||
        count($quarterFinalists) !== 8 ||
        count($semiFinalists) !== 4 ||
        count($europaFinalists) !== 2
    ) {
        throw new RuntimeException(
            'Invalid previous season qualification data.'
        );
    }

    foreach (
        [
            $roundOf16,
            $quarterFinalists,
            $semiFinalists,
            $europaFinalists
        ] as $teams
    ) {
        if (count(array_unique($teams)) !== count($teams)) {
            throw new RuntimeException('Duplicate team in results.');
        }
    }

    if (
        array_diff($quarterFinalists, $roundOf16) ||
        array_diff($semiFinalists, $quarterFinalists) ||
        !in_array($championId, $semiFinalists, true)
    ) {
        throw new RuntimeException(
            'Inconsistent Champions League results.'
        );
    }

    // ============================================
    // CHAMPIONS - Initialize League Quotas
    // ============================================

    $quotas = [];

    foreach ($leagueNames as $league) {
        $quotas[$league] = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0
        ];
    }

    // Resolve the league of a team.
    $getLeague = static function (int $teamId)
    use ($teamLeagueById, $quotas): string {

        $league = $teamLeagueById[$teamId] ?? null;

        if (!is_string($league) || !isset($quotas[$league])) {
            throw new RuntimeException(
                "Unknown league for team {$teamId}."
            );
        }

        return $league;
    };

    // ============================================
    // CHAMPIONS - Pot 1: Quarter-finalists
    // ============================================

    foreach ($quarterFinalists as $teamId) {
        $quotas[$getLeague($teamId)][1]++;
    }

    // ============================================
    // CHAMPIONS - Pot 2: Eliminated in Round of 16
    // ============================================

    $eliminatedR16 = array_diff(
        $roundOf16,
        $quarterFinalists
    );

    foreach ($eliminatedR16 as $teamId) {
        $quotas[$getLeague($teamId)][2]++;
    }

    // ============================================
    // CHAMPIONS - Pot 3: Seven Regular Seats
    // ============================================

    // Four semi-finalists.
    foreach ($semiFinalists as $teamId) {
        $quotas[$getLeague($teamId)][3]++;
    }

    // One additional seat for Champions League winner.
    $championLeague = $getLeague($championId);
    $quotas[$championLeague][3]++;

    // Two seats for Europa League finalists.
    foreach ($europaFinalists as $teamId) {
        $quotas[$getLeague($teamId)][3]++;
    }

    // ============================================
    // CHAMPIONS - Pot 4: All Leagues Except Winner's
    // ============================================

    foreach ($leagueNames as $league) {
        if ($league !== $championLeague) {
            $quotas[$league][4] = 1;
        }
    }

    return [
        'quotas' => $quotas,
        'champion_league' => $championLeague,
        'wildcard_pot' => 3
    ];
}


// ============================================
// CHAMPIONS - Load Previous Season Results
// ============================================

function loadPreviousChampionsResults(int $currentSeason): array
{
    if ($currentSeason <= 1) {
        throw new RuntimeException(
            'No previous season is available.'
        );
    }

    $previousSeason = $currentSeason - 1;

    $progress = loadTournamentProgress(
        $previousSeason,
        'champions'
    );

    // ============================================
    // CHAMPIONS - Validate Previous Tournament
    // ============================================

    if (($progress['stage'] ?? null) !== 'group_stage') {
        throw new RuntimeException(
            'Previous Champions League data is missing.'
        );
    }

    $rounds = [
        'round_of_16'   => 16,
        'quarter_finals' => 8,
        'semi_finals'    => 4,
        'finalists'      => 2
    ];

    $results = [];

    foreach ($rounds as $round => $expectedCount) {
        $slots = $progress[$round] ?? null;

        if (
            !is_array($slots) ||
            count($slots) !== $expectedCount
        ) {
            throw new RuntimeException(
                "Invalid previous tournament round: {$round}"
            );
        }

        $ids = array_values($slots);

        foreach ($ids as $id) {
            if (!is_int($id) || $id <= 0) {
                throw new RuntimeException(
                    "Invalid team ID in {$round}."
                );
            }
        }

        if (count(array_unique($ids)) !== $expectedCount) {
            throw new RuntimeException(
                "Duplicate teams in {$round}."
            );
        }

        $results[$round] = $ids;
    }

    // ============================================
    // CHAMPIONS - Verify Qualification Progression
    // ============================================

    $progression = [
        ['round_of_16', 'quarter_finals'],
        ['quarter_finals', 'semi_finals'],
        ['semi_finals', 'finalists']
    ];

    foreach ($progression as [$previous, $next]) {
        if (array_diff($results[$next], $results[$previous])) {
            throw new RuntimeException(
                "Inconsistent progression: {$next}."
            );
        }
    }

    $winner = $progress['winner'] ?? null;

    if (
        !is_int($winner) ||
        !in_array($winner, $results['finalists'], true)
    ) {
        throw new RuntimeException(
            'Invalid previous Champions League winner.'
        );
    }

    // ============================================
    // CHAMPIONS - Calculate Eliminated R16 Teams
    // ============================================

    $eliminatedR16 = array_values(array_diff(
        $results['round_of_16'],
        $results['quarter_finals']
    ));

    return [
        'previous_season' => $previousSeason,
        'round_of_16' => $results['round_of_16'],
        'quarter_finalists' => $results['quarter_finals'],
        'eliminated_r16' => $eliminatedR16,
        'semi_finalists' => $results['semi_finals'],
        'finalists' => $results['finalists'],
        'champion_id' => $winner
    ];
}


// ============================================
// CHAMPIONS - Load Previous Season League Standings
// ============================================

function loadPreviousLeagueStandings(int $currentSeason): array
{
    if ($currentSeason <= 1) {
        throw new RuntimeException(
            'No previous season is available.'
        );
    }

    $previousSeason = $currentSeason - 1;

    // ============================================
    // CHAMPIONS - Supported Domestic Leagues
    // ============================================

    $competitions = [
        'league_england',
        'league_spain',
        'league_italy',
        'league_germany',
        'league_france',
        'league_portugal',
        'league_netherlands',
        'league_turkey',
        'league_rest'
    ];

    $standings = [];
    $allTeamIds = [];

    // ============================================
    // CHAMPIONS - Read Saved League Rankings
    // ============================================

    foreach ($competitions as $competition) {

        $progress = loadTournamentProgress(
            $previousSeason,
            $competition
        );

        $teamIds = $progress['team_ids'] ?? null;

        if (
            !is_array($teamIds) ||
            !array_is_list($teamIds) ||
            empty($teamIds)
        ) {
            throw new RuntimeException(
                "Missing or invalid standings: {$competition}"
            );
        }

        foreach ($teamIds as $teamId) {

            if (!is_int($teamId) || $teamId <= 0) {
                throw new RuntimeException(
                    "Invalid team ID in {$competition}."
                );
            }

            if (isset($allTeamIds[$teamId])) {
                throw new RuntimeException(
                    "Duplicate team across leagues: {$teamId}"
                );
            }

            $allTeamIds[$teamId] = true;
        }

        // Array order represents final league positions.
        $standings[$competition] = $teamIds;
    }

    return $standings;
}


// ============================================
// CHAMPIONS - Map Teams to Domestic Leagues
// ============================================

function buildChampionsTeamLeagueMap(array $standings): array
{
    $teamLeagueById = [];

    foreach ($standings as $competition => $teamIds) {

        if (!is_string($competition) || !is_array($teamIds)) {
            throw new RuntimeException(
                'Invalid domestic league standings.'
            );
        }

        foreach ($teamIds as $teamId) {

            if (!is_int($teamId) || $teamId <= 0) {
                throw new RuntimeException(
                    'Invalid team ID in league standings.'
                );
            }

            if (isset($teamLeagueById[$teamId])) {
                throw new RuntimeException(
                    "Team {$teamId} belongs to multiple leagues."
                );
            }

            $teamLeagueById[$teamId] = $competition;
        }
    }

    return $teamLeagueById;
}



// ============================================
// CHAMPIONS - Load Previous Europa League Finalists
// ============================================

function loadPreviousEuropaFinalists(int $currentSeason): array
{
    if ($currentSeason <= 1) {
        throw new RuntimeException(
            'No previous season is available.'
        );
    }

    $previousSeason = $currentSeason - 1;

    $progress = loadTournamentProgress(
        $previousSeason,
        'europa'
    );

    // ============================================
    // CHAMPIONS - Validate Europa League Results
    // ============================================

    if (($progress['stage'] ?? null) !== 'knockout') {
        throw new RuntimeException(
            'Previous Europa League data is missing.'
        );
    }

    $finalists = $progress['finalists'] ?? null;

    if (
        !is_array($finalists) ||
        count($finalists) !== 2 ||
        !isset($finalists['F1'], $finalists['F2'])
    ) {
        throw new RuntimeException(
            'Invalid previous Europa League finalists.'
        );
    }

    $teamIds = [
        $finalists['F1'],
        $finalists['F2']
    ];

    if (
        !is_int($teamIds[0]) ||
        !is_int($teamIds[1]) ||
        $teamIds[0] <= 0 ||
        $teamIds[1] <= 0 ||
        $teamIds[0] === $teamIds[1]
    ) {
        throw new RuntimeException(
            'Invalid Europa League finalist teams.'
        );
    }

    $winner = $progress['winner'] ?? null;

    if (
        !is_int($winner) ||
        !in_array($winner, $teamIds, true)
    ) {
        throw new RuntimeException(
            'Invalid previous Europa League winner.'
        );
    }

    return $teamIds;
}


 // ============================================
 // CHAMPIONS - Build Qualification Quotas
 // ============================================

function buildChampionsQualificationQuotas(
    int $currentSeason
): array {

    // ============================================
    // CHAMPIONS - Load Previous Season Data
    // ============================================

    $champions = loadPreviousChampionsResults(
        $currentSeason
    );

    $standings = loadPreviousLeagueStandings(
        $currentSeason
    );

    $europaFinalists = loadPreviousEuropaFinalists(
        $currentSeason
    );

    // ============================================
    // CHAMPIONS - Build League Mapping
    // ============================================

    $teamLeagueById = buildChampionsTeamLeagueMap(
        $standings
    );

    $leagueNames = array_keys($standings);

    // ============================================
    // CHAMPIONS - Calculate League Seat Distribution
    // ============================================

    $distribution = calculateChampionsLeagueQuotas(
        $champions['round_of_16'],
        $champions['quarter_finalists'],
        $champions['semi_finalists'],
        $champions['champion_id'],
        $europaFinalists,
        $teamLeagueById,
        $leagueNames
    );

    // ============================================
    // CHAMPIONS - Validate Total Seats
    // ============================================

    $totals = [
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 0
    ];

    foreach ($distribution['quotas'] as $leagueQuotas) {
        foreach ($totals as $pot => $_) {
            $totals[$pot] += $leagueQuotas[$pot];
        }
    }

    if (
        $totals[1] !== 8 ||
        $totals[2] !== 8 ||
        $totals[3] !== 7 ||
        $totals[4] !== 8
    ) {
        throw new RuntimeException(
            'Invalid Champions League seat distribution.'
        );
    }

    // ============================================
    // CHAMPIONS - Return Qualification Data
    // ============================================

    return [
        'previous_season' => $currentSeason - 1,
        'quotas' => $distribution['quotas'],
        'standings' => $standings,
        'champion_league' => $distribution['champion_league'],
        'wildcard_pot' => 3,
        'seat_totals' => $totals
    ];
}


/* ============================================
   CHAMPIONS - Build Qualified Teams
   ============================================ */

function buildChampionsQualifiedTeams(
    mysqli $con,
    int $currentSeason
): array {

    // ============================================
    // CHAMPIONS - Load Qualification Quotas
    // ============================================

    $qualification = buildChampionsQualificationQuotas(
        $currentSeason
    );

    $quotas = $qualification['quotas'];
    $standings = $qualification['standings'];

    $pots = [
        1 => [],
        2 => [],
        3 => [],
        4 => []
    ];

    $qualifiedTeamIds = [];

    // ============================================
    // CHAMPIONS - Assign 31 Regular Seats
    // ============================================

    foreach ($standings as $league => $teamIds) {

        $nextTeamIndex = 0;

        // Higher-ranked domestic teams enter higher pots.
        for ($pot = 1; $pot <= 4; $pot++) {

            $seats = $quotas[$league][$pot] ?? 0;

            if (!is_int($seats) || $seats < 0) {
                throw new RuntimeException(
                    "Invalid quota for {$league}, Pot {$pot}."
                );
            }

            for ($seat = 0; $seat < $seats; $seat++) {

                if (!isset($teamIds[$nextTeamIndex])) {
                    throw new RuntimeException(
                        "Not enough teams in {$league}."
                    );
                }

                $teamId = $teamIds[$nextTeamIndex];
                $nextTeamIndex++;

                if (isset($qualifiedTeamIds[$teamId])) {
                    throw new RuntimeException(
                        "Team {$teamId} qualified more than once."
                    );
                }

                $qualifiedTeamIds[$teamId] = true;
                $pots[$pot][] = $teamId;
            }
        }
    }

    // ============================================
    // CHAMPIONS - Validate Regular Seats
    // ============================================

    if (
        count($pots[1]) !== 8 ||
        count($pots[2]) !== 8 ||
        count($pots[3]) !== 7 ||
        count($pots[4]) !== 8 ||
        count($qualifiedTeamIds) !== 31
    ) {
        throw new RuntimeException(
            'Invalid regular Champions League qualification.'
        );
    }

    // ============================================
    // CHAMPIONS - Select Historical Wildcard
    // ============================================

    // Only active teams with archived statistics
    // are eligible for the historical wildcard.

    $result = $con->query("
        SELECT ta.team_id, ta.points
        FROM teams_archive AS ta
        INNER JOIN teams AS t
            ON t.id = ta.team_id
        ORDER BY ta.points DESC, ta.team_id ASC
    ");

    $wildcardTeamId = null;

    while ($team = $result->fetch_assoc()) {

        $teamId = (int)$team['team_id'];

        if (!isset($qualifiedTeamIds[$teamId])) {
            $wildcardTeamId = $teamId;
            break;
        }
    }

    $result->free();

    if ($wildcardTeamId === null) {
        throw new RuntimeException(
            'No eligible historical wildcard team found.'
        );
    }

    $pots[3][] = $wildcardTeamId;
    $qualifiedTeamIds[$wildcardTeamId] = true;

    // ============================================
    // CHAMPIONS - Validate All Four Pots
    // ============================================

    foreach ($pots as $pot => $teamIds) {

        if (count($teamIds) !== 8) {
            throw new RuntimeException(
                "Pot {$pot} does not contain 8 teams."
            );
        }
    }

    if (count($qualifiedTeamIds) !== 32) {
        throw new RuntimeException(
            'Champions League must contain 32 unique teams.'
        );
    }

    return [
        'season' => $currentSeason,
        'previous_season' => $currentSeason - 1,
        'pots' => $pots,
        'wildcard_team_id' => $wildcardTeamId,
        'quotas' => $quotas
    ];
}
