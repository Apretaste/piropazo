var service = {
	// get list of years fort the age
	getYears: function() {
		var year = new Date().getFullYear();
		var years = [];
		for (let i=year-90; i<=year-15; i++) years.push(i);
		return years;
	},

	// get list of countries to display
	getCountries: function() {
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
	},

	// submit mini profile
	submitMinimalProfile: function() {
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
			command:'PERFIL CAMBIAR', 
			data:data, 
			redirect:false, 
			callback: 'service.callbackSubmitMinimalProfile'
		});
	},

	// submit image
	submitMinimalProfile: function() {

	},

	// callback to upload data for minimal profile
	callbackSubmitMinimalProfile: function() {
		apretaste.send({command:'PIROPAZO'});
	},

	openMenu: function() {
		$('.sidenav').sidenav();
		$('.sidenav').sidenav('open');
	}
}

$(document).ready(function(){
	$('select').formSelect();
});
