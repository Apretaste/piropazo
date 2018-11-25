<!-- PICTURE -->
<center>
	<p>Agrega una foto e info b&aacute;sica necesaria para buscar citas</p>
	{img src="{$person->picture_internal}" alt="Picture" width="100" id="value_image"}
	<br/>
	{button color="grey" href="PERFIL FOTO" desc="u:Toque aqui para agregar su foto*" caption="Agregar" size="small" popup="true" wait="false" callback="reloadPicture"}
</center>

{space15}

<table id="profile" width="100%" cellspacing="0">
	<!-- NAME -->
	<tr>
		<td valign="middle"><small>&iquest;Como te llamas?</small></td>
		<td valign="middle"><b id="value_name">{$person->full_name|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Agregar" href="PERFIL NOMBRE" desc="Escriba su nombre*" popup="true" wait="false" callback="reloadName"}</td>
	</tr>

	<!-- GENDER -->
	<tr>
		<td valign="middle"><small>&iquest;Qu&eacute; eres?</small></td>
		<td valign="middle"><b id="value_sex">{$person->gender|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Agregar" href="PERFIL SEXO" desc="m:Describa su genero [Hombre,Mujer]*" popup="true" wait="false" callback="reloadSex"}</td>
	</tr>

	<!-- SEXUAL ORIENTATION -->
	<tr>
		<td valign="middle"><small>&iquest;Qu&eacute; buscas?</small></td>
		<td valign="middle"><b id="value_orientation">{$person->searchfor|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Agregar" href="PERFIL ORIENTACIONBUSCO" desc="m:Que genero de personas desea conocer? [Mujeres,Hombres,Ambos]*" popup="true" wait="false" callback="reloadOrientation"}</td>
	</tr>

	<!-- BIRTH YEAR -->
	<tr>
		<td valign="middle"><small>&iquest;Cuando nacistes?</small></td>
		<td valign="middle"><b id="value_birthday">{$person->yearBorn}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Agregar" href="PERFIL ANO" desc="m:Que a&ntilde;o usted nacio?[{$person->years}]*" popup="true"  wait="false" callback="reloadBirthday"}</td>
	</tr>

	<!-- COUNTRY -->
	<tr>
		<td valign="middle"><small>&iquest;Donde vives?</small></td>
		<td valign="middle"><b id="value_country">{$person->country_name}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Agregar" href="PERFIL PAIS" desc="m:Escoja el pais donde vive[Cuba,Estados Unidos,Espana,Italia,Mexico,Brasil,Ecuador,Canada,Venezuela,Alemania,Colombia,Otro]*" popup="true" wait="false" callback="reloadCountry"}</td>
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
	function reloadBirthday(values) { document.getElementById('value_birthday').innerHTML = values[0]; }
	function reloadCountry(values) { document.getElementById('value_country').innerHTML = values[0]; }
	function reloadPicture(values) { document.getElementById('value_image').src = 'file://' + values[0]; }
</script>
