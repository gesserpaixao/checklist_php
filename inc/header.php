<?php
// Certifique-se de que a vari\u00e1vel $u (usu\u00e1rio) e as fun\u00e7\u00f5es is...() est\u00e3o dispon\u00edveis.
// Por isso, o arquivo auth.php deve ser inclu\u00eddo antes deste.

// A vari\u00e1vel $u \u00e9 global, mas \u00e9 uma boa pr\u00e1tica pass\u00e1-la ou garantir que ela est\u00e1 no escopo.
// Se `auth.php` j\u00e1 foi inclu\u00eddo, a vari\u00e1vel $u est\u00e1 dispon\u00edvel.

$u = currentUser();

?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<header class="header-painel">
 
    <h1><img src="assets/logo.jpg" alt="Logo da Empresa" class="header-logo">Painel</h1>
    <ul class="main-menu">
        <li><a href="dashboard.php"><i class="fa-solid fa-clipboard-list"></i>Dashboard</a></li>
    </ul>
    <div class="user-info">
        <span><?= htmlspecialchars($u['nome']) ?> (<?= htmlspecialchars($u['perfil']) ?>)</span>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>Sair</a>
    </div>
</header>
<ul class="main-menu">
    <?php if (isOperador() || isMaster()): ?>
        <li><a href="workplace.php"><i class="fa-solid fa-clipboard-list"></i>Workplace</a></li>
    <?php endif; ?>
    <?php if (isSupervisor() || isMaster()): ?>
        <li><a href="aprovacao.php"><i class="fa-solid fa-check-to-slot"></i>Aprovação</a></li>
        <li><a href="manutencao.php"><i class="fa-solid fa-truck-loading"></i>Manutenção</a></li>
    <?php endif; ?>
    <?php if (isMecanica()): ?>
        <li><a href="mecanica.php"><i class="fa-solid fa-wrench"></i>Mecânica</a></li>
    <?php endif; ?>
      <?php if (isHse() || isMaster()): ?>
        <li><a href="olharhse.php"><i class="fa-solid fa-shield-halved"></i>Visão HSE</a></li>
        <li><a href="investigacoes.php"><i class="fa-solid fa-check-to-slot"></i>Investigação</a></li>
        <li><a href="investigacao_form.php"><i class="fa-solid fa-truck-loading"></i>Inv. Form</a></li>
    <?php endif; ?>
    
    <?php if (isSupervisor() || isMaster()): ?>
        <li><a href="imprimir.php"><i class="fa-solid fa-print"></i>Imprimir</a></li>
        <!-- <li><a href="download.php"><i class="fa-solid fa-download"></i>Download</a></li> -->
    <?php endif; ?>
    <?php if (isMaster()): ?>
        <li><a href="admin.php"><i class="fa-solid fa-user-gear"></i>Administração</a></li>
    <?php endif; ?>
    <!-- <li><a href="dashboard.php"><i class="fa-solid fa-wrench"></i>Voltar</a></li> -->
</ul>
