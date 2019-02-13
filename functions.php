<?php

/**
 * Function executed when a payment is finalized
 * Add new flowers and crowns to the database
 *
 * @author salvipascual
 * @param Payment $payment
 * @return boolean
 */
function payment(Payment $payment)
{
	// get the number of articles purchased
	$flowers = 0; $hearts = 0;
	if($payment->code == "FLOWER") $flowers = 1;
	if($payment->code == "HEART") $hearts = 1;
	if($payment->code == "PACK_ONE") {$flowers = 7; $hearts = 2;}
	if($payment->code == "PACK_TWO") {$flowers = 15; $hearts = 4;}

	// do not allow wrong codes
	if($flowers + $hearts <= 0) return false;

	// save the articles in the database
	Connection::query("
		UPDATE _piropazo_people
		SET flowers = flowers+$flowers, crowns = crowns+$hearts
		WHERE id_person = {$payment->buyer->id}");

	return true;
}
