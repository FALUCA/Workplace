<?php
	
	/**
	 * @author Fabio Castilhos <fabcastilhos@gmail.com> 
	 * @copyright GNU © 2020-2023, HappyHouse. 
	 * @access protected (public, protected e private) 
	 * @name config.php 
	 * @package Workplace
	 * @subpackage Workplace/Provision
	 * @version 1.0	 
	*/
	
	// Configuração do banco de dados
	$host     = 'seu_host_do_banco_de_dados';
	$username = 'seu_nome_de_usuario_do_banco_de_dados';
	$password = 'sua_senha_do_banco_de_dados';
	$database = 'seu_nome_do_banco_de_dados';
	
	// Conexão com o banco de dados
	$conn = new mysqli($host, $username, $password, $database);
	
	// Verificação da conexão
	if ($conn->connect_error) {
		die("Conexão falhou: " . $conn->connect_error);
	}
?>
