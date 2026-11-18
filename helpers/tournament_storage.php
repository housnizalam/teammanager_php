
<?php

// ============================================
// TOURNAMENT STORAGE - Configuration
// ============================================

const TOURNAMENT_STORAGE_DIR =
    __DIR__ . '/../storage/tournaments';


// ============================================
// TOURNAMENT STORAGE - File path
// ============================================

function tournamentFilePath(
    int $season,
    string $competition
): string {

    if ($season < 1) {
        throw new InvalidArgumentException(
            'Invalid season number.'
        );
    }

    if (!preg_match('/^[a-z0-9_]+$/', $competition)) {
        throw new InvalidArgumentException(
            'Invalid competition identifier.'
        );
    }

    return TOURNAMENT_STORAGE_DIR
        . "/season_{$season}_{$competition}.json";
}


// ============================================
// TOURNAMENT STORAGE - Save progress
// ============================================

function saveTournamentProgress(
    int $season,
    string $competition,
    array $progress
): void {

    $path = tournamentFilePath($season, $competition);
    $directory = dirname($path);

    if (!is_dir($directory) &&
        !mkdir($directory, 0700, true) &&
        !is_dir($directory)) {

        throw new RuntimeException(
            'Cannot create tournament storage directory.'
        );
    }

    $json = json_encode(
        $progress,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_THROW_ON_ERROR
    );

    // Write to a temporary file first.
    $tempPath = tempnam($directory, 'tournament_');

    if ($tempPath === false) {
        throw new RuntimeException(
            'Cannot create temporary file.'
        );
    }

    try {
        if (file_put_contents($tempPath, $json, LOCK_EX)
            === false) {

            throw new RuntimeException(
                'Cannot save tournament progress.'
            );
        }

        // Replace the previous file only after
        // the new JSON has been written successfully.
        if (!rename($tempPath, $path)) {
            throw new RuntimeException(
                'Cannot replace tournament progress file.'
            );
        }
    } finally {
        if (is_file($tempPath)) {
            unlink($tempPath);
        }
    }
}


// ============================================
// TOURNAMENT STORAGE - Load progress
// ============================================

function loadTournamentProgress(
    int $season,
    string $competition
): array {

    $path = tournamentFilePath($season, $competition);

    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);

    if ($json === false) {
        throw new RuntimeException(
            'Cannot read tournament progress.'
        );
    }

    $progress = json_decode(
        $json,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($progress)) {
        throw new RuntimeException(
            'Invalid tournament progress format.'
        );
    }

    return $progress;
}
