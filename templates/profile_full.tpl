{include file="../includes/appmenu.tpl"}

<h1>Edite su perfil</h1>

<!-- PICTURE -->
<center>
	<div id="img_container">
		{img id="img_picture" src="{$person->picture_internal}" alt="Picture"}
		<div id="img_check">&#x2714;</div>
	</div>

	<br/>
	{button color="grey" href="PERFIL FOTO" desc="u:Toque aqui para agregar su foto*" caption="Editar" size="small" popup="true" wait="false" callback="reloadPicture"}

	{space15}

	<small id="value_desc">{$person->about_me}</small>
	<br/>
	{button size="small" color="grey" caption="Editar" href="PERFIL DESCRIPCION" desc="a:Describase a su gusto con un maximo 250 caracteres*" popup="true" wait="false" callback="reloadDesc"}
</center>

{space15}

<table id="profile" width="100%" cellspacing="0">
	<!-- USERNAME -->
	<tr>
		<td valign="middle"><small>Username</small></td>
		<td valign="middle"><b id="value_username">@{$person->username}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL USERNAME" desc="Escriba un nombre de usuario" popup="true" wait="false" callback="reloadUsername"}</td>
	</tr>

	<!-- NAME -->
	<tr>
		<td valign="middle"><small>Nombre</small></td>
		<td valign="middle"><b id="value_name">{$person->full_name|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL NOMBRE" desc="Escriba su nombre completo" popup="true" wait="false" callback="reloadName"}</td>
	</tr>

	<!-- GENDER -->
	<tr>
		<td valign="middle"><small>&iquest;Qu&eacute; eres?</small></td>
		<td valign="middle"><b id="value_sex">{$person->gender|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL SEXO" desc="m:Describa su genero [Hombre,Mujer]*" popup="true" wait="false" callback="reloadSex"}</td>
	</tr>

	<!-- SEXUAL ORIENTATION -->
	<tr>
		<td valign="middle"><small>&iquest;Qu&eacute; buscas?</small></td>
		<td valign="middle"><b id="value_orientation">{$person->searchfor|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL ORIENTACIONBUSCO" desc="m:Que genero de personas desea conocer? [Mujeres,Hombres,Ambos]*" popup="true" wait="false" callback="reloadOrientation"}</td>
	</tr>

	<!-- BIRTH YEAR -->
	<tr>
		<td valign="middle"><small>&iquest;Cuando naci&oacute;?</small></td>
		<td valign="middle"><b id="value_birthday">{$person->year_of_birth}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL ANO" desc="m:Que a&ntilde;o usted nacio?[{$person->years}]*" popup="true"  wait="false" callback="reloadBirthday"}</td>
	</tr>

	<!-- BODY TYPE -->
	<tr>
		<td valign="middle"><small>Cuerpo</small></td>
		<td valign="middle"><b id="value_body">{$person->body_type|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL CUERPO" desc="m:Describa su composicion fisica [Delgado,Medio,Extra,Atletico]" popup="true" wait="false" callback="reloadBody"}</td>
	</tr>

	<!-- EYES -->
	<tr>
		<td valign="middle"><small>Ojos</small></td>
		<td valign="middle"><b id="value_eyes">{$person->eyes|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL OJOS" desc="m:De que color son sus ojos? [Negro,Carmelita,Verde,Azul,Avellana,Otro]" popup="true" wait="false" callback="reloadEyes"}</td>
	</tr>

	<!-- HAIR -->
	<tr>
		<td valign="middle"><small>Pelo</small></td>
		<td valign="middle"><b id="value_hair">{$person->hair|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL PELO" desc="m:De que color es tu pelo? [Trigueno,Castano,Rubio,Negro,Rojo,Blanco,Otro]" popup="true"  wait="false" callback="reloadHair"}</td>
	</tr>

	<!-- SKIN -->
	<tr>
		<td valign="middle"><small>Piel</small></td>
		<td valign="middle"><b id="value_skin">{$person->skin|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL PIEL" desc="m:Describa su piel [Blanco,Negro,Mestizo,Otro]" popup="true"  wait="false" callback="reloadSkin"}</td>
	</tr>

	<!-- MARITAL STATUS -->
	<tr>
		<td valign="middle"><small>Estado civil</small></td>
		<td valign="middle"><b id="value_civilstatus">{$person->marital_status|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL ESTADOCIVIL" desc="m:Describa su estado civil [Soltero,Saliendo,Comprometido,Casado]" popup="true"  wait="false" callback="reloadCivilStatus"}</td>
	</tr>

	<!-- HIGHEST SCHOOL LEVEL-->
	<tr>
		<td valign="middle"><small>Nivel escolar</small></td>
		<td valign="middle"><b id="value_school">{$person->highest_school_level|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL NIVEL" desc="m:Cual es su nivel escolar? [Primario,Secundario,Tecnico,Universitario,Postgraduado,Doctorado,Otro]" popup="true"  wait="false" callback="reloadSchool"}</td>
	</tr>

	<!-- OCCUPATION -->
	<tr>
		<td valign="middle"><small>Profesi&oacute;n</small></td>
		<td valign="middle"><b id="value_profesion">{$person->occupation|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL PROFESION" desc="m:Describa su profesion [Trabajador estatal,Cuentapropista,Estudiante,Ama de casa,Desempleado]" popup="true"  wait="false" callback="reloadProfesion"}</td>
	</tr>

	<!-- COUNTRY -->
	<tr>
		<td valign="middle"><small>Pa&iacute;s</small></td>
		<td valign="middle">
			<b id="value_country">
				{if {$APRETASTE_ENVIRONMENT} eq "web"}
					<img style="height:15px;" src="/images/flags/{$person->country|lower}.png" alt="{$person->country}"/>
				{/if}
				{$person->country_name|lower|capitalize}
			</b>
		</td>
		<td align="right" valign="middle"><nobr>
			{button size="small" color="grey" caption="Editar" href="PERFIL PAIS" desc="m:Escoja un pais de esta lista[Cuba,Estados Unidos,Espana,Italia,Mexico,Brasil,Ecuador,Canada,Venezuela,Alemania,Colombia]|t:O escriba el nombre del pais donde vive" popup="true" wait="false" callback="reloadCountry"}
		</nobr></td>
	</tr>

	<!-- PROVINCE-->
	<tr id="container_province" class="{if $person->country != 'CU'}hidden{/if}">
		<td valign="middle"><small>Provincia</small></td>
		<td valign="middle"><b id="value_province">{$person->province|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL PROVINCIA" desc="m:En que provincia vive? [Pinar_del_Rio,La_Habana,Artemisa,Mayabeque,Matanzas,Villa_Clara,Cienfuegos,Sancti_Spiritus,Ciego_de_Avila,Camaguey,Las_Tunas,Holguin,Granma,Santiago_de_Cuba,Guantanamo,Isla_de_la_Juventud]" popup="true"  wait="false" callback="reloadProvince"}</td>
	</tr>

	<!-- STATE-->
	<tr id="container_state" class="{if $person->country != 'US'}hidden{/if}">
		<td valign="middle"><small>Estado</small></td>
		<td valign="middle"><b id="value_state">{$person->usstate_name|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL USSTATE" desc="m:En que pais vives? [AL,AK,AZ,AR,CA,CO,CT,DE,FL,GA,HI,ID,IL,IN,IA,KS,KY,LA,ME,MD,MA,MI,MN,MS,MO,MT,NE,NV,NH,NJ,NM,NY,NC,ND,OH,OK,OR,PA,RI,SC,SD,TN,TX,UT,VT,VA,WA,WV,WI,WY]" popup="true"  wait="false" callback="reloadState"}</td>
	</tr>

	<!-- CITY -->
	<tr>
		<td valign="middle"><small>Ciudad</small></td>
		<td valign="middle"><b id="value_city">{$person->city|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL CIUDAD" desc="Escriba el nombre de la ciudad o pueblo donde vive" popup="true" wait="false" callback="reloadCity"}</td>
	</tr>

	<!-- INTERESTS -->
	<tr>
		<td valign="middle"><small>Intereses</small></td>
		<td valign="middle"><b id="value_interests">{$person->interests} intereses</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL INTERESES" desc="Escriba sus intereses separados por coma. Por ejemplo jardineria, musica, bailar" popup="true" wait="false" callback="reloadInterests"}</td>
	</tr>

	<!-- RELIGION -->
	<tr>
		<td valign="middle"><small>Religi&oacute;n</small></td>
		<td valign="middle"><b id="value_religion">{$person->religion|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Editar" href="PERFIL RELIGION" desc="m:Describa su religion [Cristianismo,Catolicismo,Yoruba,Protestante,Santero,Abakua,Budismo,Islam,Ateismo,Agnosticismo,Secularismo,Otra]" popup="true" wait="false" callback="reloadReligion"}</td>
	</tr>
</table>

{space15}

<center>
	{button caption="Mi Perfil" href="PIROPAZO PERFIL"}
	<p class="note">Si ya encontr&oacute; pareja puede {link href="PIROPAZO SALIR" style="color:gray;" caption="salir de Piropazo"}</p>
</center>

<style>
	#profile tr {
		height: 40px;
	}
	#profile tr:nth-child(odd) {
		background-color: #F2F2F2;
	}
	.hidden{
		display: none;
	}
	.note {
		font-size: small;
		color: gray;
	}
	#img_container {
		position: relative;
		text-align: center;
	}
	#img_picture {
		border: 1px solid grey;
		border-radius: 100px;
		width: 100px;
		height: 100px;
	}
	#img_check {
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		color: green;
		font-size: 80px;
		font-weight: bold;
		display: none;
	}
</style>

<script>
	function reloadUsername(values) { document.getElementById('value_username').innerHTML = '@'+values[0]; }
	function reloadName(values) { document.getElementById('value_name').innerHTML = values[0]; }
	function reloadSex(values) { document.getElementById('value_sex').innerHTML = values[0]; }
	function reloadOrientation(values) { document.getElementById('value_orientation').innerHTML = values[0]; }
	function reloadBody(values) { document.getElementById('value_body').innerHTML = values[0]; }
	function reloadEyes(values) { document.getElementById('value_eyes').innerHTML = values[0]; }
	function reloadHair(values) { document.getElementById('value_hair').innerHTML = values[0]; }
	function reloadSkin(values) { document.getElementById('value_skin').innerHTML = values[0]; }
	function reloadCivilStatus(values) { document.getElementById('value_civilstatus').innerHTML = values[0]; }
	function reloadSchool(values) { document.getElementById('value_school').innerHTML = values[0]; }
	function reloadProfesion(values) { document.getElementById('value_profesion').innerHTML = values[0]; }
	function reloadProvince(values) { document.getElementById('value_province').innerHTML = values[0].replace("_", " "); }
	function reloadState(values) { document.getElementById('value_state').innerHTML = values[0]; }
	function reloadCity(values) { document.getElementById('value_city').innerHTML = values[0]; }
	function reloadInterests(values) { document.getElementById('value_interests').innerHTML = values[0].split(",").length + " intereses"; }
	function reloadReligion(values) { document.getElementById('value_religion').innerHTML = values[0]; }
	function reloadBirthday(values) { document.getElementById('value_birthday').innerHTML = values[0]; }
	function reloadDesc(values) { document.getElementById('value_desc').innerHTML = values[0]; }
	function reloadPicture(values) {
		document.getElementById('img_picture').style.filter = "blur(3px)";
		document.getElementById('img_check').style.display = "inline";
	}
	function reloadCountry(values) {
		var country = values[0] == "" ? values[1] : values[0];
		document.getElementById('value_country').innerHTML = country;

		if(country == "Cuba") {
			document.getElementById('container_province').style.display = "table-row";
			document.getElementById('container_state').style.display = "none";
		}
		if(country == "Estados Unidos") {
			document.getElementById('container_state').style.display = "table-row";
			document.getElementById('container_province').style.display = "none";
		}
	}
</script>
