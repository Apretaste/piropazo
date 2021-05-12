<?php

use Apretaste\Core;
use Apretaste\Money;
use Apretaste\Alert;
use Apretaste\Level;
use Apretaste\Bucket;
use Apretaste\Person;
use Apretaste\Amulets;
use Apretaste\Request;
use Apretaste\Response;
use Apretaste\Database;
use Apretaste\Challenges;
use Apretaste\Notifications;
use Apretaste\GoogleAnalytics;

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
		$this->resetViews($request->person);

		if (!$this->isActive($request->person->id)) {
			$content = [
				'header' => 'Tiempo sin verte',
				'icon' => 'access_time',
				'text' => 'Parece que es primera vez que usa Piropazo o que ha desactivado su uso anteriormente. Si desea buscar pareja en Piropazo, presione el botón a continuación para hacer su perfil público. Otros usuarios podrán ver su foto, nombre y datos del perfil, pero no podrán ver su @username',
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
				'text' => 'Esto es vergonzoso, pero no pudimos encontrar a nadie que vaya con usted. Por favor regrese más tarde, o cambie su perfil e intente nuevamente.',
				'button' => ['href' => 'PIROPAZO PERFIL', 'caption' => 'Editar perfil'],
				'title' => 'Citas'];
			$response->setLayout('piropazo.ejs');
			$response->setTemplate('message.ejs', $content);
			return;
		}

		// add view
		Database::query("UPDATE _piropazo_people SET views = views + 1 WHERE id_person = {$match->id};");

		// add a comma separate list of tags to the profile
		Person::setProfileTags($match);

		// prepare tags for showing
		$match->country = $match->country === 'cu' ? 'Cuba' : 'Otro';
		$match->education = Core::$education[$match->education] ?? '';
		$match->religion = Core::$religions[$match->religion] ?? '';

		// get match images into an array and the content
		$images = [];
		if ($match->picture) {
			$images[] = Bucket::getPathByEnvironment('perfil', $match->picture);
		}

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
	 * Say Yes to a match
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _si(Request $request, Response $response)
	{
		$this->resetViews($request->person);

		// get the emails from and to
		$idFrom = $request->person->id;
		$idTo = $request->input->data->id;
		if (empty($idTo)) return;

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

				// submit to Google Analytics 
				GoogleAnalytics::event('piropazo_match', $idTo);

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

		// insert the new relationship
		$threeDaysForward = date('Y-m-d H:i:s', strtotime('+3 days'));
		Database::query("
		START TRANSACTION;
		DELETE FROM _piropazo_relationships WHERE id_from='$idFrom' AND id_to='$idTo';
		INSERT INTO _piropazo_relationships (id_from,id_to,status,expires_matched_blocked) VALUES ('$idFrom','$idTo','like','$threeDaysForward');
		COMMIT");

		// remove match from the cache so it won't show again
		Database::query("DELETE FROM _piropazo_cache WHERE user={$idFrom} AND suggestion={$idTo}");

		// submit to Google Analytics 
		GoogleAnalytics::event('piropazo_yes', $idTo);

		// add challenge
		Challenges::complete('piropazo-say-yes-no', $request->person->id);
	}

	/**
	 * Say No to a match
	 *
	 * @param Request
	 * @param Response
	 * @author salvipascual
	 */
	public function _no(Request $request, Response $response)
	{
		$this->resetViews($request->person);

		// get the ids from and to
		$idFrom = $request->person->id;
		$idTo = $request->input->data->id;
		if (empty($idTo)) {
			return;
		}

		// mark the transaction as blocked
		Database::query("
		START TRANSACTION;
		DELETE FROM _piropazo_relationships WHERE (id_from='$idFrom' AND id_to='$idTo') OR (id_to='$idFrom' AND id_from='$idTo');
		INSERT INTO _piropazo_relationships (id_from,id_to,status,expires_matched_blocked) VALUES ('$idFrom','$idTo','dislike',CURRENT_TIMESTAMP);
		COMMIT");

		// remove match from the cache so it won't show again
		Database::query("DELETE FROM _piropazo_cache WHERE user={$idFrom} AND suggestion={$idTo}");

		// submit to Google Analytics 
		GoogleAnalytics::event('piropazo_no', $idTo);

		// add challenge
		Challenges::complete('piropazo-say-yes-no', $request->person->id);
	}

	/**
	 * Open the user's profile
	 *
	 * @param Request
	 * @param Response
	 * @return Response
	 * @author salvipascual
	 */
	public function _perfil(Request $request, Response $response)
	{
		$this->resetViews($request->person);

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
		if ($profile->picture) {
			$images[] = Bucket::getPathByEnvironment('perfil', $profile->picture);
		}

		// list of values
		$content = [
			'profileIncomplete' => $profileIncomplete,
			'profile' => $profile,
			'isMyOwnProfile' => $isMyOwnProfile,
			'title' => 'Perfil',
		];

		// prepare response for the view
		$response->setLayout('piropazo.ejs');
		$response->setTemplate('profile.ejs', $content, $images);
	}

	/**
	 * Get the list of matches for your user
	 *
	 * @param Request $request
	 * @param Response $response
	 * @author salvipascual
	 */
	public function _parejas(Request $request, Response $response)
	{
		$this->resetViews($request->person);

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
			if ($match->picture) {
				$images[] = Bucket::getPathByEnvironment('perfil', $match->picture);
			}

			// get match properties
			$match->matched_on = date('d/m/Y', strtotime($match->matched_on));
			$match->education = isset(Core::$education[$match->education]) ? Core::$education[$match->education] : '';
			$match->religion = isset(Core::$religions[$match->religion]) ? Core::$religions[$match->religion] : '';

			// erase unwanted properties in the object
			$properties = ['id', 'username', 'firstName', 'gender', 'age', 'type', 'location', 'religion', 'education', 'picture', 'matched_on', 'time_left', 'isOnline'];
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
	 * Sends a flower to another user
	 *
	 * @param Request $request
	 * @param Response $response
	 * @author salvipascual
	 */
	public function _flor(Request $request, Response $response)
	{
		$this->resetViews($request->person);

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
	 * @return Response
	 * @author salvipascual
	 */
	public function _corazon(Request $request, Response $response)
	{
		$this->resetViews($request->person);

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
			'Usted ha usado un corazón y su perfil se mostrará a muchos más usuarios por los próximos tres días',
			'favorite',
			'{"command":"piropazo"}'
		);
	}

	/**
	 * Open the store
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 * @author salvipascual
	 */
	public function _tienda(Request $request, Response $response)
	{
		$this->resetViews($request->person);

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
	 * Pay for an item and add the items to the database
	 *
	 * @param Request $request
	 * @param Response $response
	 * @return Response
	 */
	public function _pay(Request $request, Response $response)
	{
		$this->resetViews($request->person);

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
	 * Activate a profile in Piropazo
	 * 
	 * @param Request $request
	 * @param Response $response
	 */
	public function _activate(Request $request, Response $response)
	{
		// create or activate piropazo user
		$this->activatePiropazoUser($request->person->id);
		$this->_main($request, $response);
	}

	/**
	 * Deactivate a profile in Piropazo
	 * 
	 * @param Request $request
	 * @param Response $response
	 */
	public function _deactivate(Request $request, Response $response)
	{
		// create or activate piropazo user
		Database::query("UPDATE _piropazo_people SET active=0 WHERE id_person = '{$request->person->id}'");
		$this->_main($request, $response);
	}

	/**
	 * Reset the number of views for an user
	 *
	 * @param Person $person
	 */
	private function resetViews($person)
	{
		Database::query("UPDATE _piropazo_people SET views = 0 WHERE id_person = {$person->id}");
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
		/** @var Person $person */
		return (
			empty($person->firstName) ||
			empty($person->picture) ||
			empty($person->gender) ||
			empty($person->sexualOrientation) /*||
			intval($person->age) < 18*/
		);
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
	 * Get the person who best matches with you
	 *
	 * @param Person $user
	 * @return mixed
	 * @throws Alert
	 * @author salvipascual
	 */
	private function getMatchFromCache($user)
	{
		// create cache if needed
		$this->createMatchesCache($user);

		$matches = Database::query("
			SELECT 
				A.id, A.suggestion AS user, 
				IFNULL(TIMESTAMPDIFF(DAY, B.crowned,NOW()),3) < 3 AS heart
			FROM _piropazo_cache A
			JOIN _piropazo_people B
			ON A.suggestion = B.id_person
			WHERE A.user = {$user->id}
			ORDER BY heart DESC, A.match DESC, A.id");

		foreach ($matches as $match) {
			$person = Person::find($match->user);

			if (!$this->isProfileIncomplete($person)) {
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

			Database::query("DELETE FROM _piropazo_cache WHERE user={$user->id} AND suggestion={$person->id}");
		}

		return false;
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
		if (Database::queryFirst("SELECT COUNT(id) AS cnt FROM _piropazo_cache WHERE user = {$user->id}")->cnt < 1) {

			$piropazoPreferences = Database::queryFirst("SELECT minAge, maxAge FROM _piropazo_people WHERE id_person = {$user->id}");

			// filter based on sexual orientation
			switch ($user->sexualOrientation) {
				case 'HETERO':
					$clauseSex = "A.gender <> '$user->gender' AND COALESCE(A.sexual_orientation,'HETERO') <> 'HOMO' ";
					break;
				case 'HOMO':
					$clauseSex = "A.gender = '$user->gender' AND COALESCE(A.sexual_orientation, 'HETERO') <> 'HETERO' ";
					break;
				case 'BI':
					$clauseSex = "(COALESCE(A.sexual_orientation, 'HETERO') = 'BI' 
						OR (COALESCE(A.sexual_orientation, 'HETERO') = 'HOMO' 
							AND A.gender = '$user->gender') 
						OR (COALESCE(A.sexual_orientation, 'HETERO') = 'HETERO' 
							AND A.gender <> '$user->gender')
						) ";
					break;
			}

			// create final query with the match score
			$matches = Database::query("
				SELECT {$user->id}, id,
					IF(province = '$user->provinceCode', 50, 0) +   
					IF(ABS(IFNULL(YEAR(CURRENT_DATE) - year_of_birth, 0) - $user->age) <= 5, 20, 0) +
					crown * 25 +
					IF(religion = '$user->religion', 20, 0) +
					IF(results.active, 50, 0)    
					AS percent_match
				FROM (
					SELECT 
						A.id, A.year_of_birth, A.province, A.religion, A.active, A.picture,
						IFNULL(TIMESTAMPDIFF(DAY, B.crowned,NOW()), 3) < 3 AS crown 
					FROM _piropazo_people B 
					 LEFT JOIN _piropazo_relationships R1 ON R1.id_to = B.id_person AND R1.id_from = {$user->id}
					 INNER JOIN person A ON A.id = B.id_person
					WHERE true
						AND R1.id_from is NULL
						AND B.active = 1
						AND $clauseSex 
						AND (A.year_of_birth IS NULL OR IFNULL(YEAR(NOW())-year_of_birth,0) >= {$piropazoPreferences->minAge})
						AND (A.year_of_birth IS NULL OR IFNULL(YEAR(NOW())-year_of_birth,0) <= {$piropazoPreferences->maxAge})
						AND NOT A.id = {$user->id}
						AND NULLIF(A.picture, '') IS NOT NULL
				) AS results 
				ORDER BY results.active DESC, percent_match DESC
				LIMIT 50");

			foreach ($matches as $match) {
				Database::query("INSERT INTO _piropazo_cache (`user`, suggestion, `match`) VALUES ({$user->id}, {$match->id}, {$match->percent_match});");
			}

			return count($matches) > 0;
		}

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
}
