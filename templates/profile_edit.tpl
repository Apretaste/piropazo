<!-- PICTURE -->
<center>
	{if $person->picture}
		{img src="{$person->picture_internal}" alt="Picture" width="100" id="value_image"}
	{else}
		<img id="value_image" src=""/>
		{noimage}
	{/if}
	<br/>
	{button color="grey" href="PERFIL FOTO" desc="u:Adjunte su foto de perfil*" caption="Cambiar" size="small" popup="true" callback="reloadPicture"}
</center>

{space15}

<table id="profile" width="100%" cellspacing="0">
	<!-- NAME -->
	<tr>
		<td valign="middle"><small>Nombre</small></td>
		<td valign="middle"><b id="value_name">{$person->full_name|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL NOMBRE" desc="Escriba su nombre completo*" popup="true" wait="false" callback="reloadName"}</td>
	</tr>

	<!-- GENDER -->
	<tr>
		<td valign="middle"><small>Sexo</small></td>
		<td valign="middle"><b id="value_sex">{$person->gender|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL SEXO" desc="m:Describa su genero [Masculino,Femenino]*" popup="true" wait="false" callback="reloadSex"}</td>
	</tr>

	<!-- SEXUAL ORIENTATION -->
	<tr>
		<td valign="middle"><small>Orientaci&oacute;n sexual</small></td>
		<td valign="middle"><b id="value_orientation">{$person->sexual_orientation|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL ORIENTACION" desc="m:Describa su orientacion sexual [Hetero,Homo,Bi]*" popup="true" wait="false" callback="reloadOrientation"}</td>
	</tr>

	<!-- DATE OF BIRTH -->
	<tr>
		<td valign="middle"><small>Cumplea&ntilde;os</small></td>
		<td valign="middle"><b id="value_birthday">{$person->date_of_birth|date_format:"%e/%m/%Y"}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL CUMPLEANOS" desc="m:Que dia usted nacio?[01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,30,31]*|m:Que mes usted nacio?[01,02,03,04,05,06,07,08,09,10,11,12]*|m:Que a&ntilde;o usted nacio?[{$person->years}]*" popup="true"  wait="false" callback="reloadBirthday"}</td>
	</tr>

	<!-- COUNTRY -->
	<tr>
		<td valign="middle"><small>Pa&iacute;s</small></td>
		<td valign="middle"><b id="value_birthday">{$person->country_name}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL PAIS" desc="m:Escoja el pais donde vive[Cuba,Estados Unidos,Espana,Italia,Mexico,Brasil,Ecuador,Canada,Venezuela,Alemania,Colombia,Otro]*" popup="true" wait="false" callback="reloadCountry"}</td>
	</tr>
</table>

{space15}

<center>
	{button href="PIROPAZO" caption="Continuar"}
</center>

<style>
	#profile tr {
		height: 40px;
	}
	#profile tr:nth-child(odd) {
		background-color: #F2F2F2;
	}
	#value_image {
		border: 1px solid grey;
		border-radius: 100px;
	}
</style>

<script>
	function reloadName(values) { document.getElementById('value_name').innerHTML = values[0]; }
	function reloadSex(values) { document.getElementById('value_sex').innerHTML = values[0]; }
	function reloadOrientation(values) { document.getElementById('value_orientation').innerHTML = values[0]; }
	function reloadBirthday(values) { document.getElementById('value_birthday').innerHTML = values[0]+"/"+values[1]+"/"+values[2]; }
	function reloadCountry(values) { document.getElementById('value_country').innerHTML = values[0]; }
	function reloadPicture(values) { document.getElementById('value_image').src = 'file://' + values[0]; }
</script>
