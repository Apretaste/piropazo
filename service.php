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
		// get values from the response
		$user = $this->utils->getPerson($request->email);
		$request->query = trim($request->query);
		$offset = 0;

		$limit = empty(intval($request->query)) ? 1 : $request->query;
		$limit = intval($limit);
		if($limit > 50) $limit = 50;

		// activate new users and people who left
		$this->activatePiropazoUser($request->email);

		// get best matches for you
		if($user->completion < 85) $matches = $this->getMatchesByPopularity($user, $limit, $offset);
		else $matches = $this->getMatchesByUserFit($user, $limit, $offset);

		// if no matches, let the user know
		if(empty($matches)) {
			$content = [
				"header"=>"No encontramos a nadie",
				"icon"=>"&#x1F64D;",
				"text" => "Esto es vergonsozo, pero no pudimos encontrar a nadie que vaya con usted. Por favor regrese mas tarde, o cambie su perfil e intente nuevamente.",
				"button" => ["href"=>"PERFIL EDITAR", "caption"=>"Editar perfil"]];
			$response = new Response();
			$response->setEmailLayout('piropazo.tpl');
			$response->createFromTemplate('message.tpl', $content);
			return $response;
		}

		// organize list of matches and get images
		$images = array();
		$social = new Social();

		$inlineUsernames = '';
		foreach ($matches as $match)
		{
			// get the full profile
			$match = $social->prepareUserProfile($match);

			// get the link to the image
			if($match->picture) $images[] = $match->picture_internal;

			// calculate the tags
			$tags = array();
			if(array_intersect($match->interests, $user->interests)) $tags[] = $this->int18("tag_interests");
			if(($match->city && ($match->city == $user->city)) || ($match->usstate && ($match->usstate == $user->usstate)) || ($match->province && ($match->province == $user->province))) $tags[] = $this->int18("tag_nearby");
			if($match->popularity > 70) $tags[] = $this->int18("tag_popular");
			if(abs($match->age - $user->age) <= 3) $tags[] = $this->int18("tag_same_age");
			if($match->religion && ($match->religion == $user->religion)) $tags[] = $this->int18("tag_religion");
			if($match->highest_school_level && ($match->highest_school_level == $user->highest_school_level)) $tags[] = $this->int18("tag_same_education");
			if($match->body_type == "ATLETICO") $tags[] = $this->int18("tag_hot");
			$match->tags = array_slice($tags, 0, 2); // show only two tags

			// erase unwanted properties in the object
			$properties = ["username","gender","interests","about_me","picture","pictureURL","picture_public","picture_internal","crown","country","location","age","tags","online"];
			$match = $this->filterObjectProperties($properties, $match);
			$inlineUsernames .= $match->username.' ';
		}

		// mark the last time the system was used
		$this->markLastTimeUsed($request->email);

		// check if your user has been crowned
		$crowned = $this->checkUserIsCrowned($request->email);

		// create response
		$content = [
			"noProfilePic" => empty($user->picture),
			"noProvince" => empty($user->country) || ($user->country=="US" && empty($user->usstate)) || ($user->country=="CU" && empty($user->province)),
			"fewInterests" => count($user->interests) <= 3,
			"completion" => $user->completion,
			"crowned" => $crowned,
			"people" => $matches,
			"limit" => $limit,
			"inlineUsernames" => $inlineUsernames];

		// build the response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->setResponseSubject('Personas de tu interes');
		$response->createFromTemplate('people.tpl', $content, $images);
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
		$emailfrom = $request->email;
		$emailto = $this->utils->getEmailFromUsername($username);
		if( ! $emailto) return new Response();

		// check if there is any previous record between you and that person
		$record = Connection::query("SELECT status FROM _piropazo_relationships WHERE email_from='$emailto' AND email_to='$emailfrom'");

		// get the person From from the database
		$personFrom = $this->utils->getPerson($emailfrom);

		// if they liked you, like too, if they dislike you, block
		if( ! empty($record))
		{
			// if they liked you, create a match
			if($record[0]->status == "like")
			{
				// update to create a match and let you know of the match
				Connection::query("UPDATE _piropazo_relationships SET status='match', expires_matched_blocked=CURRENT_TIMESTAMP WHERE email_from='$emailto' AND email_to='$emailfrom'");
				$this->utils->addNotification($emailfrom, "piropazo", "Felicidades, ambos tu y @$username se han gustado, ahora pueden chatear", "PIROPAZO CHAT @$username");

				// let the other person know of the match
				$this->utils->addNotification($emailto, "piropazo", "Felicidades, ambos tu y @{$personFrom->username} se han gustado, ahora pueden chatear", "PIROPAZO CHAT @{$personFrom->username}");
			}

			// if they dislike you, block that match
			if($record[0]->status == "dislike") Connection::query("UPDATE _piropazo_relationships SET status='blocked', expires_matched_blocked=CURRENT_TIMESTAMP WHERE email_from='$emailto' AND email_to='$emailfrom'");
			return new Response();
		}

		// insert the new relationship
		$threeDaysForward = date("Y-m-d H:i:s", strtotime("+3 days"));
		Connection::query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE email_from='$emailfrom' AND email_to='$emailto';
			INSERT INTO _piropazo_relationships (email_from,email_to,status,expires_matched_blocked) VALUES ('$emailfrom','$emailto','like','$threeDaysForward');
			COMMIT");

		// prepare notification
		$pushNotification = new PushNotification();
		$appid = $pushNotification->getAppId($emailto, "piropazo");

		// send push notification for users with the piropazo app
		if($appid) {
			$pushNotification->piropazoLikePush($appid, $personFrom);
			return new Response();
		}
		// post an internal notification for the user
		else $this->utils->addNotification($emailto, "piropazo", "El usuario @{$personFrom->username} ha mostrado interes en ti, deberias revisar su perfil.", "PIROPAZO parejas");

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
	 * Say Yes to a match and go to Matches
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _siMatches (Request $request)
	{
		$this->_si($request);
		return $this->_parejas($request);
	}

	/**
	 * Say No to a match
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _no (Request $request)
	{
		// get the emails from and to
		$emailfrom = $request->email;
		$emailto = $this->utils->getEmailFromUsername($request->query);
		if( ! $emailto) return new Response();

		// insert the new relationship
		Connection::query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE (email_from='$emailfrom' AND email_to='$emailto') OR (email_to='$emailfrom' AND email_from='$emailto');
			INSERT INTO _piropazo_relationships (email_from,email_to,status,expires_matched_blocked) VALUES ('$emailfrom','$emailto','dislike',CURRENT_TIMESTAMP);
			COMMIT");

		// do not return anything
		return new Response();
	}

	/**
	 * Say No to a person and return next match
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
	 * Say No to a person and go to Matches
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _noMatches (Request $request)
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
		// get @username and text
		$parts = explode(" ", $request->query);
		$username = array_shift($parts);
		$text = implode(" ", $parts);

		// get code from text
		if(php::exists($text, "ofensivo")) $text = "OFFENSIVE";
		if(php::exists($text, "info")) $text = "FAKE";
		if(php::exists($text, "no luce")) $text = "MISLEADING";
		if(php::exists($text, "impersonando")) $text = "IMPERSONATING";
		if(php::exists($text, "autor")) $text = "COPYRIGHT";

		// only acept the types allowed
		$text = strtoupper($text);
		if( ! in_array($text, ['OFFENSIVE','FAKE','MISLEADING','IMPERSONATING','COPYRIGHT'])) return new Response();

		// get email of the person to report
		$emailTo = $this->utils->getEmailFromUsername($username);

		// save the report
		Connection::query("INSERT INTO _piropazo_reports (creator,user,type) VALUES ('{$request->email}','$emailTo','$text')");

		// say NO to the user
		$request->query = $username;
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
		$this->activatePiropazoUser($request->email);

		// get list of people whom you liked or liked you
		$matches = Connection::query("
			SELECT B.*, 'LIKE' as type, A.email_to as email, '' as matched_on,datediff(A.expires_matched_blocked, CURDATE()) as time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.email_to = B.email
			WHERE expires_matched_blocked > CURRENT_TIMESTAMP
			AND status = 'like'
			AND email_from = '{$request->email}'
			UNION
			SELECT B.*, 'WAITING' as type, A.email_from as email, '' as matched_on, datediff(A.expires_matched_blocked, CURDATE()) as time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.email_from = B.email
			WHERE expires_matched_blocked > CURRENT_TIMESTAMP
			AND status = 'like'
			AND email_to = '{$request->email}'
			UNION
			SELECT B.*, 'MATCH' as type, A.email_from as email, A.expires_matched_blocked as matched_on, '' as time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.email_from = B.email
			WHERE status = 'match'
			AND email_to = '{$request->email}'
			UNION
			SELECT B.*, 'MATCH' as type, A.email_to as email, A.expires_matched_blocked as matched_on, '' as time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.email_to = B.email
			WHERE status = 'match'
			AND email_from = '{$request->email}'");

		// if no matches, let the user know
		if(empty($matches)) {
			$content = [
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
		foreach ($matches as $match)
		{
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
		$this->markLastTimeUsed($request->email);

		// create response array
		$responseArray = array(
			"code" => "ok",
			"likeCounter" => $likeCounter,
			"waitingCounter" => $waitingCounter,
			"matchCounter" => $matchCounter,
			"people"=>$matches);

		// Building the response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->setResponseSubject('Tu lista de parejas');
		$response->createFromTemplate('matches.tpl', $responseArray, $images);
		return $response;
	}

	/**
	 * Send a flower to another user
	 *
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _flor (Request $request)
	{
		// if coming as FLOR ID, open the flower
		$flower = Connection::query("SELECT sender, message FROM _piropazo_flowers WHERE id='{$request->query}'");
		if($flower) {
			$person = $this->utils->getPerson($flower[0]->sender);
			$message = $flower[0]->message;

			$response = new Response();
			$response->setEmailLayout('piropazo.tpl');
			$response->setResponseSubject('@$username le mando una flor');
			$response->createFromTemplate('flower.tpl', ["person"=>$person, "message"=>$message]);
			return $response;
		}

		// separate username and message
		$arr = explode(" ", $request->query);
		$username = str_replace("@", "", array_shift($arr));
		$message = implode(" ", $arr);

		// do not allow inexistant people
		$receiver = $this->utils->getEmailFromUsername($username);
		if(empty($receiver)) {
			$response = new Response();
			return $response->createFromJSON('{"code":"ERROR", "message":"Wrong username"}');
		}

		// check if you have enought flowers to send
		$flowers = Connection::query("SELECT email FROM _piropazo_people WHERE email='{$request->email}' AND flowers>0");
		if(empty($flowers)) {
			$content = [
				"code"=>"ERROR", "message"=>"Not enought flowers", "items"=>"flores",
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
		$flowerId = Connection::query("INSERT INTO _piropazo_flowers (sender,receiver,message) VALUES ('{$request->email}','$receiver','$message')");
		Connection::query("
			UPDATE _piropazo_people SET flowers=flowers-1 WHERE email='{$request->email}';
			UPDATE _piropazo_relationships SET expires_matched_blocked = ADDTIME(expires_matched_blocked,'168:00:00.00') WHERE email_from = '{$request->email}' AND email_to = '$receiver';");

		// prepare notification
		$pushNotification = new PushNotification();
		$appid = $pushNotification->getAppId($receiver, "piropazo");

		// send push notification for users with Piropazo App
		if($appid) {
			$person = $this->utils->getPerson($request->email);
			$pushNotification->piropazoFlowerPush($appid, $person);
			return new Response();
		}

		// send emails for users with app/email/web
		$this->utils->addNotification($receiver, "Piropazo", "Enhorabuena, @{$request->username} le ha mandado una flor. Este es un sintoma inequivoco de le gustas", "PIROPAZO FLOR $flowerId");

		// return message
		$content = [
			"header"=>"Hemos enviado su flor a @$username",
			"icon"=>"&#x1F339;",
			"text" => "@$username recibira una notificacion y de seguro le contestara lo antes posible. Ademas, le hemos dado una semana extra para que le responda.",
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
		$crowns = Connection::query("SELECT crowns FROM _piropazo_people WHERE email='{$request->email}' AND crowns>0");

		// return error response if the user has no crowns
		if(empty($crowns)) {
			$content = [
				"code"=>"ERROR", "message"=>"Not enought crowns", "items"=>"coronas",
				"header"=>"No tiene suficientes coronas",
				"icon"=>"&#x1F451;",
				"text" => "Actualmente usted no tiene suficientes coronas para usar. Puede comprar algunas coronas en la tienda de Piropazo.",
				"button" => ["href"=>"PIROPAZO TIENDA", "caption"=>"Tienda"]];
			$response = new Response();
			$response->setEmailLayout('piropazo.tpl');
			$response->createFromTemplate('message.tpl', $content);
			return $response;
		}

		// set the crown
		Connection::query("
			START TRANSACTION;
			INSERT INTO _piropazo_crowns (email) VALUES ('{$request->email}');
			UPDATE _piropazo_people SET crowns=crowns-1, crowned=CURRENT_TIMESTAMP WHERE email='{$request->email}';
			COMMIT");

		// post a notification for the user
		$this->utils->addNotification($request->email, "piropazo", "Enhorabuena, Usted ha sido coronado. Ahora su perfil se mostrara a muchos mas usuarios por los proximos tres dias", "PIROPAZO");

		// build the response
		$content = [
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
		$credit = Connection::query("SELECT credit FROM person WHERE email = '{$request->email}'")[0]->credit;

		// build the response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->setResponseSubject('Tienda de Piropazo');
		$response->createFromTemplate('store.tpl', ["credit"=>$credit]);
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
		$friendEmail = $this->utils->getEmailFromUsername($request->query);
		if(empty($friendEmail)) return new Response();

		// get the list of people chating with you
		$social = new Social();
		$chats = $social->chatConversation($request->email, $friendEmail);

		// respond to the view
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->setResponseSubject("Charla con @".$request->query);
		$response->createFromTemplate("conversation.tpl", ["username"=>str_replace("@", "", $request->query), "chats"=>$chats]);
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
		Connection::query("UPDATE _piropazo_people SET active=0 WHERE email='{$request->email}'");

		$response = new Response();
		$response->setResponseSubject('Haz salido de Piropazo');
		$response->createFromText('Haz salido de nuestra red de busqueda de parejas. No recibir&aacute;s m&aacute;s emails de otros usuarios diciendo que le gustas ni aparecer&aacute;s en la lista de Piropazo. &iexcl;Gracias!');
		return $response;
	}

	//
	// Methods for the Phone App
	//

	/**
	 * Unmatch you from another person
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _borrar (Request $request)
	{
		// get the emails from and to
		$emailfrom = $request->email;
		$emailto = $this->utils->getEmailFromUsername($request->query);
		if( ! $emailto) return new Response();

		// insert the new relationship
		Connection::query("
			UPDATE _piropazo_relationships
			SET status='blocked', expires_matched_blocked=CURRENT_TIMESTAMP
			WHERE (email_from='$emailto' AND email_to='$emailfrom')
			OR (email_from='$emailfrom' AND email_to='$emailto')");
		return new Response();
	}

	/**
	 * Alias for subservice profile. We need profile for the app
	 *
	 * @api
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _perfil (Request $request)
	{
		return $this->_profile($request);
	}

	/**
	 * Get info about your own profile, useful for the API
	 *
	 * @api
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _profile (Request $request)
	{
		// get the user's profile
		$email = Utils::getEmailFromUsername($request->query);
		if(empty($email)) $email = $request->email;
		$profile = Utils::getPerson($email);

		// erase unwanted properties in the object
		$properties = ['username','date_of_birth','gender','eyes','skin','body_type','hair','province','city','highest_school_level','occupation','marital_status','interests','about_me','lang','picture','sexual_orientation','religion','country','usstate','full_name','picture_public','picture_internal','location','age'];
		$profile = $this->filterObjectProperties($properties, $profile);

		// check the specific values of piropazo
		$piropazo = Connection::query("
			SELECT flowers, crowns,
			(IFNULL(DATEDIFF(CURRENT_TIMESTAMP, crowned),99) < 3) as crowned
			FROM _piropazo_people
			WHERE email = '$email'");

		// ensure the user exists
		if(empty($profile) || empty($piropazo)) {
			$response = new Response();
			return $response->createFromJSON('{"code":"fail"}');
		}

		// check if is my own profile
		$isMyOwnProfile = $email == $request->email;

		$percentageMatch = "100";
		$status = "no_relationship";
		if( ! $isMyOwnProfile) {
			// check status of the relationship
			$res = Connection::query("SELECT status FROM _piropazo_relationships WHERE email_from='{$request->email}' AND email_to='$email'");
			if($res) $status = $res[0]->status;
			if($status == "like") $status = "you_like_them";
			else {
				$res = Connection::query("SELECT status FROM _piropazo_relationships WHERE email_from='$email' AND email_to='{$request->email}'");
				if($res && $res[0]->status == "like") $status = "they_like_you";
				elseif($res) $status = $res[0]->status;
			}

			// get the percentage math for two profiles
			$percentageMatch = $this->getPercentageMatch($email, $request->email);
		}

		// create the response object
		$content = [
			"code" => "ok",
			"username" => $profile->username,
			"flowers" => $piropazo[0]->flowers,
			"crowns" => $piropazo[0]->crowns,
			"crowned" => $piropazo[0]->crowned,
			"isMyOwnProfile" => $isMyOwnProfile,
			"percentageMatch" => $percentageMatch,
			"status" => $status,
			"profile" => $profile];

		// Building response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->setResponseSubject("Perfil de @{$profile->username}");
		$response->createFromTemplate('profile.tpl', $content, [$profile->picture_internal]);
		return $response;

		// respond back to the API
		$response = new Response();
		return $response->createFromJSON(json_encode($jsonResponse));
	}

	/**
	 * Asigns a purchase to the user profile
	 *
	 * @api
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _confirm (Request $request)
	{
		// get the number of articles purchased
		$flowers = 0; $crowns = 0;
		if($request->query == "3FLOWERS") $flowers = 3;
		if($request->query == "1CROWN") $crowns = 1;
		if($request->query == "PACK_SMALL") {$flowers = 5; $crowns = 1;}
		if($request->query == "PACK_MEDIUM") {$flowers = 10; $crowns = 2;}
		if($request->query == "PACK_LARGE") {$flowers = 15; $crowns = 3;}

		// do not allow wrong codes
		if($flowers + $crowns == 0) {
			$response = new Response();
			return $response->createFromJSON('{"code":"fail", "message":"invalid code"}');
		}

		// save the articles in the database
		Connection::query("
			UPDATE _piropazo_people
			SET flowers=flowers+$flowers, crowns=crowns+$crowns
			WHERE email='{$request->email}'");

		// return ok response
		$response = new Response();
		return $response->createFromJSON('{"code":"ok", "flower":"'.$flowers.'", "crowns":"'.$crowns.'"}');
	}

	/**
	 * Return the count of all unread notes. Useful for the API
	 *
	 * @api
	 * @author salvipascual
	 * @param Request
	 * @return Response
	 */
	public function _unread(Request $request)
	{
		// get count of unread notes
		$notes = Connection::query("
			SELECT B.username, MAX(send_date) as sent, COUNT(B.username) as counter
			FROM _note A LEFT JOIN person B
			ON A.from_user = B.email
			WHERE to_user = '{$request->email}'
			AND read_date IS NULL
			AND B.email IN (
				SELECT email_to as email FROM _piropazo_relationships WHERE status = 'match' AND email_from = '{$request->email}'
				UNION
				SELECT email_from as email FROM _piropazo_relationships WHERE status = 'match' AND email_to = '{$request->email}')
			GROUP BY B.username");

		// get the total counter
		$total = 0;
		foreach ($notes as $note) $total += $note->counter;

		// respond back to the API
		$response = new Response();
		$jsonResponse = array("code" => "ok", "total" => $total, "items" => $notes);
		return $response->createFromJSON(json_encode($jsonResponse));
	}

	/**
	 * Get the list of matches solely by popularity
	 *
	 * @author salvipascual
	 * @param Object $user, the person to match against
	 * @param Int $limit, returning number
	 * @param Int $offset
	 * @return array of People
	 */
	private function getMatchesByPopularity ($user, $limit, $offset = 0)
	{
		// get the list of people
		switch ($user->sexual_orientation) {
			case 'HETERO':
			return Connection::query("
				SELECT
					A.*, B.likes*(B.likes/(B.likes+B.dislikes)) AS popularity,
					(IFNULL(datediff(CURDATE(), B.crowned),99) < 3) as crown
				FROM person A
				LEFT JOIN _piropazo_people B
				ON (A.email = B.email AND B.active=1)
				WHERE A.picture IS NOT NULL
				AND (IFNULL(YEAR(CURDATE()) - YEAR(A.date_of_birth), 0) >= 17 OR A.date_of_birth IS NULL)
				AND A.email <> '{$user->email}'
				AND A.gender <> '{$user->gender}'
				AND A.marital_status = 'SOLTERO'
				AND A.email NOT IN (SELECT email_to as email FROM _piropazo_relationships WHERE email_from = '{$user->email}' UNION SELECT email_from as email FROM _piropazo_relationships WHERE email_to = '{$user->email}')
				ORDER BY popularity DESC
				LIMIT $offset, $limit");
				break;
			case 'HOMO':
			return Connection::query("
				SELECT
					A.*, B.likes*(B.likes/(B.likes+B.dislikes)) AS popularity,
					(IFNULL(datediff(CURDATE(), B.crowned),99) < 3) as crown
				FROM person A
				LEFT JOIN _piropazo_people B
				ON (A.email = B.email AND B.active=1)
				WHERE A.picture IS NOT NULL
				AND (IFNULL(YEAR(CURDATE()) - YEAR(A.date_of_birth), 0) >= 17 OR A.date_of_birth IS NULL)
				AND A.email <> '{$user->email}'
				AND A.gender = '{$user->gender}'
				AND A.marital_status = 'SOLTERO'
				AND A.email NOT IN (SELECT email_to as email FROM _piropazo_relationships WHERE email_from = '{$user->email}' UNION SELECT email_from as email FROM _piropazo_relationships WHERE email_to = '{$user->email}')
				ORDER BY popularity DESC
				LIMIT $offset, $limit");
				break;

			case 'BI':
			return Connection::query("
				SELECT
					A.*, B.likes*(B.likes/(B.likes+B.dislikes)) AS popularity,
					(IFNULL(datediff(CURDATE(), B.crowned),99) < 3) as crown
				FROM person A
				LEFT JOIN _piropazo_people B
				ON (A.email = B.email AND B.active=1)
				WHERE A.picture IS NOT NULL
				AND (IFNULL(YEAR(CURDATE()) - YEAR(A.date_of_birth), 0) >= 17 OR A.date_of_birth IS NULL)
				AND A.email <> '{$user->email}'
				AND A.marital_status = 'SOLTERO'
				AND (sexual_orientation = 'BI' OR (sexual_orientation = 'HOMO' AND gender = '{$user->gender}') OR (sexual_orientation = 'HETERO' AND gender <> '{$user->gender}'))
				AND A.email NOT IN (SELECT email_to as email FROM _piropazo_relationships WHERE email_from = '{$user->email}' UNION SELECT email_from as email FROM _piropazo_relationships WHERE email_to = '{$user->email}')
				ORDER BY popularity DESC
				LIMIT $offset, $limit");
				break;
		}
	}

	/**
	 * Get the list of matches best fit to your profile
	 *
	 * @author salvipascual
	 * @param Object $user, the person to match against
	 * @param Int $limit, returning number
	 * @param Int $offset
	 * @return Array of People
	 */
	private function getMatchesByUserFit ($user, $limit, $offset = 0)
	{
		// create the where clause for the query
		$where  = "A.email <> '{$user->email}' ";
		$where .= " AND A.email NOT IN (SELECT email_to as email FROM _piropazo_relationships WHERE email_from = '{$user->email}' UNION SELECT email_from as email FROM _piropazo_relationships WHERE email_to = '{$user->email}')";
		$where .= " AND (IFNULL(YEAR(CURDATE()) - YEAR(date_of_birth), 0) >= 17 OR A.date_of_birth IS NULL) ";
		$where .= " AND A.marital_status = 'SOLTERO' ";
		if ($user->sexual_orientation == 'HETERO') $where .= "AND gender <> '{$user->gender}' AND sexual_orientation <> 'HOMO' ";
		if ($user->sexual_orientation == 'HOMO') $where .= "AND gender = '{$user->gender}' AND sexual_orientation <> 'HETERO' ";
		if ($user->sexual_orientation == 'BI') $where .= " AND (sexual_orientation = 'BI' OR (sexual_orientation = 'HOMO' AND gender = '{$user->gender}') OR (sexual_orientation = 'HETERO' AND gender <> '{$user->gender}')) ";
		$where .= "AND (marital_status <> 'CASADO' OR marital_status IS NULL) ";
		$where .= "AND A.active=1 AND B.active=1";

		// @TODO find a way to calculate interests faster
		// @TODO this is very important, but I dont have the time now

		// create subquery to calculate the percentages
		if(empty($user->age)) $user->age = 0;
		$subsql  = "SELECT A.*, ";
		$subsql .= "(select IFNULL(city, '') = '{$user->city}') * 60 as city_proximity, ";
		$subsql .= "(select IFNULL(province, '') = '{$user->province}') * 50 as province_proximity, ";
		$subsql .= "(select IFNULL(usstate, '') = '{$user->usstate}') * 50 as state_proximity, ";
		$subsql .= "(select IFNULL(country, '') = '{$user->country}') * 10 as country_proximity, ";
		$subsql .= "(select IFNULL(marital_status, '') = 'SOLTERO') * 20 as percent_single, ";
		$subsql .= "(select B.likes*B.likes/(B.likes+B.dislikes)) as popularity, ";
		$subsql .= "(select IFNULL(skin, '') = '{$user->skin}') * 5 as same_skin, ";
		$subsql .= "(select picture IS NOT NULL) * 30 as having_picture, ";
		$subsql .= "(ABS(IFNULL(YEAR(CURDATE()) - YEAR(date_of_birth), 0) - {$user->age}) < 20) * 15 as age_proximity,  ";
		$subsql .= "(select IFNULL(datediff(CURDATE(), B.crowned),99) < 3) as crown, ";
		$subsql .= "(select IFNULL(body_type, '') = '{$user->body_type}') * 5 as same_body_type, ";
		$subsql .= "(select IFNULL(religion, '') = '{$user->religion}') * 20 as same_religion ";
		$subsql .= "FROM person A RIGHT JOIN _piropazo_people B ON (A.email = B.email AND B.active=1) ";
		$subsql .= "WHERE $where";

		// create final query
		$sql  = "SELECT *, percent_single + city_proximity + province_proximity + state_proximity + country_proximity + same_skin + having_picture + age_proximity + same_body_type + same_religion + crown*50 as percent_match ";
		$sql .= "FROM ($subsql) as subq2 ";
		$sql .= "ORDER BY percent_match DESC, email ASC ";
		$sql .= "LIMIT $offset,$limit; ";

		// executing the query
		return Connection::query(trim($sql));
	}

	/**
	 * Check if the user us crowned
	 *
	 * @author salvipascual
	 * @param String $email
	 * @return Boolean
	 */
	private function checkUserIsCrowned($email)
	{
		$crowned = Connection::query("
			SELECT COUNT(email) AS crowned
			FROM _piropazo_people
			WHERE email='$email'
			AND datediff(CURRENT_TIMESTAMP, crowned) <= 3");
		return $crowned[0]->crowned;
	}

	/**
	 * Make active if the person uses Piropazo for the first time, or if it was inactive
	 *
	 * @author salvipascual
	 * @param String $email
	 */
	private function activatePiropazoUser($email)
	{
		Connection::query("INSERT INTO _piropazo_people (email) VALUES('$email') ON DUPLICATE KEY UPDATE active = 1");
	}

	/**
	 * Mark the last time the system was used by a user
	 *
	 * @author salvipascual
	 * @param String $email
	 */
	private function markLastTimeUsed($email)
	{
		Connection::query("UPDATE _piropazo_people SET last_access=CURRENT_TIMESTAMP WHERE email='$email'");
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
		foreach($objProperties as $prop=>$value)
		{
			if( ! in_array($prop, $properties)) unset($object->$prop);
		}
		return $object;
	}

	/**
	 * Get a language tag text on a language
	 *
	 * @author salvipascual
	 * @param String $tag
	 * @param String $lang
	 * @return String
	 */
	private function int18($tag, $lang="ES")
	{
		// only allow known languages
		if( ! in_array($lang, ["ES","EN"])) return false;

		// return the tag name
		$language = [$tag => [$lang => $tag]];
		require "$this->pathToService/lang.php";
		return $language[$tag][$lang];
	}

	/**
	 * Get the percentage of match for two profiles
	 *
	 * @author salvipascual
	 * @param String $emailOne
	 * @param String $emailTwo
	 * @return Number
	 */
	private function getPercentageMatch($emailOne, $emailTwo)
	{
		// get both profiles
		$p = Connection::query("SELECT eyes, skin, body_type, hair, highest_school_level, interests, lang, religion, country, usstate, province, date_of_birth FROM person WHERE email='$emailOne' OR email='$emailTwo'");
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
