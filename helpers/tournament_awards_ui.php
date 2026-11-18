<?php
// ============================================
// TOURNAMENT AWARDS - Render searchable player selectors
// ============================================

function renderTournamentAwardFields(mysqli $con, array $teamIds): void
{
    static $instance = 0;
    $instance++;

    $teamIds = array_values(array_unique(array_map('intval', $teamIds)));
    $teamIds = array_values(array_filter($teamIds, static fn(int $id): bool => $id > 0));

    if ($teamIds === []) {
        throw new RuntimeException('No eligible tournament teams were supplied.');
    }

    $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
    $playersResult = $con->execute_query(
        "SELECT p.id, p.name, t.name AS team_name
         FROM players AS p
         JOIN teams AS t ON t.id = p.team_id
         WHERE p.team_id IN ($placeholders)
         ORDER BY p.name ASC, t.name ASC, p.id ASC",
        $teamIds
    );
    $players = $playersResult->fetch_all(MYSQLI_ASSOC);
    $playersResult->free();

    $jsonId = 'tournament-awards-data-' . $instance;
    $encoded = json_encode(
        $players,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    );
    ?>
    <!-- ============================================
         TOURNAMENT AWARDS - Three required selections
         ============================================ -->
    <fieldset class="tournament-awards" data-awards-source="<?= $jsonId ?>">
        <legend>Individual Awards</legend>
        <p class="tournament-awards-hint">Select players from teams participating in this competition.</p>
        <div class="tournament-awards-grid">
            <?php foreach ([
                'best_player_id' => 'Best Player',
                'top_scorer_id' => 'Top Scorer',
                'top_assister_id' => 'Top Assister',
            ] as $key => $label): ?>
                <?php $inputId = 'award-' . $instance . '-' . $key; ?>
                <div class="tournament-award-field">
                    <label for="<?= $inputId ?>"><?= $label ?></label>
                    <input
                        id="<?= $inputId ?>"
                        class="tournament-award-search"
                        type="search"
                        placeholder="Search player name..."
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-expanded="false"
                        required
                    >
                    <input type="hidden" name="<?= $key ?>" value="">
                    <div class="tournament-award-options" role="listbox" hidden></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($players === []): ?>
            <p class="tournament-awards-error">No eligible players found for these teams.</p>
        <?php endif; ?>
    </fieldset>
    <script type="application/json" id="<?= $jsonId ?>"><?= $encoded ?></script>
    <?php
}
