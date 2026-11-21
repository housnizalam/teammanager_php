# Football Team Manager

A PHP-based web application for managing football teams, players, seasons, and competitions. It combines server-rendered PHP pages with MySQL, JavaScript, and CSS.

## Features visible in the source

- Create, view, update, and delete teams and players.
- Browse and filter team/player information and statistics.
- Manage leagues, Champions League, and Europa League competitions.
- Select Champions League teams into pots and progress through tournament stages.
- Save and load tournament progress as JSON files.
- End a season and access archived team/player information.

## Tech stack

- PHP and MySQL (MySQLi)
- HTML, CSS, and JavaScript
- JSON-based tournament progress storage

## Project structure

| Directory | Purpose |
| --- | --- |
| `teams/` | Team management pages |
| `players/` | Player management pages |
| `tournaments/` | League and European competition workflows |
| `season/` | Season-ending operations |
| `archive/` | Archived team and player views |
| `helpers/` | Shared database, query, UI and storage utilities |
| `assets/` | Styles, images and JavaScript |
| `storage/tournaments/` | Persisted tournament state |
| `index.php` | Application entry page |

## Running locally

1. Install a local PHP/MySQL environment such as XAMPP. Use a PHP version supporting `mysqli::execute_query()` (PHP 8.2+).
2. Place the repository in your web server's document root.
3. Create a MySQL database named `teammanager`.
4. Configure database access in `helpers/db.php` for your local installation.
5. Import the required database schema and sample data **from your own local setup**. A reproducible SQL schema/setup script has not been identified in the repository, so this step is not yet automated.
6. Start Apache and MySQL, then open `http://localhost/teammanager_php/` (adjust the folder/URL as needed).

> **Status:** This README describes the inspected source; an end-to-end installation or deployment has not been verified. The database schema is a known setup prerequisite.

## Security and deployment notes

- `helpers/db.php` contains local database connection settings. Replace these with secure environment-specific configuration before deployment.
- Review all data files and Git history for sensitive or personal information before making this repository public.
- Keep writable tournament storage protected from direct public access on a production server.
- Review authorization, input validation, CSRF protection, and backup behavior before deploying for real users.

## Planned documentation improvements

- Database schema and installation/sample-data instructions.
- Screenshots of teams, players, tournament setup, and bracket views.
- A feature-by-feature walkthrough and automated tests.

## Project context

A personal software development portfolio project demonstrating PHP application logic, relational data access, form handling, tournament state management, and a web-based administrative interface.
