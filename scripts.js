"use strict";

// create global variables
var imgPath = null;
var activeId;
var sexual_orientation = ["MUJERES", "HOMBRES", "AMBOS"];
var marital_status = ["SOLTERO", "SALIENDO", "COMPROMETIDO", "CASADO"];
var highest_school_level = ["PRIMARIO", "SECUNDARIO", "TECNICO", "UNIVERSITARIO", "POSTGRADUADO", "DOCTORADO", "OTRO"];
var religion = ["CRISTIANISMO", "CATOLICISMO", "YORUBA", "PROTESTANTE", "SANTERO", "ABAKUA", "BUDISMO", "ISLAM", "ATEISMO", "AGNOSTICISMO", "SECULARISMO", "OTRA"];
var province = {
	'PINAR_DEL_RIO': 'Pinar del Río',
	'ARTEMISA': 'Artemisa',
	'LA_HABANA': 'La Habana',
	'MAYABEQUE': 'Mayabeque',
	'MATANZAS': 'Matanzas',
	'CIENFUEGOS': 'Cienfuegos',
	'VILLA_CLARA': 'Villa Clara',
	'SANCTI_SPIRITUS': 'Sancti Spíritus',
	'CIEGO_DE_AVILA': 'Ciego de Ávila',
	'CAMAGUEY': 'Camagüey',
	'LAS_TUNAS': 'Las Tunas',
	'GRANMA': 'Granma',
	'HOLGUIN': 'Holguín',
	'SANTIAGO_DE_CUBA': 'Santiago de Cuba',
	'GUANTANAMO': 'Guantánamo',
	'ISLA_DE_LA_JUVENTUD': 'Isla de la Juventud'
};

// initaite libs
$(document).ready(function () {
	$('select').formSelect();
	$('.tabs').tabs();
	$('.modal').modal();
});

// get list of years fort the age
function getYears() {
	var year = new Date().getFullYear();
	var years = [];

	for (var i = year - 18; i >= year - 90; i--) {
		years.push(i);
	}

	return years;
}

// say yes/no to a date
function respondToDate(personId, answer) {
	if ($('#desc').attr('status') == "opened") return;
	apretaste.send({
		command: "PIROPAZO " + answer + "NEXT",
		data: {
			id: personId
		}
	});
}

// terminates a date on the parejas section
function sayNoAndBlock(personId) {
	// say no
	apretaste.send({
		command: 'PIROPAZO NO',
		data: {'id': personId},
		redirect: false
	});

	// remove from the view
	$('#' + personId).remove();
}

// do match
function doMatch(personId) {
	apretaste.send({
		command: 'PIROPAZO SI',
		data: {'id': personId},
		redirect: false,
		callback: {name:'open', data:'PAREJAS'}
	});
}

// opens a Piropazo command, useful for callbacks
function open(subservice) {
	apretaste.send({command: 'PIROPAZO ' + subservice});
}

// open the modal to send a flower
function openFlowerModal(personId, name) {
	// do not open if the user do not have flowers
	if (myflowers < 1) {
		showToast('Tristemente, usted no tiene ninguna flor. Puede comprar más flores en nuestra tienda');
		return false;
	}

	activeId = personId;
	$('#modalFlower .name').html(name); // open the modal

	var popup = document.getElementById('modalFlower');
	var modal = M.Modal.init(popup);
	modal.open();
}

// send a flower
function sendFlower() {
	// get data
	var message = $('#flowerMsg').val();

	if (typeof match != "undefined") {
		apretaste.send({
			command: "PIROPAZO SI",
			data: {
				id: activeId
			},
			redirect: false,
			callback: {
				name: "sendFlowerCallback",
				data: message
			}
		});
	}
}

function sendFlowerCallback(message) {
	apretaste.send({
		'command': 'PIROPAZO FLOR',
		'data': {
			'id': activeId,
			'msg': message
		}
	});
}

