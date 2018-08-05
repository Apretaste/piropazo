<h1>Edite su perfil</h1>

<table id="profile" width="100%" cellspacing="0">
	<!-- NAME -->
	<tr>
		<td valign="middle"><small>Nombre</small></td>
		<td valign="middle"><b>{$person->first_name}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL NOMBRE" desc="t:Escriba su nombre" popup="true"  wait="false"}</td>
	</tr>

	<!-- GENDER -->
	<tr>
		<td valign="middle"><small>Sexo</small></td>
		<td valign="middle"><b>{$person->gender|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL SEXO" desc="m:Describa su genero [Masculino,Femenino]" popup="true"  wait="false"}</td>
	</tr>

	<!-- SEXUAL ORIENTATION -->
	<tr>
		<td valign="middle"><small>Orientaci&oacute;n sexual</small></td>
		<td valign="middle"><b>{$person->sexual_orientation|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL ORIENTACION" desc="m:Describa su orientacion sexual [Hetero,Homo,Bi]" popup="true"  wait="false"}</td>
	</tr>

	<!-- DAY OF BIRTH -->
	<tr>
		<td valign="middle"><small>Cumplea&ntilde;os</small></td>
		<td valign="middle"><b>{$person->date_of_birth|date_format:"%e/%m/%Y"}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL CUMPLEANOS" desc="d:Escriba su fecha de cumpleannos usando la notacion DD/MM/AAAA, por ejemplo 5/2/1980" popup="true"  wait="false"}</td>
	</tr>

	<!-- PROVINCE-->
	<tr>
		<td valign="middle"><small>Provincia</small></td>
		<td valign="middle"><b>{$person->province|lower|capitalize}</b></td>
		<td align="right" valign="middle">{button size="small" color="grey" caption="Cambiar" href="PERFIL PROVINCIA" desc="m:En que provincia vive? [Pinar_del_Rio,La_Habana,Artemisa,Mayabeque,Matanzas,Villa_Clara,Cienfuegos,Sancti_Spiritus,Ciego_de_Avila,Camaguey,Las_Tunas,Holguin,Granma,Santiago_de_Cuba,Guantanamo,Isla_de_la_Juventud]" popup="true"  wait="false"}</td>
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
</style>
