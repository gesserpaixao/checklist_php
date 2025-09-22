<?php
// Certifique-se de que a vari\u00e1vel $u (usu\u00e1rio) e as fun\u00e7\u00f5es is...() est\u00e3o dispon\u00edveis.
// Por isso, o arquivo auth.php deve ser inclu\u00eddo antes deste.

// A vari\u00e1vel $u \u00e9 global, mas \u00e9 uma boa pr\u00e1tica pass\u00e1-la ou garantir que ela est\u00e1 no escopo.
// Se `auth.php` j\u00e1 foi inclu\u00eddo, a vari\u00e1vel $u est\u00e1 dispon\u00edvel.

$u = currentUser();

?>
<header class="header-painel">
    <h1>Painel</h1>
    <ul class="main-menu">
        <li><a href="dashboard.php"><i class="fa-solid fa-clipboard-list"></i>Dashboard</a></li>
    </ul>
    <div class="user-info">
        <span><?= htmlspecialchars($u['nome']) ?> (<?= htmlspecialchars($u['perfil']) ?>)</span>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>Sair</a>
    </div>
</header>
<ul class="main-menu">
    menu horizontal com esconder/aparecer 
</ul>