// open the modal to set a heart
function heartModalOpen(incomplete, hearts) {
	// do not pass if the profile is incomplete
	if (incomplete) {
		showToast('Antes llene su perfil y agregue una foto');
		return false;
	}

	// do not pass if there are no flowers
	if (hearts <= 0) {
		showToast('No tiene corazones, consiga alguno en la tienda');
		return false;
	}

	// open the modal
	M.Modal.getInstance($('#heartModal')).open();
}

// open the popup to upload a new profile picture
function uploadPicture() {
	loadFileToBase64();
}

function submitProfileData() {
	// get the array of fields
	var fields = ['picture', 'first_name', 'gender', 'sexual_orientation', 'year_of_birth', 'highest_school_level', 'province', 'religion', 'marital_status'];

	// create the JSON of data
	var data = new Object();
	fields.forEach(function (field) {
		var value = $('#' + field).val();
		if (value && value.trim() != '') data[field] = value;
	});

	// translate "que buscas" to sexual_orientation
	if (data.sexual_orientation) {
		if (data.sexual_orientation === "AMBOS") data.sexual_orientation = "BI";
		if (data.gender === "M" && data.sexual_orientation === "MUJERES") data.sexual_orientation = "HETERO";
		if (data.gender === "M" && data.sexual_orientation === "HOMBRES") data.sexual_orientation = "HOMO";
		if (data.gender === "F" && data.sexual_orientation === "HOMBRES") data.sexual_orientation = "HETERO";
		if (data.gender === "F" && data.sexual_orientation === "MUJERES") data.sexual_orientation = "HOMO";
	}

	// save information in the backend
	var minAge = parseInt($('#ageFrom').val());
	var maxAge = parseInt($('#ageTo').val());
	if (profile.minAge != minAge) data.minAge = minAge;
	if (profile.maxAge != maxAge) data.maxAge = maxAge;

	// show confirmation text
	apretaste.send({
		command: "PERFIL UPDATE",
		data: data,
		redirect: false,
		callback: {name: 'open', data: ''}
	});

	// show ok message
	showToast("Su informacion se ha salvado correctamente");
}

// show the first char as uppercase
function firstUpper(text) {
	return text.charAt(0).toUpperCase() + text.substr(1).toLowerCase();
};

// show a toast message
function showToast(text) {
	M.toast({html: text});
}

function updatePicture(file) {
	$('.picture-container > img').attr('src', "data:image/jpg;base64," + file);
	showToast('Su foto ha sido cambiada correctamente');
}

function callbackBringNewDate() {
	apretaste.send({
		command: "PIROPAZO"
	});
}

function openChat(id) {
	apretaste.send({
		'command': 'chat',
		'data': {'userId': id}
	});
}

function viewProfile(id) {
	apretaste.send({
		'command': 'PERFIL',
		'data': {'username': id}
	});
}

function deleteMatchModalOpen(id, name) {
	$('#deleteModal .name').html(name);
	activeId = id;
	M.Modal.getInstance($('#deleteModal')).open();
}

function deactivateModalOpen() {
	M.Modal.getInstance($('#deactivateModal')).open();
}

// show the modal popup
function openModal(code) {
	$('#code').val(code);
	$('#modal').modal('open');
}

// start a new purchase
function buy() {
	var code = $('#code').val();

	// execute the transfer
	apretaste.send({
		command: "PIROPAZO PAY",
		data: {'code': code},
		redirect: true
	});
}

function sendFile(base64File) {
	// error picture too large
	if (base64File.length > 2584000) {
		showToast("Imagen demasiado pesada");
		$('input:file').val(null);
		return;
	}

	// submit the profile informacion
	apretaste.send({
		"command": "PERFIL FOTO",
		"data": {
			'picture': base64File,
			'updatePicture': true
		},
		"redirect": false,
		"callback": {
			"name": "updatePicture",
			"data": base64File
		}
	});
}
