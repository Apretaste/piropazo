//
// ON LOAD FUNCTIONS
//

$(document).ready(function(){
	$('select').formSelect();
	showStateOrProvince();
	$('.modal').modal();
	$('.materialboxed').materialbox({'onCloseEnd':()=> resizeImg()});

	$('.profile-img, .col.s4').click(function(){
		if($('#desc').attr('status') == "opened") {
			$('#desc').slideToggle({direction: "up"}).attr('status', 'closed'); //, () => resizeImg()
		}
	})

	if(typeof match != "undefined"){
		var infoElement = $('#info');
		$('#info').remove();
		$(infoElement).insertBefore('.profile-img');

		var moreElement = $('#more');
		$('#more').remove();
		$(moreElement).insertBefore('.profile-img');

		var descElement = $('#desc');
		$('#desc').remove();
		$(descElement).insertBefore('.profile-img');
	}

	resizeImg();
	$(window).resize(() => resizeImg());

	$('#chat-row').parent().css('margin-bottom','0');
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

			$('.profile-img').height($('.profile-img').height() - $($('.row')[0]).outerHeight(true) - $($('#actions')[0]).outerHeight(true) - 40)
		}

		var lastWidth = 0;
		while($('html').height() < $(window).height() - 1) {
			$('.profile-img').height($('.profile-img').height()+1);

			if($('.profile-img').width() == lastWidth) break;
			lastWidth = $('.profile-img').width();
		}

		var imgMargin = parseFloat($('.profile-img').css('margin-left').replace("px",""));
		$('#profile .position-bottom.right').css("right", imgMargin+'px');
		imgMargin += 8;
		$('#profile .position-bottom.left').css("left", imgMargin+'px');
	}
	else{
		$('#profile-rounded-img').height($(window).height()/4); // picture must be 1/4 of the screen
		$('#profile-rounded-img').width($(window).height()/4);
		$('#profile-rounded-img').css('top',(-4-$(window).height()/8)+'px'); // align the picture with the div
		$('#edit-fields').css('margin-top',(5-$(window).height()/8)+'px'); // move the row before to the top to fill the empty space
		$('#img-pre').height($('#profile-rounded-img').height()*0.8); // set the height of the colored div after the photo
	}
}

