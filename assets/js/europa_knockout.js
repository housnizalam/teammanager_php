// ============================================
// EUROPA - Complete Knockout Interaction
// ============================================
document.addEventListener("DOMContentLoaded", () => {
  const board = document.getElementById("europa-bracket-board");
  const savedNode = document.getElementById("europa-saved-progress");
  const form = document.getElementById("europa-save-form");
  if (!board || !savedNode || !form) return;

  // ============================================
  // EUROPA - DOM references and state
  // ============================================
  const finished = board.dataset.finished === "1";
  const rowElements = [...document.querySelectorAll("#europa-team-list tr")];
  const slotsByRound = {
    r16: [...board.querySelectorAll(".champions-bracket-slot-r16")],
    qf: [...board.querySelectorAll(".champions-bracket-slot-qf")],
    sf: [...board.querySelectorAll(".champions-bracket-slot-sf")],
    final: [...board.querySelectorAll(".champions-bracket-slot-final")],
  };
  const nationFilter = document.getElementById("europa-nation");
  const teamSearch = document.getElementById("europa-team-search");
  const availableSection = document.getElementById("europa-available-section");
  const title = document.getElementById("europa-current-stage");
  const help = document.getElementById("europa-stage-help");
  const counter = document.getElementById("europa-stage-counter");
  const winnerName = document.getElementById("europa-winner-name");

  const teamById = new Map();
  rowElements.forEach((row) => {
    const id = Number(row.dataset.teamId);
    teamById.set(id, { id, name: row.dataset.teamName, row });
  });

  const state = {
    round_of_16: new Map(),
    quarter_finals: new Map(),
    semi_finals: new Map(),
    finalists: new Map(),
    winner: null,
  };
  let selectedSlot = null;

  // ============================================
  // EUROPA - Fixed bracket matchups
  // ============================================
  const matchups = {
    quarter_finals: {
      Q1: ["L1", "L2"],
      Q2: ["L3", "L4"],
      Q3: ["L5", "L6"],
      Q4: ["L7", "L8"],
      Q5: ["R1", "R2"],
      Q6: ["R3", "R4"],
      Q7: ["R5", "R6"],
      Q8: ["R7", "R8"],
    },
    semi_finals: {
      S1: ["Q1", "Q2"],
      S2: ["Q3", "Q4"],
      S3: ["Q5", "Q6"],
      S4: ["Q7", "Q8"],
    },
    finalists: {
      F1: ["S1", "S2"],
      F2: ["S3", "S4"],
    },
  };
  const parentSlot = (round, childSlot) =>
    Object.keys(matchups[round]).find((slot) =>
      matchups[round][slot].includes(childSlot),
    );

  // ============================================
  // EUROPA - Restore saved JSON progress
  // ============================================
  const saved = JSON.parse(savedNode.textContent);
  const restore = (field, validSlots) => {
    const values = saved[field] ?? {};
    if (!values || typeof values !== "object" || Array.isArray(values)) return;
    const allowed = new Set(validSlots);
    Object.entries(values).forEach(([slot, rawId]) => {
      if (allowed.has(slot) && Number.isInteger(rawId) && teamById.has(rawId)) {
        state[field].set(slot, rawId);
      }
    });
  };
  restore(
    "round_of_16",
    slotsByRound.r16.map((el) => el.dataset.slot),
  );
  restore("quarter_finals", Object.keys(matchups.quarter_finals));
  restore("semi_finals", Object.keys(matchups.semi_finals));
  restore("finalists", Object.keys(matchups.finalists));
  if (Number.isInteger(saved.winner) && teamById.has(saved.winner)) {
    state.winner = saved.winner;
  }

  // ============================================
  // EUROPA - Active round and slot rendering
  // ============================================
  function activeRound() {
    if (state.round_of_16.size < 16) return "selection";
    if (state.quarter_finals.size < 8) return "r16";
    if (state.semi_finals.size < 4) return "qf";
    if (state.finalists.size < 2) return "sf";
    return "final";
  }

  function render() {
    const active = activeRound();
    const selectedIds = new Set(state.round_of_16.values());
    availableSection.hidden = finished || active !== "selection";

    // ============================================
    // EUROPA - Filter available teams
    // ============================================

    const searchText = teamSearch.value.trim().toLocaleLowerCase();

    rowElements.forEach((row) => {
      const teamId = Number(row.dataset.teamId);
      const selected = selectedIds.has(teamId);

      const matchesNation =
        nationFilter.value === "" || row.dataset.nation === nationFilter.value;

      const matchesSearch = row.dataset.teamName
        .toLocaleLowerCase()
        .includes(searchText);

      row.hidden = selected || !matchesNation || !matchesSearch;
    });

    const roundDefinitions = [
      [
        "r16",
        "round_of_16",
        "selection",
        "quarter_finals",
        "champions-r16-winner",
      ],
      ["qf", "quarter_finals", "r16", "semi_finals", "champions-qf-winner"],
      ["sf", "semi_finals", "qf", "finalists", "champions-sf-winner"],
      ["final", "finalists", "sf", null, "champions-final-winner"],
    ];

    roundDefinitions.forEach(
      ([round, field, interactiveAt, nextField, winnerClass]) => {
        slotsByRound[round].forEach((element) => {
          const slot = element.dataset.slot;
          const team = teamById.get(state[field].get(slot));
          const interactive = !finished && active === interactiveAt;
          const nextSlot = nextField ? parentSlot(nextField, slot) : null;
          const advances = nextField
            ? Boolean(team && state[nextField].get(nextSlot) === team.id)
            : Boolean(team && state.winner === team.id);

          element.textContent = team?.name ?? slot;
          element.classList.toggle("europa-slot-filled", Boolean(team));
          element.classList.toggle("europa-slot-clickable", interactive);
          element.classList.toggle(
            "europa-slot-selected",
            interactive && selectedSlot === slot,
          );
          element.classList.toggle(winnerClass, advances);
          element.setAttribute("tabindex", interactive ? "0" : "-1");
          element.setAttribute("role", interactive ? "button" : "text");
          element.setAttribute("aria-disabled", String(!interactive));
          element.title = interactive
            ? active === "selection"
              ? "Select a slot for a team"
              : "Select match winner"
            : "";
        });
      },
    );

    winnerName.textContent = teamById.get(state.winner)?.name ?? "🏆";

    const stageText = {
      selection: [
        "Select Round of 16 Teams",
        "Click an R16 slot, then Add a team from the table. Click the same filled slot twice to clear it.",
        `${state.round_of_16.size} / 16 selected`,
      ],
      r16: [
        "Round of 16",
        "Click the winner of each R16 match to fill the connected Quarter-final slot.",
        `${state.quarter_finals.size} / 8 winners`,
      ],
      qf: [
        "Quarter-finals",
        "Click the winner of each QF match to fill the connected Semi-final slot.",
        `${state.semi_finals.size} / 4 qualifiers`,
      ],
      sf: [
        "Semi-finals",
        "Click the winner of each SF match to fill the connected Final slot.",
        `${state.finalists.size} / 2 finalists`,
      ],
      final: [
        "Final",
        "Click one finalist to select the champion. Click again to clear the selection.",
        state.winner === null
          ? "Champion not selected"
          : `Champion: ${teamById.get(state.winner)?.name ?? ""}`,
      ],
    };
    const [stage, description, progress] = stageText[active];
    title.textContent = finished ? "Stage: Completed" : `Stage: ${stage}`;
    help.textContent = finished
      ? "This tournament is finalized and cannot be edited."
      : description;
    counter.textContent = progress;
  }

  // ============================================
  // EUROPA - Assign / remove teams in R16
  // ============================================
  function selectR16Slot(slot) {
    if (finished || activeRound() !== "selection") return;
    if (selectedSlot === slot && state.round_of_16.has(slot)) {
      state.round_of_16.delete(slot);
      selectedSlot = null;
    } else {
      selectedSlot = slot;
    }
    render();
  }

  function addTeam(teamId) {
    if (finished || activeRound() !== "selection" || selectedSlot === null)
      return;
    if (!teamById.has(teamId)) return;
    const map = state.round_of_16;
    const previousSlot = [...map.entries()].find(
      ([, id]) => id === teamId,
    )?.[0];
    if (previousSlot === selectedSlot) return;
    if (previousSlot) map.delete(previousSlot);
    map.set(selectedSlot, teamId);
    selectedSlot = null;
    render();
  }

  rowElements.forEach((row) => {
    row.querySelector(".europa-add-team")?.addEventListener("click", () => {
      addTeam(Number(row.dataset.teamId));
    });
  });
  nationFilter.addEventListener("change", render);

  // ============================================
  // EUROPA - Live Team Search
  // ============================================

  teamSearch.addEventListener("input", render);

  // ============================================
  // EUROPA - Select winners from completed prior round
  // ============================================
  function chooseWinner(round, slot) {
    if (finished) return;
    const current = activeRound();
    const config = {
      r16: ["r16", "round_of_16", "quarter_finals"],
      qf: ["qf", "quarter_finals", "semi_finals"],
      sf: ["sf", "semi_finals", "finalists"],
      final: ["final", "finalists", null],
    };
    if (!(round in config) || current !== config[round][0]) return;
    const [, field, next] = config[round];
    const teamId = state[field].get(slot);
    if (!teamId) return;

    if (next === null) {
      state.winner = state.winner === teamId ? null : teamId;
    } else {
      const target = parentSlot(next, slot);
      if (!target) return;
      if (state[next].get(target) === teamId) {
        state[next].delete(target);
      } else {
        state[next].set(target, teamId);
      }
    }
    render();
  }

  Object.entries(slotsByRound).forEach(([round, elements]) => {
    elements.forEach((element) => {
      const slot = element.dataset.slot;
      const activate = () => {
        if (round === "r16" && activeRound() === "selection") {
          selectR16Slot(slot);
        } else {
          chooseWinner(round, slot);
        }
      };
      element.addEventListener("click", activate);
      element.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          activate();
        }
      });
    });
  });

  // ============================================
  // EUROPA - Save all progress, including null winner
  // ============================================
  form.addEventListener("submit", (event) => {
    if (finished) {
      event.preventDefault();
      return;
    }
    const data = {
      round_of_16: Object.fromEntries(state.round_of_16),
      quarter_finals: Object.fromEntries(state.quarter_finals),
      semi_finals: Object.fromEntries(state.semi_finals),
      finalists: Object.fromEntries(state.finalists),
      winner: state.winner,
    };
    document.getElementById("europa-save-data").value = JSON.stringify(data);
  });

  render();
});
