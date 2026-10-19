
<?php

function showTable(
    mysqli_result $result,
    array $columns,
    string $defaultSort = "",
    string $emptyMessage = "No Data found."
) {
    $activeSort = $_GET["sort"] ?? $defaultSort;

    echo '<div class="table-container">';
    echo '<table class="data-table shared-table">';

    // Table Header
    echo '<thead><tr>';

    foreach ($columns as $key => $column) {
        $label = $column["label"] ?? $key;
        $sortKey = $column["sort"] ?? $key;

        $isActive = $sortKey === $activeSort && $activeSort !== "";
        $class = $isActive ? ' class="sorted-column"' : '';

        echo "<th$class>" . htmlspecialchars($label) . "</th>";
    }

    echo '</tr></thead>';

    // Table Body
    echo '<tbody>';

    if ($result->num_rows == 0) {
        echo '<tr><td colspan="' . count($columns) . '">'
            . htmlspecialchars($emptyMessage)
            . '</td></tr>';
    }

    while ($row = $result->fetch_assoc()) {
        echo '<tr>';

        foreach ($columns as $key => $column) {
            $sortKey = $column["sort"] ?? $key;

            $isActive = $sortKey === $activeSort && $activeSort !== "";
            $class = $isActive ? ' class="sorted-column"' : '';

            echo "<td$class>";

            if (isset($column["render"])) {
                // Custom HTML renderer (e.g. links)
                echo $column["render"]($row);
            } else {
                // Default: escaped text
                echo htmlspecialchars((string)($row[$key] ?? ""));
            }

            echo '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}
