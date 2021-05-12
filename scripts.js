"use strict";

var imgPath = null;

var activeId;

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

//
// ON LOAD FUNCTIONS
//
$(document).ready(function () {
	// initaite libs
	$('select').formSelect();
	$('.tabs').tabs();
	$('.modal').modal();

	$('.date-img, .col.s4').click(function () {
		if ($('#desc').attr('status') == "opened") {
			$('#desc').slideToggle({
				direction: "up"
			}).attr('status', 'closed');
		}
	});

	if (typeof profile != "undefined") {
		setInterval(function () {
			profile.heart_time_left--;
		}, 1000);
	}

	if (typeof match != "undefined") {
		var infoElement = $('#info');
		$('#info').remove();
		$(infoElement).insertBefore('.date-img');
		var moreElement = $('#more');
		$('#more').remove();
		$(moreElement).insertBefore('.date-img');
		var descElement = $('#desc');
		$('#desc').remove();
		$(descElement).insertBefore('.date-img');
	}

	$('#chat-row').parent().css('margin-bottom', '0');

	resizeImg();
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

function resizeImg() {
	if (typeof profile == "undefined" && typeof match == "undefined") return;
	$('.date-img').css('height', '');

	if (typeof match != 'undefined') {
		var floating_btns = $('.actions .btn-floating');
		var size = $(window).height() * .10;
		floating_btns.css('transition', 'none');
		floating_btns.height(size);
		floating_btns.width(size);

		var fab_icons = $('.actions .btn-floating i');
		fab_icons.css('line-height', size + 'px');
		fab_icons.css('font-size', size / 20 + 'rem');
		floating_btns.css('transition', '');

		// Flower button
		$(floating_btns[1]).height(size * 1.25);
		$(floating_btns[1]).width(size * 1.25);
		$(fab_icons[1]).css('line-height', size * 1.25 + 'px');
		$(fab_icons[1]).css('font-size', size * 1.25 / 20 + 'rem');

		if ($('.container > .row').length == 2) {
			$('.date-img').height($(window).height() - $($('.row')[0]).outerHeight(true));
		}

		$('.actions').css('bottom', '-' + size / 2 + 'px');

		$('.date-img').height($('.date-img').height() - size);
	} else {
		var img = $('#profile-rounded-img');
		var size = $(window).height() / 4; // picture must be 1/4 of the screen

		img.height(size);
		img.width(size);
		var src = img.css('background-image');
		if (typeof src != "undefined") {
			src = src.search('url') == 0 ? src.replace('url("', '').replace('")', '') : src;
			var bg = new Image();
			bg.src = src;

			if (bg.height >= bg.width) {
				var scale = bg.height / bg.width;
				img.css('background-size', size + 'px ' + size * scale + 'px');
			} else {
				var scale = bg.width / bg.height;
				img.css('background-size', size * scale + 'px ' + size + 'px');
			}

			img.css('top', -4 - $(window).height() / 8 + 'px'); // align the picture with the div

			$('#edit-fields').css('margin-top', 5 - $(window).height() / 8 + 'px'); // move the row before to the top to fill the empty space

			$('#img-pre').height(img.height() * 0.8); // set the height of the colored div after the photo
		}
	}
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
	apretaste.send({
		command: 'PIROPAZO NO',
		data: {
			'id': personId
		},
		redirect: false,
		callback: {
			name: "callbackRemoveDateFromScreen",
			data: personId
		}
	});
}

// do match
function doMatch(personId) {
	apretaste.send({
		command: 'PIROPAZO SI',
		data: {
			'id': personId
		},
		redirect: false,
		callback: {
			name: "callbackMoveToMatches",
			data: personId
		}
	});
}

// open the modal to send a flower
function openFlowerModal(personId, name) {
	// do not open if the user do not have flowers
	if (myflowers < 1) {
		M.toast({
			html: 'Tristemente, usted no tiene ninguna flor. Puede comprar más flores en nuestra tienda'
		});
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

function heartRemaining() {
	var totalLeft = profile.heart_time_left;
	var hours = Math.floor(totalLeft / 3600);
	totalLeft -= hours * 3600;
	var minutes = Math.floor(totalLeft / 60);
	totalLeft -= minutes * 60;
	var seconds = totalLeft;

	return hours + 'h: ' + minutes + 'min: ' + seconds + 'sec';
}

function heartModalOpen() {
	if (profile.heart == 0) {
		if (profile.hearts == 0) {
			apretaste.send({
				'command': 'PIROPAZO TIENDA'
			});
		} else M.Modal.getInstance($('#heartModal')).open();
	} else {

		$('#timeLeftModal h3').html(heartRemaining());
		M.Modal.getInstance($('#timeLeftModal')).open();
	}
}

function exchangeHeart() {
	apretaste.send({
		'command': "PIROPAZO CORAZON",
		'redirect': false,
		'callback': {"name": "exchangeHeartCallback"}
	});
}

function exchangeHeartCallback(){
	M.Modal.getInstance($('#congratsModal')).open();
	var heartCount = $('#heart-btn > span');
	profile.hearts--;
	profile.heart = 1;
	heartCount.html(profile.hearts);
	heartCount.removeClass('green-text');
	profile.heart_time_left = 60 * 60 * 24 * 3;
	$('#heart-btn > i').html('favorite');
}

// open the popup to upload a new profile picture
function uploadPicture() {
	loadFileToBase64();
}

function submitProfileData() {
	if (!isMyOwnProfile) return;

	// get the array of fields
	var fields = ['picture', 'first_name', 'gender', 'sexual_orientation', 'year_of_birth', 'highest_school_level', 'province', 'religion', 'marital_status']; // create the JSON of data

	var data = new Object();
	fields.forEach(function (field) {
		var value = $('#' + field).val();
		if (value && value.trim() != '') data[field] = value;
	}); // translate "que buscas" to sexual_orientation

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
		"command": "PERFIL UPDATE",
		"data": data,
		"redirect": false,
		"callback": {name: "callbackSaveProfile"}
	}); 
}

String.prototype.firstUpper = function () {
	return this.charAt(0).toUpperCase() + this.substr(1).toLowerCase();
};

function showToast(text) {
	M.toast({
		html: text
	});
}

function updatePicture(file) {
	$('.picture-container > img').attr('src', "data:image/jpg;base64," + file);
	showToast('Su foto ha sido cambiada correctamente');
}

function callbackSaveProfile() {
	var src = $('.picture-container > img').attr('src');
	if (profile.picture == null && (typeof src == "undefined" || src.indexOf('user.png') !== -1)) {
		showToast("Recuerde subir una foto")
	} else {
		showToast("Su informacion se ha salvado correctamente")
		if (typeof profileIncomplete != "undefined" && profileIncomplete) callbackBringNewDate()
	}
}

function callbackBringNewDate() {
	apretaste.send({
		command: "PIROPAZO"
	});
}

function callbackRemoveDateFromScreen(id) {
	// delete the element or the section
	$('#' + id).fadeOut('fast', function () {
		$(this).remove();
	});
}

function callbackMoveToMatches(id) {
	var element = $('#' + id).clone();
	var today = new Date();

	if ($('#matches-lists > section:nth-child(2) > ul').children().length === 1) {
		$('#matches-lists > section:nth-child(2)').fadeOut('fast', function () {
			$(this).remove();
		});
	} else callbackRemoveDateFromScreen(id);

	element.hide();
	$(element).appendTo('#matches');
	element.fadeIn('fast');
	$('#' + id + ' .second-line').html('Se unieron el ' + today.toLocaleDateString('es-ES'));
	$('#' + id + ' .secondary-content a:nth-child(1) > i').html('message');
	$('#' + id + ' .secondary-content a:nth-child(1)').attr('onclick', "apretaste.send({'command':'chat', 'data':{'userId':'" + id + "'}})");
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
