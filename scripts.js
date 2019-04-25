//
// ON LOAD FUNCTIONS
//

$(document).ready(function(){
	$('select').formSelect();
	showStateOrProvince();
	$('.modal').modal();
	$('.materialboxed').materialbox({'onCloseEnd':()=> resizeImg()});
	
	if(typeof profile != "undefined"){
		let interests = [];
		profile.interests.forEach((interest) => {
		interests.push({tag: interest});
		});
		profile.interests = JSON.stringify(interests);

		$('.chips').chips();
		$('.chips-initial').chips({data: interests});
	}

	if(typeof match != "undefined"){
		if(match.crown){
			$('<i class="material-icons yellow-text medium position-top-right">favorite</i>').insertBefore('.profile-img');
		}
		var interestsElement = $('#interests');
		$('#interests').remove();
		$(interestsElement).insertBefore('.profile-img');
	}
	else{
		if(typeof crowned != "undefined" && crowned){
			$('<i class="material-icons yellow-text medium position-top-right">favorite</i>').insertBefore('.profile-img');
		}
	}

	resizeImg();
	$(window).resize(() => resizeImg());
	
});

//
// FUCTIONS FOR THE SERVICE
//

// get list of years fort the age
function getYears() {
	var year = new Date().getFullYear();
	var years = [];
	for (let i=year-15; i>=year-90; i--) years.push(i);
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

function resizeImg(){
	if(typeof profile == "undefined" && typeof match == "undefined") return;

	$('.profile-img').css('height', '');
	if(typeof match != 'undefined'){
		if($('html').height() > $(window).height()+1){ //+1 to avoid decimals
			if($('.container > .row').length == 2){
				$('.profile-img').height($(window).height() - $($('.row')[0]).outerHeight(true));
			}

			$('.profile-img').height($('.profile-img').height() - $($('.col.s12:parent')[1]).outerHeight(true) - $($('.col.s12:parent')[2]).outerHeight(true) - 40)
		}
	}
	else{
		if($('html').height() > $(window).height()+1){
			if($('.container > .row').length == 2){
				$('.profile-img').height($(window).height() - $($('.row')[0]).outerHeight(true));
			}

			$('.profile-img').height($('.profile-img').height() - $($('.col.s12:parent')[1]).outerHeight(true) - 50)
		}
	}

	var lastWidth = 0;
	while($('html').height() < $(window).height() - 1) {
		$('.profile-img').height($('.profile-img').height()+1);

		if($('.profile-img').width() == lastWidth) break;
		lastWidth = $('.profile-img').width();
	}

	var imgMargin = parseFloat($('.profile-img').css('margin-left').replace("px",""));
	$('.material-placeholder .position-top-right').css("right", imgMargin+'px');
	imgMargin += 8;
	$('.material-placeholder .position-bottom-left').css("left", imgMargin+'px');
	
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
		$('#desc').slideDown('fast'); //, () => resizeImg() // add this to resize then opened or closed
		$('#arrowicon').html('keyboard_arrow_up').attr('status', 'opened');
	} else {
		$('#desc').slideUp('fast'); //, () => resizeImg()
		$('#arrowicon').html('keyboard_arrow_down').attr('status', 'closed');
	}
}

// open the modal to send a flower
function openFlowerModal(personId, count, username) {
	// do not open if the user do not have flowers
	if(count <= 0) {
		M.toast({html: 'Tristemente, usted no tiene ninguna flor. Puede comprar más flores en nuestra tienda'});
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

function deleteModalOpen() {
    optionsModalActive = false;
    M.Modal.getInstance($('#optionsModal')).close();
    M.Modal.getInstance($('#deleteModal')).open();
}

function deleteMessage(){
    apretaste.send({
        'command': 'CHAT BORRAR',
        'data':{'id':activeMessage, 'type': 'message'},
        'redirect': false,
        'callback':{'name':'deleteMessageCallback','data':activeMessage}
    })
}

function sendMessage() {
    var message = $('#message').val().trim();
    if (message.length > 0) {
        apretaste.send({
            'command': "CHAT ESCRIBIR",
            'data': { 'id': activeChat, 'message': message },
            'redirect': false,
            'callback': { 'name': 'sendMessageCallback', 'data': message }
        });
    }
    else showToast("Mensaje vacio");
}

function messageLengthValidate() {
    var message = $('#message').val().trim();
    if (message.length <= 500) {
        $('.helper-text').html('Restante: ' + (500 - message.length));
    }
    else {
        $('.helper-text').html('Limite excedido');
    }
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
			$('div.col').append('<p>No hay más notificaciones por leer</p>');
		}
	});
}

// open the popup to upload a new profile picture
function uploadPicture() {
	loadFileToBase64()
}

function sendFile(base64File){
    apretaste.send({
        "command":"PERFIL FOTO",
        "data":{'picture':base64File},
        "redirect":false,
        "callback":{"name":"updatePicture","data":base64File}
    });
}

// submit the profile informacion 
function submitProfileData() {
	// get the array of fields and  
	var fields = ['picture','first_name','gender','sexual_orientation','year_of_birth','body_type','eyes','hair','skin','marital_status','highest_school_level','occupation','country','province','usstate','city','religion'];

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

	if (profile.interests != JSON.stringify(M.Chips.getInstance($('.chips')).chipsData)) {
		data.interests = M.Chips.getInstance($('.chips')).chipsData;
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

function showToast(text){
	M.toast({html: text});
}

function updatePicture(file){
    // display the picture on the img
    $('#picture').attr('src', "data:image/jpg;base64,"+file);

    // show confirmation text
    showToast('Su foto ha sido cambiada correctamente');
}

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

//
// PROTOTYPES
//

String.prototype.replaceAll = function(search, replacement) {
	return this.split(search).join(replacement);
};

String.prototype.firstUpper = function() {
	return this.charAt(0).toUpperCase() + this.substr(1).toLowerCase();
};
