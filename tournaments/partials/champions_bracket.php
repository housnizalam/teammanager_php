<?php
// ============================================
// CHAMPIONS - Knockout Bracket Layout
// ============================================

// Vertical coordinates represent the center of each slot.
$r16Y = [66, 116, 186, 236, 346, 396, 466, 516];
$qfY = [91, 211, 371, 491];
$sfY = [151, 431];
$finalY = [291];

$slotWidth = 100;

// Mirrored columns: left -> center <- right.
$columns = [
    ['title' => 'R16', 'x' => 15,   'ys' => $r16Y,   'prefix' => 'L', 'type' => 'r16'],
    ['title' => 'QF',  'x' => 190,  'ys' => $qfY,    'prefix' => 'Q', 'type' => 'qf'],
    ['title' => 'SF',  'x' => 360,  'ys' => $sfY,    'prefix' => 'S', 'type' => 'sf'],
    ['title' => 'FINAL', 'x' => 530, 'ys' => $finalY, 'prefix' => 'F', 'type' => 'final'],

    ['title' => 'FINAL', 'x' => 645, 'ys' => $finalY, 'prefix' => 'F', 'type' => 'final'],
    ['title' => 'SF',  'x' => 815,  'ys' => $sfY,    'prefix' => 'S', 'type' => 'sf'],
    ['title' => 'QF',  'x' => 985,  'ys' => $qfY,    'prefix' => 'Q', 'type' => 'qf'],
    ['title' => 'R16', 'x' => 1160, 'ys' => $r16Y, 'prefix' => 'R', 'type' => 'r16'],
];

// ============================================
// CHAMPIONS - Bracket Connector Geometry
// ============================================

// One path connects two child slots to their parent.
function championsBracketBranch(
    int $childX,
    array $childY,
    int $parentX,
    int $parentY,
    bool $leftSide
): string {
    $width = 100;

    $startX = $leftSide
        ? $childX + $width
        : $childX;

    $endX = $leftSide
        ? $parentX
        : $parentX + $width;

    $midX = ($startX + $endX) / 2;

    return
        "M {$startX} {$childY[0]} H {$midX} " .
        "M {$startX} {$childY[1]} H {$midX} " .
        "M {$midX} {$childY[0]} V {$childY[1]} " .
        "M {$midX} {$parentY} H {$endX}";
}

// ============================================
// CHAMPIONS - Connector Paths
// ============================================

$connectorPaths = [];

foreach ([true, false] as $leftSide) {
    $xR16 = $leftSide ? 15 : 1160;
    $xQF = $leftSide ? 190 : 985;
    $xSF = $leftSide ? 360 : 815;
    $xFinal = $leftSide ? 530 : 645;

    // R16 -> Quarter-finals
    for ($i = 0; $i < 4; $i++) {
        $connectorPaths[] = championsBracketBranch(
            $xR16,
            [$r16Y[$i * 2], $r16Y[$i * 2 + 1]],
            $xQF,
            $qfY[$i],
            $leftSide
        );
    }

    // Quarter-finals -> Semi-finals
    for ($i = 0; $i < 2; $i++) {
        $connectorPaths[] = championsBracketBranch(
            $xQF,
            [$qfY[$i * 2], $qfY[$i * 2 + 1]],
            $xSF,
            $sfY[$i],
            $leftSide
        );
    }

    // Semi-finals -> Final
    $connectorPaths[] = championsBracketBranch(
        $xSF,
        $sfY,
        $xFinal,
        $finalY[0],
        $leftSide
    );
}
?>

<!-- ============================================
     CHAMPIONS - Knockout Bracket
     ============================================ -->

