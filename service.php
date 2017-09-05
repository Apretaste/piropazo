<?php

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
		$limit = empty(intval($request->query)) ? 5 : $request->query;
		if($limit > 50) $limit = 50;

		// activate new users and people who left
		$this->activatePiropazoUser($request->email);

		// get best matches for you
		if($user->completion < 85) $matches = $this->getMatchesByPopularity($user, $limit);
		else $matches = $this->getMatchesByUserFit($user, $limit);

		// organize list of matches and get images
		$images = array();
		$social = new Social();
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
			$properties = array("username","gender","interests","about_me","picture","picture_public","picture_internal","crown","country","location","age","tags");
			$match = $this->filterObjectProperties($properties, $match);
		}

		// mark the last time the system was used
		$this->markLastTimeUsed($request->email);

		// check if your user has been crowned
		$crowned = $this->checkUserIsCrowned($request->email);

		// check if the user is connecting via the app or email
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$notFromApp = $di->get('environment') != "app";

		// create response
		$responseContent = array(
			"noProfilePic" => empty($user->picture),
			"noProvince" => empty($user->province),
			"fewInterests" => count($user->interests) <= 5,
			"completion" => $user->completion,
			"notFromApp" => $notFromApp,
			"crowned" => $crowned,
			"people" => $matches
		);

		// Building response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->setResponseSubject('Personas de tu interes');
		$response->createFromTemplate('people.tpl', $responseContent, $images);
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
		$connection = new Connection();
		$record = $connection->query("SELECT status FROM _piropazo_relationships WHERE email_from='$emailto' AND email_to='$emailfrom'");

		// get the person From from the database
		$personFrom = $this->utils->getPerson($emailfrom);

		// if they liked you, like too, if they dislike you, block
		if( ! empty($record))
		{
			// if they liked you, create a match
			if($record[0]->status == "like")
			{
				// update to create a match and let you know of the match
				$connection->query("UPDATE _piropazo_relationships SET status='match', expires_matched_blocked=CURRENT_TIMESTAMP WHERE email_from='$emailto' AND email_to='$emailfrom'");
				$this->utils->addNotification($emailfrom, "piropazo", "Felicidades, ambos tu y @$username se han gustado, ahora pueden chatear", "NOTA @$username");

				// let the other person know of the match
				$this->utils->addNotification($emailto, "piropazo", "Felicidades, ambos tu y @{$personFrom->username} se han gustado, ahora pueden chatear", "NOTA @{$personFrom->username}");
			}

			// if they dislike you, block that match
			if($record[0]->status == "dislike") $connection->query("UPDATE _piropazo_relationships SET status='blocked', expires_matched_blocked=CURRENT_TIMESTAMP WHERE email_from='$emailto' AND email_to='$emailfrom'");
			return new Response();
		}

		// insert the new relationship
		$threeDaysForward = date("Y-m-d H:i:s", strtotime("+3 days"));
		$connection->query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE email_from='$emailfrom' AND email_to='$emailto';
			INSERT INTO _piropazo_relationships (email_from,email_to,status,expires_matched_blocked) VALUES ('$emailfrom','$emailto','like','$threeDaysForward');
			COMMIT");

		// prepare notification
		$pushNotification = new PushNotification();
		$appid = $pushNotification->getAppId($emailto, "piropazo");

		// send push notification for users with the App
		if($appid) $pushNotification->piropazoLikePush($appid, $personFrom);
		// post an internal notification for the user
		else $this->utils->addNotification($emailto, "piropazo", "El usuario @{$personFrom->username} ha mostrado interes en ti, deberias revisar su perfil.", "PIROPAZO parejas");

		// do not return anything
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
		// get the emails from and to
		$emailfrom = $request->email;
		$emailto = $this->utils->getEmailFromUsername($request->query);
		if( ! $emailto) return new Response();

		// insert the new relationship
		$connection = new Connection();
		$connection->query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE (email_from='$emailfrom' AND email_to='$emailto') OR (email_to='$emailfrom' AND email_from='$emailto');
			INSERT INTO _piropazo_relationships (email_from,email_to,status,expires_matched_blocked) VALUES ('$emailfrom','$emailto','dislike',CURRENT_TIMESTAMP);
			COMMIT");

		// do not return anything
		return new Response();
	}

	/**
	 * Flag a user's profile
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function _reportar (Request $request)
	{
		// get the parts of the report
		$parts = explode(" ", $request->query);
		$username = $parts[0];
		$type = $parts[1];

		// only acept the types allowed
		$type = strtoupper($type);
		if( ! in_array($type, ['OFFENSIVE','FAKE','MISLEADING','IMPERSONATING','COPYRIGHT'])) return new Response();

		// get email of the person to report
		$emailto = $this->utils->getEmailFromUsername($username);

		// save the report
		$connection = new Connection();
		$connection->query("INSERT INTO _piropazo_reports (creator,user,type) VALUES ('{$request->email}','$emailto','$type')");

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
		$connection = new Connection();
		$matches = $connection->query("
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
			$properties = array("username","gender","age","type","location","picture","picture_public","picture_internal","matched_on","time_left");
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
		$connection = new Connection();
		$response = new Response();

		// get the receiver's email
		$sender = $request->email;
		$receiver = $this->utils->getEmailFromUsername($request->query);
		if(empty($receiver)) return $response->createFromJSON('{"code":"ERROR", "message":"Wrong username"}');

		// check if you have enought flowers to send
		$flowers = $connection->query("SELECT email FROM _piropazo_people WHERE email='$sender' AND flowers>0");

		// return error response if the user has no flowers
		if(empty($flowers))
		{
			$values = array("code"=>"ERROR", "message"=>"Not enought flowers", "items"=>"flores");
			$response->setEmailLayout('piropazo.tpl');
			$response->setResponseSubject('No tiene suficientes flores');
			$response->createFromTemplate('need_more.tpl', $values);
			return $response;
		}

		// send the flower and expand response time 7 days
		$connection->query("
			START TRANSACTION;
			INSERT INTO _piropazo_flowers (sender,receiver) VALUES ('$sender','$receiver');
			UPDATE _piropazo_people SET flowers=flowers-1 WHERE email='$sender';
			UPDATE _piropazo_relationships SET expires_matched_blocked = ADDTIME(expires_matched_blocked,'168:00:00.00') WHERE email_from = '$sender' AND email_to = '$receiver';
			COMMIT");

		// prepare notification
		$pushNotification = new PushNotification();
		$appid = $pushNotification->getAppId($receiver, "piropazo");
		$username = $this->utils->getUsernameFromEmail($sender);

		// send push notification for users with the App
		if($appid)
		{
			$person = $this->utils->getPerson($sender);
			$pushNotification->piropazoFlowerPush($appid, $person);
		}
		// send emails for users using the email service
		else
		{
			// post an internal notification for the user
			$this->utils->addNotification($receiver, "piropazo", "Enhorabuena, @$username le ha mandado una flor. Este es un sintoma inequivoco de le gustas, y deberias revisar su perfil", "PIROPAZO PAREJAS");

			// send an email to the user
			$response->setResponseEmail($receiver);
			$response->setEmailLayout('piropazo.tpl');
			$response->setResponseSubject("El usuario @$username le ha mandado una flor");
			$response->createFromTemplate('flower.tpl', array("username"=>$username));
		}

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
		$connection = new Connection();
		$crowns = $connection->query("SELECT crowns FROM _piropazo_people WHERE email='{$request->email}' AND crowns>0");

		// return error response if the user has no crowns
		if(empty($crowns))
		{
			$values = array("code"=>"ERROR", "message"=>"Not enought crowns", "items"=>"coronas");
			$response = new Response();
			$response->setEmailLayout('piropazo.tpl');
			$response->setResponseSubject('No tiene suficientes coronas');
			$response->createFromTemplate('need_more.tpl', $values);
			return $response;
		}

		// set the crown
		$connection->query("
			START TRANSACTION;
			INSERT INTO _piropazo_crowns (email) VALUES ('{$request->email}');
			UPDATE _piropazo_people SET crowns=crowns-1, crowned=CURRENT_TIMESTAMP WHERE email='{$request->email}';
			COMMIT");

		// post a notification for the user
		$this->utils->addNotification($request->email, "piropazo", "Enhorabuena, Usted ha sido coronado. Ahora su perfil se mostrara a muchos mas usuarios por los proximos tres dias", "PIROPAZO");

		// Building the response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->setResponseSubject('Usted ha sido coronado');
		$response->createFromTemplate('crowned.tpl', array());
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
		// get the number of flowers and cowns
		$connection = new Connection();
		$person = $connection->query("SELECT flowers, crowns FROM _piropazo_people WHERE email='{$request->email}'");

		// create response
		$responseContent = array(
			"flowers" => $person[0]->flowers,
			"crowns" => $person[0]->crowns
		);

		// Building response
		$response = new Response();
		$response->setEmailLayout('piropazo.tpl');
		$response->setResponseSubject('Tienda de Piropazo');
		$response->createFromTemplate('store.tpl', $responseContent);
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
		$connection = new Connection();
		$connection->query("UPDATE _piropazo_people SET active=0 WHERE email='{$request->email}'");

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
		$connection = new Connection();
		$connection->query("
			UPDATE _piropazo_relationships
			SET status='blocked', expires_matched_blocked=CURRENT_TIMESTAMP
			WHERE (email_from='$emailto' AND email_to='$emailfrom')
			OR (email_from='$emailfrom' AND email_to='$emailto')");
		return new Response();
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
		// get the full profile for the person, and remove unused fields
		$profile = $this->utils->getPerson($request->email);

		// erase unwanted properties in the object
		$properties = array('username','date_of_birth','gender','eyes','skin','body_type','hair','province','city','highest_school_level','occupation','marital_status','interests','about_me','lang','picture','sexual_orientation','religion','country','usstate','full_name','picture_public');
		$profile = $this->filterObjectProperties($properties, $profile);

		// check the specific values of piropazo
		$connection = new Connection();
		$piropazo = $connection->query("
			SELECT flowers, crowns,
			(IFNULL(DATEDIFF(CURRENT_TIMESTAMP, crowned),99) < 3) as crowned
			FROM _piropazo_people
			WHERE email = '{$request->email}'");

		// ensure the user exists
		if(empty($profile) || empty($piropazo)) die('{"code":"fail"}');

		// create the response object
		$jsonResponse = array(
			"code" => "ok",
			"username" => $profile->username,
			"flowers" => $piropazo[0]->flowers,
			"crowns" => $piropazo[0]->crowns,
			"crowned" => $piropazo[0]->crowned,
			"profile" => $profile
		);

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
		if($flowers + $crowns == 0) die('{"code":"fail", "message":"invalid code"}');

		// save the articles in the database
		$connection = new Connection();
		$connection->query("
			UPDATE _piropazo_people
			SET flowers=flowers+$flowers, crowns=crowns+$crowns
			WHERE email='{$request->email}'");

		// return ok response
		die('{"code":"ok", "flower":"'.$flowers.'", "crowns":"'.$crowns.'"}');
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
		$connection = new Connection();
		$notes = $connection->query("
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
		$jsonResponse = array("code" => "ok", "total"=>$total, "items" => $notes);
		return $response->createFromJSON(json_encode($jsonResponse));
	}

	/**
	 * Get the list of matches solely by popularity
	 *
	 * @author salvipascual
	 * @param Object $user, the person to match against
	 * @param Int $limit, returning number
	 * @return Array of People
	 */
	private function getMatchesByPopularity ($user, $limit)
	{
		// select the people that you liked/disliked before
		$emailsToHide = "SELECT email_to as email FROM _piropazo_relationships WHERE email_from = '{$user->email}'
			UNION SELECT email_from as email FROM _piropazo_relationships WHERE email_to = '{$user->email}'";

		// get the list of people
		$connection = new Connection();
		return $connection->query("
			SELECT
				A.*, B.likes*(B.likes/(B.likes+B.dislikes)) AS popularity,
				(IFNULL(datediff(CURDATE(), B.crowned),99) < 3) as crown
			FROM person A
			LEFT JOIN _piropazo_people B
			ON A.email = B.email
			WHERE A.picture IS NOT NULL
			AND A.email <> '{$user->email}'
			AND A.gender <> '{$user->gender}'
			AND A.email NOT IN ($emailsToHide)
			ORDER BY popularity DESC
			LIMIT $limit");
	}

	/**
	 * Get the list of matches best fit to your profile
	 *
	 * @author salvipascual
	 * @param Object $user, the person to match against
	 * @param Int $limit, returning number
	 * @return Array of People
	 */
	private function getMatchesByUserFit ($user, $limit)
	{
		// select the people that you liked/disliked before
		$emailsToHide = "SELECT email_to as email FROM _piropazo_relationships WHERE email_from = '{$user->email}'
			UNION SELECT email_from as email FROM _piropazo_relationships WHERE email_to = '{$user->email}'";

		// create the where clause for the query
		$where  = "A.email <> '{$user->email}' ";
		$where .= "AND A.email NOT IN ($emailsToHide) ";
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
		$subsql .= "FROM person A RIGHT JOIN _piropazo_people B ON A.email = B.email ";
		$subsql .= "WHERE $where";

		// create final query
		$sql  = "SELECT *, percent_single + city_proximity + province_proximity + state_proximity + country_proximity + same_skin + having_picture + age_proximity + same_body_type + same_religion + crown*50 as percent_match ";
		$sql .= "FROM ($subsql) as subq2 ";
		$sql .= "ORDER BY percent_match DESC, email ASC ";
		$sql .= "LIMIT $limit; ";

		// Executing the query
		$connection = new Connection();
		$people = $connection->query(trim($sql));

		return $people;
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
		$connection = new Connection();
		$crowned = $connection->query("
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
		$connection = new Connection();
		$crowned = $connection->query("
			INSERT INTO _piropazo_people (email) VALUES('$email')
			ON DUPLICATE KEY UPDATE active = 1");
	}

	/**
	 * Mark the last time the system was used by a user
	 *
	 * @author salvipascual
	 * @param String $email
	 */
	private function markLastTimeUsed($email)
	{
		$connection = new Connection();
		$connection->query("UPDATE _piropazo_people SET last_access=CURRENT_TIMESTAMP WHERE email='$email'");
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
		require "$this->pathToService/lang.php";
		return $language[$tag][$lang];
	}

	/**
	 * Function executed when a payment is finalized
	 * Add new flowers and crowns to the database
	 *
	 *  @author salvipascual
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
		$connection = new Connection();
		$connection->query("
			UPDATE _piropazo_people
			SET flowers=flowers+$flowers, crowns=crowns+$crowns
			WHERE email='{$payment->buyer}'");

		return true;
	}
}
