<?php

/**
 * Apretaste Piropazo Service
 *
 * @version 2.0
 */
class Piropazo extends Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _main (Request $request)
	{
		// get the current user
		$user = Utils::getPerson($request->userId);

		// ensure your profile is completed
		if(
			empty($user->picture) ||
			empty($user->first_name) ||
			empty($user->gender) ||
			empty($user->sexual_orientation) ||
			$user->age < 10 || $user->age > 110 ||
			empty($user->country)
		) return $this->_inicio($request);

		// activate new users and people who left
		$this->activatePiropazoUser($request->userId);

		// get the best match for the user
		$match = $this->getMatchFromCache($user);

		// if no matches, let the user know
		if( ! $match) {
			$content = [
				"environment" => $request->environment,
				"header"=>"No encontramos a nadie",
				"icon"=>"&#x1F64D;",
				"text" => "Esto es vergonsozo, pero no pudimos encontrar a nadie que vaya con usted. Por favor regrese mas tarde, o cambie su perfil e intente nuevamente.",
				"button" => ["href"=>"PIROPAZO EDITAR", "caption"=>"Editar perfil"]];
			$response = new Response();
			$response->setEmailLayout('piropazo.tpl');
			$response->createFromTemplate('message.tpl', $content);
			return $response;
		}

		// calculate the tags
		$tags = [];
		if(array_intersect($match->interests, $user->interests)) $tags[] = "Intereses Similares";
		if(($match->city && ($match->city == $user->city)) || ($match->usstate && ($match->usstate == $user->usstate)) || ($match->province && ($match->province == $user->province))) $tags[] = "Viven Cerca";
		if(abs($match->age - $user->age) <= 3) $tags[] = "Igual Edad";
		if($match->religion && ($match->religion == $user->religion)) $tags[] = "Misma Religion";
		if($match->highest_school_level && ($match->highest_school_level == $user->highest_school_level)) $tags[] = "Misma Educacion";
		if($match->body_type == "ATLETICO") $tags[] = "Cita Caliente";
		$match->tags = array_slice($tags, 0, 2); // show only two tags

		// erase unwanted properties in the object
		$properties = ["username","gender","interests","about_me","picture","pictureURL","picture_public","picture_internal","crown","country","location","age","tags","online"];
		$match = $this->filterObjectProperties($properties, $match);

		// mark the last time the system was used
		$this->markLastTimeUsed($request->userId);

		// create response
		$content = [
			"environment" => $request->environment,
			"noProfilePic" => empty($user->picture),
			"noProvince" => empty($user->country) || ($user->country=="US" && empty($user->usstate)) || ($user->country=="CU" && empty($user->province)),
			"fewInterests" => count($user->interests) <= 3,
			"completion" => $user->completion,
			"person" => $match];

		// get match images into an array
		$images = [];
		if($match->picture) $images[] = $match->picture_internal;

		// get flag images for the web and internet app
		if(($request->environment == "web" || $request->environment == "appnet") && $match->country) {
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$wwwroot = $di->get('path')['root'];
			$images[] = "$wwwroot/public/images/flags/".strtolower($match->country).".png";
		}

		// build the response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('people.tpl', $content, $images);
		return $response;
	}

	/**
	 * Edit the user profile at start
	 * 
	 * @author salvipascual
	 */
	public function _inicio(Request $request)
	{
		// get the person to edit profile
		$person = Utils::getPerson($request->email);
		if (empty($person)) return new Response();

		// make the person's text readable
		$person->province = str_replace("_", " ", $person->province);
		if ($person->gender == 'M') $person->gender = "Masculino";
		if ($person->gender == 'F') $person->gender = "Femenino";
		$person->country_name = Utils::getCountryNameByCode($person->country);
		$person->usstate_name = Utils::getStateNameByCode($person->usstate);
		$person->interests = count($person->interests);
		$image = $person->picture ? [$person->picture_internal] : [];
		$person->province = str_replace("_", " ", $person->province);
		$person->years = implode(",", array_reverse(range(date('Y')-90, date('Y')-10)));

		// prepare response for the view
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('profile_min.tpl', ["person"=>$person], $image);
		return $response;
	}

	/**
	 * Say Yes to a match
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _si (Request $request)
	{
		// get the emails from and to
		$username = str_replace("@", "", trim($request->query));
		$idFrom = $request->userId;
		$idTo = Utils::getIdFromUsername($username);
		if( ! $idTo) return new Response();

		// check if there is any previous record between you and that person
		$record = Connection::query("SELECT status FROM _piropazo_relationships WHERE id_from='$idTo' AND id_to='$idFrom'");

		// get the person From from the database
		$personFrom = Utils::getPerson($idFrom);

		// if they liked you, like too, if they dislike you, block
		if( ! empty($record))
		{
			// if they liked you, create a match
			if($record[0]->status == "like")
			{
				// update to create a match and let you know of the match
				Connection::query("UPDATE _piropazo_relationships SET status='match', expires_matched_blocked=CURRENT_TIMESTAMP WHERE id_from='$idTo' AND id_to='$idFrom'");
				Utils::addNotification($idFrom, "piropazo", "Felicidades, ambos tu y @$username se han gustado, ahora pueden chatear", "PIROPAZO CHAT @$username");

				// let the other person know of the match
				Utils::addNotification($idTo, "piropazo", "Felicidades, ambos tu y @{$personFrom->username} se han gustado, ahora pueden chatear", "PIROPAZO CHAT @{$personFrom->username}");
			}

			// if they dislike you, block that match
			if($record[0]->status == "dislike") Connection::query("UPDATE _piropazo_relationships SET status='blocked', expires_matched_blocked=CURRENT_TIMESTAMP WHERE id_from='$idTo' AND id_to='$idFrom'");
			return new Response();
		}

		// insert the new relationship
		$threeDaysForward = date("Y-m-d H:i:s", strtotime("+3 days"));
		Connection::query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE id_from='$idFrom' AND id_to='$idTo';
			INSERT INTO _piropazo_relationships (id_from,id_to,status,expires_matched_blocked) VALUES ('$idFrom','$idTo','like','$threeDaysForward');
			COMMIT");

		// send a notification to the user
		Utils::addNotification($idTo, "piropazo", "El usuario @{$personFrom->username} ha mostrado interes en ti, deberias revisar su perfil.", "PIROPAZO parejas");

		// remove match from the cache so it won't show again
		Connection::query("DELETE FROM _piropazo_cache WHERE user={$idFrom} AND suggestion={$idTo}");

		// return empty response
		return new Response();
	}

	/**
	 * Say No to a match
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _no (Request $request)
	{
		// get the ids from and to
		$idFrom = $request->userId;
		$idTo = Utils::getIdFromUsername($request->query);
		if(empty($idTo)) return new Response();

		// insert the new relationship
		Connection::query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE (id_from='$idFrom' AND id_to='$idTo') OR (id_to='$idFrom' AND id_from='$idTo');
			INSERT INTO _piropazo_relationships (id_from,id_to,status,expires_matched_blocked) VALUES ('$idFrom','$idTo','dislike',CURRENT_TIMESTAMP);
			COMMIT");

		// remove match from the cache so it won't show again
		Connection::query("DELETE FROM _piropazo_cache WHERE user={$idFrom} AND suggestion={$idTo}");

		// do not return anything
		return new Response();
	}

	/**
	 * Say Yes to a match and return next match
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _siNext (Request $request)
	{
		$this->_si($request);
		return $this->_main($request);
	}

	/**
	 * Say No to a person and return the profile
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _noNext (Request $request)
	{
		$this->_no($request);
		return $this->_main($request);
	}

	/**
	 * Say Yes to a person and return the matches
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _siParejas (Request $request)
	{
		$this->_si($request);
		return $this->_parejas($request);
	}

	/**
	 * Say No to a person and return next matches
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _noParejas (Request $request)
	{
		$this->_no($request);
		return $this->_parejas($request);
	}

	/**
	 * Flag a user's profile
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _reportar (Request $request)
	{
		// do not report inexistant people
		$violatorUsername = $request->params[0];
		$violator = Utils::getIdFromUsername($violatorUsername);
		if(empty($violator)) return new Response();

		// get code from text
		$text = trim($request->params[1]);
		if(php::exists($text, "ofensivo")) $text = "OFFENSIVE";
		if(php::exists($text, "info")) $text = "FAKE";
		if(php::exists($text, "no luce")) $text = "MISLEADING";
		if(php::exists($text, "impersonando")) $text = "IMPERSONATING";
		if(php::exists($text, "autor")) $text = "COPYRIGHT";

		// only acept the types allowed
		$text = strtoupper($text);
		if( ! in_array($text, ['OFFENSIVE','FAKE','MISLEADING','IMPERSONATING','COPYRIGHT'])) return new Response();

		// save the report
		Connection::query("INSERT INTO _piropazo_reports (id_reporter,id_violator,type) VALUES ({$request->userId}, $violator, '$text')");

		// say NO to the user
		$request->query = $violatorUsername;
		return $this->_no($request);
	}

	/**
	 * Get the list of matches for your user
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _parejas (Request $request)
	{
		// activate new users and people who left
		$this->activatePiropazoUser($request->userId);

		// get list of people whom you liked or liked you
		$matches = Connection::query("
			SELECT B.*, 'LIKE' AS type, A.id_to AS id, '' AS matched_on,datediff(A.expires_matched_blocked, CURDATE()) AS time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_to = B.id
			WHERE expires_matched_blocked > CURRENT_TIMESTAMP
			AND status = 'like'
			AND id_from = '{$request->userId}'
			UNION
			SELECT B.*, 'WAITING' AS type, A.id_from AS id, '' AS matched_on, datediff(A.expires_matched_blocked, CURDATE()) AS time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_from = B.id
			WHERE expires_matched_blocked > CURRENT_TIMESTAMP
			AND status = 'like'
			AND id_to = '{$request->userId}'
			UNION
			SELECT B.*, 'MATCH' AS type, A.id_from AS id, A.expires_matched_blocked AS matched_on, '' AS time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_from = B.id
			WHERE status = 'match'
			AND id_to = '{$request->userId}'
			UNION
			SELECT B.*, 'MATCH' AS type, A.id_to AS id, A.expires_matched_blocked AS matched_on, '' AS time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_to = B.id
			WHERE status = 'match'
			AND id_from = '{$request->userId}'");

		// if no matches, let the user know
		if(empty($matches)) {
			$content = [
				"environment" => $request->environment,
				"header"=>"Por ahora no tiene parejas",
				"icon"=>"&#x1F64D;",
				"text" => "Por ahora nadie le ha pedido ser pareja suya ni usted le ha pedido a otros. Si esperaba ver a alguien aqu&iacute;, es posible que el tiempo de espera halla vencido. No se desanime, hay muchos peces en el oc&eacute;ano.",
				"button" => ["href"=>"PIROPAZO", "caption"=>"Buscar Pareja"]];

			$response = new Response();
			$response->setEmailLayout('piropazo.tpl');
			$response->createFromTemplate('message.tpl', $content);
			return $response;
		}

		// initialize counters
		$likeCounter = 0;
		$waitingCounter = 0;
		$matchCounter = 0;
		$images = array();

		// organize list of matches
		$social = new Social();
		foreach ($matches as $match) {
			// count the number of each
			if($match->type == "LIKE") $likeCounter++;
			if($match->type == "WAITING") $waitingCounter++;
			if($match->type == "MATCH") $matchCounter++;

			// get the full profile
			$match = $social->prepareUserProfile($match);

			// get the link to the image
			if($match->picture) $images[] = $match->picture_internal;

			// erase unwanted properties in the object
			$properties = ["username","gender","age","type","location","picture","picture_public","picture_internal","matched_on","time_left","country","online"];
			$match = $this->filterObjectProperties($properties, $match);
		}

		// mark the last time the system was used
		$this->markLastTimeUsed($request->userId);

		// create response array
		$responseArray = array(
			"environment" => $request->environment,
			"likeCounter" => $likeCounter,
			"waitingCounter" => $waitingCounter,
			"matchCounter" => $matchCounter,
			"people"=>$matches);

		// get flag images for the web
		if(($request->environment == "web" || $request->environment == "appnet")) {
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$wwwroot = $di->get('path')['root'];
			foreach ($matches as $match) {
				if($match->country) $images[] = "$wwwroot/public/images/flags/".strtolower($match->country).".png";
			}
		}

		// Building the response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('matches.tpl', $responseArray, $images);
		return $response;
	}

	/**
	 * View a flower sent by another user
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _flor (Request $request)
	{
		// get data of the flower
		$flowerId = trim($request->query);
		$flower = Connection::query("SELECT id_sender, message FROM _piropazo_flowers WHERE id='$flowerId'");
		if(empty($flower)) return new Response();

		// get params for the view
		$person = Utils::getPerson($flower[0]->id_sender);
		$message = $flower[0]->message;

		// send the reponse
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('flower.tpl', ["person"=>$person, "message"=>$message]);
		return $response;
	}

	/**
	 * Send a flower to another user
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _mandarFlor (Request $request)
	{
		$username = $request->params[0];
		$message = trim(Connection::escape($request->params[1], 200));

		// do not allow inexistant people
		$receiver = Utils::getIdFromUsername($username);
		if(empty($receiver)) return new Response();

		// check if you have enought flowers to send
		$flowers = Connection::query("SELECT id_person FROM _piropazo_people WHERE id_person='{$request->userId}' AND flowers > 0");
		if(empty($flowers)) {
			$content = [
				"environment" => $request->environment,
				"header"=>"No tiene suficientes flores",
				"icon"=>"&#x1F339;",
				"text" => "Actualmente usted no tiene suficientes flores para usar. Puede comprar algunas flores frescas en la tienda de Piropazo.",
				"button" => ["href"=>"PIROPAZO TIENDA", "caption"=>"Tienda"]];

			$response = new Response();
			$response->setEmailLayout('piropazo.tpl');
			$response->createFromTemplate('message.tpl', $content);
			return $response;
		}

		// send the flower and expand response time 7 days
		$flowerId = Connection::query("INSERT INTO _piropazo_flowers (id_sender,id_receiver,message) VALUES ('{$request->userId}','$receiver','$message')");
		Connection::query("
			UPDATE _piropazo_people SET flowers=flowers-1 WHERE id_person='{$request->userId}';
			UPDATE _piropazo_relationships SET expires_matched_blocked = ADDTIME(expires_matched_blocked,'168:00:00.00') WHERE id_from = '{$request->userId}' AND id_to = '$receiver';");

		// send emails for users with app/email/web
		Utils::addNotification($receiver, "Piropazo", "Enhorabuena, @{$request->username} le ha mandado una flor. Este es un sintoma inequivoco de le gustas", "PIROPAZO FLOR $flowerId");

		// return message
		$content = [
			"environment" => $request->environment,
			"header"=>"Hemos enviado su flor a $username",
			"icon"=>"&#x1F339;",
			"text" => "$username recibira una notificacion y de seguro le contestara lo antes posible. Ademas, le hemos dado una semana extra para que le responda.",
			"button" => ["href"=>"PIROPAZO PAREJAS", "caption"=>"Mis parejas"]];

		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('message.tpl', $content);
		return $response;
	}

	/**
	 * Use a crown to highlight your profile
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _corona (Request $request)
	{
		// check if you have enought crowns
		$crowns = Connection::query("SELECT crowns FROM _piropazo_people WHERE id_person='{$request->userId}' AND crowns > 0");

		// return error response if the user has no crowns
		if(empty($crowns)) {
			$content = [
				"environment" => $request->environment,
				"header"=>"No tiene suficientes coronas",
				"icon"=>"&#x1F451;",
				"text" => "Actualmente usted no tiene suficientes coronas para usar. Puede comprar algunas coronas en la tienda de Piropazo.",
				"button" => ["href"=>"PIROPAZO TIENDA", "caption"=>"Tienda"]];

			$response = new Response();
			$response->setEmailLayout('piropazo.tpl');
			$response->createFromTemplate('message.tpl', $content);
			return $response;
		}

		// set the crown and substract a crown
		Connection::query("UPDATE _piropazo_people SET crowns=crowns-1, crowned=CURRENT_TIMESTAMP WHERE id_person={$request->userId}");

		// post a notification for the user
		Utils::addNotification($request->userId, "piropazo", "Enhorabuena, Usted ha sido coronado. Ahora su perfil se mostrara a muchos mas usuarios por los proximos tres dias", "PIROPAZO PERFIL");

		// build the response
		$content = [
			"environment" => $request->environment,
			"header"=>"Usted ha sido coronado",
			"icon"=>"&#x1F451;",
			"text" => "Usted ha sido coronado, y en los proximos tres dias su perfil se mostrara muchas mas veces a otros usuarios, lo cual mejorara sus chances de recibir solicitudes y flores. Mantenganse revisando a diario su lista de parejas.",
			"button" => ["href"=>"PIROPAZO PERFIL", "caption"=>"Ver perfil"]];

		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('message.tpl', $content);
		return $response;
	}

	/**
	 * Open the store
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _tienda (Request $request)
	{
		// get the user credit
		$credit = Connection::query("SELECT credit FROM person WHERE id={$request->userId}")[0]->credit;

		// build the response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('store.tpl', ["credit"=>$credit, "email"=>$request->email]);
		return $response;
	}
	/**
	 * Open the store
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _notificaciones (Request $request)
	{
		// get notifications
		$notifications = Utils::getNotifications($request->userId, 20, ['piropazo', 'chat']);

		// build the response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('notifications.tpl', ['notificactions' => $notifications]);
		return $response;
	}

	/**
	 * Chat with somebody
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _chat(Request $request)
	{
		// get person to chat
		$friendId = Utils::getIdFromUsername($request->query);
		if(empty($friendId)) return new Response();

		// get the list of people chating with you
		$social = new Social();
		$chats = $social->chatConversation($request->userId, $friendId);

		// create content to send to the view
		$content = [
			"environment" => $request->environment,
			"username" => str_replace("@", "", $request->query),
			"chats" => $chats
		];

		// get images for the web
		$images = [];
		if(($request->environment == "web" || $request->environment == "appnet")) {
			foreach ($chats as $chat) $images[] = $chat->picture_internal;
		}

		// respond to the view
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate("conversation.tpl", $content, $images);
		return $response;
	}

	/**
	 * Exit the Piropazo network
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _salir (Request $request)
	{
		// remove from piropazo
		Connection::query("UPDATE _piropazo_people SET active=0 WHERE id_person={$request->userId}");

		// respond to user
		$content = [
			"environment" => $request->environment,
			"header"=>"Usted ha salido de Piropazo",
			"icon"=>"&#x1F64D;",
			"text" => "No recibir&aacute; m&aacute;s mensajes de otros usuarios ni aparecer&aacute; en la lista de Piropazo. Si revisa Piropazo nuevamente, su perfil sera agregado autom&aacute;ticamente.",
			"button" => ["href"=>"SERVICIOS", "caption"=>"Otros Servicios"]];

		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('message.tpl', $content);
		return $response;
	}

	/**
	 * Open the user's profile
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _perfil (Request $request)
	{
		// get the user's profile
		$id = Utils::getIdFromUsername($request->query);
		if(empty($id)) $id = $request->userId;
		$profile = Utils::getPerson($id);

		// erase unwanted properties in the object
		$properties = ['username','date_of_birth','gender','eyes','skin','body_type','hair','province','city','highest_school_level','occupation','marital_status','interests','about_me','lang','picture','sexual_orientation','religion','country','usstate','full_name','picture_public','picture_internal','location','age'];
		$profile = $this->filterObjectProperties($properties, $profile);

		// check the specific values of piropazo
		$piropazo = Connection::query("
			SELECT flowers, crowns, (IFNULL(DATEDIFF(CURRENT_TIMESTAMP, crowned),99) < 3) AS crowned
			FROM _piropazo_people
			WHERE id_person = $id");

		// ensure the user exists
		if(empty($profile) || empty($piropazo)) return new Response();

		// check if is my own profile
		$isMyOwnProfile = $id == $request->userId;

		$returnTo = "";
		$percentageMatch = "";
		if( ! $isMyOwnProfile) {
			// calculate the percentage of a math
			$percentageMatch = $this->getPercentageMatch($id, $request->userId);

			// return to people or matches
			$back = Connection::query("
				SELECT COUNT(id) AS cnt
				FROM _piropazo_relationships 
				WHERE (id_from = $id AND id_to = {$request->userId}) 
				OR (id_from = {$request->userId} AND id_to = $id)");
			if($back[0]->cnt > 0) $returnTo = "PAREJAS";
		}

		// create the response object
		$content = [
			"environment" => $request->environment,
			"username" => $profile->username,
			"flowers" => $piropazo[0]->flowers,
			"crowns" => $piropazo[0]->crowns,
			"crowned" => $piropazo[0]->crowned,
			"isMyOwnProfile" => $isMyOwnProfile,
			"percentageMatch" => $percentageMatch,
			"returnTo" => $returnTo,
			"profile" => $profile];

		// get images for the web
		$images = [$profile->picture_internal];
		if(($request->environment == "web" || $request->environment == "appnet") && $profile->country) {
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$wwwroot = $di->get('path')['root'];
			$images[] = "$wwwroot/public/images/flags/".strtolower($profile->country).".png";
		}

		// Building response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('profile.tpl', $content, $images);
		return $response;
	}

	/**
	 * Open the user's profile
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _editar (Request $request)
	{
		// get the person to edit profile
		$person = Utils::getPerson($request->userId);
		if (empty($person)) return new Response();

		// make the person's text readable
		$person->province = str_replace("_", " ", $person->province);
		if ($person->gender == 'M') $person->gender = "Masculino";
		if ($person->gender == 'F') $person->gender = "Femenino";
		$person->country_name = Utils::getCountryNameByCode($person->country);
		$person->usstate_name = Utils::getStateNameByCode($person->usstate);
		$person->interests = count($person->interests);
		$person->years = implode(",", array_reverse(range(date('Y')-90, date('Y')-10)));

		// get the person images
		// @TODO add multiple images
		$image = $person->picture ? [$person->picture_internal] : [];

		// prepare response for the view
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->createFromTemplate('profile_full.tpl', ["person"=>$person], $image);
		return $response;
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
		$person = Utils::getPerson($match->user);
		$person->crown = $match->crown;
		$person->match = $this->getPercentageMatch($user->id, $match->user);
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

	/**
	 * Function executed when a payment is finalized
	 * Add new flowers and crowns to the database
	 *
	 * @author salvipascual
	 * @param Payment $payment
	 * @return boolean
	 */
	public function payment(Payment $payment)
	{
		// get the number of articles purchased
		$flowers = 0; $crowns = 0;
		if($payment->code == "FLOWER") $flowers = 1;
		if($payment->code == "CROWN") $crowns = 1;
		if($payment->code == "PACK_ONE") {$flowers = 7; $crowns = 2;}
		if($payment->code == "PACK_TWO") {$flowers = 15; $crowns = 4;}

		// do not allow wrong codes
		if(empty($flowers) && empty($crowns)) return false;

		// save the articles in the database
		Connection::query("
			UPDATE _piropazo_people
			SET flowers=flowers+$flowers, crowns=crowns+$crowns
			WHERE email='{$payment->buyer}'");

		return true;
	}
}