<section class="content-card champions-bracket-section">

    <div class="page-toolbar">
        <h2>Champions League - Knockout Bracket</h2>
        <span>Round of 16 → Final</span>
    </div>


    <!-- ============================================
     CHAMPIONS - R16 Selection Toolbar
     ============================================ -->

    <div class="champions-r16-toolbar">

        <div>

            <strong id="champions-current-stage">
                Stage: Select Round of 16 Teams
            </strong>

            <p class="page-subtitle" id="champions-stage-help">
                Select a slot, then select a team from the groups.
                Maximum 2 teams per group.
            </p>

            <span id="champions-r16-counter">0 / 16 selected</span>

            <span id="champions-qf-counter" hidden>
                Quarter-finals: 0 / 8 winners
            </span>

            <span id="champions-sf-counter" hidden>
                Semi-finals: 0 / 4 qualifiers
            </span>


            <span id="champions-final-counter" hidden>
                Final: 0 / 2 finalists
            </span>

            <span id="champions-winner-status" hidden>
                Champion not selected
            </span>


        </div>

        <form
            id="champions-r16-save-form"
            method="post"
            action="save_champions_r16.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                            $_SESSION['champions_csrf'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">

            <input
                type="hidden"
                name="slots"
                id="champions-r16-save-data">

            <!-- CHAMPIONS - Partial quarter-final results -->
            <input
                type="hidden"
                name="quarter_finals"
                id="champions-qf-save-data">

            <!-- CHAMPIONS - Partial semi-final qualifiers -->
            <input
                type="hidden"
                name="semi_finals"
                id="champions-sf-save-data">


            <!-- ============================================
     CHAMPIONS - Final and Winner Save Fields
     ============================================ -->

            <input
                type="hidden"
                name="finalists"
                id="champions-final-save-data">

            <input
                type="hidden"
                name="winner"
                id="champions-winner-save-data">



            <button
                type="submit"
                class="action-button"
                <?= $isPlayed ? 'disabled' : '' ?>>
                Save
            </button>

        </form>

    </div>

    <?php if (($_GET['r16_saved'] ?? '') === '1'): ?>
        <p role="status" class="champions-save-message">
            Round of 16 selections saved successfully.
        </p>
    <?php endif; ?>


    <div class="champions-bracket-scroll">

        <div
            class="champions-bracket-board"
            data-finished="<?= $isPlayed ? '1' : '0' ?>">


            <!-- CHAMPIONS - Connector lines -->
            <svg
                class="champions-bracket-lines"
                viewBox="0 0 1275 570"
                aria-hidden="true">
                <?php foreach ($connectorPaths as $path): ?>
                    <path d="<?= htmlspecialchars(
                                    $path,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>" />
                <?php endforeach; ?>

                <!-- Finalists -> Winner -->
                <path d="
                    M 580 308 V 385 H 695 V 308
                    M 637.5 385 V 410
                " />
            </svg>

            <!-- CHAMPIONS - Stage headings -->
            <div class="champions-bracket-heading" style="left:15px">R16</div>
            <div class="champions-bracket-heading" style="left:190px">QF</div>
            <div class="champions-bracket-heading" style="left:360px">SF</div>
            <div class="champions-bracket-heading" style="left:530px">FINAL</div>
            <div class="champions-bracket-heading" style="left:645px">FINAL</div>
            <div class="champions-bracket-heading" style="left:815px">SF</div>
            <div class="champions-bracket-heading" style="left:985px">QF</div>
            <div class="champions-bracket-heading" style="left:1160px">R16</div>

            <!-- CHAMPIONS - Empty round slots -->
            <?php foreach ($columns as $columnIndex => $column): ?>

                <?php foreach ($column['ys'] as $index => $centerY): ?>

                    <?php
                    $label = match ($column['type']) {
                        'r16' => $column['prefix'] . ($index + 1),
                        'qf' => 'Q' . (
                            $columnIndex < 4
                            ? $index + 1
                            : $index + 5
                        ),
                        'sf' => 'S' . (
                            $columnIndex < 4
                            ? $index + 1
                            : $index + 3
                        ),
                        'final' => $columnIndex < 4 ? 'F1' : 'F2',
                    };
                    ?>

                    <div
                        class="champions-bracket-slot champions-bracket-slot-<?= $column['type'] ?>"
                        data-slot="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                        style="
                            left: <?= (int) $column['x'] ?>px;
                            top: <?= (int) ($centerY - 17) ?>px;
                        ">
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                <?php endforeach; ?>

            <?php endforeach; ?>



            <!-- CHAMPIONS - Winner display -->
            <div class="champions-bracket-winner">
                <span>WINNER</span>
                <strong id="champions-winner-name">🏆</strong>
            </div>



        </div>
    </div>


    <!-- ============================================
     CHAMPIONS - Finalize Tournament Action
     ============================================ -->

    <?php if ($isPlayed): ?>

        <p class="champions-tournament-completed" role="status">
            Champions League completed successfully.
        </p>

    <?php elseif (
        isset($savedProgress['winner']) &&
        is_int($savedProgress['winner'])
    ): ?>

        <div class="champions-finish-toolbar">

            <form
                method="post"
                action="finalize_champions.php"
                onsubmit="return confirm(
                'Finish Champions League permanently? ' +
                'Points and matches will be added to all 32 teams. ' +
                'This action cannot be undone here.'
            );">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                                $_SESSION['champions_csrf'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

                <!-- ============================================
                     CHAMPIONS - Individual tournament awards
                     ============================================ -->
                <?php
                $awardTeamIds = [];
                foreach (range('A', 'H') as $groupName) {
                    foreach (($savedGroups[$groupName] ?? []) as $teamId) {
                        $awardTeamIds[] = (int) $teamId;
                    }
                }
                renderTournamentAwardFields($con, $awardTeamIds);
                ?>

                <button type="submit" class="action-button">
                    Finish Tournament
                </button>
            </form>

        </div>

    <?php endif; ?>


</section>


<!-- CHAMPIONS - Saved R16 selections -->
<script type="application/json" id="champions-r16-saved">
    <?= json_encode(
        $savedProgress['round_of_16'] ?? new stdClass(),
        JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_THROW_ON_ERROR
    ) ?>
</script>


<!-- CHAMPIONS - Saved quarter-final qualifiers -->
<script type="application/json" id="champions-qf-saved">
    <?= json_encode(
        $savedProgress['quarter_finals'] ?? new stdClass(),
        JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_THROW_ON_ERROR
    ) ?>
</script>


<!-- ============================================
     CHAMPIONS - Saved semi-final qualifiers
     ============================================ -->

<script type="application/json" id="champions-sf-saved">
    <?= json_encode(
        $savedProgress['semi_finals'] ?? new stdClass(),
        JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_THROW_ON_ERROR
    ) ?>
</script>


<!-- ============================================
     CHAMPIONS - Saved Final and Winner
     ============================================ -->

<script type="application/json" id="champions-final-saved">
    <?= json_encode(
        $savedProgress['finalists'] ?? new stdClass(),
        JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_THROW_ON_ERROR
    ) ?>
</script>

<script type="application/json" id="champions-winner-saved">
    <?= json_encode(
        $savedProgress['winner'] ?? null,
        JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_THROW_ON_ERROR
    ) ?>
</script>