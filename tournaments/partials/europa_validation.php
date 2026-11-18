<?php
// ============================================
// EUROPA - Shared validation of knockout progress
// Used by save_europa.php and finalize_europa.php.
// ============================================

function europaRequireSlotMap(mixed $value, array $validKeys, string $name): array
{
    if (!is_array($value)) {
        throw new RuntimeException("Invalid {$name} data.");
    }
    if (count($value) > count($validKeys)) {
        throw new RuntimeException("Too many {$name} entries.");
    }
    foreach ($value as $slot => $teamId) {
        if (!in_array((string) $slot, $validKeys, true)) {
            throw new RuntimeException("Unknown {$name} slot.");
        }
        if (!is_int($teamId) || $teamId <= 0) {
            throw new RuntimeException("Invalid {$name} team ID.");
        }
    }
    if (count(array_unique(array_values($value))) !== count($value)) {
        throw new RuntimeException("Duplicate team in {$name}.");
    }
    return $value;
}

function europaValidateMatchups(array $source, array $destination, array $matchups): void
{
    foreach ($destination as $slot => $winnerId) {
        [$first, $second] = $matchups[$slot];
        if (
            !isset($source[$first], $source[$second]) ||
            ($winnerId !== $source[$first] && $winnerId !== $source[$second])
        ) {
            throw new RuntimeException("Invalid qualifier in {$slot}.");
        }
    }
}

// ============================================
// EUROPA - Validate current state and progression
// ============================================
function europaValidateProgress(mixed $progress, mysqli $con, bool $complete = false): array
{
    if (!is_array($progress) || ($progress['stage'] ?? null) !== 'knockout') {
        throw new RuntimeException('Invalid Europa League progress stage.');
    }

    $r16Keys = array_merge(
        array_map(static fn(int $n): string => "L{$n}", range(1, 8)),
        array_map(static fn(int $n): string => "R{$n}", range(1, 8))
    );
    $qfMatchups = [
        'Q1' => ['L1', 'L2'], 'Q2' => ['L3', 'L4'],
        'Q3' => ['L5', 'L6'], 'Q4' => ['L7', 'L8'],
        'Q5' => ['R1', 'R2'], 'Q6' => ['R3', 'R4'],
        'Q7' => ['R5', 'R6'], 'Q8' => ['R7', 'R8'],
    ];
    $sfMatchups = [
        'S1' => ['Q1', 'Q2'], 'S2' => ['Q3', 'Q4'],
        'S3' => ['Q5', 'Q6'], 'S4' => ['Q7', 'Q8'],
    ];
    $finalMatchups = [
        'F1' => ['S1', 'S2'], 'F2' => ['S3', 'S4'],
    ];

    $r16 = europaRequireSlotMap($progress['round_of_16'] ?? null, $r16Keys, 'R16');
    $qf = europaRequireSlotMap(
        $progress['quarter_finals'] ?? null,
        array_keys($qfMatchups),
        'quarter-final'
    );
    $sf = europaRequireSlotMap(
        $progress['semi_finals'] ?? null,
        array_keys($sfMatchups),
        'semi-final'
    );
    $finalists = europaRequireSlotMap(
        $progress['finalists'] ?? null,
        array_keys($finalMatchups),
        'final'
    );
    $winner = $progress['winner'] ?? null;
    if ($winner !== null && (!is_int($winner) || $winner < 1)) {
        throw new RuntimeException('Invalid champion ID.');
    }

    // Each new round requires every slot of the previous one.
    if ($qf !== [] && count($r16) !== 16) {
        throw new RuntimeException('R16 must be complete before choosing winners.');
    }
    if ($sf !== [] && count($qf) !== 8) {
        throw new RuntimeException('Quarter-finals must be complete first.');
    }
    if ($finalists !== [] && count($sf) !== 4) {
        throw new RuntimeException('Semi-finals must be complete first.');
    }
    if ($winner !== null && count($finalists) !== 2) {
        throw new RuntimeException('Both finalists must be selected first.');
    }

    europaValidateMatchups($r16, $qf, $qfMatchups);
    europaValidateMatchups($qf, $sf, $sfMatchups);
    europaValidateMatchups($sf, $finalists, $finalMatchups);

    if ($winner !== null && !in_array($winner, array_values($finalists), true)) {
        throw new RuntimeException('Champion must be one of the two finalists.');
    }

    if ($complete && (
        count($r16) !== 16 || count($qf) !== 8 ||
        count($sf) !== 4 || count($finalists) !== 2 || $winner === null
    )) {
        throw new RuntimeException('Complete all knockout stages and save the champion first.');
    }

    // Check that selected R16 participants still exist in teams.
    if ($r16 !== []) {
        $ids = array_values($r16);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $result = $con->execute_query(
            "SELECT COUNT(*) AS total FROM teams WHERE id IN ($placeholders)",
            $ids
        );
        $existing = (int) $result->fetch_assoc()['total'];
        $result->free();
        if ($existing !== count($ids)) {
            throw new RuntimeException('One or more selected teams no longer exist.');
        }
    }

    // Canonical keys and strict integer values come from validation above.
    return [
        'stage' => 'knockout',
        'round_of_16' => $r16,
        'quarter_finals' => $qf,
        'semi_finals' => $sf,
        'finalists' => $finalists,
        'winner' => $winner,
    ];
}
