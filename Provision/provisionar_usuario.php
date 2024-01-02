<?php
	
	/**
	 * Provisionar Usuário - Configurações Token 
	 * @author Fabio Castilhos <fabio.castilhos.net.br> 
	 * @copyright GNU © 2020-2023, HappyHouse. 
	 * @access protected (public, protected e private) 
	 * @name config.php 
	 * @package Workplace_PoC
	 * @subpackage PHP/ReconhecimentoBot
	 * @version 1.0	 
	*/
	
	require_once 'config.php';
	require_once 'accessToken.php'
	
	// Dados do novo usuário a serem provisionados
	$newUserData = [
		'userName'    => 'novo_usuario',
		'displayName' => 'Novo Usuário',
		'password'    => 'senha_gerada_aleatoriamente',
		'emails'      => [
			['value'  => 'novo_usuario@example.com', 'type' => 'work']
		],
		// Adicione outros atributos do usuário conforme necessário
	];
	
	// Endpoint da API SCIM 2.0 do Workplace from Meta para criar usuários
	$scimApiUrl = "https://api.workplace.com/scim/v2/Users";	
	$headers    = [
		'Authorization: Bearer ' . $accessToken,
		'Content-Type: application/scim+json',
	];

	// Inicializa a requisição cURL
	$ch = curl_init($scimApiUrl);
	
	// Configura as opções da requisição cURL
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newUserData));
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	// Executa a requisição cURL e obtém o response
	$response = curl_exec($ch);
	
	// Verifica se a requisição foi bem-sucedida (código de status 2xx)
	if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 201) {
		
		// Converte a resposta JSON em um array associativo
		$createdUser = json_decode($response, true);
		
		// Obtém o Access Code da extensão "AccountStatusDetails"
		$accessCode = isset($createdUser['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User']['AccountStatusDetails']['accessCode']) ? $createdUser['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User']['AccountStatusDetails']['accessCode'] : null;
		
		// Grava os dados do usuário e Access Code no banco de dados
		$sql  = "INSERT INTO users (user_id, username, display_name, email, access_code) VALUES (?, ?, ?, ?, ?)";
		$stmt = $conn->prepare($sql);

		// Verifica se a preparação da declaração foi bem-sucedida
		if($stmt) {
			$stmt->bind_param('sssss', $createdUser['id'], $createdUser['userName'], $createdUser['displayName'], $createdUser['emails'][0]['value'], $accessCode);
			$stmt->execute();
			$stmt->close();
			echo "Usuário provisionado com sucesso e dados gravados no banco de dados. Access Code: $accessCode";
		} 
		else {
			echo "Erro ao preparar a declaração SQL.";
		}
	} 
	else {
		echo "Erro na requisição: " . $response;
	}
	
	// Fecha a sessão cURL
	curl_close($ch);

	// Fecha a conexão com o banco de dados
	$conn->close();
	
	
?>
