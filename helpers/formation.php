<?php

function formationPlayerName($name)
{
    $parts = preg_split('/\s+/', trim($name));

    if (count($parts) <= 1) {
        return $name;
    }

    $firstInitial = mb_substr($parts[0], 0, 1);
    $lastName = $parts[count($parts) - 1];

    return $firstInitial . ". " . $lastName;
}

function showFormation($players)
{
    // Required players for 4-3-3
    $required = [
        "GK" => 1,
        "CB" => 2,
        "RB" => 1,
        "LB" => 1,
        "CDM" => 1,
        "CM" => 2,
        "RW" => 1,
        "LW" => 1,
        "ST" => 1
    ];

    $groups = [];

    foreach ($required as $position => $number) {
        $groups[$position] = [];
    }

    // Group players by position
    foreach ($players as $player) {
        $position = $player["position"];

        // CAM players are also CM candidates
        if ($position == "CAM") {
            $position = "CM";
        }

        if (isset($groups[$position])) {
            $groups[$position][] = $player;
        }
    }

    // Check if formation is possible
    $complete = true;

    foreach ($required as $position => $number) {
        if (count($groups[$position]) < $number) {
            $complete = false;
        }
    }

    $selected = [];

    if ($complete) {

        // Best rating, then goals, then assists
        foreach ($groups as $position => $group) {

            usort($group, function ($a, $b) {
                if ($a["rating"] != $b["rating"]) {
                    return $b["rating"] <=> $a["rating"];
                }

                if ($a["goals"] != $b["goals"]) {
                    return $b["goals"] <=> $a["goals"];
                }

                if ($a["assists"] != $b["assists"]) {
                    return $b["assists"] <=> $a["assists"];
                }

                return strcmp($a["name"], $b["name"]);
            });

            $selected[$position] = array_slice(
                $group,
                0,
                $required[$position]
            );
        }
    }

    // Position coordinates: x%, y%
    $slots = [
        ["GK", 0, 50, 89],
        ["LB", 0, 14, 72],
        ["CB", 0, 37, 76],
        ["CB", 1, 63, 76],
        ["RB", 0, 86, 72],
        ["CDM", 0, 50, 58],
        ["CM", 0, 30, 43],
        ["CM", 1, 70, 43],
        ["LW", 0, 16, 19],
        ["ST", 0, 50, 14],
        ["RW", 0, 84, 19]
    ];

?>

    <div class="formation-panel">

        <h2>Best XI - 4-3-3</h2>

        <div class="football-field">

            <div class="field-middle-line"></div>
            <div class="field-center-circle"></div>
            <div class="field-top-box"></div>
            <div class="field-bottom-box"></div>
            <div class="field-top-goal"></div>
            <div class="field-bottom-goal"></div>
            <div class="field-center-point"></div>

            <?php foreach ($slots as $slot):

                $position = $slot[0];
                $index = $slot[1];
                $x = $slot[2];
                $y = $slot[3];

                $player = null;

                if ($complete) {
                    $player = $selected[$position][$index];
                }
            ?>

                <div class="formation-player"
                    style="left: <?= $x ?>%; top: <?= $y ?>%;">

                    <?php if ($player): ?>
                        <div class="player-label">
                            <?= htmlspecialchars(formationPlayerName($player["name"])) ?> </div>
                    <?php endif; ?>

                    <div class="player-point">
                        <?= $position ?>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

        <?php if (!$complete): ?>
            <p class="formation-warning">
                Not enough players available for the 4-3-3 formation.
            </p>
        <?php endif; ?>

    </div>

    <style>
        .formation-layout {
            display: flex;
            align-items: flex-start;
            gap: 35px;
            flex-wrap: wrap;
        }

        .formation-panel {
            width: 560px;
            max-width: 100%;
            flex-shrink: 0;
        }

        .football-field {
            position: relative;
            width: 100%;
            aspect-ratio: 0.8;
            box-sizing: border-box;
            background: #237a42;
            border: 3px solid white;
            outline: 2px solid #14522d;
            overflow: hidden;
        }

        .field-middle-line {
            position: absolute;
            top: 50%;
            width: 100%;
            border-top: 2px solid white;
        }

        .field-center-circle {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 22%;
            aspect-ratio: 1;
            border: 2px solid white;
            border-radius: 50%;
            transform: translate(-50%, -50%);
        }

        .field-center-point {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: white;
            transform: translate(-50%, -50%);
        }

        .field-top-box,
        .field-bottom-box {
            position: absolute;
            left: 25%;
            width: 50%;
            height: 15%;
            border: 2px solid white;
            box-sizing: border-box;
        }

        .field-top-box {
            top: 0;
            border-top: 0;
        }

        .field-bottom-box {
            bottom: 0;
            border-bottom: 0;
        }

        .field-top-goal,
        .field-bottom-goal {
            position: absolute;
            left: 39%;
            width: 22%;
            height: 5%;
            border: 2px solid white;
            box-sizing: border-box;
        }

        .field-top-goal {
            top: 0;
            border-top: 0;
        }

        .field-bottom-goal {
            bottom: 0;
            border-bottom: 0;
        }

        .formation-player {
            position: absolute;
            transform: translate(-50%, -50%);
            text-align: center;
            width: 110px;
            z-index: 2;
        }

        .player-point {
            width: 30px;
            height: 30px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 2px solid white;
            background: #18375c;
            color: white;
            font-weight: bold;
            font-size: 11px;
        }

        .player-label {
            color: white;
            background: rgba(16, 43, 32, 0.5);
            border-radius: 4px;
            margin-bottom: 4px;
            padding: 4px 6px;
            font-size: 18px;
            overflow-wrap: anywhere;
        }

        .formation-warning {
            color: #b00020;
            font-weight: bold;
        }
    </style>

<?php
}
