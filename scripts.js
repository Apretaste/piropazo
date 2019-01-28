//
// ON LOAD FUNCTIONS
//

$(document).ready(function(){
	$('select').formSelect();
	showStateOrProvince();
//	$('#picturefield').on('change', displayPicture);
	$("#picturefield").change(displayPicture);
});

//
// FUCTIONS FOR THE SERVICE
//

// get list of years fort the age
function getYears() {
	var year = new Date().getFullYear();
	var years = [];
	for (let i=year-90; i<=year-15; i++) years.push(i);
	return years;
}

// get list of countries to display
function getCountries() {
	return [
		{code:'cu', name:'Cuba'},
		{code:'us', name:'Estados Unidos'},
		{code:'es', name:'Espana'},
		{code:'it', name:'Italia'},
		{code:'mx', name:'Mexico'},
		{code:'br', name:'Brasil'},
		{code:'ec', name:'Ecuador'},
		{code:'ca', name:'Canada'},
		{code:'vz', name:'Venezuela'},
		{code:'al', name:'Alemania'},
		{code:'co', name:'Colombia'},
		{code:'OTRO', name:'Otro'}
	];
}

// open the menu for small devices
function openMenu() {
	$('.sidenav').sidenav();
	$('.sidenav').sidenav('open');
}

// say yes/no to a date
function respondToDate(personId, answer) {
	apretaste.send({
		command: "PIROPAZO " + answer,
		data: {id: personId}, 
		redirect: false,
		callback: {name: "callbackBringNewDate"}
	});
}

// show the denounce drop down menu
function showDenounceMenu() {
	$('#denounce-link').hide();
	$('#denounce-menu').show();
}

// denounce a user
function denounceUser(violation, violator) {
	apretaste.send({
		command: 'PIROPAZO REPORTAR',
		data: {code:violation, id:violator},
		redirect: false,
		callback: {name: "callbackDenounceFinish"}
	});
}

// terminates a date on the parejas section
function sayNoAndBlock(personId, element) {
	apretaste.send({
		command: 'PIROPAZO NO',
		data: {'id': personId},
		redirect: false,
		callback: {
			name: "callbackRemoveDateFromScreen", 
			data: {element: element}
		}
	});
}

// toggles the user description visible/invisible on the "dates" page
function toggleDescVisible() {
	var status = $('#arrowicon').attr('status');

	if(status == "closed") {
		$('#desc').slideDown('fast');
		$('#arrowicon').html('keyboard_arrow_up').attr('status', 'opened');
	} else {
		$('#desc').slideUp('fast');
		$('#arrowicon').html('keyboard_arrow_down').attr('status', 'closed');
	}
}

// open the modal to send a flower
function openFlowerModal(personId, count, username) {
	// do not open if the user do not have flowers
	if(count <= 0) {
		M.toast({html: 'Tristemente, usted no tiene ninguna flor. Puede comprar m&aacute;s flores en nuestra tienda'});
		return false;
	}

	// replace values on the modal
	$('#flowerCount').html(count);
	$('#flowerUsername').html(username);
	$('#modalFlower').attr('toId', personId);

	// open the modal
	var popup = document.getElementById('modalFlower');
	var modal = M.Modal.init(popup);
	modal.open();
}

// send a flower
function sendFlower() {
	// get data
	var personId = $('#modalFlower').attr('toId');
	var message = $('#flowerMsg').val();

	// send the flower
	apretaste.send({'command':'PIROPAZO FLOR','data':{id:personId, msg:message}});
}

// send the notificationd to be deleted
function deleteNotification(id) {
	// delete from the backend
	apretaste.send({
		command: 'NOTIFICACIONES LEER',
		data: {id: id},
		redirect: false
	});

	// remove from the view
	$('#'+id).fadeOut(function() {
		$(this).remove();

		// show message if all notifications were deleted
		var count = $("ul.collection li").length;
		if(count <= 0) {
			$('ul.collection').remove();
			$('div.col').append('<p>No hay mas notificaciones por leer</p>');
		}
	});
}

// open the popup to upload a new profile picture
function uploadPicture() {
	$("#picturefield").trigger("click");
}

// display the picture on the image
function displayPicture() {
	// get the file
	var file = $('#picturefield').files[0];

	file.toBase64().then(data => {
		// send the picture
		apretaste.send({
		"command":"PERFIL FOTO",
		"data": {'picture':data},
		"redirect": false,
		"callback":{"name":"updatePicture","data":file}
		});
	});
}

// submit the profile informacion 
function submitProfileData() {
	// get the array of fields and  
	var fields = ['picture','first_name','gender','sexual_orientation','year_of_birth','body_type','eyes','hair','skin','marital_status','highest_school_level','occupation','country','province','usstate','city','interests','religion'];

	// create the JSON of data
	var data = new Object;
	fields.forEach(function(field) {
		var value = $('#'+field).val();
		if(value) data[field] = value;
	});

	// translate "que buscas" to sexual_orientation
	if(data.sexual_orientation) {
		if(data.sexual_orientation=="AMBOS") data.sexual_orientation="BI";
		if(data.gender=="M" && data.sexual_orientation=="MUJERES") data.sexual_orientation="HETERO";
		if(data.gender=="M" && data.sexual_orientation=="HOMBRES") data.sexual_orientation="HOMO";
		if(data.gender=="F" && data.sexual_orientation=="HOMBRES") data.sexual_orientation="HETERO";
		if(data.gender=="F" && data.sexual_orientation=="MUJERES") data.sexual_orientation="HOMO";
	}

	// save information in the backend
	apretaste.send({
		"command": "PERFIL UPDATE",
		"data": data,
		"redirect": false
	});

	// show confirmation text
	M.toast({html: 'Su informacion se ha salvado correctamente'});
}

// hide state or province based on country
function showStateOrProvince() {
	var country = $('#country').val();
	var province = $('.province-div');
	var usstate = $('.usstate-div');

	province.hide();
	usstate.hide();

	if(country == 'cu') {
		province.show();
		usstate.hide();
	}

	if(country == 'us') {
		province.hide();
		usstate.show();
	}
}

//
// CALLBACKS
//

function callbackBringNewDate() {
	apretaste.send({command: "PIROPAZO"});
}

function callbackDenounceFinish() {
	$('#denounce-menu').hide();
	$('#denounce-done').show();
}

function callbackRemoveDateFromScreen(values) {
	// delete the whole section if only one match is left
	var liCnt = $(values.element).parents('ul.collection').find('li').length;
	var toDelete = liCnt > 1 ? '.collection-item' : 'section';

	// delete the element or the section
	$(values.element).parents(toDelete).fadeOut('fast', function(){
		$(this).remove();
	});
}

function callbackUpdatePicture(file){
    // display the picture on the img
	var URL = window.URL || window.webkitURL;
	var url = URL.createObjectURL(file);
    $('#picture').attr('src', url);

    // show confirmation text
    showToast('Su foto ha sido cambiada correctamente');
}

//
// PROTOTYPES
//

String.prototype.replaceAll = function(search, replacement) {
	return this.split(search).join(replacement);
};

String.prototype.firstUpper = function() {
	return this.charAt(0).toUpperCase() + this.substr(1).toLowerCase();
};
