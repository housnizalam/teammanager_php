// ============================================
// CHAMPIONS - Pot selection
// ============================================

document.addEventListener("DOMContentLoaded", () => {
  const teamRows = document.querySelectorAll("#champions-team-list tr");

  const nationFilter = document.getElementById("champions-nation");

  const totalCount = document.getElementById("champions-total-count");

  const selectedTeams = new Map();
  const MAX_PER_POT = 8;

  // ============================================
  // CHAMPIONS - Active pot selection
  // ============================================

  const potCards = document.querySelectorAll(".champions-pot");
  let activePot = 1;

  function selectActivePot(pot) {
    activePot = pot;

    // Update all available team dropdowns.
    document.querySelectorAll(".champions-pot-select").forEach((select) => {
      select.value = String(pot);
    });

    // Highlight the selected pot.
    potCards.forEach((card) => {
      const isActive = Number(card.dataset.pot) === pot;

      card.classList.toggle("champions-pot-active", isActive);
      card.setAttribute("aria-pressed", String(isActive));
    });
  }

  potCards.forEach((card) => {
    card.setAttribute("role", "button");
    card.setAttribute("tabindex", "0");

    const pot = Number(card.dataset.pot);

    card.addEventListener("click", (event) => {
      // Do not change pot when removing a team.
      if (event.target.closest(".champions-team-card")) {
        return;
      }

      selectActivePot(pot);
    });

    card.addEventListener("keydown", (event) => {
      if (event.target !== card) return;

      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        selectActivePot(pot);
      }
    });
  });

  // Default selection on page load.
  selectActivePot(1);

  // ============================================
  // CHAMPIONS - Render pots
  // ============================================

  function renderPots() {
    for (let pot = 1; pot <= 4; pot++) {
      const container = document.querySelector(
        `.champions-pot[data-pot="${pot}"]`,
      );

      const list = container.querySelector(".champions-pot-list");

      const teams = [...selectedTeams.values()].filter(
        (team) => team.pot === pot,
      );

      list.replaceChildren();

      teams.forEach((team, index) => {
        // ============================================
        // CHAMPIONS - Clickable team card
        // ============================================

        const item = document.createElement("li");
        item.className = "champions-pot-item";

        const card = document.createElement("button");
        card.type = "button";
        card.className = "champions-team-card";
        card.title = "Click to remove team";
        card.setAttribute("aria-label", `Remove ${team.name} from Pot ${pot}`);

        const number = document.createElement("span");
        number.className = "champions-team-number";
        number.textContent = index + 1;

        const name = document.createElement("span");
        name.className = "champions-team-name";
        name.textContent = team.name;

        card.append(number, name);
        item.appendChild(card);
        list.appendChild(item);

        card.addEventListener("click", (event) => {
          event.stopPropagation();

          selectedTeams.delete(team.id);

          renderPots();
          updateAvailableTeams();
        });
      });

      container.querySelector(".champions-pot-count").textContent =
        `${teams.length} / 8`;
    }

    totalCount.textContent = `${selectedTeams.size} / 32`;
  }

  // ============================================
  // CHAMPIONS - Available teams
  // ============================================

  function updateAvailableTeams() {
    const nation = nationFilter.value;

    teamRows.forEach((row) => {
      const id = Number(row.dataset.teamId);
      const selected = selectedTeams.has(id);

      row.hidden = selected || (nation !== "" && row.dataset.nation !== nation);
    });
  }

  // ============================================
  // CHAMPIONS - Add team
  // ============================================

  teamRows.forEach((row) => {
    const button = row.querySelector(".champions-add-team");

    button.addEventListener("click", () => {
      const id = Number(row.dataset.teamId);
      const name = row.dataset.teamName;

      const pot = Number(row.querySelector(".champions-pot-select").value);

      if (selectedTeams.has(id)) {
        return;
      }

      const potCount = [...selectedTeams.values()].filter(
        (team) => team.pot === pot,
      ).length;

      if (potCount >= MAX_PER_POT) {
        alert(`Pot ${pot} already contains 8 teams.`);
        return;
      }

      selectedTeams.set(id, { id, name, pot });

      renderPots();
      updateAvailableTeams();
    });
  });

  // ============================================
  // CHAMPIONS - Filter by nation
  // ============================================

  nationFilter.addEventListener("change", updateAvailableTeams);

  // ============================================
  // CHAMPIONS - Restore saved pots
  // ============================================

  const savedPotsElement = document.getElementById("champions-saved-pots");

  if (savedPotsElement) {
    const savedPots = JSON.parse(savedPotsElement.textContent);

    const rowsById = new Map(
      [...teamRows].map((row) => [Number(row.dataset.teamId), row]),
    );

    for (let pot = 1; pot <= 4; pot++) {
      const ids = savedPots[String(pot)] ?? [];

      ids.forEach((id) => {
        const row = rowsById.get(id);

        if (!row) return;

        selectedTeams.set(id, {
          id,
          name: row.dataset.teamName,
          pot,
        });
      });
    }
  }

  // ============================================
  // CHAMPIONS - Save all pots
  // ============================================

  const saveForm = document.getElementById("champions-save-form");

  if (saveForm) {
    saveForm.addEventListener("submit", () => {
      const pots = {
        1: [],
        2: [],
        3: [],
        4: [],
      };

      selectedTeams.forEach((team) => {
        pots[team.pot].push(team.id);
      });

      document.getElementById("champions-save-data").value =
        JSON.stringify(pots);
    });
  }

  renderPots();
  updateAvailableTeams();
});
