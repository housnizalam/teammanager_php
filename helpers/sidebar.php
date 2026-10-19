
<?php
$currentPage = basename($_SERVER["SCRIPT_NAME"]);
?>

<aside class="app-sidebar">

    <div class="app-logo">
        <div>FOOTBALL</div>
        <div><span>MANAGER</span></div>
    </div>

    <nav class="app-nav">

        <a href="/teammanager/index.php"
           class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
            Home
        </a>

        <a href="/teammanager/teams/teams.php"
           class="<?= $currentPage == 'teams.php' ? 'active' : '' ?>">
            Teams
        </a>

        <a href="/teammanager/players/players.php"
           class="<?= $currentPage == 'players.php' ? 'active' : '' ?>">
            Players
        </a>

        <a href="/teammanager/archive/teams_archive.php"
           class="<?= $currentPage == 'teams_archive.php' ? 'active' : '' ?>">
            Teams Archive
        </a>

        <a href="/teammanager/archive/players_archive.php"
           class="<?= $currentPage == 'players_archive.php' ? 'active' : '' ?>">
            Players Archive
        </a>

    </nav>

</aside>
