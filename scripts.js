//
// ON LOAD FUNCTIONS
//

$(document).ready(function(){
	$('select').formSelect();
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
		{code:'CU', name:'Cuba'},
		{code:'US', name:'Estados Unidos'},
		{code:'ES', name:'Espa&ntilde;a'},
		{code:'IT', name:'Italia'},
		{code:'MX', name:'Mexico'},
		{code:'BR', name:'Brasil'},
		{code:'EC', name:'Ecuador'},
		{code:'CA', name:'Canada'},
		{code:'VZ', name:'Venezuela'},
		{code:'AL', name:'Alemania'},
		{code:'CO', name:'Colombia'},
		{code:'OTRO', name:'Otro'}
	];
}

// submit mini profile
function submitMinimalProfile() {
	// get data from the form
	var name = $('#name').val();
	var gender = $('#gender').val();
	var orientation = $('#orientation').val();
	var birthday = $('#birthday').val();
	var country = $('#country').val();

	// do not allow empty inputs
	if(!name || !gender || !orientation || !birthday || !country) {
		M.toast({html: 'Llene todos los campos para buscar su media naranja'});
		return false;
	}

	// create data JSON string
	var data = JSON.stringify({
		name: name,
		gender: gender,
		orientation: orientation,
		birthday: birthday,
		country: country
	});

	// submit data and execute callback
	apretaste.send({
		command: 'PERFIL CAMBIAR', 
		data: data, 
		redirect: false, 
		callback: 'callbackBringNewDate'
	});
}

// submit image
function submitMinimalProfile() {

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

//
// PROTOTYPES
//

String.prototype.replaceAll = function(search, replacement) {
    var target = this;
    return target.split(search).join(replacement);
};
