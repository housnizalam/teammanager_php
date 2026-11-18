
<?php

// ============================================
// SHARED TABLE - Renderer
// ============================================

function showTable(
    mysqli_result $result,
    array $columns,
    string $defaultSort = "",
    string $emptyMessage = "No Data found."
) {
    static $selectionScriptPrinted = false;

    $activeSort = $_GET["sort"] ?? $defaultSort;
    $rank = 1;

    echo '<div class="table-container">';
    echo '<table class="data-table shared-table shared-table-interactive">';

    // ============================================
    // SHARED TABLE - Column Widths
    // ============================================

    echo '<colgroup>';
    echo '<col style="width: 38px;">';

    foreach ($columns as $column) {
        echo '<col>';
    }

    echo '</colgroup>';

    // ============================================
    // SHARED TABLE - Header
    // ============================================

    echo '<thead><tr>';
    echo '<th class="shared-table-rank">#</th>';

    foreach ($columns as $key => $column) {
        $label = $column["label"] ?? $key;
        $sortKey = $column["sort"] ?? $key;

        $isActive = $sortKey === $activeSort && $activeSort !== "";
        $class = $isActive ? ' class="sorted-column"' : '';

        echo "<th$class>" . htmlspecialchars($label) . "</th>";
    }

    echo '</tr></thead>';

    // ============================================
    // SHARED TABLE - Body
    // ============================================

    echo '<tbody>';

    if ($result->num_rows === 0) {
        echo '<tr><td colspan="' . (count($columns) + 1) . '">'
            . htmlspecialchars($emptyMessage)
            . '</td></tr>';
    }

    while ($row = $result->fetch_assoc()) {
        echo '<tr tabindex="0">';

        echo '<td class="shared-table-rank">'
            . $rank++
            . '</td>';

        foreach ($columns as $key => $column) {
            $sortKey = $column["sort"] ?? $key;
            $isActive = $sortKey === $activeSort && $activeSort !== "";

            $class = $isActive ? ' class="sorted-column"' : '';

            echo "<td$class>";

            if (isset($column["render"])) {
                // Custom HTML renderer
                echo $column["render"]($row);
            } else {
                // Default escaped text
                echo htmlspecialchars(
                    (string)($row[$key] ?? "")
                );
            }

            echo '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';

    // ============================================
    // SHARED TABLE - Global Row Selection
    // ============================================

    if (!$selectionScriptPrinted) {
        $selectionScriptPrinted = true;

        echo <<<'HTML'
<script>
(() => {
    function selectTableRow(row) {
        const table = row.closest("table");

        table.querySelectorAll("tbody tr.is-selected").forEach(
            selectedRow => selectedRow.classList.remove("is-selected")
        );

        row.classList.add("is-selected");
    }

    document.addEventListener("click", (event) => {
        const row = event.target.closest(
            ".shared-table-interactive tbody tr[tabindex]"
        );

        if (!row) return;

        // Preserve existing links, buttons and form controls.
        if (event.target.closest(
            "a, button, input, select, textarea, label"
        )) {
            return;
        }

        selectTableRow(row);
    });

    document.addEventListener("keydown", (event) => {
        const row = event.target.closest(
            ".shared-table-interactive tbody tr[tabindex]"
        );

        if (!row || event.target !== row) return;

        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            selectTableRow(row);
        }
    });
})();
</script>
HTML;
    }
}
