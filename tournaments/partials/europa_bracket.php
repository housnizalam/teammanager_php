<?php
// ============================================
// EUROPA - Mirrored bracket geometry
// ============================================
$r16Y = [66, 116, 186, 236, 346, 396, 466, 516];
$qfY = [91, 211, 371, 491];
$sfY = [151, 431];
$finalY = [291];

$columns = [
    ['x' => 15,   'ys' => $r16Y,   'prefix' => 'L', 'type' => 'r16'],
    ['x' => 190,  'ys' => $qfY,    'prefix' => 'Q', 'type' => 'qf'],
    ['x' => 360,  'ys' => $sfY,    'prefix' => 'S', 'type' => 'sf'],
    ['x' => 530,  'ys' => $finalY, 'prefix' => 'F', 'type' => 'final'],
    ['x' => 645,  'ys' => $finalY, 'prefix' => 'F', 'type' => 'final'],
    ['x' => 815,  'ys' => $sfY,    'prefix' => 'S', 'type' => 'sf'],
    ['x' => 985,  'ys' => $qfY,    'prefix' => 'Q', 'type' => 'qf'],
    ['x' => 1160, 'ys' => $r16Y,   'prefix' => 'R', 'type' => 'r16'],
];

// ============================================
// EUROPA - Connector helper (local to Europa)
// ============================================
$makeBranch = static function (
    int $childX,
    array $childY,
    int $parentX,
    int $parentY,
    bool $leftSide
): string {
    $startX = $leftSide ? $childX + 100 : $childX;
    $endX = $leftSide ? $parentX : $parentX + 100;
    $midX = ($startX + $endX) / 2;
    return "M {$startX} {$childY[0]} H {$midX} "
         . "M {$startX} {$childY[1]} H {$midX} "
         . "M {$midX} {$childY[0]} V {$childY[1]} "
         . "M {$midX} {$parentY} H {$endX}";
};

$paths = [];
foreach ([true, false] as $leftSide) {
    $xR16 = $leftSide ? 15 : 1160;
    $xQF = $leftSide ? 190 : 985;
    $xSF = $leftSide ? 360 : 815;
    $xFinal = $leftSide ? 530 : 645;

    for ($i = 0; $i < 4; $i++) {
        $paths[] = $makeBranch(
            $xR16,
            [$r16Y[$i * 2], $r16Y[$i * 2 + 1]],
            $xQF,
            $qfY[$i],
            $leftSide
        );
    }
    for ($i = 0; $i < 2; $i++) {
        $paths[] = $makeBranch(
            $xQF,
            [$qfY[$i * 2], $qfY[$i * 2 + 1]],
            $xSF,
            $sfY[$i],
            $leftSide
        );
    }
    $paths[] = $makeBranch($xSF, $sfY, $xFinal, $finalY[0], $leftSide);
}
?>
<!-- ============================================
     EUROPA - Bracket and saving toolbar
     ============================================ -->
<section class="content-card champions-bracket-section europa-bracket-section">
    <div class="page-toolbar">
        <h2>Europa League - Knockout Bracket</h2>
        <span>Round of 16 → Final</span>
    </div>

    <div class="champions-r16-toolbar europa-toolbar">
        <div>
            <strong id="europa-current-stage">Stage: Select Round of 16 Teams</strong>
            <p id="europa-stage-help" class="page-subtitle">
                Click an empty R16 slot, then add a team from Available Teams.
            </p>
            <span id="europa-stage-counter">0 / 16 selected</span>
        </div>
        <form id="europa-save-form" method="post" action="save_europa.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['europa_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="progress" id="europa-save-data">
            <button type="submit" class="action-button" <?= $isPlayed ? 'disabled' : '' ?>>Save</button>
        </form>
    </div>

    <?php if (($_GET['saved'] ?? '') === '1'): ?>
        <p role="status" class="champions-save-message">Europa League progress saved successfully.</p>
    <?php endif; ?>
    <?php if (($_GET['finished'] ?? '') === '1' && $isPlayed): ?>
        <p role="status" class="champions-save-message">Europa League results applied successfully.</p>
    <?php endif; ?>

    <div class="champions-bracket-scroll">
        <div class="champions-bracket-board" id="europa-bracket-board" data-finished="<?= $isPlayed ? '1' : '0' ?>">
            <!-- EUROPA - Connector lines -->
            <svg class="champions-bracket-lines" viewBox="0 0 1275 570" aria-hidden="true">
                <?php foreach ($paths as $path): ?>
                    <path d="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>" />
                <?php endforeach; ?>
                <path d="M 580 308 V 385 H 695 V 308 M 637.5 385 V 410" />
            </svg>

            <!-- EUROPA - Stage headings -->
            <?php foreach ([
                [15, 'R16'], [190, 'QF'], [360, 'SF'], [530, 'FINAL'],
                [645, 'FINAL'], [815, 'SF'], [985, 'QF'], [1160, 'R16']
            ] as [$x, $title]): ?>
                <div class="champions-bracket-heading" style="left:<?= $x ?>px"><?= $title ?></div>
            <?php endforeach; ?>

            <!-- EUROPA - All slots start empty, populated by JavaScript -->
            <?php foreach ($columns as $columnIndex => $column): ?>
                <?php foreach ($column['ys'] as $index => $centerY): ?>
                    <?php
                    $slot = match ($column['type']) {
                        'r16' => $column['prefix'] . ($index + 1),
                        'qf' => 'Q' . ($columnIndex < 4 ? $index + 1 : $index + 5),
                        'sf' => 'S' . ($columnIndex < 4 ? $index + 1 : $index + 3),
                        'final' => $columnIndex < 4 ? 'F1' : 'F2',
                    };
                    ?>
                    <div
                        class="champions-bracket-slot champions-bracket-slot-<?= $column['type'] ?>"
                        data-slot="<?= htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') ?>"
                        style="left:<?= (int) $column['x'] ?>px; top:<?= (int) ($centerY - 17) ?>px;">
                        <?= htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <!-- EUROPA - Champion -->
            <div class="champions-bracket-winner">
                <span>WINNER</span>
                <strong id="europa-winner-name">🏆</strong>
            </div>
        </div>
    </div>

    <!-- EUROPA - Finalization is separate from Save -->
    <?php if ($isPlayed): ?>
        <p class="champions-tournament-completed" role="status">
            Europa League completed successfully.
        </p>
    <?php elseif (is_int($savedProgress['winner'] ?? null)): ?>
        <div class="champions-finish-toolbar">
            <form method="post" action="finalize_europa.php"
                onsubmit="return confirm('Finish Europa League permanently? Points and matches will be added to all 16 teams.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['europa_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <!-- ============================================
                     EUROPA - Individual tournament awards
                     ============================================ -->
                <?php
                $awardTeamIds = array_values($savedProgress['round_of_16'] ?? []);
                renderTournamentAwardFields($con, $awardTeamIds);
                ?>
                <button type="submit" class="action-button">Finish Tournament</button>
            </form>
        </div>
    <?php endif; ?>
</section>
