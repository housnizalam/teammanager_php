
<?php

// ============================================
// RATING FIX - Required competitions
// ============================================

function ratingFixCompetitions(): array
{
    return [
        'league_england',
        'league_spain',
        'league_italy',
        'league_germany',
        'league_france',
        'league_portugal',
        'league_netherlands',
        'league_turkey',
        'league_rest',
        'champions',
        'europa'
    ];
}

// ============================================
// RATING FIX - Check all tournaments
// ============================================

function ratingFixAllCompleted(
    mysqli $con,
    int $season,
    bool $lock = false
): bool {
    $competitions = ratingFixCompetitions();

    $placeholders = implode(
        ', ',
        array_fill(0, count($competitions), '?')
    );

    $sql = "
        SELECT competition, is_played
        FROM tournaments
        WHERE season = ?
          AND competition IN ($placeholders)
    ";

    if ($lock) {
        $sql .= ' FOR UPDATE';
    }

    $result = $con->execute_query(
        $sql,
        array_merge([$season], $competitions)
    );

    $completed = [];

    while ($row = $result->fetch_assoc()) {
        if ((int)$row['is_played'] === 1) {
            $completed[$row['competition']] = true;
        }
    }

    $result->free();

    // All eleven records must exist and be completed.
    foreach ($competitions as $competition) {
        if (!isset($completed[$competition])) {
            return false;
        }
    }

    return true;
}

// ============================================
// RATING FIX - Check previous application
// ============================================

function ratingFixAlreadyApplied(
    mysqli $con,
    int $season,
    bool $lock = false
): bool {
    $sql = "
        SELECT is_played
        FROM tournaments
        WHERE season = ?
          AND competition = 'rating_fix'
    ";

    if ($lock) {
        $sql .= ' FOR UPDATE';
    }

    $result = $con->execute_query($sql, [$season]);
    $row = $result->fetch_assoc();
    $result->free();

    return $row !== null
        && (int)$row['is_played'] === 1;
}
