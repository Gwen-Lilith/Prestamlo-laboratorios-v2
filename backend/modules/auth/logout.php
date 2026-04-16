<?php
/**
 * Endpoint: Logout (cerrar sesión)
 * Método: POST o GET
 * 
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';

Auth::logout();
Response::json(null, 200, 'Sesión cerrada correctamente.');
