<?php

/**
 * Apretaste Piropazo Service
 */
class Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _main (Request $request, Response $response)
	{
		// by default, open citas
		$this->_citas($request, $response);
	}

	/**
	 * Get dates for your profile
	 * 
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _citas(Request $request, Response $response)
	{
		// ensure your profile is completed
		if(
			empty($request->person->picture) ||
			empty($request->person->first_name) ||
			empty($request->person->gender) ||
			empty($request->person->sexual_orientation) ||
			$request->person->age < 10 || $request->person->age > 110 ||
			empty($request->person->country)
		){
			// get the edit response
			$request->extra_fields = "hide";
			return $this->_perfil ($request, $response);
		}

		// activate new users and people who left
		$this->activatePiropazoUser($request->person->id);

		// get the best match for the user
		$match = $this->getMatchFromCache($request->person);

		// if no matches, let the user know
		if( ! $match) {
			$content = [
				"header"=>"No hay citas",
				"icon"=>"sentiment_very_dissatisfied",
				"text" => "Esto es vergonsozo, pero no pudimos encontrar a nadie que vaya con usted. Por favor regrese más tarde, o cambie su perfil e intente nuevamente.",
				"button" => ["href"=>"PIROPAZO PERFIL", "caption"=>"Editar perfil"]];

			$response->setLayout('piropazo.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		$profileTags = [];
		$professionTags = [];
		$this->getTags($profileTags, $professionTags, $match);

		$match->profile_tags = implode(', ', $profileTags);
		$match->profession_tags = implode(', ', $professionTags);

		$match->country = $match->country == "cu" ? "Cuba" : "Otro";

		// erase unwanted properties in the object
		$properties = ["id","username", "first_name","gender","skin", "about_me", "profile_tags", "profession_tags", "picture","religion", "education", "country","location","age","online"];
		$match = $this->filterObjectProperties($properties, $match);

		// mark the last time the system was used
		$this->markLastTimeUsed($request->person->id);

		// get match images into an array and the content
		$images = ($match->picture) ? [$match->picture] : [];
		$images[] = Utils::getPathToService($response->serviceName)."/images/icon.png";
		$content = [
			"match" => $match,
			"menuicon" => "favorite"
		];

		// build the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('dates.ejs', $content, $images);
	}

	/**
	 * Say Yes to a match
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _si (Request $request, Response $response)
	{
		// get the emails from and to
		$idFrom = $request->person->id;
		$idTo = $request->input->data->id;
		if(empty($idTo)) return $response;

		// check if there is any previous record between you and that person
		$record = Connection::query("SELECT status FROM _piropazo_relationships WHERE id_from='$idTo' AND id_to='$idFrom'");

		// if they liked you, like too; if they dislike you, block
		if($record) {
			// if they liked you, create a match
			if($record[0]->status == "like") {
				// get the target @username
				$username = Connection::query("SELECT username FROM person WHERE id = $idFrom")[0]->username;

				// update to create a match
				Connection::query("UPDATE _piropazo_relationships SET status='match', expires_matched_blocked=CURRENT_TIMESTAMP WHERE id_from='$idTo' AND id_to='$idFrom'");

				// create notifications for both you and your date
				Utils::addNotification($idFrom, "Felicidades, ambos tu y @$username se han gustado", '{"command":"PIROPAZO PAREJAS"}', 'people');
				Utils::addNotification($idTo, "Felicidades, ambos tu y @{$request->person->username} se han gustado", '{"command":"PIROPAZO PAREJAS"}', "people");
			}

			// if they dislike you, block that match
			if($record[0]->status == "dislike") Connection::query("UPDATE _piropazo_relationships SET status='blocked', expires_matched_blocked=CURRENT_TIMESTAMP WHERE id_from='$idTo' AND id_to='$idFrom'");
			return $response;
		}

		// insert the new relationship
		$threeDaysForward = date("Y-m-d H:i:s", strtotime("+3 days"));
		Connection::query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE id_from='$idFrom' AND id_to='$idTo';
			INSERT INTO _piropazo_relationships (id_from,id_to,status,expires_matched_blocked) VALUES ('$idFrom','$idTo','like','$threeDaysForward');
			COMMIT");

		// remove match from the cache so it won't show again
		Connection::query("DELETE FROM _piropazo_cache WHERE user={$idFrom} AND suggestion={$idTo}");
	}

	/**
	 * Say No to a match
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _no (Request $request, Response $response)
	{
		// get the ids from and to
		$idFrom = $request->person->id;
		$idTo = $request->input->data->id;
		if(empty($idTo)) return $response;

		// mark the transaction as blocked
		Connection::query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE (id_from='$idFrom' AND id_to='$idTo') OR (id_to='$idFrom' AND id_from='$idTo');
			INSERT INTO _piropazo_relationships (id_from,id_to,status,expires_matched_blocked) VALUES ('$idFrom','$idTo','dislike',CURRENT_TIMESTAMP);
			COMMIT");

		// remove match from the cache so it won't show again
		Connection::query("DELETE FROM _piropazo_cache WHERE user={$idFrom} AND suggestion={$idTo}");
	}

	/**
	 * Flag a user's profile
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _reportar (Request $request, Response $response)
	{
		// do not allow empty codes
		$violatorId = $request->input->data->id;
		$violationCode = $request->input->data->violation;
		if(empty($violatorId) || empty($violationCode)) return $response;

		// save the report
		Connection::query("
			INSERT INTO _piropazo_reports (id_reporter, id_violator, type) 
			VALUES ({$request->person->id}, $violatorId, '$violationCode')");

		// say NO to the user
		$request->query = $violatorId;
		$this->_no($request, $response);
	}

	/**
	 * Get the list of matches for your user
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _parejas (Request $request, Response $response)
	{
		// activate new users and people who left
		$this->activatePiropazoUser($request->person->id);

		// get list of people whom you liked or liked you
		$matches = Connection::query("
			SELECT B.*, 'LIKE' AS type, A.id_to AS id, '' AS matched_on,datediff(A.expires_matched_blocked, CURDATE()) AS time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_to = B.id
			WHERE expires_matched_blocked > CURRENT_TIMESTAMP
			AND status = 'like'
			AND id_from = '{$request->person->id}'
			UNION
			SELECT B.*, 'WAITING' AS type, A.id_from AS id, '' AS matched_on, datediff(A.expires_matched_blocked, CURDATE()) AS time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_from = B.id
			WHERE expires_matched_blocked > CURRENT_TIMESTAMP
			AND status = 'like'
			AND id_to = '{$request->person->id}'
			UNION
			SELECT B.*, 'MATCH' AS type, A.id_from AS id, A.expires_matched_blocked AS matched_on, '' AS time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_from = B.id
			WHERE status = 'match'
			AND id_to = '{$request->person->id}'
			UNION
			SELECT B.*, 'MATCH' AS type, A.id_to AS id, A.expires_matched_blocked AS matched_on, '' AS time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_to = B.id
			WHERE status = 'match'
			AND id_from = '{$request->person->id}'");

		// if no matches, let the user know
		if(empty($matches)) {
			$content = [
				"header"=>"No tiene parejas",
				"icon"=>"sentiment_very_dissatisfied",
				"text" => "Por ahora nadie le ha pedido ser pareja suya ni usted le ha pedido a otros. Si esperaba ver a alguien aquí, es posible que el tiempo de espera halla vencido. No se desanime, hay muchos más peces en el océano.",
				"button" => ["href"=>"PIROPAZO CITAS", "caption"=>"Buscar Pareja"]];

			$response->setLayout('piropazo.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		// organize list of matches
		$liked = $waiting = $matched = $images = [];
		foreach ($matches as $match) {
			// get the full profile
			$match = Social::prepareUserProfile($match);

			// get the link to the image
			if($match->picture) $images[] = $match->picture;
			$match->matched_on = date('d/m/Y', strtotime($match->matched_on));

			// erase unwanted properties in the object
			$properties = ["id","username", "first_name", "gender","age","type","location","picture","matched_on","time_left","online"];
			$match = $this->filterObjectProperties($properties, $match);

			// count the number of each
			if($match->type == "LIKE") $liked[] = $match;
			if($match->type == "WAITING") $waiting[] = $match;
			if($match->type == "MATCH") $matched[] = $match;
		}

		// mark the last time the system was used
		$this->markLastTimeUsed($request->person->id);

		// get the number of flowers for the logged user 
		$myFlowers = Connection::query("SELECT flowers FROM _piropazo_people WHERE id_person={$request->person->id}");

		$images[] = Utils::getPathToService($response->serviceName)."/images/icon.png";

		// create response array
		$content = [
			"myflowers" => $myFlowers[0]->flowers,
			"liked" => $liked,
			"waiting" => $waiting,
			"matched" => $matched,
			"title" => "Parejas",
			"menuicon" => "people"];

		// Building the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('matches.ejs', $content, $images);
	}

	/**
	 * Sends a flower to another user
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _flor (Request $request, Response $response)
	{
		// check if you have enought flowers to send
		$flowers = Connection::query("SELECT id_person FROM _piropazo_people WHERE id_person='{$request->person->id}' AND flowers>0");
		if(empty($flowers)) {
			$content = [
				"header"=>"No tiene suficientes flores",
				"icon"=>"local_florist",
				"text" => "Actualmente usted no tiene suficientes flores para usar. Puede comprar algunas flores frescas en la tienda de Piropazo.",
				"button" => ["href"=>"PIROPAZO TIENDA", "caption"=>"Tienda"]];

			$response->setLayout('piropazo.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		// get the message sent with the flower
		$message = trim(Connection::escape($request->input->data->msg, 200));

		// get the recipient's username
		$username = Connection::query("SELECT username FROM person WHERE id='{$request->input->data->id}'")[0]->username;

		// send the flower and increase response time in 7 days
		Connection::query("
			INSERT INTO _piropazo_flowers (id_sender,id_receiver,message) VALUES ('{$request->person->id}','{$request->input->data->id}','$message');
			UPDATE _piropazo_people SET flowers=flowers-1 WHERE id_person='{$request->person->id}';
			UPDATE _piropazo_relationships SET expires_matched_blocked=ADDTIME(expires_matched_blocked,'168:00:00.00') WHERE id_from='{$request->person->id}' AND id_to='{$request->input->data->id}';");

		// create a notification for the user
		Utils::addNotification($request->input->data->id, "@{$request->person->username} le envia una flor: $message", '{"command":"PIROPAZO PAREJAS"}');

		// let the sender know the flower was delivered
		$content = [
			"header"=>"Su flor fue enviada",
			"icon"=>"local_florist",
			"text" => "@$username recibirá una notificación y seguro le contestará lo antes posible. También le hemos dado una semana extra para que responda.",
			"button" => ["href"=>"PIROPAZO PAREJAS", "caption"=>"Mis parejas"]];

		$images = [Utils::getPathToService($response->serviceName)."/images/icon.png"];

		$response->setLayout('piropazo.ejs');
		$response->setTemplate('message.ejs', $content);
	}

	/**
	 * Use a heart to highlight your profile
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _corazon (Request $request, Response $response)
	{
		// check if you have enought crowns
		$crowns = Connection::query("SELECT crowns FROM _piropazo_people WHERE id_person='{$request->person->id}' AND crowns > 0");
		$images = [Utils::getPathToService($response->serviceName)."/images/icon.png"];

		// return error response if the user has no crowns
		if(empty($crowns)) {
			$content = [
				"header"=>"No tiene suficientes corazones",
				"icon"=>"favorite",
				"text" => "Actualmente usted no tiene suficientes corazones para usar. Puede comprar más en la tienda de Piropazo.",
				"button" => ["href"=>"PIROPAZO TIENDA", "caption"=>"Tienda"]];

			$response->setLayout('piropazo.ejs');
			$response->setTemplate('message.ejs', $content, $images);

		}

		// set the crown and substract a crown
		Connection::query("UPDATE _piropazo_people SET crowns=crowns-1, crowned=CURRENT_TIMESTAMP WHERE id_person={$request->person->id}");

		// post a notification for the user
		Utils::addNotification($request->person->id, "Enhorabuena, Usted se ha agregado un corazon. Ahora su perfil se mostrara a muchos más usuarios por los proximos tres dias", 'piropazo', 'favorite');

		// build the response
		$content = [
			"header"=>"Su perfil ha sido promovido",
			"icon"=>"favorite",
			"text" => "Su perfil ha sido promovido, y en los próximos tres días se mostrará muchas más veces a otros usuarios, lo cual mejorará sus chances de recibir solicitudes. Manténganse revisando a diario su lista de parejas.",
			"button" => ["href"=>"PIROPAZO PERFIL", "caption"=>"Ver perfil"]];

		$response->setLayout('piropazo.ejs');
		$response->setTemplate('message.ejs', $content, $images);
	}

	public function _conversacion(Request $request, Response $response){
		// get the username of the note
		$user = Utils::getPerson($request->input->data->userId);

		// check if the username is valid
		if(!$user){
			$response->setTemplate("notFound.ejs");
			return;
		}

		$messages = Social::chatConversation($request->person->id, $user->id);
		
		$chats = [];

		foreach ($messages as $message) {
			$chat = new stdClass();
			$chat->id = $message->note_id;
			$chat->username = $message->username;
			$chat->text = $message->text;
			$chat->sent = date_format((new DateTime($message->sent)), 'd/m/Y h:i a');
			$chat->read = date('d/m/Y h:i a', strtotime($message->read));
			$chat->readed = $message->readed;
			$chats[] = $chat;
		}

		$content =  [
			"messages" => $chats,
			"username" => $user->username,
			"myusername" => $request->person->username,
			"id" => $user->id,
			"online" => $user->online,
			"last" => date('d/m/Y h:i a', strtotime($user->last_access)),
			"title" => $user->first_name
		];

		$response->setlayout('piropazo.ejs');
		$response->setTemplate("conversation.ejs", $content);
	}

	/**
	 * Open the store
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _tienda (Request $request, Response $response)
	{
		// get the user items
		$user = Connection::query("
			SELECT flowers, crowns
			FROM _piropazo_people
			WHERE id_person = {$request->person->id}")[0];

		// get the user credit
		$credit = Connection::query("SELECT credit FROM person WHERE id={$request->person->id}")[0]->credit;

		// prepare content for the view
		$content = [
			"credit" => $credit,
			"flowers" => $user->flowers,
			"crowns" => $user->crowns,
			"menuicon" => "shopping_cart"
		];

		$images = [Utils::getPathToService($response->serviceName)."/images/icon.png"];

		// build the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('store.ejs', $content, $images);
	}

	/**
	 * Show a list of notifications
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _notificaciones (Request $request, Response $response)
	{

		// get all unread notifications
		$notifications = Connection::query("
			SELECT id,icon,`text`,link,inserted
			FROM notification
			WHERE `to` = {$request->person->id} 
			AND service = 'piropazo'
			ORDER BY inserted DESC");

		// if no notifications, let the user know
		if(empty($notifications)) {
			$content = [
				"header"=>"Nada por leer",
				"icon"=>"notifications_off",
				"text" => "Por ahora usted no tiene ninguna notificación por leer.",
				"button" => ["href"=>"PIROPAZO CITAS", "caption"=>"Buscar Pareja"]
			];

			$response->setLayout('piropazo.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		foreach($notifications as $noti) $noti->inserted = strtoupper(date('d/m/Y h:ia', strtotime(($noti->inserted))));

		// prepare content for the view
		$content = [
			"notifications" => $notifications,
			"title" => "Notificaciones",
			"menuicon" => "notifications"
		];

		$images = [Utils::getPathToService($response->serviceName)."/images/icon.png"];

		// build the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('notifications.ejs', $content, $images);
	}

	/**
	 * Exit the Piropazo network
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _salir (Request $request, Response $response)
	{
		// remove from piropazo
		Connection::query("UPDATE _piropazo_people SET active=0 WHERE id_person={$request->person->id}");

		// respond to user
		$content = [
			"header"=>"Ha salido de Piropazo",
			"icon"=>"directions_walk",
			"text" => "No recibirá más mensajes de otros usuarios ni aparecerá en la lista de Piropazo. Si revisa Piropazo nuevamente, su perfil será agregado automáticamente.",
			"button" => ["href"=>"SERVICIOS", "caption"=>"Otros Servicios"]];

		$images = [Utils::getPathToService($response->serviceName)."/images/icon.png"];
		
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('message.ejs', $content, $images);
	}

	/**
	 * Chats lists with matches filter
	 * 
	 * @author ricardo
	 * @param Request
	 * @param Response
	 */

	public function _chat(Request $request, Response $response)
	{
		// get the list of people chating with you
		$chats = Social::chatsOpen($request->person->id);

		$matches = Connection::query("SELECT id, first_name, picture FROM person WHERE id IN(SELECT id_from AS id
		FROM _piropazo_relationships
		WHERE status = 'match'
		AND id_to = '{$request->person->id}'
		UNION
		SELECT id_to AS id
		FROM _piropazo_relationships
		WHERE status = 'match'
		AND id_from = '{$request->person->id}')");

		// if no matches, let the user know
		if(empty($chats) || empty($matches)) {
			$content = [
				"header"=>"No tiene conversaciones",
				"icon"=>"sentiment_very_dissatisfied",
				"text" => "Aun no has hablado con nadie, busca pareja y animate a escribirle",
				"button" => ["href"=>"PIROPAZO", "caption"=>"Buscar pareja"],
				"title" => "chats",
				"menuicon" => "message"];

			$response->setLayout('piropazo.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		$matchesId = [];
		foreach($matches as $match) $matchesId[$match->id] = $match;

		$onlyMatchesChats = [];
		$images = [];
		foreach ($chats as $chat) {
			if (key_exists($chat->id,  $matchesId)){
				$chat->first_name = $matchesId[$chat->id]->first_name;
				$chat->picture = $matchesId[$chat->id]->picture;
				$chat->last_sent = explode(' ', $chat->last_sent)[0];
				$images[] = $chat->picture;
				$onlyMatchesChats[] = $chat;
			}
		}

		$content = [
			"chats" => $onlyMatchesChats,
			"myuserid" => $request->person->id,
			"title" => "Chat",
			"menuicon" => "message"
		];

		$response->setLayout('piropazo.ejs');
		$response->setTemplate("chats.ejs", $content, $images);
	}

	/**
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _escribir(Request $request, Response $response)
	{
		if(!isset($request->input->data->id)) return;
		$userTo = Utils::getPerson($request->input->data->id);
		if(!$userTo) return;
		$message = $request->input->data->message;

		$blocks = Social::isBlocked($request->person->id ,$userTo->id);
		if ($blocks->blocked>0 || $blocks->blockedByMe>0){
			Utils::addNotification(
				$request->person->id, 
				"Su mensaje para @{$userTo->username} no pudo ser entregado, es posible que usted haya sido bloqueado por esa persona.", 
				"{}",
				'error'
			);
			return;
		}
		
		// store the note in the database
		$message = Connection::escape($message, 499);
		Connection::query("INSERT INTO _note (from_user, to_user, `text`) VALUES ({$request->person->id},{$userTo->id},'$message')");

		// send notification for the app
		Utils::addNotification(
			$userTo->id,
			"@{$request->person->username} le ha enviado un mensaje",
			"{'command':'PIROPAZO CONVERSACION', 'data':{'userId':'{$request->person->id}'}}",
			'message'
		);
	}

	/**
	 * Open the user's profile
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _perfil(Request $request, Response $response)
	{
		// get the user's profile
		$id = isset($request->input->data->id) ? $request->input->data->id : $request->person->id;
		$isMyOwnProfile = $id == $request->person->id;
		$profile = Social::prepareUserProfile(Utils::getPerson($id));

		// get what gender do you search for
		if($profile->sexual_orientation == "BI") $profile->searchfor = "AMBOS";
		elseif($profile->gender == "M" && $profile->sexual_orientation == "HETERO") $profile->searchfor = "MUJERES";
		elseif($profile->gender == "F" && $profile->sexual_orientation == "HETERO") $profile->searchfor = "HOMBRES";		
		elseif($profile->gender == "M" && $profile->sexual_orientation == "HOMO") $profile->searchfor = "HOMBRES";
		elseif($profile->gender == "F" && $profile->sexual_orientation == "HOMO") $profile->searchfor = "MUJERES";
		else $profile->searchfor = "";

		// get array of images
		$images = [];
		if($profile->picture) {
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$wwwroot = $di->get('path')['root'];
			$images[] = $profile->picture;
		}

		// list of values
		$content = [
			"extra_fields" => isset($request->extra_fields) ? $request->extra_fields : "",
			"profile" => $profile,
			"isMyOwnProfile" => $isMyOwnProfile,
			"title" => "Perfil",
			"menuicon" => "person"
		];

		$images[] = Utils::getPathToService($response->serviceName)."/images/icon.png";

		// prepare response for the view
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('profile.ejs', $content, $images);
	}

	/**
	 * Show the list of support messages
	 *
	 * @param Request
	 * @param Response
	 */
	public function _soporte(Request $request, Response $response)
	{
		// @TODO replace email with ids
		$email = $request->person->email;
		$username = $request->person->username;

		// get the list of messages
		$tickets = Connection::query("
			SELECT A.*, B.username 
			FROM support_tickets A 
			JOIN person B
			ON A.from = B.email
			WHERE A.from = '$email' 
			OR A.requester = '$email' 
			ORDER BY A.creation_date ASC");

		// prepare chats for the view
		$chat = [];
		foreach($tickets as $ticket) {
			$message = new stdClass();
			$message->class = $ticket->from == $email ? "me" : "you";
			$message->from = $ticket->username;
			$message->text = preg_replace('/[\x00-\x1F\x7F]/u', '', $ticket->body);
			$message->date = date_format((new DateTime($ticket->creation_date)),'d/m/Y h:i a');
			$message->status = $ticket->status;
			$chat[] = $message;
		}

		// send data to the view
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('soporte.ejs',['messages' => $chat, 'myusername' => $username, "title" => "Soporte"]);
	}

	/**
	 * Get the person who best matches with you
	 *
	 * @author salvipascual
	 * @param Person $user
	 * @return Person
	 */
	private function getMatchFromCache($user)
	{
		// create cache if needed
		$this->createMatchesCache($user);

		// get one suggestion from cache
		$match = Connection::query("
			SELECT 
				A.id, A.suggestion AS user, 
				IFNULL(TIMESTAMPDIFF(DAY, B.crowned,NOW()),3) < 3 AS crown
			FROM _piropazo_cache A
			JOIN _piropazo_people B
			ON A.suggestion = B.id_person
			WHERE A.user = {$user->id}
			ORDER BY crown DESC, A.match DESC, A.id
			LIMIT 1");

		// return false if no match
		if(empty($match)) return false;
		else $match = $match[0];

		// return the best match as a Person object
		$person = Social::prepareUserProfile(Utils::getPerson($match->user));
		$person->crown = $match->crown;

		// get the match color class based on gender
		if($person->gender == "M") $person->color = "male";
		elseif($person->gender == "F") $person->color = "female";
		else $person->color = "neutral";

		// return the match
		return $person;
	}

	/**
	 * Create matches cache to speed up searches
	 *
	 * @author salvipascual
	 * @param Person $user, you
	 * @return Boolean
	 */
	private function createMatchesCache($user)
	{
		// do not cache if already exist data
		$isCache = Connection::query("SELECT COUNT(id) AS cnt FROM _piropazo_cache WHERE user = {$user->id}");
		if($isCache[0]->cnt > 0) return false;

		// filter based on sexual orientation
		switch ($user->sexual_orientation) {
			case 'HETERO': $clauseSex = "A.gender <> '$user->gender' AND A.sexual_orientation <> 'HOMO' "; break;
			case 'HOMO': $clauseSex = "A.gender = '$user->gender' AND A.sexual_orientation <> 'HETERO' "; break;
			case 'BI': $clauseSex = "(A.sexual_orientation = 'BI' OR (A.sexual_orientation = 'HOMO' AND A.gender = '$user->gender') OR (A.sexual_orientation = 'HETERO' AND A.gender <> '$user->gender')) "; break;
		}

		// get the list of people already voted
		$clauseVoted = [];
		$voted = Connection::query("
			SELECT id_to as id FROM _piropazo_relationships WHERE id_from = {$user->id} 
			UNION 
			SELECT id_from as id FROM _piropazo_relationships WHERE id_to = {$user->id}");
		if(empty($voted)) $clauseVoted = "''";
		else {
			foreach ($voted as $v) $clauseVoted[] = "'".$v->id."'";
			$clauseVoted = implode(",", $clauseVoted);
		}

		// select all users to filter by
		$clauseSubquery = "
			SELECT 
				A.id, A.username, A.first_name, A.date_of_birth, A.gender, A.province, A.city, A.picture, A.country, A.usstate, A.religion,
				IFNULL(TIMESTAMPDIFF(DAY, B.crowned,NOW()), 3) < 3 AS crown 
			FROM person A 
			JOIN _piropazo_people B
			ON A.id = B.id_person 
			AND B.id_person NOT IN ($clauseVoted) 
			AND A.active = 1 
			AND B.active = 1
			AND A.marital_status = 'SOLTERO' 
			AND NOT ISNULL(A.picture)
			AND $clauseSex 
			AND (IFNULL(TIMESTAMPDIFF(YEAR,date_of_birth,NOW()), 0) >= 17 OR A.date_of_birth IS NULL)
			AND NOT A.id = '$user->id'";

		// create final query with the match score
		$cacheUsers = Connection::query("
			SELECT id,
				(IFNULL(country, 'NO') = '$user->country') * 10 +
				(IFNULL(province, 'NO') = '$user->province') * 50 +
				(IFNULL(usstate, 'NO') = '$user->usstate') * 50 +
				(ABS(IFNULL(TIMESTAMPDIFF(YEAR,date_of_birth,NOW()), 0) - $user->age) <= 5) * 20 +
				crown * 25 +
				(IFNULL(religion, 'NO') = '$user->religion') * 20
				AS percent_match
			FROM ($clauseSubquery) AS results 
			HAVING percent_match > 0
			ORDER BY percent_match DESC
			LIMIT 50");

		// do not create cache if no suggestions were found
		if(empty($cacheUsers)) return false;

		// create the cache of suggestions
		$inserts = [];
		foreach ($cacheUsers as $c) $inserts[] = "({$user->id}, {$c->id}, {$c->percent_match})";
		Connection::query("INSERT INTO _piropazo_cache (`user`, suggestion, `match`) VALUES ".implode(",", $inserts));

		return true;
	}

	/**
	 * Make active if the person uses Piropazo for the first time, or if it was inactive
	 *
	 * @author salvipascual
	 * @param Int $id
	 */
	private function activatePiropazoUser($id)
	{
		Connection::query("INSERT INTO _piropazo_people (id_person) VALUES('$id') ON DUPLICATE KEY UPDATE active = 1");
	}

	/**
	 * Mark the last time the system was used by a user
	 *
	 * @author salvipascual
	 * @param Int $id
	 */
	private function markLastTimeUsed($id)
	{
		Connection::query("UPDATE _piropazo_people SET last_access=CURRENT_TIMESTAMP WHERE id_person='$id'");
	}

	/**
	 * Removs all properties in an object except the ones passes in the array
	 *
	 * @author salvipascual
	 * @param Array $properties, array of poperties to keep
	 * @param Object $object, object to clean
	 * @return Object, clean object
	 */
	private function filterObjectProperties($properties, $object)
	{
		$objProperties = get_object_vars($object);
		foreach($objProperties as $prop=>$value) {
			if( ! in_array($prop, $properties)) unset($object->$prop);
		}
		return $object;
	}

	/**
	 * Get the percentage of match for two profiles
	 *
	 * @author salvipascual
	 * @param Int $idOne
	 * @param Int $idTwo
	 * @return Number
	 */
	private function getPercentageMatch($idOne, $idTwo)
	{
		// get both profiles
		$p = Connection::query("
			SELECT eyes, skin, body_type, hair, highest_school_level, interests, lang, religion, country, usstate, province, date_of_birth 
			FROM person 
			WHERE id='$idOne' 
			OR id='$idTwo'");
		if(empty($p[0]) || empty($p[1])) return 0;

		// calculate basic values
		$percentage = 0;
		if($p[0]->eyes == $p[1]->eyes) $percentage += 5;
		if($p[0]->skin == $p[1]->skin) $percentage += 5;
		if($p[0]->body_type == $p[1]->body_type) $percentage += 5;
		if($p[0]->hair == $p[1]->hair) $percentage += 5;
		if($p[0]->highest_school_level == $p[1]->highest_school_level) $percentage += 10;
		if($p[0]->lang == $p[1]->lang) $percentage += 10;
		if($p[0]->religion == $p[1]->religion) $percentage += 10;
		if($p[0]->country == $p[1]->country) $percentage += 5;
		if($p[0]->usstate == $p[1]->usstate || $p[0]->province == $p[1]->province) $percentage += 10;

		// calculate interests
		$arrOne = explode(",", strtolower($p[0]->interests));
		$arrTwo = explode(",", strtolower($p[1]->interests));
		$intersect = array_intersect($arrOne, $arrTwo);
		if($intersect) $percentage += 20;

		// calculate age
		$ageOne = date("Y") - date("Y", strtotime($p[0]->date_of_birth));
		$ageTwo = date("Y") - date("Y", strtotime($p[1]->date_of_birth));
		$diff = abs($ageOne - $ageTwo);
		if($diff == 0) $percentage += 15;
		if($diff >= 1 && $diff <= 5) $percentage += 10;
		if($diff >= 6 && $diff <= 10) $percentage += 5;

		return $percentage;
	}

	private function getTags(&$profileTags, &$professionTags, $match){
		$genderLetter = $match->gender == 'M' ? 'o' : 'a';

		$profileTags[] = $match->gender == 'M' ? "Hombre" : "Mujer"; 
		$profileTags[] = substr(strtolower($match->skin), 0, -1) . $genderLetter;
		$profileTags[] = strtolower($match->religion);
		$profileTags[] = $match->age. " años";
		
		$professionTags[] = $match->highest_school_level;
		$professionTags[] = $match->occupation;
	}
}
