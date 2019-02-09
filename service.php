<?php

/**
 * Apretaste Piropazo Service
 * @version 3.0
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
		) return $this->_inicio($request, $response);

		// activate new users and people who left
		$this->activatePiropazoUser($request->person->id);

		// get the best match for the user
		$match = $this->getMatchFromCache($request->person);

		// if no matches, let the user know
		if( ! $match) {
			$content = [
				"header"=>"No hay citas",
				"icon"=>"sentiment_very_dissatisfied",
				"text" => "Esto es vergonsozo, pero no pudimos encontrar a nadie que vaya con usted. Por favor regrese mas tarde, o cambie su perfil e intente nuevamente.",
				"button" => ["href"=>"PIROPAZO EDITAR", "caption"=>"Editar perfil"]];

			$response->setLayout('piropazo.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		// calculate the tags
		$tags = [];
		if(array_intersect($match->interests, $request->person->interests)) $tags[] = "Intereses Similares";
		if(($match->city && ($match->city == $request->person->city)) || ($match->usstate && ($match->usstate == $request->person->usstate)) || ($match->province && ($match->province == $request->person->province))) $tags[] = "Viven Cerca";
		if($match->age && abs($match->age - $request->person->age) <= 3) $tags[] = "Igual Edad";
		if($match->religion && ($match->religion == $request->person->religion)) $tags[] = "Misma Religion";
		if($match->highest_school_level && ($match->highest_school_level == $request->person->highest_school_level)) $tags[] = "Misma Educacion";
		if($match->body_type == "ATLETICO") $tags[] = "Cita Caliente";
		$match->tags = array_slice($tags, 0, 2); // show only two tags

		// erase unwanted properties in the object
		$properties = ["id","username","gender","interests","about_me","picture","picture","crown","country","location","age","tags","online","color"];
		$match = $this->filterObjectProperties($properties, $match);

		// mark the last time the system was used
		$this->markLastTimeUsed($request->person->id);

		// get match images into an array
		$images = ($match->picture) ? [$match->picture] : [];

		// build the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('dates.ejs', ["match" => $match], $images);
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
				Utils::addNotification($idFrom, "Felicidades, ambos tu y @$username se han gustado", 'chat_bubble_outline', '{"command":"PIROPAZO PAREJAS"}');
				Utils::addNotification($idTo, "Felicidades, ambos tu y @{$request->person->username} se han gustado", "chat_bubble_outline", '{"command":"PIROPAZO PAREJAS"}');
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
		$request->query = $violatorUsername;
		$this->_no($request);
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
				"text" => "Por ahora nadie le ha pedido ser pareja suya ni usted le ha pedido a otros. Si esperaba ver a alguien aqu&iacute;, es posible que el tiempo de espera halla vencido. No se desanime, hay muchos peces en el oc&eacute;ano.",
				"button" => ["href"=>"PIROPAZO CITAS", "caption"=>"Buscar Pareja"]];

			$response->setLayout('piropazo.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		// organize list of matches
		$liked = $waiting = $matched = $images = [];
		foreach ($matches as $match) {
			// get the full profile
			$match = Social::prepareUserProfile($match);

			// get the match color class based on gender
			if($match->gender == "M") $match->color = "male";
			elseif($match->gender == "F") $match->color = "female";
			else $match->color = "neutral";

			// get the link to the image
			if($match->picture) $images[] = $match->picture;

			// erase unwanted properties in the object
			$properties = ["id","email","username","gender","age","type","location","picture","picture_public","picture","matched_on","time_left","country","online","color"];
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

		// create response array
		$content = [
			"myflowers" => $myFlowers[0]->flowers,
			"liked" => $liked,
			"waiting" => $waiting,
			"matched" => $matched];

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
				"icon"=>"&#x1F339;",
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
			"text" => "@$username recibira una notificacion y seguro le contestara lo antes posible. Tambien le hemos dado una semana extra para que responda.",
			"button" => ["href"=>"PIROPAZO PAREJAS", "caption"=>"Mis parejas"]];

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

		// return error response if the user has no crowns
		if(empty($crowns)) {
			$content = [
				"header"=>"No tiene suficientes coronas",
				"icon"=>"&#x1F451;",
				"text" => "Actualmente usted no tiene suficientes coronas para usar. Puede comprar algunas coronas en la tienda de Piropazo.",
				"button" => ["href"=>"PIROPAZO TIENDA", "caption"=>"Tienda"]];

			$response->setLayout('piropazo.ejs');
			$response->setTemplate('message.ejs', $content);

		}

		// set the crown and substract a crown
		Connection::query("UPDATE _piropazo_people SET crowns=crowns-1, crowned=CURRENT_TIMESTAMP WHERE id_person={$request->person->id}");

		// post a notification for the user
		Utils::addNotification($request->person->id, "Enhorabuena, Usted se ha agregado un corazon. Ahora su perfil se mostrara a muchos mas usuarios por los proximos tres dias", 'favorite_border');

		// build the response
		$content = [
			"header"=>"Usted ha sido coronado",
			"icon"=>"&#x1F451;",
			"text" => "Usted ha sido coronado, y en los proximos tres dias su perfil se mostrara muchas mas veces a otros usuarios, lo cual mejorara sus chances de recibir solicitudes y flores. Mantenganse revisando a diario su lista de parejas.",
			"button" => ["href"=>"PIROPAZO PERFIL", "caption"=>"Ver perfil"]];

		$response->setLayout('piropazo.ejs');
		$response->setTemplate('message.ejs', $content);
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
		// get the user credit
		$credit = Connection::query("SELECT credit FROM person WHERE id={$request->person->id}")[0]->credit;

		// build the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('store.ejs', ["credit"=>$credit, "email"=>$request->person->email]);
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
			AND `read` IS NULL");

		// if no notifications, let the user know
		if(empty($notifications)) {
			$content = [
				"header"=>"Nada por leer",
				"icon"=>"notifications_off",
				"text" => "Por ahora usted no tiene ninguna notificacion por leer.",
				"button" => ["href"=>"PIROPAZO CITAS", "caption"=>"Buscar Pareja"]];

			$response->setLayout('piropazo.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		// build the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('notifications.ejs', ['notifications' => $notifications]);
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
			"text" => "No recibir&aacute; m&aacute;s mensajes de otros usuarios ni aparecer&aacute; en la lista de Piropazo. Si revisa Piropazo nuevamente, su perfil sera agregado autom&aacute;ticamente.",
			"button" => ["href"=>"SERVICIOS", "caption"=>"Otros Servicios"]];

		$response->setLayout('piropazo.ejs');
		$response->setTemplate('message.ejs', $content);
	}

	/**
	 * Open the user's profile
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _perfil (Request $request, Response $response)
	{
		// get the user's profile
		$id = isset($request->input->data->id) ? $request->input->data->id : $request->person->id;
		$profile = Social::prepareUserProfile(Utils::getPerson($id));

		// erase unwanted properties in the object
		$properties = ['username','date_of_birth','gender','eyes','skin','body_type','hair','province','city','highest_school_level','occupation','marital_status','interests','about_me','lang','picture','sexual_orientation','religion','country','usstate','full_name','picture_public','picture','location','age','completion'];
		$profile = $this->filterObjectProperties($properties, $profile);

		// check the specific values of piropazo
		$piropazo = Connection::query("
			SELECT flowers, crowns, (IFNULL(DATEDIFF(CURRENT_TIMESTAMP, crowned),99) < 3) AS crowned
			FROM _piropazo_people
			WHERE id_person = $id");

		// ensure the user exists
		if(empty($profile) || empty($piropazo)) return $response;

		// check if is my own profile
		$isMyOwnProfile = $id == $request->person->id;

		// calculate the percentage of a math
		$percentageMatch = $isMyOwnProfile ? "" : $this->getPercentageMatch($id, $request->person->id);

		// get the match color class based on gender
		if($profile->gender == "M") $profile->color = "male";
		elseif($profile->gender == "F") $profile->color = "female";
		else $profile->color = "neutral";

		// get the profile image
		$images = [];
		if($request->person->picture) $images[] = $profile->picture;

		// create the response object
		$content = [
			"flowers" => $piropazo[0]->flowers,
			"crowns" => $piropazo[0]->crowns,
			"crowned" => $piropazo[0]->crowned,
			"isMyOwnProfile" => $isMyOwnProfile,
			"percentageMatch" => $percentageMatch,
			"profile" => $profile];

		// Building response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('profile.ejs', $content, $images);
	}

	/**
	 * Edit the user profile at start
	 * 
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _inicio(Request $request, Response $response)
	{
		// get the edit response
		$request->extra_fields = "hidden";
		$this->_editar ($request, $response);
	}

	/**
	 * Open the user's profile
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _editar (Request $request, Response $response)
	{
		// get what gender do you search for
		if($request->person->sexual_orientation == "BI") $request->person->searchfor = "AMBOS";
		elseif($request->person->gender == "M" && $request->person->sexual_orientation == "HETERO") $request->person->searchfor = "MUJERES";
		elseif($request->person->gender == "F" && $request->person->sexual_orientation == "HETERO") $request->person->searchfor = "HOMBRES";		
		elseif($request->person->gender == "M" && $request->person->sexual_orientation == "HOMO") $request->person->searchfor = "HOMBRES";
		elseif($request->person->gender == "F" && $request->person->sexual_orientation == "HOMO") $request->person->searchfor = "MUJERES";
		else $request->person->searchfor = "";

		// get array of images
		$images = [];
		if($request->person->picture) {
			$di = \Phalcon\DI\FactoryDefault::getDefault();
			$wwwroot = $di->get('path')['root'];
			$images[] = $request->person->picture;
		}

		// list of values
		$content = [
			"extra_fields" => isset($request->extra_fields) ? $request->extra_fields : "",
			"profile" => $request->person
		];

		// prepare response for the view
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('profile_edit.ejs', $content, $images);
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
		$person->match = $this->getPercentageMatch($user->id, $match->user);

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
}
