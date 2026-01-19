<?php

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'tienda_pescadores';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn-> connect_error) {
    die("Error de conexion: ". $conn->connect_error);
}

?>