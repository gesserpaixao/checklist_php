<?php
// Define o tempo de vida do cookie de sess\u00e3o para 1 hora (3600 segundos)
// Isso garante que a sess\u00e3o persista no navegador por um per\u00edodo definido. 150 = 2 minutos e meio
session_set_cookie_params(150);

// Inicia a sess\u00e3o se ainda n\u00e3o estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui o arquivo de fun\u00e7\u00f5es CSV
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
                $_SESSION['LAST_ACTIVITY'] = time(); // Define a hora da \u00faltima atividade
                return true;
            }
            // Fallback para senhas em texto simples
            if ($u['senha'] === $senha) {
                $_SESSION['user'] = $u;
                $_SESSION['LAST_ACTIVITY'] = time(); // Define a hora da \u00faltima atividade
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
    // Checa se o usu\u00e1rio n\u00e3o est\u00e1 logado
    if (!currentUser()) {
        header('Location: index.php');
        exit;
    }

    // Checa o tempo de inatividade
    // Define o tempo m\u00e1ximo de inatividade em segundos (30 minutos)
    $inactivity_timeout = 1800; 
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $inactivity_timeout)) {
        logout();
        header('Location: index.php?msg=sessao_expirada'); // Redireciona com uma mensagem de sess\u00e3o expirada
        exit;
    }

    // Atualiza o tempo da \u00faltima atividade para o tempo atual
    $_SESSION['LAST_ACTIVITY'] = time();
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function isSupervisor() {
    $u = currentUser();
    // Um supervisor tamb\u00e9m \u00e9 um master
    return $u && ($u['perfil'] === 'supervisor' || $u['perfil'] === 'master');
}

function isAdministrador() {
    $u = currentUser();
    // Um administrador tamb\u00e9m \u00e9 um master
    return $u && ($u['perfil'] === 'administrador' || $u['perfil'] === 'master');
}

function isMecanica() {
    $u = currentUser();
    // Um mec\u00e2nico tamb\u00e9m \u00e9 um master
    return $u && ($u['perfil'] === 'mecanico' || $u['perfil'] === 'master');
}

function isOperador() {
    $u = currentUser();
    // Um operador tamb\u00e9m \u00e9 um master
    return $u && ($u['perfil'] === 'operador' || $u['perfil'] === 'master');
}

function isMaster() {
    $u = currentUser();
    return $u && $u['perfil'] === 'master';
}

function isHse(): bool
{
    $user = currentUser();
    return ($user['perfil'] ?? '') === 'hse';
}

?>
