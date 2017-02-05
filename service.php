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
		$limit = empty($request->query) ? 5 : $request->query;

		// activate new users and people who left
		$this->activatePiropazoUser($request->email);

		// get the completion percentage of your profile
		$completion = $this->utils->getProfileCompletion($request->email);

		// get best matches for you
		if($completion < 85) $matches = $this->getMatchesByPopularity($user, $limit);
		else $matches = $this->getMatchesByUserFit($user, $limit);

		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$wwwhttp = $di->get('path')['http'];
		$wwwroot = $di->get('path')['root'];
		$social = new Social();
		$images = array();

		// organize list of matches
		foreach ($matches as $match)
		{
			// create the about me section for those without it
			if (empty($match->about_me)) $match->about_me = $social->profileToText($match, $user->lang);

			// calculate the location
			$location = $match->country;
			if($match->city) $location = $match->city;
			if($match->usstate) $location = $match->usstate;
			if($match->province) $location = $match->province;
			$location = str_replace("_", " ", $location);
			$match->location = ucwords(strtolower($location));

			// calculate the age
			$match->age = empty($match->date_of_birth) ? "" : date_diff(date_create($match->date_of_birth), date_create('today'))->y;

			// calculate the tags
			$match->tags = array();
			if($match->popularity > 70) $match->tags[] = "POPULAR";
			if($match->religion && ($match->religion == $user->religion)) $match->tags[] = "RELIGION";
			if(($match->city && ($match->city == $user->city)) || ($match->usstate && ($match->usstate == $user->usstate)) || ($match->province && ($match->province == $user->province))) $match->tags[] = "NEARBY";
			// @TODO missing tag SIMILAR for similar interests

			// get the link to the image
			if($match->picture)
			{
				$match->pictureURL = "$wwwhttp/profile/thumbnail/{$match->email}.jpg";
				$images[] = "$wwwroot/public/profile/thumbnail/{$match->email}.jpg";
			}

			// get rid of the pin and other unnecesary stuff
			unset($match->pin,$match->email,$match->credit,$match->lang,$match->active,$match->mail_list,$match->last_update_date,$match->updated_by_user,$match->cupido,$match->sexual_orientation,$match->religion,$match->source,$match->blocked,$match->notifications,$match->city_proximity,$match->province_proximity,$match->state_proximity,$match->country_proximity,$match->percent_single,$match->popularity,$match->same_skin,$match->having_picture,$match->age_proximity,$match->same_body_type,$match->same_religion,$match->percent_match,$match->insertion_date,$match->last_access,$match->first_name,$match->middle_name,$match->last_name,$match->mother_name,$match->date_of_birth,$match->phone,$match->cellphone,$match->eyes,$match->skin,$match->body_type,$match->hair,$match->highest_school_level,$match->occupation,$match->marital_status,$match->usstate,$match->province,$match->city);
		}

		// mark the last time the system was used
		$this->markLastTimeUsed($request->email);

		// check if your user has been crowned
		$crowned = $this->checkUserIsCrowned($request->email);

		// create response
		$responseContent = array(
			"noProfilePic" => empty($user->thumbnail),
			"noProvince" => empty($user->province),
			"fewInterests" => count($user->interests) <= 10,
			"completion" => $completion,
			"crowned" => $crowned,
			"people" => $matches
		);

		// Building response
		$response = new Response();
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
		$record = $connection->deepQuery("SELECT status FROM _piropazo_relationships WHERE email_from='$emailto' AND email_to='$emailfrom'");

		// if they liked you, like too, if they dislike you, block
		if( ! empty($record))
		{
			// if they liked you, create a match
			if($record[0]->status == "like")
			{
				// update to create a match and let you know of the match
				$connection->deepQuery("UPDATE _piropazo_relationships SET status='match', expires_matched_blocked=CURRENT_TIMESTAMP WHERE email_from='$emailto' AND email_to='$emailfrom'");
				$this->utils->addNotification($emailfrom, "piropazo", "Felicidades, ambos tu y @$username se han gustado, ahora pueden chatear", "NOTA @$username");

				// let the other person know of the match
				$usernameFrom = $this->utils->getPerson($emailfrom)->username;
				$this->utils->addNotification($emailto, "piropazo", "Felicidades, ambos tu y @$usernameFrom se han gustado, ahora pueden chatear", "NOTA @$usernameFrom");
			}

			// if they dislike you, block that match
			if($record[0]->status == "dislike") $connection->deepQuery("UPDATE _piropazo_relationships SET status='blocked', expires_matched_blocked=CURRENT_TIMESTAMP WHERE email_from='$emailto' AND email_to='$emailfrom'");
			return new Response();
		}

		// insert the new relationship
		$threeDaysForward = date("Y-m-d H:i:s", strtotime("+3 days"));
		$connection->deepQuery("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE email_from='$emailfrom' AND email_to='$emailto';
			INSERT INTO _piropazo_relationships (email_from,email_to,status,expires_matched_blocked) VALUES ('$emailfrom','$emailto','like','$threeDaysForward');
			COMMIT");

		// Generate a notification
		$this->utils->addNotification($emailto, "piropazo", "El usuario @$username ha mostrado interes en ti, deberias revisar su perfil.", "PIROPAZO parejas");

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
		$connection->deepQuery("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE (email_from='$emailfrom' AND email_to='$emailto') OR (email_to='$emailfrom' AND email_from='$emailto');
			INSERT INTO _piropazo_relationships (email_from,email_to,status,expires_matched_blocked) VALUES ('$emailfrom','$emailto','dislike',CURRENT_TIMESTAMP);
			COMMIT");

		// do not return anything
		return new Response();
	}

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
		$connection->deepQuery("
			UPDATE _piropazo_relationships
			SET status='blocked', expires_matched_blocked=CURRENT_TIMESTAMP
			WHERE (email_from='$emailto' AND email_to='$emailfrom')
			OR (email_from='$emailfrom' AND email_to='$emailto')");
		return new Response();
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
		$matches = $connection->deepQuery("
			SELECT B.username, B.gender, B.province, B.city, B.usstate, B.country, YEAR(CURDATE())-YEAR(B.date_of_birth) AS age, 'LIKE' as type, email_to as email, B.picture, '' as matched_on,datediff(A.expires_matched_blocked, CURDATE()) as time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.email_to = B.email
			WHERE expires_matched_blocked > CURRENT_TIMESTAMP
			AND status = 'like'
			AND email_from = '{$request->email}'
			UNION
			SELECT B.username, B.gender, B.province, B.city, B.usstate, B.country, YEAR(CURDATE())-YEAR(B.date_of_birth) AS age, 'WAITING' as type, email_from as email, B.picture, '' as matched_on, datediff(A.expires_matched_blocked, CURDATE()) as time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.email_from = B.email
			WHERE expires_matched_blocked > CURRENT_TIMESTAMP
			AND status = 'like'
			AND email_to = '{$request->email}'
			UNION
			SELECT B.username, B.gender, B.province, B.city, B.usstate, B.country, YEAR(CURDATE())-YEAR(B.date_of_birth) AS age, 'MATCH' as type, email_from as email, B.picture, A.expires_matched_blocked as matched_on, '' as time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.email_from = B.email
			WHERE status = 'match'
			AND email_to = '{$request->email}'
			UNION
			SELECT B.username, B.gender, B.province, B.city, B.usstate, B.country, YEAR(CURDATE())-YEAR(B.date_of_birth) AS age, 'MATCH' as type, email_to as email, B.picture, A.expires_matched_blocked as matched_on, '' as time_left
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.email_to = B.email
			WHERE status = 'match'
			AND email_from = '{$request->email}'");

		// get values to construct the image path
		$di = \Phalcon\DI\FactoryDefault::getDefault();
		$wwwhttp = $di->get('path')['http'];
		$wwwroot = $di->get('path')['root'];
		$images = array();

		// initialize counters
		$likeCounter = 0;
		$waitingCounter = 0;
		$matchCounter = 0;

		// organize list of matches
		foreach ($matches as $match)
		{
			// count the number of each
			if($match->type == "LIKE") $likeCounter++;
			if($match->type == "WAITING") $waitingCounter++;
			if($match->type == "MATCH") $matchCounter++;

			// calculate the location
			$location = $match->country;
			if($match->city) $location = $match->city;
			if($match->usstate) $location = $match->usstate;
			if($match->province) $location = $match->province;
			$location = str_replace("_", " ", $location);
			$match->location = ucwords(strtolower($location));

			// get the link to the image
			if($match->picture)
			{
				$match->pictureURL = "$wwwhttp/profile/thumbnail/{$match->email}.jpg";
				$images[] = "$wwwroot/public/profile/thumbnail/{$match->email}.jpg";
			}

			// get rid of unnecesary stuff
			unset($match->email,$match->province,$match->country,$match->usstate,$match->city);
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

		$sender = $request->email;
		$receiver = $this->utils->getEmailFromUsername($request->query);
		if(empty($receiver)) return $response->createFromJSON('{"code":"ERROR", "message":"Wrong username"}');

		// check if you have enought flowers to send
		$flowers = $connection->deepQuery("SELECT email FROM _piropazo_people WHERE email='$sender' AND flowers>0");
		if(empty($flowers)) return $response->createFromJSON('{"code":"ERROR", "message":"Not enought flowers"}');

		// ensure the relation exists and it is a "like"
		$relation = $connection->deepQuery("SELECT status FROM _piropazo_relationships WHERE email_from='$sender' AND email_to='$receiver'");
		if(empty($relation)) return $response->createFromJSON('{"code":"ERROR", "message":"No relationship exist"}');
		if($relation[0]->status != "like") return $response->createFromJSON('{"code":"ERROR", "message":"Wrong relationship type"}');

		// send the flower
		$newExpire = date('Y-m-d', strtotime('+7 days'));
		$connection->deepQuery("
			START TRANSACTION;
			INSERT INTO _piropazo_flowers (sender,receiver) VALUES ('$sender','$receiver');
			UPDATE _piropazo_people SET flowers=flowers-1 WHERE email='$sender';
			UPDATE _piropazo_relationships SET expires_matched_blocked='$newExpire' WHERE email_from='$sender' AND email_to='$receiver';
			COMMIT");

		// post a notification for the user
		$username = $this->utils->getUsernameFromEmail($sender);
		$this->utils->addNotification($receiver, "piropazo", "Enhorabuena, @$username le ha mandado una flor. Este es un sintoma inequivoco de le gustas, y deberias revisar su perfil", "PIROPAZO PAREJAS");

		// send an email to the user
		$response = new Response();
		$response->setResponseEmail($receiver);
		$response->setEmailLayout("email_simple.tpl");
		$response->setResponseSubject("@$username le ha mandado una flor");
		$response->createFromTemplate('flower.tpl', array("username"=>$username));
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
		$connection = new Connection();
		$response = new Response();

		// check if you have enought crowns
		$crowns = $connection->deepQuery("SELECT crowns FROM _piropazo_people WHERE email='{$request->email}' AND crowns>0");
		if(empty($crowns)) return $response->createFromJSON('{"code":"ERROR", "message":"Not enought crowns"}');

		// send the flower
		$connection->deepQuery("
			START TRANSACTION;
			INSERT INTO _piropazo_crowns (email) VALUES ('{$request->email}');
			UPDATE _piropazo_people SET crowns=crowns-1, crowned=CURRENT_TIMESTAMP WHERE email='{$request->email}';
			COMMIT");

		// post a notification for the user
		$this->utils->addNotification($request->email, "piropazo", "Enhorabuena, Usted ha sido coronado. Ahora su perfil se mostrara a muchos mas usuarios por los proximos tres dias", "PIROPAZO");

		// do not return any response to the user
		return new Response();
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
		$connection->deepQuery("UPDATE _piropazo_people SET active=0 WHERE email='{$request->email}'");

		$response = new Response();
		$response->setResponseSubject('Haz salido de Piropazo');
		$response->createFromText('Haz salido de nuestra red de busqueda de parejas. No recibir&aacute;s m&aacute;s emails de otros usuarios diciendo que le gustas ni aparecer&aacute;s en la lista de Piropazo. &iexcl;Gracias!');
		return $response;
	}

	/**
	 * Get all unread notes, likes and flowers up to a timestamp.
	 * Useful for real-time conversations in the API
	 * Pass the time as YYYY-MM-DDTHH:MM:SS
	 *
	 * @api
	 * @author salvipascual
	 * @param Request $request
	 * @return Response
	 */
	public function _unread (Request $request)
	{
		// get current time and date
		if(empty($request->query)) $currentTime = strtotime("-1 month");
		else $currentTime = $request->query;
		$currentDateTime = date("Y-m-d H:i:s", $currentTime);

		// get the state of the system
		$connection = new Connection();
		$state = $connection->deepQuery("
			SELECT * FROM (
				SELECT 'NOTE' as type, B.username, MAX(send_date) as sent, COUNT(B.username) as counter
				FROM _note A LEFT JOIN person B
				ON A.from_user = B.email
				WHERE to_user = '{$request->email}'
				AND send_date > '$currentDateTime'
				GROUP BY B.username
				UNION
				SELECT 'LIKE' as type, B.username, A.inserted as sent, '1' as counter
				FROM _piropazo_relationships A LEFT JOIN person B
				ON A.email_from = B.email
				WHERE email_to = '{$request->email}'
				AND A.inserted > '$currentDateTime'
				UNION
				SELECT 'FLOWER' as type, B.username, MAX(A.sent) as sent, COUNT(B.username) as counter
				FROM _piropazo_flowers A LEFT JOIN person B
				ON A.sender = B.email
				WHERE receiver = '{$request->email}'
				AND A.sent > '$currentDateTime'
				GROUP BY B.username) C
			ORDER BY sent DESC");

		// create the response object
		$jsonResponse = array(
			"last" => mktime(),
			"code" => "ok",
			"items" => $state
		);

		// respond back to the API
		$response = new Response();
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
		// get the list of people
		$connection = new Connection();
		return $connection->deepQuery("
			SELECT
				A.*, B.likes*(B.likes/(B.likes+B.dislikes)) AS popularity,
				(IFNULL(datediff(CURDATE(), B.crowned),99) < 3) as crown
			FROM person A
			RIGHT JOIN _piropazo_people B
			ON A.email = B.email
			WHERE A.picture = 1
			AND A.gender <> '{$user->gender}'
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
		// create the where clause for the query
		$where  = "A.email <> '{$user->email}' ";
		$where .= "AND A.email NOT IN (SELECT email_to FROM _piropazo_relationships WHERE email_from = '{$user->email}') ";
		if ($user->sexual_orientation == 'HETERO') $where .= "AND gender <> '{$user->gender}' AND sexual_orientation <> 'HOMO' ";
		if ($user->sexual_orientation == 'HOMO') $where .= "AND gender = '{$user->gender}' AND sexual_orientation <> 'HETERO' ";
		if ($user->sexual_orientation == 'BI') $where .= " AND (sexual_orientation = 'BI' OR (sexual_orientation = 'HOMO' AND gender = '{$user->gender}') OR (sexual_orientation = 'HETERO' AND gender <> '{$user->gender}')) ";
		$where .= "AND (marital_status <> 'CASADO' OR marital_status IS NULL) ";
		$where .= "AND A.active=1 AND B.active=1";

		// @TODO find a way to calculate interests faster
		// @TODO this is very important, but I dont have the time now

		// create subquery to calculate the percentages
		$subsql  = "SELECT A.*, ";
		$subsql .= "(select IFNULL(city, '') = '{$user->city}') * 60 as city_proximity, ";
		$subsql .= "(select IFNULL(province, '') = '{$user->province}') * 50 as province_proximity, ";
		$subsql .= "(select IFNULL(usstate, '') = '{$user->usstate}') * 50 as state_proximity, ";
		$subsql .= "(select IFNULL(country, '') = '{$user->country}') * 10 as country_proximity, ";
		$subsql .= "(select IFNULL(marital_status, '') = 'SOLTERO') * 20 as percent_single, ";
		$subsql .= "(select B.likes*B.likes/(B.likes+B.dislikes)) as popularity, ";
		$subsql .= "(select IFNULL(skin, '') = '{$user->skin}') * 5 as same_skin, ";
		$subsql .= "(select picture = 1) * 30 as having_picture, ";
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
		$people = $connection->deepQuery(trim($sql));

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
		$crowned = $connection->deepQuery("
			SELECT COUNT(id) AS crowned
			FROM _piropazo_crowns
			WHERE email='html@apretaste.com'
			AND datediff(CURDATE(), crowned) < 3");
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
		$crowned = $connection->deepQuery("
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
		$connection->deepQuery("UPDATE _piropazo_people SET last_access=CURRENT_TIMESTAMP WHERE email='$email'");
	}
}
