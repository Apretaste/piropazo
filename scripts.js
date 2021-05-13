// 
// GENERAL
// 

// create global variables
var sexual_orientation = ["MUJERES", "HOMBRES", "AMBOS"];
var marital_status = ["SOLTERO", "SALIENDO", "COMPROMETIDO", "CASADO"];
var highest_school_level = ["PRIMARIO", "SECUNDARIO", "TECNICO", "UNIVERSITARIO", "POSTGRADUADO", "DOCTORADO", "OTRO"];
var religion = ["CRISTIANISMO", "CATOLICISMO", "YORUBA", "PROTESTANTE", "SANTERO", "ABAKUA", "BUDISMO", "ISLAM", "ATEISMO", "AGNOSTICISMO", "SECULARISMO", "OTRA"];
var province = { 'PINAR_DEL_RIO': 'Pinar del Río', 'ARTEMISA': 'Artemisa', 'LA_HABANA': 'La Habana', 'MAYABEQUE': 'Mayabeque', 'MATANZAS': 'Matanzas', 'CIENFUEGOS': 'Cienfuegos', 'VILLA_CLARA': 'Villa Clara', 'SANCTI_SPIRITUS': 'Sancti Spíritus', 'CIEGO_DE_AVILA': 'Ciego de Ávila', 'CAMAGUEY': 'Camagüey', 'LAS_TUNAS': 'Las Tunas', 'GRANMA': 'Granma', 'HOLGUIN': 'Holguín', 'SANTIAGO_DE_CUBA': 'Santiago de Cuba', 'GUANTANAMO': 'Guantánamo', 'ISLA_DE_LA_JUVENTUD': 'Isla de la Juventud'};

// initaite libs
$(document).ready(function () {
	$('select').formSelect();
	$('.tabs').tabs();
	$('.modal').modal();
});

//
// CORE FUNCTIONALITY
//

// Redirects to a subservice, useful for callbacks
// String goto is a subservice name, like 'CITAS'
function open(goto) {
	apretaste.send({command: 'PIROPAZO ' + goto});
}

// Say 'yes' or 'not' to a personId
// String answer can be "SI" or "NO"
// String goto is the subservice to redirect
function respondToDate(personId, answer, goto) {
	apretaste.send({
		command: 'PIROPAZO ' + answer,
		data: {'id': personId},
		redirect: false,
		callback: {name:'open', data:goto}
	});
}

// send a flower
function sendFlower() {
	// get data
	var personId = $('#personId').val();
	var message = $('#flowerMsg').val();

	// send the flower
	apretaste.send({
		'command': 'PIROPAZO FLOR',
		'data': {'id': personId, 'msg': message},
		redirect: true
	});
}

// disable a button to avoid double pressing
function block(btn) {
	$(btn).prop('disabled', true)
		.attr('onclick', '')
		.attr('href', '#!');
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

// function to send a file on the web
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

// callback to update the picture on the image element
function updatePicture(file) {
	$('#citas-image').attr('src', "data:image/jpg;base64," + file).removeClass('hide');
	$('#citas-no-image').addClass('hide');
	showToast('Su foto ha sido cambiada correctamente');
}

// open the popup to upload a new profile picture
function uploadPicture() {
	loadFileToBase64();
}

// submits the profile information
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
	showToast("Su información se ha salvado correctamente");
}

//
// OPEN MODALS
//

function openFlowerModal(personId, name, flowers) {
	// do not open if the user do not have flowers
	if (flowers < 1) {
		showToast('Tristemente, usted no tiene ninguna flor. Puede comprar más flores en nuestra tienda');
		return false;
	}

	// configure and open the modal
	$('#personId').val(personId);
	$('#modalFlower .name').html(name);
	$('#modalFlower').modal('open');
}

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
	$('#heartModal').modal('open');
}

function deleteModalOpen(personId, name) {
	$('#personId').val(personId);
	$('#deleteModal .name').html(name);
	$('#deleteModal').modal('open');
}

function deactivateModalOpen() {
	$('#deactivateModal').modal('open');
}

function buyModalOpen(code) {
	$('#code').val(code);
	$('#buyModal').modal('open');
}

//
// SUPPORTING FUNCTIONS
//

// show the first char as uppercase
function firstUpper(text) {
	return text.charAt(0).toUpperCase() + text.substr(1).toLowerCase();
}

// show a toast message
function showToast(text) {
	M.toast({html: text});
}

// get list of years for the age
function getYears() {
	var year = new Date().getFullYear();
	var years = [];

	for (var i = year - 18; i >= year - 90; i--) {
		years.push(i);
	}

	return years;
}
