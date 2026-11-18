// ============================================
// TOURNAMENT AWARDS - Shared searchable player selector
// ============================================
(() => {
    for (const fieldset of document.querySelectorAll('.tournament-awards')) {
        const source = document.getElementById(fieldset.dataset.awardsSource);
        if (!source) continue;
        const players = JSON.parse(source.textContent).map(player => ({
            id: Number(player.id),
            name: String(player.name),
            team: String(player.team_name)
        }));

        for (const field of fieldset.querySelectorAll('.tournament-award-field')) {
            const search = field.querySelector('.tournament-award-search');
            const hidden = field.querySelector('input[type="hidden"]');
            const options = field.querySelector('.tournament-award-options');
            let matches = [];
            let activeIndex = -1;

            function closeOptions() {
                options.hidden = true;
                options.replaceChildren();
                search.setAttribute('aria-expanded', 'false');
                activeIndex = -1;
            }

            function choose(player) {
                hidden.value = String(player.id);
                search.value = player.name + ' — ' + player.team;
                search.setCustomValidity('');
                closeOptions();
            }

            function updateActive() {
                const buttons = options.querySelectorAll('button');
                buttons.forEach((button, index) => {
                    button.classList.toggle('is-active', index === activeIndex);
                });
            }

            function renderMatches() {
                const term = search.value.trim().toLocaleLowerCase();
                matches = term.length === 0 ? [] : players
                    .filter(player => (player.name + ' ' + player.team)
                        .toLocaleLowerCase().includes(term))
                    .slice(0, 10);

                options.replaceChildren();
                activeIndex = -1;
                for (const player of matches) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'tournament-award-option';
                    const name = document.createElement('strong');
                    name.textContent = player.name;
                    const team = document.createElement('span');
                    team.textContent = player.team;
                    button.append(name, team);
                    button.addEventListener('mousedown', event => event.preventDefault());
                    button.addEventListener('click', () => choose(player));
                    options.append(button);
                }
                options.hidden = matches.length === 0;
                search.setAttribute('aria-expanded', String(matches.length > 0));
            }

            search.addEventListener('input', () => {
                hidden.value = '';
                search.setCustomValidity('');
                renderMatches();
            });
            search.addEventListener('focus', () => {
                if (!hidden.value) renderMatches();
            });
            // A fully typed, unique player name is also an accepted choice.
            // Duplicate names still require explicit selection from suggestions.
            search.addEventListener('change', () => {
                if (hidden.value) return;
                const typed = search.value.trim().toLocaleLowerCase();
                const exact = players.filter(player =>
                    player.name.toLocaleLowerCase() === typed ||
                    (player.name + ' — ' + player.team).toLocaleLowerCase() === typed
                );
                if (exact.length === 1) choose(exact[0]);
            });
            search.addEventListener('keydown', event => {
                if (options.hidden || matches.length === 0) return;
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    activeIndex = event.key === 'ArrowDown'
                        ? (activeIndex + 1) % matches.length
                        : (activeIndex - 1 + matches.length) % matches.length;
                    updateActive();
                } else if (event.key === 'Enter' && activeIndex >= 0) {
                    event.preventDefault();
                    choose(matches[activeIndex]);
                } else if (event.key === 'Escape') {
                    closeOptions();
                }
            });
            search.addEventListener('blur', () => {
                // click on an option uses mousedown.preventDefault to retain focus
                if (!field.contains(document.activeElement)) closeOptions();
            });
        }

        const form = fieldset.closest('form');
        if (form) {
            form.addEventListener('submit', event => {
                for (const field of fieldset.querySelectorAll('.tournament-award-field')) {
                    const search = field.querySelector('.tournament-award-search');
                    const hidden = field.querySelector('input[type="hidden"]');
                    if (!hidden.value) {
                        // Apply the same exact-match rule when submitting directly.
                        const typed = search.value.trim().toLocaleLowerCase();
                        const exact = players.filter(player =>
                            player.name.toLocaleLowerCase() === typed ||
                            (player.name + ' — ' + player.team).toLocaleLowerCase() === typed
                        );
                        if (exact.length === 1) {
                            hidden.value = String(exact[0].id);
                            search.value = exact[0].name + ' — ' + exact[0].team;
                            search.setCustomValidity('');
                            continue;
                        }
                        event.preventDefault();
                        search.setCustomValidity('Select a player from the suggestions.');
                        search.reportValidity();
                        search.focus();
                        return;
                    }
                }
            });
        }
    }
})();
