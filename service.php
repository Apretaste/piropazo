<?php

use Apretaste\Amulets;
use Apretaste\Challenges;
use Apretaste\Chats;
use Apretaste\Level;
use Apretaste\Money;
use Apretaste\Notifications;
use Apretaste\Person;
use Apretaste\Request;
use Apretaste\Response;
use Framework\Alert;
use Framework\Core;
use Framework\Database;
use Framework\Images;
use Framework\Utils;

/**
 * Apretaste Piropazo Service
 */
class Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _main(Request $request, Response $response)
	{
		// by default, open citas
		$this->_citas($request, $response);
	}

	/**
	 * Get dates for your profile
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return Response|void
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _citas(Request $request, Response $response)
	{
		if (!$this->isActive($request->person->id)) {
			$content = [
				'header' => 'Tiempo sin verte',
				'icon' => 'access_time',
				'text' => 'Parece que es la primera vez que entras al servicio o has desactivado su uso antes, si deseas usar Piropazo debes ser visible para las demas personas.',
				'button' => ['href' => 'PIROPAZO ACTIVATE', 'caption' => 'Usar Piropazo']];
			$response->setLayout('empty.ejs');
			$response->setTemplate('message.ejs', $content);
			return;
		}

		if ($this->isProfileIncomplete($request->person)) {
			// get the edit response
			$request->extra_fields = 'hide';
			$this->_perfil($request, $response);
			return;
		}

		// get the best match for the user
		$match = $this->getMatchFromCache($request->person);

		// if no matches, let the user know
		if (!$match) {
			$content = [
				'header' => 'No hay citas',
				'icon' => 'sentiment_very_dissatisfied',
				'text' => 'Esto es vergonsozo, pero no pudimos encontrar a nadie que vaya con usted. Por favor regrese más tarde, o cambie su perfil e intente nuevamente.',
				'button' => ['href' => 'PIROPAZO PERFIL', 'caption' => 'Editar perfil'],
				'title' => 'Citas'];
			$response->setLayout('piropazo.ejs');
			$response->setTemplate('message.ejs', $content);
			return;
		}

		Person::setProfileTags($match);

		$match->country = $match->country === 'cu' ? 'Cuba' : 'Otro';
		$match->education = Core::$education[$match->education];
		$match->religion = Core::$religions[$match->religion];

		// get match images into an array and the content
		$images = $match->picture ? [SHARED_PUBLIC_PATH . 'profile/' . $match->picture] : [];

		// erase unwanted properties in the object
		$properties = ['id', 'username', 'firstName', 'heart', 'gender', 'aboutMe', 'education', 'religion', 'picture', 'country', 'location', 'age', 'isOnline'];
		$match = $this->filterObjectProperties($properties, $match);

		// mark the last time the system was used
		$this->markLastTimeUsed($request->person->id);

		// get the number of flowers for the logged user
		$myFlowers = Database::query("SELECT flowers FROM _piropazo_people WHERE id_person={$request->person->id}");

		$content = [
			'match' => $match,
			'myflowers' => $myFlowers[0]->flowers
		];

		// build the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('dates.ejs', $content, $images);
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws \FeedException
	 * @throws \Framework\Alert
	 */

	public function _sinext(Request $request, Response $response)
	{
		$this->_si($request, $response);
		$this->_main($request, $response);
	}

	/**
	 * Say Yes to a match
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _si(Request $request, Response $response)
	{
		// get the emails from and to
		$idFrom = $request->person->id;
		$idTo = $request->input->data->id;
		if (empty($idTo)) {
			return;
		}

		// check if there is any previous record between you and that person
		$record = Database::query("SELECT status FROM _piropazo_relationships WHERE id_from='$idTo' AND id_to='$idFrom'");

		// if they liked you, like too; if they dislike you, block
		if ($record) {
			// if they liked you, create a match
			if ($record[0]->status === 'like') {
				// get the target @username
				$username = Database::query("SELECT username FROM person WHERE id = $idTo")[0]->username;

				// make friends
				$request->person->requestFriend($idTo);
				Person::find($idTo)->requestFriend($idFrom);

				// update to create a match
				Database::query("UPDATE _piropazo_relationships SET status='match', expires_matched_blocked=CURRENT_TIMESTAMP WHERE id_from='$idTo' AND id_to='$idFrom'");

				// add the experience
				Level::setExperience('PIROPAZO_MATCH', $idFrom);
				Level::setExperience('PIROPAZO_MATCH', $idTo);

				// track challenges
				$mutate = static function ($track) {
					$track++;
					return $track;
				};

				Challenges::track($idFrom, 'piropazo-matches-3', 0, $mutate);
				Challenges::track($idTo, 'piropazo-matches-3', 0, $mutate);

				// create notifications for both you and your date
				Notifications::alert($idFrom, "Felicidades, ambos tu y @$username se han gustado", 'people', '{"command":"PIROPAZO PAREJAS"}');
				Notifications::alert(
					$idTo,
					"Felicidades, ambos tu y @{$request->person->username} se han gustado",
					'people',
					'{"command":"PIROPAZO PAREJAS"}'
				);
			}

			// if they dislike you, block that match
			if ($record[0]->status === 'dislike') {
				Database::query("UPDATE _piropazo_relationships SET status='blocked', expires_matched_blocked=CURRENT_TIMESTAMP WHERE id_from='$idTo' AND id_to='$idFrom'");
			}
			return;
		}

		if ($this->isActive($idTo)) {
			// insert the new relationship
			$threeDaysForward = date('Y-m-d H:i:s', strtotime('+3 days'));
			Database::query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE id_from='$idFrom' AND id_to='$idTo';
			INSERT INTO _piropazo_relationships (id_from,id_to,status,expires_matched_blocked) VALUES ('$idFrom','$idTo','like','$threeDaysForward');
			COMMIT");

			// remove match from the cache so it won't show again
			Database::query("DELETE FROM _piropazo_cache WHERE user={$idFrom} AND suggestion={$idTo}");

			// add challenge
			Challenges::complete('piropazo-say-yes-no', $request->person->id);
		}
	}

	/**
	 * Check if a profile is incomplete
	 *
	 * @param Object $person
	 * @return Boolean
	 * @throws Alert
	 * @author salvipascual
	 */
	private function isProfileIncomplete($person)
	{
		// run powers for amulet ROMEO
		// NOTE: added here for convenience, since this function is called all over the place
		if (Amulets::isActive(Amulets::ROMEO, $person->id)) {
			Database::query("UPDATE _piropazo_people SET crowned=CURRENT_TIMESTAMP WHERE id_person={$person->id}");
		}

		// ensure your profile is completed
		return (
			empty($person->picture) ||
			empty($person->firstName) ||
			empty($person->gender) ||
			empty($person->sexualOrientation) ||
			$person->age < 10 || $person->age > 110 ||
			empty($person->province) || empty($person->education) ||
			empty($person->religion)
		);
	}

	/**
	 * Open the user's profile
	 *
	 * @param Request
	 * @param Response
	 * @return Response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _perfil(Request $request, Response $response)
	{
		$profileIncomplete = $this->isProfileIncomplete($request->person);

		// get the user's profile
		$id = $request->input->data->id ?? $request->person->id;
		$isMyOwnProfile = $id === $request->person->id;
		$profile = Person::find($id);

		if (!$isMyOwnProfile) {
			// run powers for amulet DETECTIVE
			if (Amulets::isActive(Amulets::DETECTIVE, $id)) {
				$msg = "Los poderes del amuleto del Druida te avisan: @{$request->person->username} está revisando tu perfil";
				Notifications::alert($profile->id, $msg, 'pageview', "{command:'PERFIL', data:{username:'@{$request->person->username}'}}");
			}

			// run powers for amulet SHADOWMODE
			if (Amulets::isActive(Amulets::SHADOWMODE, $id)) {
				return $response->setTemplate('message.ejs', [
					'header' => 'Shadow-Mode',
					'icon' => 'visibility_off',
					'text' => 'La magia oscura de un amuleto rodea este perfil y te impide verlo. Por mucho que intentes romperlo, el hechizo del druida es poderoso.'
				]);
			}
		}

		$user = Database::queryFirst("
			SELECT crowns, IFNULL(TIMESTAMPDIFF(DAY, crowned,NOW()),3) < 3 AS heart, IFNULL(TIMESTAMPDIFF(SECOND, crowned,NOW()),0) AS heart_time_left, minAge, maxAge
			FROM _piropazo_people
			WHERE id_person = {$request->person->id}");

		$profile->hearts = $user->crowns;
		$profile->heart = $user->heart;
		$profile->heart_time_left = 60 * 60 * 24 * 3 - $user->heart_time_left;

		$profile->minAge = intval($user->minAge);
		$profile->maxAge = intval($user->maxAge);

		// get what gender do you search for
		if ($profile->sexualOrientation === 'BI') {
			$profile->searchfor = 'AMBOS';
		} elseif ($profile->gender === 'M' && $profile->sexualOrientation === 'HETERO') {
			$profile->searchfor = 'MUJERES';
		} elseif ($profile->gender === 'F' && $profile->sexualOrientation === 'HETERO') {
			$profile->searchfor = 'HOMBRES';
		} elseif ($profile->gender === 'M' && $profile->sexualOrientation === 'HOMO') {
			$profile->searchfor = 'HOMBRES';
		} elseif ($profile->gender === 'F' && $profile->sexualOrientation === 'HOMO') {
			$profile->searchfor = 'MUJERES';
		} else {
			$profile->searchfor = '';
		}

		// get array of images
		$images = [];
		if ($profile->picture ?? false) {
			$images[] = SHARED_PUBLIC_PATH . 'profile/' . $profile->picture;
		}

		// list of values
		$content = [
			'profileIncomplete' => $profileIncomplete,
			'profile' => $profile,
			'isMyOwnProfile' => $isMyOwnProfile,
			'title' => 'Perfil',
		];


		$images[] = SERVICE_PATH . $response->service . '/images/icon.png';

		// prepare response for the view
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('profile.ejs', $content, $images);
	}

	/**
	 * Make active if the person uses Piropazo for the first time
	 *
	 * @param Int $id
	 * @throws Alert
	 * @author salvipascual
	 */
	private function activatePiropazoUser($id)
	{
		Database::query("INSERT INTO _piropazo_people (id_person) VALUES('$id') ON DUPLICATE KEY UPDATE active = 1");
	}

	/**
	 * Check if the user exists in piropazo and is active
	 *
	 * @param Int $id
	 * @throws Alert
	 * @author ricardo
	 */
	private function isActive($id)
	{
		$res = Database::query("SELECT active FROM _piropazo_people WHERE id_person='$id'");
		if ($res) {
			return $res[0]->active;
		} else {
			return false;
		}
	}

	/**
	 * Get the person who best matches with you
	 *
	 * @param Person $user
	 * @return object | Person
	 * @throws Alert
	 * @author salvipascual
	 */
	private function getMatchFromCache($user)
	{
		// create cache if needed
		$this->createMatchesCache($user);

		// get one suggestion from cache
		$match = Database::query("
			SELECT 
				A.id, A.suggestion AS user, 
				IFNULL(TIMESTAMPDIFF(DAY, B.crowned,NOW()),3) < 3 AS heart
			FROM _piropazo_cache A
			JOIN _piropazo_people B
			ON A.suggestion = B.id_person
			WHERE A.user = {$user->id}
			ORDER BY heart DESC, A.match DESC, A.id
			LIMIT 1");

		// return false if no match
		if (empty($match)) {
			return false;
		} else {
			$match = $match[0];
		}

		// return the best match as a Person object
		$person = Person::find($match->user);

		// check if match has all the data
		if ($this->isProfileIncomplete($person)) {
			// remove match from the cache so it won't show again
			Database::query("DELETE FROM _piropazo_cache WHERE user={$user->id} AND suggestion={$person->id}");
			return $this->getMatchFromCache($user);
		}

		$person->heart = $match->heart;

		// get the match color class based on gender
		if ($person->gender === 'M') {
			$person->color = 'male';
		} elseif ($person->gender === 'F') {
			$person->color = 'female';
		} else {
			$person->color = 'neutral';
		}

		// return the match
		return $person;
	}

	/**
	 * Create matches cache to speed up searches
	 *
	 * @param Person $user , you
	 * @return Boolean
	 * @throws Alert
	 * @author salvipascual
	 */
	private function createMatchesCache($user)
	{
		// do not cache if already exist data
		$isCache = Database::query("SELECT COUNT(id) AS cnt FROM _piropazo_cache WHERE user = {$user->id}");
		$piropazoPreferences = Database::queryFirst("SELECT minAge, maxAge FROM _piropazo_people WHERE id_person = {$user->id}");
		if ($isCache[0]->cnt > 0) {
			return false;
		}

		// filter based on sexual orientation
		switch ($user->sexualOrientation) {
			case 'HETERO':
				$clauseSex = "A.gender <> '$user->gender' AND A.sexual_orientation <> 'HOMO' ";
				break;
			case 'HOMO':
				$clauseSex = "A.gender = '$user->gender' AND A.sexual_orientation <> 'HETERO' ";
				break;
			case 'BI':
				$clauseSex = "(A.sexual_orientation = 'BI' OR (A.sexual_orientation = 'HOMO' AND A.gender = '$user->gender') OR (A.sexual_orientation = 'HETERO' AND A.gender <> '$user->gender')) ";
				break;
		}

		// get the list of people already voted
		$clauseVoted = [];
		$voted = Database::query("
			SELECT id_to as id FROM _piropazo_relationships WHERE id_from = {$user->id} 
			UNION 
			SELECT id_from as id FROM _piropazo_relationships WHERE id_to = {$user->id}");
		if (empty($voted)) {
			$clauseVoted = "''";
		} else {
			foreach ($voted as $v) {
				$clauseVoted[] = "'" . $v->id . "'";
			}
			$clauseVoted = implode(',', $clauseVoted);
		}

		// select all users to filter by
		$clauseSubquery = "
			SELECT 
				A.id, A.username, A.first_name, A.year_of_birth, A.gender, A.province, A.city, A.picture, A.country, A.usstate, A.religion,
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
			AND (A.year_of_birth IS NULL OR IFNULL(YEAR(NOW())-year_of_birth,0) >= {$piropazoPreferences->minAge})
			AND (A.year_of_birth IS NULL OR IFNULL(YEAR(NOW())-year_of_birth,0) <= {$piropazoPreferences->maxAge})
			AND NOT A.id = '$user->id'";

		// create final query with the match score
		$cacheUsers = Database::query("
			SELECT id,
				(IFNULL(country, 'NO') = '$user->countryCode') * 10 +
				(IFNULL(province, 'NO') = '$user->provinceCode') * 50 +
				(IFNULL(usstate, 'NO') = '$user->stateCode') * 50 +
				(ABS(IFNULL(YEAR(NOW())-year_of_birth,0) - $user->age) <= 5) * 20 +
				crown * 25 +
				(IFNULL(religion, 'NO') = '$user->religion') * 20
				AS percent_match
			FROM ($clauseSubquery) AS results 
			HAVING percent_match > 0
			ORDER BY percent_match DESC
			LIMIT 10");

		// do not create cache if no suggestions were found
		if (empty($cacheUsers)) {
			return false;
		}

		// create the cache of suggestions
		$inserts = [];
		foreach ($cacheUsers as $c) {
			$inserts[] = "({$user->id}, {$c->id}, {$c->percent_match})";
		}
		Database::query('INSERT INTO _piropazo_cache (`user`, suggestion, `match`) VALUES ' . implode(',', $inserts));

		return true;
	}

	/**
	 * Remove all properties in an object except the ones passes in the array
	 *
	 * @param array $properties , array of properties to keep
	 * @param Object $object , object to clean
	 * @return Object, clean object
	 * @author salvipascual
	 */
	private function filterObjectProperties($properties, $object)
	{
		$objProperties = get_object_vars($object);
		foreach ($objProperties as $prop => $value) {
			if (!in_array($prop, $properties)) {
				unset($object->$prop);
			}
		}
		return $object;
	}

	/**
	 * Mark the last time the system was used by a user
	 *
	 * @param Int $id
	 * @throws Alert
	 * @author salvipascual
	 */
	private function markLastTimeUsed($id)
	{
		Database::query("UPDATE _piropazo_people SET last_access=CURRENT_TIMESTAMP WHERE id_person='$id'");
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws \FeedException
	 * @throws \Framework\Alert
	 */

	public function _nonext(Request $request, Response $response)
	{
		$this->_no($request, $response);
		$this->_main($request, $response);
	}

	/**
	 * Say No to a match
	 *
	 * @param Request
	 * @param Response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _no(Request $request, Response $response)
	{
		// get the ids from and to
		$idFrom = $request->person->id;
		$idTo = $request->input->data->id;
		if (empty($idTo)) {
			return;
		}

		if ($this->isActive($idTo)) {
			// mark the transaction as blocked
			Database::query("
			START TRANSACTION;
			DELETE FROM _piropazo_relationships WHERE (id_from='$idFrom' AND id_to='$idTo') OR (id_to='$idFrom' AND id_from='$idTo');
			INSERT INTO _piropazo_relationships (id_from,id_to,status,expires_matched_blocked) VALUES ('$idFrom','$idTo','dislike',CURRENT_TIMESTAMP);
			COMMIT");

			// remove match from the cache so it won't show again
			Database::query("DELETE FROM _piropazo_cache WHERE user={$idFrom} AND suggestion={$idTo}");

			// add challenge
			Challenges::complete('piropazo-say-yes-no', $request->person->id);
		}
	}

	/**
	 * Flag a user's profile
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @return \Apretaste\Response
	 * @throws \Framework\Alert
	 * @author salvipascual
	 */
	public function _reportar(Request $request, Response $response)
	{
		// do not allow empty codes
		$violatorId = $request->input->data->id;
		$violationCode = $request->input->data->violation;
		if (empty($violatorId) || empty($violationCode)) {
			return $response;
		}

		// save the report
		Database::query("
			INSERT INTO _piropazo_reports (id_reporter, id_violator, type) 
			VALUES ({$request->person->id}, $violatorId, '$violationCode')");

		// say NO to the user
		$request->query = $violatorId;
		$this->_no($request, $response);
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert|FeedException
	 */

	public function _activate(Request $request, Response $response)
	{
		// create or activate piropazo user
		$this->activatePiropazoUser($request->person->id);
		$this->_main($request, $response);
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert|FeedException
	 */

	public function _deactivate(Request $request, Response $response)
	{
		// create or activate piropazo user
		Database::query("UPDATE _piropazo_people SET active=0 WHERE id_person = '{$request->person->id}'");
		$this->_main($request, $response);
	}

	/**
	 * Get the list of matches for your user
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _parejas(Request $request, Response $response)
	{
		if ($this->isProfileIncomplete($request->person)) {
			// get the edit response
			$request->extra_fields = 'hide';
			$this->_perfil($request, $response);
			return;
		}

		// get list of people whom you liked or liked you
		$matches = Database::query("
			SELECT B.*, 'WAITING' AS type, A.id_from AS id, '' AS matched_on, datediff(A.expires_matched_blocked, CURDATE()) AS time_left,
			       last_access < CURRENT_DATE as is_first_access_today,
			       MONTH(last_access) < MONTH(CURRENT_DATE) as is_first_access_month 
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_from = B.id
			WHERE expires_matched_blocked > CURRENT_TIMESTAMP
			AND A.status = 'like'
			AND id_to = '{$request->person->id}'
			UNION
			SELECT B.*, 'MATCH' AS type, A.id_from AS id, A.expires_matched_blocked AS matched_on, '' AS time_left,
			       last_access < CURRENT_DATE as is_first_access_today,
			       MONTH(last_access) < MONTH(CURRENT_DATE) as is_first_access_month 
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_from = B.id
			WHERE A.status = 'match'
			AND id_to = '{$request->person->id}'
			UNION
			SELECT B.*, 'MATCH' AS type, A.id_to AS id, A.expires_matched_blocked AS matched_on, '' AS time_left,
			       last_access < CURRENT_DATE as is_first_access_today,
			       MONTH(last_access) < MONTH(CURRENT_DATE) as is_first_access_month 
			FROM _piropazo_relationships A
			LEFT JOIN person B
			ON A.id_to = B.id
			WHERE A.status = 'match'
			AND id_from = '{$request->person->id}' LIMIT 20");

		// if no matches, let the user know
		if (empty($matches)) {
			$content = [
				'header' => 'No tiene parejas',
				'icon' => 'sentiment_very_dissatisfied',
				'text' => 'Aún no tiene parejas, ni nadie ha pedido ser pareja suya. Si esperaba ver a alguien aquí es posible que hayan dejado de usar el servicio. No se desanime, hay muchos más peces en el océano.',
				'button' => ['href' => 'PIROPAZO CITAS', 'caption' => 'Buscar Pareja'],
				'title' => 'Parejas'];
			$response->setLayout('piropazo.ejs');
			$response->setTemplate('message.ejs', $content);
			return;
		}

		// organize list of matches
		$waiting = $matched = $images = [];
		foreach ($matches as $match) {
			// get the full profile
			$match = (object)array_merge((array)$match, (array)Person::prepareProfile($match));

			// get match images into an array and the content
			$images = $match->picture ? [SHARED_PUBLIC_PATH . 'profile/' . $match->picture] : [];

			// get match properties
			$match->matched_on = date('d/m/Y', strtotime($match->matched_on));
			$match->education = isset(Core::$education[$match->education]) ? Core::$education[$match->education] : '';
			$match->religion = isset(Core::$religions[$match->religion]) ? Core::$religions[$match->religion] : '';

			// erase unwanted properties in the object
			$properties = ['id','username','firstName','gender','age','type','location','religion','education','picture','matched_on','time_left','isOnline'];
			$match = $this->filterObjectProperties($properties, $match);

			// count the number of waiting
			if ($match->type === 'WAITING') {
				$waiting[] = $match;
			}

			// count the number of matches
			if ($match->type === 'MATCH') {
				$matched[] = $match;
			}
		}

		// mark the last time the system was used
		$this->markLastTimeUsed($request->person->id);

		// get the number of flowers for the logged user
		$myFlowers = Database::query("SELECT flowers FROM _piropazo_people WHERE id_person={$request->person->id}");

		// create response array
		$content = [
			'myflowers' => $myFlowers[0]->flowers,
			'waiting' => $waiting,
			'matched' => $matched,
			'title' => 'Parejas',
		];

		// Building the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('matches.ejs', $content, $images);
	}

	/**
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws \FeedException
	 * @throws \Framework\Alert
	 */
	public function _flornext(Request $request, Response $response)
	{
		$this->_flor($request, $response);
		$this->_main($request, $response);
	}

	/**
	 * Sends a flower to another user
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _flor(Request $request, Response $response)
	{
		// get the edit response
		if ($this->isProfileIncomplete($request->person)) {
			$request->extra_fields = 'hide';
			$this->_perfil($request, $response);
			return;
		}

		// check if you have enought flowers to send
		$flowers = Database::query("SELECT id_person FROM _piropazo_people WHERE id_person='{$request->person->id}' AND flowers>0");
		if (empty($flowers)) {
			$content = [
				'header' => 'No tiene suficientes flores',
				'icon' => 'local_florist',
				'text' => 'Actualmente usted no tiene suficientes flores para usar. Puede comprar algunas flores frescas en la tienda de Piropazo.',
				'button' => ['href' => 'PIROPAZO TIENDA', 'caption' => 'Tienda']];
			$response->setLayout('empty.ejs');
			$response->setTemplate('message.ejs', $content);
			return;
		}

		// get the message sent with the flower
		$message = trim(Database::escape($request->input->data->msg, 200));
		$message = !empty($message) ? "{$request->person->firstName} le envia una flor: $message" : "{$request->person->firstName} le envia una flor";

		// get the recipient's username
		$name = Database::query("SELECT first_name FROM person WHERE id='{$request->input->data->id}'")[0]->first_name;

		// send the flower and increase response time in 7 days
		Database::query("
			INSERT INTO _piropazo_flowers (id_sender,id_receiver,message) VALUES ('{$request->person->id}','{$request->input->data->id}','$message');
			UPDATE _piropazo_people SET flowers=flowers-1 WHERE id_person='{$request->person->id}';
			UPDATE _piropazo_relationships SET expires_matched_blocked=ADDTIME(expires_matched_blocked,'168:00:00.00') WHERE id_from='{$request->person->id}' AND id_to='{$request->input->data->id}';");

		// create a notification for the user
		Notifications::alert($request->input->data->id, "$message", 'local_florist', '{"command":"PIROPAZO PAREJAS"}');

		// let the sender know the flower was delivered
		$content = [
			'header' => 'Su flor fue enviada',
			'icon' => 'local_florist',
			'text' => "$name recibirá una notificación y seguro le contestará lo antes posible. También le hemos dado una semana extra para que responda.",
			'button' => ['href' => 'PIROPAZO PAREJAS', 'caption' => 'Mis parejas']];
		$response->setLayout('empty.ejs');
		$response->setTemplate('message.ejs', $content);
	}

	/**
	 * Use a heart to highlight your profile
	 *
	 * @param Request
	 * @param Response
	 * @return Response|void
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _corazon(Request $request, Response $response)
	{
		// get the edit response
		if ($this->isProfileIncomplete($request->person)) {
			$request->extra_fields = 'hide';
			return $this->_perfil($request, $response);
		}

		// check if you have enought crowns
		$crowns = Database::query("SELECT crowns FROM _piropazo_people WHERE id_person='{$request->person->id}' AND crowns > 0");

		// return error response if the user has no crowns
		if (empty($crowns)) {
			return;
		}

		// set the crown and substract a crown
		Database::query("UPDATE _piropazo_people SET crowns=crowns-1, crowned=CURRENT_TIMESTAMP WHERE id_person={$request->person->id}");

		// post a notification for the user
		Notifications::alert(
			$request->person->id,
			'Enhorabuena, Usted se ha agregado un corazon. Ahora su perfil se mostrara a muchos más usuarios por los proximos tres dias',
			'favorite',
			'{"command":"piropazo"}'
		);
	}

	/**
	 * Open the conversation
	 *
	 * @param Request
	 * @param Response
	 * @throws Alert
	 * @throws Exception
	 * @author salvipascual
	 */
	public function _conversacion(Request $request, Response $response)
	{
		// get the edit response
		if ($this->isProfileIncomplete($request->person)) {
			$request->extra_fields = 'hide';
			return $this->_perfil($request, $response);
		}

		// get the username of the note
		$user = Person::find($request->input->data->userId);

		// check if the username is valid
		if (!$user) {
			return $response->setTemplate('notFound.ejs');
		}

		// get the conversation
		$messages = Chats::conversation($request->person->id, $user->id);

		$chats = [];
		$images = [];
		$chatImgDir = SHARED_PUBLIC_PATH . '/content/chat';

		foreach ($messages as $message) {
			$me = $message->username == $request->person->username;

			$chat = new stdClass();
			$chat->id = $message->id;
			$chat->username = $message->username;
			$chat->text = $message->text;
			$chat->sent = $message->sent;
			$chat->read = $message->read;
			$chat->readed = $message->readed;
			$chat->name = $me ? $request->person->firstName : $user->firstName;
			$chat->gender = $me ? $request->person->gender : $user->gender;

			$chat->picture = $me ? $request->person->picture : $user->picture;
			$images[] = SHARED_PUBLIC_PATH . 'profile/' . $chat->picture;

			if ($message->image) {
				$message->image .= '.jpg';
				$images[] = "$chatImgDir/{$message->image}";

				$chat->image = $message->image;
			}

			$chats[] = $chat;
		}

		$content = [
			'messages' => $chats,
			'myName' => $request->person->firstName,
			'myPicture' => $request->person->picture,
			'myGender' => $request->person->gender,
			'id' => $user->id,
			'online' => $user->isOnline,
			'last' => date('d/m/Y h:i a', strtotime($user->lastAccess)),
			'title' => 'Parejas'
		];

		$response->setlayout('piropazo.ejs');
		$response->setTemplate('conversation.ejs', $content, $images);
	}

	/**
	 * Open the store
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _tienda(Request $request, Response $response)
	{
		if ($this->isProfileIncomplete($request->person)) {
			// get the edit response
			$request->extra_fields = 'hide';
			return $this->_perfil($request, $response);
		}

		// get the user items
		$user = Database::query("
			SELECT flowers, crowns
			FROM _piropazo_people
			WHERE id_person = {$request->person->id}")[0];

		// get the user credit
		$credit = Database::query("SELECT credit FROM person WHERE id={$request->person->id}")[0]->credit;

		// prepare content for the view
		$content = [
			'credit' => $credit,
			'flowers' => $user->flowers,
			'crowns' => $user->crowns,
			'title' => 'Tienda'
		];

		// build the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('store.ejs', $content);
	}

	/**
	 * Show a list of notifications
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _notificaciones(Request $request, Response $response)
	{
		if ($this->isProfileIncomplete($request->person)) {
			// get the edit response
			$request->extra_fields = 'hide';
			return $this->_perfil($request, $response);
		}

		// get all unread notifications
		$notifications = Database::query("
			SELECT id,icon,`text`,link,inserted
			FROM notification
			WHERE `to` = {$request->person->id} 
			AND service = 'piropazo'
			AND `hidden` = 0
			ORDER BY inserted DESC");

		// if no notifications, let the user know
		if (empty($notifications)) {
			$content = [
				'header' => 'Nada por leer',
				'icon' => 'notifications_off',
				'text' => 'Por ahora usted no tiene ninguna notificación por leer.',
				'button' => ['href' => 'PIROPAZO CITAS', 'caption' => 'Buscar Pareja']];
			$response->setLayout('empty.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		foreach ($notifications as $noti) {
			$noti->inserted = strtoupper(date('d/m/Y h:ia', strtotime(($noti->inserted))));
		}

		// prepare content for the view
		$content = [
			'notifications' => $notifications,
			'title' => 'Notificaciones',
		];

		// build the response
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('notifications.ejs', $content);
	}

	/**
	 * Exit the Piropazo network
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _salir(Request $request, Response $response)
	{
		// remove from piropazo
		Database::query("UPDATE _piropazo_people SET active=0 WHERE id_person={$request->person->id}");

		// respond to user
		$content = [
			'header' => 'Ha salido de Piropazo',
			'icon' => 'directions_walk',
			'text' => 'No recibirá más mensajes de otros usuarios ni aparecerá en la lista de Piropazo. Si revisa Piropazo nuevamente, su perfil será agregado automáticamente.',
			'button' => ['href' => 'SERVICIOS', 'caption' => 'Otros Servicios']];
		$response->setLayout('empty.ejs');
		$response->setTemplate('message.ejs', $content);
	}

	/**
	 * Chats lists with matches filter
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 * @throws Alert
	 * @author ricardo
	 */
	public function _chat(Request $request, Response $response)
	{
		if ($this->isProfileIncomplete($request->person)) {
			// get the edit response
			$request->extra_fields = 'hide';
			return $this->_perfil($request, $response);
		}

		// get the list of people chating with you
		$chats = Chats::open($request->person->id);

		$matches = Database::query("SELECT id_from AS id
		FROM _piropazo_relationships
		WHERE status = 'match'
		AND id_to = '{$request->person->id}'
		UNION
		SELECT id_to AS id
		FROM _piropazo_relationships
		WHERE status = 'match'
		AND id_from = '{$request->person->id}'");

		$matchesId = [];
		foreach ($matches as $match) {
			$matchesId[$match->id] = $match;
		}

		$onlyMatchesChats = [];
		$images = [];
		foreach ($chats as $chat) {
			if (key_exists($chat->id, $matchesId)) {
				$chat->last_sent = explode(' ', $chat->last_sent)[0];
				if ($chat->picture) {
					$images[] = SHARED_PUBLIC_PATH . 'profile/' . $chat->picture;
				}
				$onlyMatchesChats[] = $chat;
			}
		}

		// if no matches, let the user know
		if (empty($onlyMatchesChats)) {
			$content = [
				'header' => 'No tiene conversaciones',
				'icon' => 'sentiment_very_dissatisfied',
				'text' => 'Aún no ha hablado con nadie. Cuando dos personas se gustan, pueden empezar una conversación.',
				'button' => ['href' => 'PIROPAZO', 'caption' => 'Buscar pareja']];
			$response->setLayout('empty.ejs');
			return $response->setTemplate('message.ejs', $content);
		}

		$content = [
			'chats' => $onlyMatchesChats,
			'myuserid' => $request->person->id,
			'title' => 'Chat',
		];

		$response->setLayout('piropazo.ejs');
		$response->setTemplate('chats.ejs', $content, $images);
	}

	/**
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _escribir(Request $request, Response $response)
	{
		if (!isset($request->input->data->id)) {
			return;
		}
		$userTo = Person::find($request->input->data->id);
		if (!$userTo) {
			return;
		}
		$message = $request->input->data->message;

		$blocks = Chats::isBlocked($request->person->id, $userTo->id);
		if ($blocks->blocked > 0 || $blocks->blockedByMe > 0) {
			Notifications::alert(
				$request->person->id,
				"Su mensaje para @{$userTo->username} no pudo ser entregado, es posible que usted haya sido bloqueado por esa persona.",
				'error'
			);
			return;
		}

		$image = $request->input->data->image ?? false;
		$fileName = '';

		// get the image name and path
		if ($image) {
			$chatImgDir = SHARED_PUBLIC_PATH . '/content/chat';
			$fileName = Utils::randomHash();
			$filePath = "$chatImgDir/$fileName.jpg";

			// save the optimized image on the user folder
			file_put_contents($filePath, base64_decode($image));
			Images::optimize($filePath);
		}

		// store the note in the database
		$message = Database::escape($message, 499, 'utf8mb4');
		Database::query("INSERT INTO _note (from_user, to_user, `text`, image) VALUES ({$request->person->id},{$userTo->id},'$message', '$fileName')", true, 'utf8mb4');

		// send notification for the app
		Notifications::alert(
			$userTo->id,
			"@{$request->person->username} le ha enviado un mensaje",
			'message',
			"{'command':'chat', 'data':{'userId':'{$request->person->id}'}}"
		);
	}

	/**
	 * Show the list of support messages
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @throws Exception
	 */
	public function _soporte(Request $request, Response $response)
	{
		// @TODO replace email with ids
		$email = $request->person->email;
		$username = $request->person->username;

		// get the list of messages
		$tickets = Database::query("
			SELECT A.*, B.username 
			FROM support_tickets A 
			JOIN person B
			ON A.from = B.email
			WHERE A.from = '$email' 
			OR A.requester = '$email' 
			ORDER BY A.creation_date ASC");

		// prepare chats for the view
		$chat = [];
		foreach ($tickets as $ticket) {
			$message = new stdClass();
			$message->class = $ticket->from == $email ? 'me' : 'you';
			$message->from = $ticket->username;
			$message->text = preg_replace('/[\x00-\x1F\x7F]/u', '', $ticket->body);
			$message->date = date_format((new DateTime($ticket->creation_date)), 'd/m/Y h:i a');
			$message->status = $ticket->status;
			$chat[] = $message;
		}

		// send data to the view
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('soporte.ejs', ['messages' => $chat, 'myusername' => $username, 'title' => 'Soporte']);
	}

	/**
	 * Pay for an item and add the items to the database
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 * @throws Alert
	 */
	public function _pay(Request $request, Response $response)
	{
		// get buyer and code
		$buyer = $request->person->id;
		$code = $request->input->data->code;

		// process the payment
		try {
			Money::purchase($buyer, $code);
		} catch (Alert $e) {
			$response->setLayout('empty.ejs');
			return $response->setTemplate('message.ejs', [
				'header' => 'Error inesperado',
				'icon' => 'sentiment_very_dissatisfied',
				'text' => $e->message,
				'button' => ['href' => 'PIROPAZO TIENDA', 'caption' => 'Reintentar']]);
		}

		// get the number of flowers and hearts, and the message
		$flowers = 0;
		$hearts = 0;
		$message = '';
		if ($code === 'FLOWER') {
			$flowers = 1;
			$message = 'una flor';
		}
		if ($code === 'HEART') {
			$hearts = 1;
			$message = 'un corazón';
		}
		if ($code === 'PACK_ONE') {
			$flowers = 7;
			$hearts = 2;
			$message = 'siete flores y dos corazones';
		}
		if ($code === 'PACK_TWO') {
			$flowers = 15;
			$hearts = 4;
			$message = 'quince flores y cuatro corazones';
		}

		// run powers for amulet FLORISTA
		if (Amulets::isActive(Amulets::FLORISTA, $buyer)) {
			// duplicate flowers
			$flowers *= 2;

			// alert the user
			$msg = 'Los poderes del amuleto del Druida han duplicado las flores que canjeaste. ¡Aprovéchalas!';
			Notifications::alert($request->person->id, $msg, 'local_florist', '{command:"PIROPAZO PERFIL"}');
		}

		// save the articles in the database
		Database::query("
			UPDATE _piropazo_people
			SET flowers=flowers+$flowers, crowns=crowns+$hearts
			WHERE id_person=$buyer");

		// possitive response
		$response->setLayout('empty.ejs');
		return $response->setTemplate('message.ejs', [
			'header' => 'Caje realizado',
			'icon' => 'sentiment_very_satisfied',
			'text' => "Su canje se ha realizado satisfactoriamente, por lo cual ahora tiene $message a su disposición para ayudarle a buscar su pareja ideal.",
			'button' => ['href' => 'PIROPAZO CITAS', 'caption' => 'Buscar pareja']]);
	}

	/**
	 * Get the percentage of match for two profiles
	 *
	 * @param Int $idOne
	 * @param Int $idTwo
	 * @return Number
	 * @throws Alert
	 * @author salvipascual
	 */
	private function getPercentageMatch($idOne, $idTwo)
	{
		// get both profiles
		$p = Database::query("
			SELECT eyes, skin, body_type, hair, highest_school_level, interests, lang, religion, country, usstate, province, year_of_birth 
			FROM person 
			WHERE id='$idOne' 
			OR id='$idTwo'");

		// do not continue if any user can't be found
		if (empty($p[0]) || empty($p[1])) {
			return 0;
		}

		// calculate basic values
		$percentage = 0;
		if ($p[0]->eyes == $p[1]->eyes) {
			$percentage += 5;
		}
		if ($p[0]->skin == $p[1]->skin) {
			$percentage += 5;
		}
		if ($p[0]->body_type == $p[1]->body_type) {
			$percentage += 5;
		}
		if ($p[0]->hair == $p[1]->hair) {
			$percentage += 5;
		}
		if ($p[0]->highest_school_level == $p[1]->highest_school_level) {
			$percentage += 10;
		}
		if ($p[0]->lang == $p[1]->lang) {
			$percentage += 10;
		}
		if ($p[0]->religion == $p[1]->religion) {
			$percentage += 10;
		}
		if ($p[0]->country == $p[1]->country) {
			$percentage += 5;
		}
		if ($p[0]->usstate == $p[1]->usstate || $p[0]->province == $p[1]->province) {
			$percentage += 10;
		}

		// calculate interests
		$arrOne = explode(',', strtolower($p[0]->interests));
		$arrTwo = explode(',', strtolower($p[1]->interests));
		$intersect = array_intersect($arrOne, $arrTwo);
		if ($intersect) {
			$percentage += 20;
		}

		// calculate age
		$ageOne = date('Y') - $p[0]->year_of_birth;
		$ageTwo = date('Y') - $p[1]->year_of_birth;
		$diff = abs($ageOne - $ageTwo);
		if ($diff == 0) {
			$percentage += 15;
		}
		if ($diff >= 1 && $diff <= 5) {
			$percentage += 10;
		}
		if ($diff >= 6 && $diff <= 10) {
			$percentage += 5;
		}
		return $percentage;
	}
}
