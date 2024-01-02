<?php
	
	/**
	 * ChatBot - Modelo FAQ 
	 * @author Fabio Castilhos <fabio.castilhos.net.br> 
	 * @copyright GPL © 2020-2023, HappyHouse. 
	 * @access protected (public, protected e private) 
	 * @name webhook_faq_bot.php 
	 * @package Workplace_PoC
	 * @subpackage PHP/FAQBot
	 * @version 1.0	 
	*/
	
	// Habilitar erros para depuração.
	// Comentar antes de enviá-lo para produção
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	// Inicializar Variáveis Integração 
	require_once 'config.php';

	// Monto a trade de termos e perguntas do bot de FAQ
	$questions_and_answers = array(
		"viagem"      => "https://work.workplace.com/work/knowledge/2640967899488641",
		"treinamento" => "https://work.workplace.com/work/knowledge/2640968196155278",
		"helpdesk"    => "https://work.workplace.com/work/knowledge/2640967599488671",
		"london"      => "https://work.workplace.com/work/knowledge/2730400090545421"
	);
	
	// Responder ao salvar as alterações dos webhooks no painel Integrações do Workplace
	if (isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] == $verify_token) {
		echo $_GET['hub_challenge'];
		logging_to_txt_file("Webhook inscrito/modificado");
		exit;
	}

	// CÓDIGO PARA VERIFICAR AS SOLICITAÇÕES DO WEBHOOK 
	
	// Obter os cabeçalhos e comparando a assinatura
	$headers      = getallheaders();
	$request_body = file_get_contents('php://input');
	
	$signature    = "sha1=" . hash_hmac('sha1', $request_body, $app_secret);
	
	if (!isset($headers['X-Hub-Signature']) || ($headers['X-Hub-Signature'] != $signature)) {
		logging_to_txt_file("X-Hub-Signature não correspondente");
        exit("X-Hub-Signature não correspondente");
	}
	
	// Obter dados enviados pelo webhook
	$request_body = file_get_contents('php://input');
	logging_to_txt_file($request_body);

	// Obter o ID do destinatário dos dados do evento do webhook
	$recipient = $data['entry'][0]['messaging'][0]['sender']['id'];

	// Obter mensagem dos dados do evento do webhook
	$received_text = $data['entry'][0]['messaging'][0]['message']['text'];
	
	// Configurar um curl para interagir com a API Workplace Messaging
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,"https://graph.facebook.com/v13.0/me/messages");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type:application/json',
		'User-Agent:GithubRep-HRFAQBot',
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
	$server_output = curl_exec($ch); //Opcionalmente, processar a saída do servidor


	// Montar a resposta dependendo do texto que o usuário enviou ao bot
	if ("faq" == $received_text) { // Quando a palavra faq é recebida
		$fields = array(
			"message"        => array("text" => "Olá! Obrigado por usar nosso serviço de perguntas frequentes. Por favor, digite uma palavra-chave ou qualquer dúvida que você possa ter."),
			"recipient"      => array("id" => $recipient),
			"messaging_type" => "RESPONSE"
		);
	} 
	else {
		// Cortar a frase para ver se há uma palavra que faz parte da minha matriz de perguntas e respostas
		$parts = explode(" ", $received_text);
		
		foreach ($parts as $part) {
			$part = strtolower($part);
			if (isset($questions_and_answers[$part])) {
				$answer = $questions_and_answers[$part];
				break;
			}
		}

		// Monta a resposta para a requisição
		if (isset($answer)) {
			$fields = array(
				"message"        => array("text" => "Encontrei esta resposta para você relacionada a " . ucfirst($part) . ": " . $answer),
				"recipient"      => array("id" => $recipient),
				"messaging_type" => "RESPONSE"
			);
		} 
		else {
			$fields = array(
				"message"        => array("text" => "Infelizmente não consegui encontrar uma resposta. Por favor, tente com outra pergunta!"),
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

	// Processamento adicional...
	if ($server_output == "OK") { 
		echo "CERTA!"; 
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