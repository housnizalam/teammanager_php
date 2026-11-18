
<?php

// ============================================
// TOURNAMENT AWARDS - Validate selected players
// ============================================

function validateTournamentPlayerAwards(
    mysqli $con,
    array $teamIds
): array {
    $fields = [
        'best_player_id',
        'top_scorer_id',
        'top_assister_id'
    ];

    $selected = [];

    // ============================================
    // TOURNAMENT AWARDS - Validate submitted IDs
    // ============================================

    foreach ($fields as $field) {
        $value = $_POST[$field] ?? null;

        if (!is_string($value) && !is_int($value)) {
            throw new RuntimeException(
                'All three player awards must be selected.'
            );
        }

        $playerId = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($playerId === false) {
            throw new RuntimeException(
                "Invalid player selection: {$field}."
            );
        }

        $selected[$field] = $playerId;
    }

    // ============================================
    // TOURNAMENT AWARDS - Validate participating teams
    // ============================================

    $teamIds = array_values(array_unique(
        array_map('intval', $teamIds)
    ));

    if (empty($teamIds) || min($teamIds) <= 0) {
        throw new RuntimeException(
            'Invalid tournament participants.'
        );
    }

    // Same player may win multiple awards.
    $playerIds = array_values(array_unique(
        array_values($selected)
    ));

    $playerPlaceholders = implode(
        ',',
        array_fill(0, count($playerIds), '?')
    );

    $teamPlaceholders = implode(
        ',',
        array_fill(0, count($teamIds), '?')
    );

    // ============================================
    // TOURNAMENT AWARDS - Check player membership
    // ============================================

    $sql = "
        SELECT p.id
        FROM players AS p
        WHERE p.id IN ($playerPlaceholders)
          AND p.team_id IN ($teamPlaceholders)
        FOR UPDATE
    ";

    $result = $con->execute_query(
        $sql,
        array_merge($playerIds, $teamIds)
    );

    $validIds = array_map(
        'intval',
        array_column(
            $result->fetch_all(MYSQLI_ASSOC),
            'id'
        )
    );

    $result->free();

    if (count($validIds) !== count($playerIds)) {
        throw new RuntimeException(
            'One or more selected players do not belong '
            . 'to participating teams.'
        );
    }

    return $selected;
}
