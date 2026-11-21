
<?php

// ============================================
// SEASONS - Select Best XI and Individual Awards
// ============================================

function calculateSeasonAwards(mysqli $con): array
{
    $result = $con->query("
        SELECT id, position, rating, matches, goals, assists
        FROM players
    ");

    $players = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

    // ============================================
    // SEASONS - Ranking by rating and matches
    // ============================================

    $byRating = static function (array $a, array $b): int {
        return ((float)$b['rating'] <=> (float)$a['rating'])
            ?: ((int)$b['matches'] <=> (int)$a['matches'])
            ?: ((int)$a['id'] <=> (int)$b['id']);
    };

    $byGoals = static function (array $a, array $b) use ($byRating): int {
        return ((int)$b['goals'] <=> (int)$a['goals'])
            ?: $byRating($a, $b);
    };

    $byAssists = static function (array $a, array $b) use ($byRating): int {
        return ((int)$b['assists'] <=> (int)$a['assists'])
            ?: $byRating($a, $b);
    };

    // ============================================
    // SEASONS - Best XI position groups
    // ============================================

    $positions = [
        'gk_id'  => 'GK',
        'rb_id'  => 'RB',
        'lb_id'  => 'LB',
        'cb1_id' => 'CB',
        'cb2_id' => 'CB',
        'cdm_id' => 'CDM',
        'cm1_id' => 'CM',
        'cm2_id' => 'CM',
        'rw_id'  => 'RW',
        'lw_id'  => 'LW',
        'st_id'  => 'ST'
    ];

    $groups = [];

    foreach ($players as $player) {
        if ((float)$player['rating'] <= 0) {
            continue;
        }

        $position = $player['position'];

        // CAM players are eligible for CM.
        if ($position === 'CAM') {
            $position = 'CM';
        }

        $groups[$position][] = $player;
    }

    foreach ($groups as &$group) {
        usort($group, $byRating);
    }
    unset($group);

    $positionIndexes = [];
    $awards = [];

    foreach ($positions as $column => $position) {
        $index = $positionIndexes[$position] ?? 0;

        $awards[$column] = isset($groups[$position][$index])
            ? (int)$groups[$position][$index]['id']
            : null;

        $positionIndexes[$position] = $index + 1;
    }

    // ============================================
    // SEASONS - Individual Awards
    // ============================================

    $ratedPlayers = array_values(array_filter(
        $players,
        static fn(array $p): bool => (float)$p['rating'] > 0
    ));
    usort($ratedPlayers, $byRating);

    $scorers = array_values(array_filter(
        $players,
        static fn(array $p): bool => (int)$p['goals'] > 0
    ));
    usort($scorers, $byGoals);

    $assisters = array_values(array_filter(
        $players,
        static fn(array $p): bool => (int)$p['assists'] > 0
    ));
    usort($assisters, $byAssists);

    $awards['top_scorer_id'] = isset($scorers[0])
        ? (int)$scorers[0]['id']
        : null;

    $awards['top_assister_id'] = isset($assisters[0])
        ? (int)$assisters[0]['id']
        : null;

    $awards['best_player_id'] = isset($ratedPlayers[0])
        ? (int)$ratedPlayers[0]['id']
        : null;

    return $awards;
}

// ============================================
// SEASONS - Save Awards Within Transaction
// ============================================

function saveSeasonAwards(
    mysqli $con,
    int $season
): void {
    $awards = calculateSeasonAwards($con);

    // Lock the existing season record, if present.
    $result = $con->execute_query(
        "SELECT * FROM seasons
         WHERE season_id = ?
         FOR UPDATE",
        [$season]
    );

    $existing = $result->fetch_assoc();
    $result->free();

    $columns = array_keys($awards);

    // Prevent overwriting already stored results.
    if ($existing !== null) {
        foreach ($columns as $column) {
            if ($existing[$column] !== null) {
                throw new RuntimeException(
                    'Season awards are already recorded.'
                );
            }
        }
    }

    $setClause = implode(
        ', ',
        array_map(
            static fn(string $column): string => "$column = ?",
            $columns
        )
    );

    if ($existing !== null) {
        $con->execute_query(
            "UPDATE seasons
             SET $setClause
             WHERE season_id = ?",
            [...array_values($awards), $season]
        );
    } else {
        $columnNames = implode(', ', ['season_id', ...$columns]);
        $placeholders = implode(
            ', ',
            array_fill(0, count($columns) + 1, '?')
        );

        $con->execute_query(
            "INSERT INTO seasons ($columnNames)
             VALUES ($placeholders)",
            [$season, ...array_values($awards)]
        );
    }
}
