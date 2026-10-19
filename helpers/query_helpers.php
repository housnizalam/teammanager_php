
<?php

function buildListQuery(
    $baseSql,
    $filters,
    $sorts,
    $input,
    $baseParams = [],
    $defaultSort = "name",
    $defaultOrder = "DESC"
) {
    $sql = $baseSql;
    $params = $baseParams;

// Filter
foreach ($filters as $key => $column) {
    $value = $input[$key] ?? "";

    if (!is_scalar($value) || (string)$value === "") {
        continue;
    }

    if ($key === "name") {
        $sql .= " AND $column LIKE ?";
        $params[] = "%" . $value . "%";
    } else {
        $sql .= " AND $column = ?";
        $params[] = $value;
    }
}

    // Sort
    $sort = $input["sort"] ?? $defaultSort;

    if (!is_string($sort) || !isset($sorts[$sort])) {
        $sort = $defaultSort;
    }

    $order = $input["order"] ?? $defaultOrder;

    if (!is_string($order)) {
        $order = $defaultOrder;
    }

    $order = strtoupper($order);

    if ($order != "ASC" && $order != "DESC") {
        $order = $defaultOrder;
    }

    $sql .= " ORDER BY " . $sorts[$sort] . " " . $order;

    // Stable order when values are equal
    if ($sort != $defaultSort) {
        $sql .= ", " . $sorts[$defaultSort] . " ASC";
    }

    // Limit
    $limit = $input["limit"] ?? "all";

    if (is_scalar($limit) && ctype_digit((string)$limit)) {
        $limit = (int)$limit;

        if ($limit >= 1) {
            $sql .= " LIMIT " . $limit;
        }
    }

    return [$sql, $params];
}


function showListFilters(
    $filterOptions,
    $sortOptions,
    $input,
    $defaultSort = "name",
    $defaultOrder = "DESC",
    $hidden = []
) {
    echo '<form method="get" class="auto-filter-form">';

    // Hidden values, such as team_id
    foreach ($hidden as $key => $value) {
        echo '<input type="hidden" name="'
            . htmlspecialchars($key)
            . '" value="'
            . htmlspecialchars((string)$value)
            . '">';
    }

    // Filters
    foreach ($filterOptions as $key => $options) {

        // Special text filter for player/team names
        if ($key === "name") {
            echo '<label for="filter-name">Name:</label>';

            echo '<input
                type="search"
                id="filter-name"
                name="name"
                placeholder="Search name..."
                autocomplete="off"
                value="' . htmlspecialchars((string)($input["name"] ?? "")) . '">';

            continue;
        }

        echo '<label>' . htmlspecialchars(ucfirst($key)) . ':</label>';
        echo '<select name="' . htmlspecialchars($key) . '">';

        foreach ($options as $value => $label) {
            $selected = (
                (string)($input[$key] ?? "") === (string)$value
            ) ? "selected" : "";

            echo '<option value="'
                . htmlspecialchars((string)$value)
                . '" ' . $selected . '>'
                . htmlspecialchars((string)$label)
                . '</option>';
        }

        echo '</select>';
    }

    // Sort
    echo '<label>Sort by:</label>';
    echo '<select name="sort">';

    $selectedSort = $input["sort"] ?? $defaultSort;

    foreach ($sortOptions as $value => $label) {
        $selected = ($selectedSort == $value) ? "selected" : "";

        echo '<option value="'
            . htmlspecialchars($value)
            . '" ' . $selected . '>'
            . htmlspecialchars($label)
            . '</option>';
    }

    echo '</select>';

    // Order
    $selectedOrder = $input["order"] ?? $defaultOrder;

    echo '<label>Order:</label>';
    echo '<select name="order">';

    foreach ([
        "ASC" => "Ascending",
        "DESC" => "Descending"
    ] as $value => $label) {

        $selected = ($selectedOrder == $value) ? "selected" : "";

        echo "<option value='$value' $selected>$label</option>";
    }

    echo '</select>';

    // Result limit
    $selectedLimit = $input["limit"] ?? "";

    echo '<label>Results:</label>';

    echo '<input
        type="number"
        name="limit"
        min="1"
        step="1"
        placeholder="All"
        value="' . htmlspecialchars((string)$selectedLimit) . '"
        style="width: 80px;">';

    // No Apply button
    echo '<a href="?' . htmlspecialchars(http_build_query($hidden)) . '">Reset</a>';

    echo '</form>';

    // Automatic submission
    echo <<<'HTML'
<script>
(() => {
    const form = document.currentScript.previousElementSibling;

    if (!form || !form.matches(".auto-filter-form")) {
        return;
    }

    let timer;

    const nameInput = form.querySelector('[name="name"]');
    const limitInput = form.querySelector('[name="limit"]');
    const focusKey = "filter-focus:" + location.pathname;

    const savedFocus = sessionStorage.getItem(focusKey);

    if (nameInput && savedFocus !== null) {
        sessionStorage.removeItem(focusKey);

        if (nameInput.value === savedFocus) {
            nameInput.focus();
            nameInput.setSelectionRange(
                nameInput.value.length,
                nameInput.value.length
            );
        }
    }

    // Dropdowns: submit immediately
    form.querySelectorAll("select").forEach(select => {
        select.addEventListener("change", () => {
            sessionStorage.removeItem(focusKey);
            form.requestSubmit();
        });
    });

    // Name: submit after typing stops
    if (nameInput) {
        nameInput.addEventListener("input", () => {
            clearTimeout(timer);

            timer = setTimeout(() => {
                sessionStorage.setItem(focusKey, nameInput.value);
                form.requestSubmit();
            }, 450);
        });
    }

    // Results limit: automatic delayed submission
    if (limitInput) {
        limitInput.addEventListener("input", () => {
            clearTimeout(timer);

            timer = setTimeout(() => {
                sessionStorage.removeItem(focusKey);

                if (limitInput.checkValidity()) {
                    form.requestSubmit();
                }
            }, 600);
        });
    }
})();
</script>
HTML;
}


function positionSortSql($column = "position")
{
    return "CASE $column
        WHEN 'GK' THEN 10
        WHEN 'CB' THEN 9
        WHEN 'RB' THEN 8
        WHEN 'LB' THEN 7
        WHEN 'CDM' THEN 6
        WHEN 'CM' THEN 5
        WHEN 'CAM' THEN 4
        WHEN 'RW' THEN 3
        WHEN 'LW' THEN 2
        WHEN 'ST' THEN 1
        ELSE 0
    END";
}
