// ============================================
// CHAMPIONS - Knockout Progress
// R16 Selection -> Quarter-finals -> Semi-finals
// ============================================

document.addEventListener("DOMContentLoaded", () => {
  // ============================================
  // CHAMPIONS - DOM References
  // ============================================

  const bracket = document.querySelector(".champions-bracket-board");
  const r16SavedElement = document.getElementById("champions-r16-saved");
  const qfSavedElement = document.getElementById("champions-qf-saved");
  const sfSavedElement = document.getElementById("champions-sf-saved");
  const finalSavedElement = document.getElementById("champions-final-saved");
  const winnerSavedElement = document.getElementById("champions-winner-saved");
  const saveForm = document.getElementById("champions-r16-save-form");

  if (
    !bracket ||
    !r16SavedElement ||
    !qfSavedElement ||
    !sfSavedElement ||
    !finalSavedElement ||
    !winnerSavedElement ||
    !saveForm
  ) {
    return;
  }
  // ============================================
  // CHAMPIONS - Finished tournament state
  // ============================================

  const tournamentFinished = bracket.dataset.finished === "1";

  const r16Elements = [
    ...document.querySelectorAll(".champions-bracket-slot-r16"),
  ];

  const qfElements = [
    ...document.querySelectorAll(".champions-bracket-slot-qf"),
  ];

  const sfElements = [
    ...document.querySelectorAll(".champions-bracket-slot-sf"),
  ];

  // ============================================
  // CHAMPIONS - Final DOM References
  // ============================================

  const finalElements = [
    ...document.querySelectorAll(".champions-bracket-slot-final"),
  ];

  const winnerNameElement = document.getElementById("champions-winner-name");

  const finalCounter = document.getElementById("champions-final-counter");

  const winnerStatus = document.getElementById("champions-winner-status");

  const teamElements = [...document.querySelectorAll(".champions-group-team")];

  const r16Counter = document.getElementById("champions-r16-counter");
  const qfCounter = document.getElementById("champions-qf-counter");
  const sfCounter = document.getElementById("champions-sf-counter");
  const stageTitle = document.getElementById("champions-current-stage");
  const stageHelp = document.getElementById("champions-stage-help");

  // ============================================
  // CHAMPIONS - State
  // ============================================

  const r16Slots = new Map();
  const quarterFinals = new Map();
  const semiFinals = new Map();
  const finalists = new Map();
  let winnerId = null;

  const teamById = new Map();
  const groupCounts = new Map();

  let activeSlot = null;

  // ============================================
  // CHAMPIONS - Matchup Mapping
  // ============================================

  const r16Matchups = {
    Q1: ["L1", "L2"],
    Q2: ["L3", "L4"],
    Q3: ["L5", "L6"],
    Q4: ["L7", "L8"],
    Q5: ["R1", "R2"],
    Q6: ["R3", "R4"],
    Q7: ["R5", "R6"],
    Q8: ["R7", "R8"],
  };

  const qfMatchups = {
    S1: ["Q1", "Q2"],
    S2: ["Q3", "Q4"],
    S3: ["Q5", "Q6"],
    S4: ["Q7", "Q8"],
  };

  // ============================================
  // CHAMPIONS - Semi-final Matchup Mapping
  // ============================================

  const sfMatchups = {
    F1: ["S1", "S2"],
    F2: ["S3", "S4"],
  };

  function getParentSlot(matchups, childSlot) {
    return Object.keys(matchups).find((parent) =>
      matchups[parent].includes(childSlot),
    );
  }

  // ============================================
  // CHAMPIONS - Index Group Teams
  // ============================================

  teamElements.forEach((element) => {
    const id = Number(element.dataset.teamId);

    teamById.set(id, {
      id,
      group: element.dataset.group,
      name:
        element.querySelector(".champions-group-name")?.textContent.trim() ??
        "",
      element,
    });

    element.setAttribute("role", "button");
    element.setAttribute("tabindex", "0");
  });

  // ============================================
  // CHAMPIONS - Restore Saved Progress
  // ============================================

  function restoreMap(source, target, allowedSlots) {
    const data = JSON.parse(source.textContent);
    const validSlots = new Set(allowedSlots);

    Object.entries(data).forEach(([slot, id]) => {
      const teamId = Number(id);

      if (validSlots.has(slot) && teamById.has(teamId)) {
        target.set(slot, teamId);
      }
    });
  }

  restoreMap(
    r16SavedElement,
    r16Slots,
    r16Elements.map((element) => element.dataset.slot),
  );

  restoreMap(qfSavedElement, quarterFinals, Object.keys(r16Matchups));

  restoreMap(sfSavedElement, semiFinals, Object.keys(qfMatchups));

  // ============================================
  // CHAMPIONS - Restore Final and Winner
  // ============================================

  restoreMap(finalSavedElement, finalists, Object.keys(sfMatchups));

  const savedWinnerId = JSON.parse(winnerSavedElement.textContent);

  if (Number.isInteger(savedWinnerId) && teamById.has(savedWinnerId)) {
    winnerId = savedWinnerId;
  }

  // ============================================
  // CHAMPIONS - Group Qualification Counts
  // ============================================

  function updateGroupCounts() {
    groupCounts.clear();

    r16Slots.forEach((teamId) => {
      const team = teamById.get(teamId);
      if (!team) return;

      groupCounts.set(team.group, (groupCounts.get(team.group) ?? 0) + 1);
    });
  }

  function isRoundOf16Complete() {
    return (
      r16Slots.size === 16 &&
      groupCounts.size === 8 &&
      [...groupCounts.values()].every((count) => count === 2)
    );
  }

  function areQuarterFinalsComplete() {
    return isRoundOf16Complete() && quarterFinals.size === 8;
  }

  // ============================================
  // CHAMPIONS - Determine Active Stage
  // ============================================

  function getActiveStage() {
    if (!isRoundOf16Complete()) {
      return "selection";
    }

    if (!areQuarterFinalsComplete()) {
      return "r16";
    }

    if (semiFinals.size < 4) {
      return "quarter_finals";
    }

    if (finalists.size < 2) {
      return "semi_finals";
    }

    return "final";
  }

  // ============================================
  // CHAMPIONS - Render All Stages
  // ============================================

  function render() {
    updateGroupCounts();

    const activeStage = getActiveStage();
    const selectedIds = new Set(r16Slots.values());

    // --------------------------------------------
    // CHAMPIONS - R16 Boxes
    // --------------------------------------------

    r16Elements.forEach((element) => {
      const slot = element.dataset.slot;
      const team = teamById.get(r16Slots.get(slot));

      const qfSlot = getParentSlot(r16Matchups, slot);

      const isWinner = Boolean(team && quarterFinals.get(qfSlot) === team.id);

      element.textContent = team?.name ?? slot;

      element.classList.toggle("champions-r16-filled", Boolean(team));

      element.classList.toggle(
        "champions-r16-active",
        activeStage === "selection" && activeSlot === slot,
      );

      element.classList.toggle("champions-r16-winner", isWinner);

      element.classList.toggle(
        "champions-knockout-locked",
        activeStage !== "selection" && activeStage !== "r16",
      );

      const r16Interactive =
        activeStage === "selection" || activeStage === "r16";

      element.setAttribute("tabindex", r16Interactive ? "0" : "-1");

      element.setAttribute("role", r16Interactive ? "button" : "text");

      element.title =
        activeStage === "selection"
          ? "Select a slot to assign a group team"
          : activeStage === "r16"
            ? "Click to choose the match winner"
            : "";
    });

    // --------------------------------------------
    // CHAMPIONS - Quarter-final Boxes
    // --------------------------------------------

    qfElements.forEach((element) => {
      const slot = element.dataset.slot;
      const team = teamById.get(quarterFinals.get(slot));

      const sfSlot = getParentSlot(qfMatchups, slot);

      const isWinner = Boolean(team && semiFinals.get(sfSlot) === team.id);

      element.textContent = team?.name ?? slot;

      element.classList.toggle("champions-qf-filled", Boolean(team));

      element.classList.toggle("champions-qf-winner", isWinner);

      element.classList.toggle(
        "champions-qf-clickable",
        activeStage === "quarter_finals",
      );

      element.setAttribute(
        "tabindex",
        activeStage === "quarter_finals" ? "0" : "-1",
      );

      element.setAttribute(
        "role",
        activeStage === "quarter_finals" ? "button" : "text",
      );

      element.title =
        activeStage === "quarter_finals"
          ? "Click to choose the semi-final qualifier"
          : "";
    });

    // --------------------------------------------
    // CHAMPIONS - Semi-final Boxes
    // --------------------------------------------

    sfElements.forEach((element) => {
      const slot = element.dataset.slot;
      const team = teamById.get(semiFinals.get(slot));

      element.textContent = team?.name ?? slot;

      element.classList.toggle("champions-sf-filled", Boolean(team));

      // Semi-final boxes are display-only in this step.
      element.setAttribute("tabindex", "-1");
    });

    // ============================================
    // CHAMPIONS - Render Semi-final Winners
    // ============================================

    sfElements.forEach((element) => {
      const slot = element.dataset.slot;
      const team = teamById.get(semiFinals.get(slot));

      const finalSlot = getParentSlot(sfMatchups, slot);

      const isWinner = Boolean(team && finalists.get(finalSlot) === team.id);

      element.classList.toggle("champions-sf-winner", isWinner);

      element.classList.toggle(
        "champions-sf-clickable",
        activeStage === "semi_finals",
      );

      element.setAttribute(
        "role",
        activeStage === "semi_finals" ? "button" : "text",
      );

      element.setAttribute(
        "tabindex",
        activeStage === "semi_finals" ? "0" : "-1",
      );

      element.title =
        activeStage === "semi_finals" ? "Click to select the finalist" : "";
    });

    // ============================================
    // CHAMPIONS - Render Finalists
    // ============================================

    finalElements.forEach((element) => {
      const slot = element.dataset.slot;
      const team = teamById.get(finalists.get(slot));

      element.textContent = team?.name ?? slot;

      element.classList.toggle("champions-final-filled", Boolean(team));

      element.classList.toggle(
        "champions-final-clickable",
        activeStage === "final",
      );

      element.classList.toggle(
        "champions-final-winner",
        Boolean(team && winnerId === team.id),
      );

      element.setAttribute("role", activeStage === "final" ? "button" : "text");

      element.setAttribute("tabindex", activeStage === "final" ? "0" : "-1");

      element.title =
        activeStage === "final" ? "Click to select the champion" : "";
    });

    // ============================================
    // CHAMPIONS - Render Winner
    // ============================================

    const champion = teamById.get(winnerId);

    winnerNameElement.textContent = champion?.name ?? "🏆";

    // --------------------------------------------
    // CHAMPIONS - Group Selection Status
    // --------------------------------------------

    teamElements.forEach((element) => {
      const id = Number(element.dataset.teamId);
      const group = element.dataset.group;

      const selected = selectedIds.has(id);
      const full = (groupCounts.get(group) ?? 0) >= 2;

      const locked = activeStage !== "selection";
      const unavailable = locked || (full && !selected);

      element.classList.toggle("champions-group-qualified", selected);

      element.classList.toggle(
        "champions-group-unavailable",
        unavailable && !selected,
      );

      element.classList.toggle("champions-group-locked", locked);

      element.setAttribute("aria-disabled", String(unavailable));

      element.setAttribute("tabindex", unavailable ? "-1" : "0");
    });

    document.querySelectorAll(".champions-group-card").forEach((card) => {
      const first = card.querySelector(".champions-group-team");
      if (!first) return;

      card.classList.toggle(
        "champions-group-complete",
        (groupCounts.get(first.dataset.group) ?? 0) === 2,
      );
    });

    // --------------------------------------------
    // CHAMPIONS - Progress Counters
    // --------------------------------------------

    r16Counter.textContent = `${r16Slots.size} / 16 selected`;

    qfCounter.textContent = `Quarter-finals: ${quarterFinals.size} / 8 winners`;

    sfCounter.textContent = `Semi-finals: ${semiFinals.size} / 4 qualifiers`;

    // ============================================
    // CHAMPIONS - Final Progress Counters
    // ============================================

    finalCounter.textContent = `Final: ${finalists.size} / 2 finalists`;

    winnerStatus.textContent =
      winnerId === null
        ? "Champion not selected"
        : `Champion: ${teamById.get(winnerId)?.name ?? ""}`;

    finalCounter.hidden =
      activeStage !== "semi_finals" && activeStage !== "final";

    winnerStatus.hidden = activeStage !== "final";

    qfCounter.hidden = activeStage === "selection";
    sfCounter.hidden = activeStage === "selection" || activeStage === "r16";

    // --------------------------------------------
    // CHAMPIONS - Current Stage Label
    // --------------------------------------------

    if (activeStage === "selection") {
      stageTitle.textContent = "Stage: Select Round of 16 Teams";

      stageHelp.textContent =
        "Select an R16 slot, then select a team from the groups. " +
        "Maximum two teams per group.";
    } else if (activeStage === "r16") {
      stageTitle.textContent = "Stage: Round of 16";

      stageHelp.textContent =
        "Click the winner of each R16 match. " +
        "The winner moves to its connected QF slot.";
    } else if (activeStage === "quarter_finals") {
      stageTitle.textContent = "Stage: Quarter-finals";

      stageHelp.textContent =
        "Click the winner of each QF match. " +
        "The winner moves to its connected SF slot.";
    } else if (activeStage === "semi_finals") {
      stageTitle.textContent = "Stage: Semi-finals";

      stageHelp.textContent =
        "Click the winner of each SF match. " +
        "The winner moves to its connected Final slot.";
    } else {
      stageTitle.textContent = "Stage: Final";

      stageHelp.textContent =
        winnerId === null
          ? "Click one of the two finalists to select the champion."
          : "Champion selected. Save to preserve the result.";
    }
  }

  // ============================================
  // CHAMPIONS - R16 Click Handler
  // ============================================

  function handleR16Click(slot) {
    if (tournamentFinished) return;
    const activeStage = getActiveStage();

    if (activeStage !== "selection" && activeStage !== "r16") {
      return;
    }

    if (activeStage === "r16") {
      const teamId = r16Slots.get(slot);
      const qfSlot = getParentSlot(r16Matchups, slot);

      if (!teamId || !qfSlot) return;

      if (quarterFinals.get(qfSlot) === teamId) {
        quarterFinals.delete(qfSlot);
      } else {
        quarterFinals.set(qfSlot, teamId);
      }

      render();
      return;
    }

    // Team selection stage.
    if (activeSlot === slot && r16Slots.has(slot)) {
      r16Slots.delete(slot);
      activeSlot = null;
    } else {
      activeSlot = slot;
    }

    render();
  }

  r16Elements.forEach((element) => {
    const slot = element.dataset.slot;

    element.addEventListener("click", () => {
      handleR16Click(slot);
    });

    element.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        handleR16Click(slot);
      }
    });
  });

  // ============================================
  // CHAMPIONS - QF Click Handler
  // ============================================

  function handleQfClick(slot) {
    if (tournamentFinished) return;
    if (getActiveStage() !== "quarter_finals") return;

    const teamId = quarterFinals.get(slot);
    const sfSlot = getParentSlot(qfMatchups, slot);

    if (!teamId || !sfSlot) return;

    if (semiFinals.get(sfSlot) === teamId) {
      semiFinals.delete(sfSlot);
    } else {
      semiFinals.set(sfSlot, teamId);
    }

    render();
  }

  qfElements.forEach((element) => {
    const slot = element.dataset.slot;

    element.addEventListener("click", () => {
      handleQfClick(slot);
    });

    element.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        handleQfClick(slot);
      }
    });
  });

  // ============================================
  // CHAMPIONS - Assign Group Team to R16
  // ============================================

  function selectTeam(teamId) {
    if (tournamentFinished) return;
    if (!activeSlot || getActiveStage() !== "selection") {
      return;
    }

    const team = teamById.get(teamId);
    if (!team) return;

    const existingSlot = [...r16Slots.entries()].find(
      ([, id]) => id === teamId,
    )?.[0];

    if (existingSlot === activeSlot) return;

    const count = groupCounts.get(team.group) ?? 0;

    if (!existingSlot && count >= 2) return;

    if (existingSlot) {
      r16Slots.delete(existingSlot);
    }

    r16Slots.set(activeSlot, teamId);
    activeSlot = null;

    render();
  }

  teamElements.forEach((element) => {
    const teamId = Number(element.dataset.teamId);

    element.addEventListener("click", (event) => {
      if (event.target.closest("button")) return;
      selectTeam(teamId);
    });

    element.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        selectTeam(teamId);
      }
    });
  });

  // ============================================
  // CHAMPIONS - Semi-final Click Handler
  // ============================================

  function handleSfClick(slot) {
    if (tournamentFinished) return;
    if (getActiveStage() !== "semi_finals") return;

    const teamId = semiFinals.get(slot);
    const finalSlot = getParentSlot(sfMatchups, slot);

    if (!teamId || !finalSlot) return;

    if (finalists.get(finalSlot) === teamId) {
      finalists.delete(finalSlot);
    } else {
      finalists.set(finalSlot, teamId);
    }

    render();
  }

  sfElements.forEach((element) => {
    const slot = element.dataset.slot;

    element.addEventListener("click", () => {
      handleSfClick(slot);
    });

    element.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        handleSfClick(slot);
      }
    });
  });

  // ============================================
  // CHAMPIONS - Final Click Handler
  // ============================================

  function handleFinalClick(slot) {
    if (tournamentFinished) return;
    if (getActiveStage() !== "final") return;

    const teamId = finalists.get(slot);
    if (!teamId) return;

    // Clicking the selected champion again clears it.
    winnerId = winnerId === teamId ? null : teamId;

    render();
  }

  finalElements.forEach((element) => {
    const slot = element.dataset.slot;

    element.addEventListener("click", () => {
      handleFinalClick(slot);
    });

    element.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        handleFinalClick(slot);
      }
    });
  });

  // ============================================
  // CHAMPIONS - Save All Current Progress
  // ============================================

  saveForm.addEventListener("submit", () => {
    if (tournamentFinished) return;
    document.getElementById("champions-r16-save-data").value = JSON.stringify(
      Object.fromEntries(r16Slots),
    );

    document.getElementById("champions-qf-save-data").value = JSON.stringify(
      Object.fromEntries(quarterFinals),
    );

    document.getElementById("champions-sf-save-data").value = JSON.stringify(
      Object.fromEntries(semiFinals),
    );

    // ============================================
    // CHAMPIONS - Save Final and Winner
    // ============================================

    document.getElementById("champions-final-save-data").value = JSON.stringify(
      Object.fromEntries(finalists),
    );

    document.getElementById("champions-winner-save-data").value =
      JSON.stringify(winnerId);
  });

  // ============================================
  // CHAMPIONS - Initial Render
  // ============================================

  render();
});
