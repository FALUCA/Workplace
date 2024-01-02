<?php
	
	/**
	 * ChatBot - Modelo HelpDesk 
	 * @author Fabio Castilhos <fabio.castilhos.net.br> 
	 * @copyright GPL © 2020-2023, HappyHouse. 
	 * @access protected (public, protected e private) 
	 * @name webhook_support_bot.php 
	 * @package Workplace_PoC
	 * @subpackage PHP/HelpdeskBot
	 * @version 1.0	 
	*/
	
	// Habilitar erros para depuração.
	// Comentar antes de enviá-lo para produção
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	
	// Inicializar Variáveis Integração 
	require_once 'config.php';
	
	// Responder ao desafio ao salvar as alterações dos webhooks no painel Integrações do Workplace
	if (isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] == $verify_token) {
		echo $_GET['hub_challenge'];
		logging_to_txt_file("Webhook inscrito/modificado");
		exit;
	}

	// CÓDIGO PARA VERIFICAR AS SOLICITAÇÕES DO WEBHOOK - Obtendo os cabeçalhos e comparando a assinatura
	$headers = getallheaders();
	$request_body = file_get_contents('php://input');
	$signature = "sha1=" . hash_hmac('sha1', $request_body, $app_secret);
	
	
	if (!isset($headers['X-Hub-Signature']) || ($headers['X-Hub-Signature'] != $signature)) {
		logging_to_txt_file("X-Hub-Signature não correspondente");
        exit("X-Hub-Signature não correspondente");
	}
	
	// Obter dados enviados pelo webhook
	$data = json_decode($request_body, true);
	logging_to_txt_file($request_body);

	// Obter o ID do destinatário dos dados do evento do webhook
	$recipient = $data['entry'][0]['messaging'][0]['sender']['id'];
	
	// Obter mensagem dos dados do evento do webhook
	$received_text = $data['entry'][0]['messaging'][0]['message']['text'];

	// Configurar curl para interagir com a API Workplace Messaging
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,"https://graph.facebook.com/v13.0/me/messages");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
		'Content-Type:application/json',
		'User-Agent:GithubRep-SupportBot',
		'Authorization:Bearer ' . $access_token
	));
	
	// Enviar marca como visto ao usuário que enviou a mensagem enquanto a processamos
	// Esta ação é opcional
	$fields = array(
		"sender_action" => "mark_seen",
		"recipient"     => array("id" => $recipient)
	);
	
	$fields_string = json_encode($fields);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

	$server_output = curl_exec($ch); // Opcionalmente, podemos processar a saída do servidor

	// Compor uma resposta diferente dependendo do texto que o usuário enviou ao bot
	if ("support" == $received_text) { // Quando a palavra support é recebida
		$fields = array(
			"message" => array(
				"text" => "O que você está procurando?",
				"quick_replies" => array(
					array(
						"content_type" => "text",
						"title" => "Problema no dispositivo",
						"payload" => "device_issue",
						"image_url" => "https://img.icons8.com/computer-support"
					),
					array(
						"content_type" => "text",
						"title" => "Relatar Incidente",
						"payload" => "report_incidence",
						"image_url" => "https://img.icons8.com/solve"
					),
					array(
						"content_type" => "text",
						"title" => "Solicitar acesso à ferramenta",
						"payload" => "request_access",
						"image_url" => "https://img.icons8.com/lock"
					)
				)
			),
			"recipient" => array("id" => $recipient),
			"messaging_type" => "RESPONSE"
		);
	} 
	else {
		if (isset($data['entry'][0]['messaging'][0]['message']['quick_reply']['payload'])) { // Verificamos se uma carga útil foi recebida do webhook, ou seja, se algum botão de resposta rápida foi pressionado
			$quick_reply = $data['entry'][0]['messaging'][0]['message']['quick_reply']['payload'];
			if ("device_issue" == $quick_reply) { // Quando o botão de resposta rápida é o do problema do dispositivo
				$fields = array(
					"message" => array(
						"text" => "Você poderia nos dizer qual dispositivo foi afetado?",
						"quick_replies" => array(
	                		array(
								"content_type" => "text",
								"title"        => "Problema de computador",
								"payload"      => "computer_issue",
								"image_url"    => "https://img.icons8.com/computer"
							),
							array(
								"content_type" => "text",
								"title"        => "Problema de telefone celular",
								"payload"      => "mobile_issue",
								"image_url"    => "https://img.icons8.com/mobile"
							)
						)
					),
					"recipient" => array("id" => $recipient),
					"messaging_type" => "RESPONSE"
				);
			} 
			else if ("computer_issue" == $quick_reply) { // Quando o botão de resposta rápida é o do problema do computador
				$fields = array(
					"message"        => array("text" => "Um ticket foi aberto e alguém do Helpdesk deverá entrar em contato com você em breve para corrigi-lo"),
					"recipient"      => array("id" => $recipient),
					"messaging_type" => "RESPONSE"
				);
			} 
			else if ("mobile_issue" == $quick_reply) { // Quando o botão de resposta rápida é o do problema do celular
				$fields = array(
					"message"        => array("text" => "Um ticket foi aberto e alguém do Helpdesk deverá entrar em contato com você em breve para substituir seu dispositivo móvel"),
					"recipient"      => array("id" => $recipient),
					"messaging_type" => "RESPONSE"
				);
			}
		} 
		else {
			$fields = array(
				"message"        => array("text" => "Infelizmente não entendo esse comando. Por favor, tente com outro comando!"),
				"recipient"      => array("id" => $recipient),
				"messaging_type" => "RESPONSE"
			);
		}
	}
	
	// Enviar resposta para a API do Workplace para que a mensagem possa ser entregue ao usuário
	$fields_string = json_encode($fields);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	$server_output = curl_exec($ch);
	curl_close ($ch);

	// Processar adicional...
	if ($server_output == "OK") { 
		echo "CERTO!"; 
	} 
	else { 
		echo "NÃO. " . $server_output; 
	}

	// Gravar Log de Execução
	function logging_to_txt_file($text_to_log) {
		$fp           = fopen('my_log_file.txt', 'a');
		$datetime_now = date('Y-m-d H:i:s');
		fwrite($fp, '[' . $datetime_now . '] ' . $text_to_log . "\r\n");
		fclose($fp);
	}

?>