<?php
// inc/auth.php
// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui o arquivo de funções CSV
require_once __DIR__ . '/csv.php';

function login($nome, $senha) {
    $usuarios_data = csvRead(__DIR__ . '/../data/usuarios.csv');
    if ($usuarios_data === false || !isset($usuarios_data['rows'])) {
        return false;
    }
    
    $usuarios = array_map(function($row) use ($usuarios_data) {
        if (count($row) === count($usuarios_data['header'])) {
             return array_combine($usuarios_data['header'], $row);
        }
        return false;
    }, $usuarios_data['rows']);

    foreach ($usuarios as $u) {
        if ($u && strtolower($u['nome']) === strtolower($nome)) {
            // Tenta verificar com password_verify (para senhas criptografadas)
            if (password_verify($senha, $u['senha'])) {
                $_SESSION['user'] = $u;
                return true;
            }
            // Fallback para senhas em texto simples
            if ($u['senha'] === $senha) {
                $_SESSION['user'] = $u;
                return true;
            }
        }
    }
    return false;
}

function logout() {
    session_unset();
    session_destroy();
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

function requireLogin() {
    if (!currentUser()) {
        header('Location: index.php');
        exit;
    }
}

function isSupervisor() {
    $u = currentUser();
    // Um supervisor também é um master
    return $u && ($u['perfil'] === 'supervisor' || $u['perfil'] === 'master');
}

function isAdministrador() {
    $u = currentUser();
    // Um administrador também é um master
    return $u && ($u['perfil'] === 'administrador' || $u['perfil'] === 'master');
}

function isMecanica() {
    $u = currentUser();
    // Um mecânico também é um master
    return $u && ($u['perfil'] === 'mecanico' || $u['perfil'] === 'master');
}

function isOperador() {
    $u = currentUser();
    // Um operador também é um master
    return $u && ($u['perfil'] === 'operador' || $u['perfil'] === 'master');
}

function isMaster() {
    $u = currentUser();
    return $u && $u['perfil'] === 'master';
}
?>