// say yes/no to a date
function respondToDate(personId, answer) {
	if($('#desc').attr('status') == "opened") return;
	
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

// do match

function doMatch(personId){
	apretaste.send({
		command: 'PIROPAZO SI',
		data: {'id': personId},
		redirect: false,
		callback: {
			name: "callbackMoveToMatches", 
			data: personId
		}
	});
}

// toggles the user description visible/invisible on the "dates" page
function toggleDescVisible() {
	var status = $('#desc').attr('status');

	if(status == "closed") {
		$('#desc').slideToggle({direction: "up"}).attr('status', 'opened'); //, () => resizeImg() // add this to resize then opened or closed
	} else {
		$('#desc').slideToggle({direction: "up"}).attr('status', 'closed'); //, () => resizeImg()
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

function messageLengthValidate(max) {
    var message = $('#message').val().trim();
    if (message.length <= max) {
        $('.helper-text').html('Restante: ' + (max - message.length));
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
	if(!isMyOwnProfile) return;
	// get the array of fields and  
	var fields = ['picture','full_name', 'username', 'about_me','gender','sexual_orientation','year_of_birth','body_type','eyes','hair','skin','marital_status','highest_school_level','occupation','country','province','usstate','city','religion'];

	// create the JSON of data
	var data = new Object;
	fields.forEach(function(field) {
		var value = $('#'+field).val();
		if(field == 'full_name' && value && value.trim() != ''){
			data['first_name'] = value.split(' ')[0];
			if (value.split(' ')[1] != undefined) data['last_name'] = value.split(' ')[1];
		}
		if(value && value.trim() != '') data[field] = value;
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

function callbackMoveToMatches(id){
	var element = $('#'+id);
	var today = new Date();
	callbackRemoveDateFromScreen(element[0]);
	$(element).appendTo('#matches');
	$('#'+id+' .second-line').html('Se unieron el '+today.toLocaleDateString('es-ES'));
	$('#'+id+' .secondary-content a:nth-child(1) > i').html('message');
	$('#'+id+' .secondary-content a:nth-child(1)').attr('onclick',"apretaste.send({'command':'PIROPAZO CONVERSACION', 'data':{'userId':'"+id+"'}})");
}


///////// CHAT SCRIPTS /////////

var optionsModalActive = false;
var moved = false;
var activeChat;
var activeMessage;
var activeUsername;
var timer;

$(() => {
    if (typeof messages != "undefined") {
		resizeChat();
		$(window).resize(() => resizeChat());
		$('.chat').scrollTop($('.bubble:last-of-type').offset().top);
        $('#message').focus();
		activeChat = id;
        activeUsername = username;

        $('.bubble')
            .on("touchstart", event => { runTimer(); activeMessage = event.currentTarget.id; })
            .on("touchmove", event => { clearTimeout(timer); moved = true; })
            .on("touchend", event => { clearTimeout(timer); });

        $('.bubble')
            .on("mousedown", event => { runTimer(); activeMessage = event.currentTarget.id; })
            .on("mouseup", event => {clearTimeout(timer);});
    }
    
    $('.modal').modal();
    $('.openchat')
        .on("touchstart", event => { runTimer(); activeChat = event.currentTarget.id; activeName = event.currentTarget.getAttribute('name'); })
        .on("touchmove", event => { clearTimeout(timer); moved = true; })
        .on("touchend", event => { openChat() });

    $('.openchat')
        .on("mousedown", event => { runTimer(); activeChat = event.currentTarget.id; activeName = event.currentTarget.getAttribute('name'); })
        .on("mouseup", event => { openChat() });
});

function openChat() {
    if (!optionsModalActive && !moved) apretaste.send({ 'command': 'PIROPAZO CONVERSACION', 'data': { 'userId': activeChat } });
    optionsModalActive = false;
    moved = false;
    clearTimeout(timer);
}

function viewProfile() {
    apretaste.send({ 'command': 'PIROPAZO PERFIL', 'data': { 'id': activeChat } });
}

function writeModalOpen() {
    optionsModalActive = false;
    M.Modal.getInstance($('#optionsModal')).close();
    M.Modal.getInstance($('#writeMessageModal')).open();
}

function deleteModalOpen() {
    optionsModalActive = false;
    M.Modal.getInstance($('#optionsModal')).close();
    if(typeof messages == "undefined") $('#deleteModal p').html('¿Esta seguro de eliminar su chat con '+ activeName.trim() +'?');
    M.Modal.getInstance($('#deleteModal')).open();
}

function deleteChat(){
    apretaste.send({
        'command': 'CHAT BORRAR',
        'data':{'id':activeChat, 'type': 'chat'},
        'redirect': false,
        'callback':{'name':'deleteChatCallback','data':activeChat}
    })
}

function deleteMessage(){
    apretaste.send({
        'command': 'CHAT BORRAR',
        'data':{'id':activeMessage, 'type': 'message'},
        'redirect': false,
        'callback':{'name':'deleteMessageCallback','data':activeMessage}
    })
}

function deleteChatCallback(chatId){
    $('#'+chatId).remove();
    showToast('Chat eliminado');
}

function deleteMessageCallback(messageId){
    $('#'+messageId).remove();
    showToast('Mensaje eliminado');
}

function runTimer() {
    timer = setTimeout(function () {
        optionsModalActive = true;
        M.Modal.getInstance($('#optionsModal')).open();
    }, 800);
}

function sendMessage(toService) {
	var message = $('#message').val().trim();
	var minLength = toService == 'CHAT' ? 1 : 30;
    if (message.length >= minLength) {
        apretaste.send({
            'command': toService+" ESCRIBIR",
            'data': { 'id': activeChat, 'message': message },
            'redirect': false,
            'callback': { 'name': 'sendMessageCallback', 'data': message }
        });
    }
    else {
		if(toService == "CHAT") showToast("Mensaje vacio")
		else showToast("Por favor describanos mejor su solicitud")
	}
}

function sendMessageCallback(message) {
    if (typeof messages != "undefined") {
        if (messages.length == 0) {
            $('#nochats').remove();
            $('#messageField').insertBefore("<div class=\"chat\"></div>");
        }

        $('.chat').append(
            "<div class=\"bubble me\" id=\"last\">" +
			message +
			"<br>"+
			"<small>"+(new Date()).toLocaleString('es-ES')+"</small>"+
            "</div>"
        );
    }
    else{
        if(message.length > 70) message = message.substr(0, 70)+'...';
        $('#'+activeChat+' msg').html(message)
    }
    $('#message').val('')
}

function resizeChat(){
	if($('.row').length == 3){
		$('.chat').height($(window).height() - $($('.row')[0]).outerHeight(true) - $('#messageField').outerHeight(true)-20);
	}
	else $('.chat').height($(window).height() - $('#messageField').outerHeight(true)-20);
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
