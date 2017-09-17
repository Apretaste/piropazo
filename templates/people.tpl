{if $noProfilePic}
	<table width="100%" cellpadding="0" cellspacing="0"><tr bgcolor="#F2DEDE">
		<td width="1">&nbsp;</td>
		<td><small><font color="#A94442">No tiene foto de perfil. Usuarios con foto reciben 70% m&aacute;s atenci&oacute;n</font></small></td>
		{if $notFromApp}
			<td width="1">&nbsp;</td>
			<td align="right" valign="middle">{button href="PERFIL EDITAR" size="small" caption="Agregar foto" body="Envie este email tal y como esta. Recibira como respuesta su perfil en modo de edicion."}</td>
		{/if}
	</tr></table>
	{space10}
{/if}

{if $completion lt 85}
	<table width="100%" cellpadding="0" cellspacing="0"><tr bgcolor="#F2DEDE">
		<td width="1">&nbsp;</td>
		<td><small><font color="#A94442">Solo ha llenado el <b>{$completion|number_format}%</b> de su perfil. Complete al menos el 85% de su perfil para sugerirle personas m&aacute;s afines a usted</font></small></td>
		{if $notFromApp}
			<td width="1">&nbsp;</td>
			<td align="right" valign="middle">{button href="PERFIL EDITAR" size="small" caption="Completar" body="Envie este email tal y como esta. Recibira como respuesta su perfil en modo de edicion."}</td>
		{/if}
	</tr></table>
	{space10}
{/if}

{if $noProvince}
	<table width="100%" cellpadding="0" cellspacing="0"><tr bgcolor="#F2DEDE">
		<td width="1">&nbsp;</td>
		<td><small><font color="#A94442">No ha agregado su provincia en su perfil. Agregue su provincia para poder sugerirle personas cercanas a usted</font></small></td>
		{if $notFromApp}
			<td width="1">&nbsp;</td>
			<td align="right" valign="middle">{button href="PERFIL EDITAR" size="small" caption="Incluir" body="Envie este email tal y como esta. Recibira como respuesta su perfil en modo de edicion."}</td>
		{/if}
	</tr></table>
	{space10}
{/if}

{if $fewInterests}
	<table width="100%" cellpadding="0" cellspacing="0"><tr bgcolor="#F2DEDE">
		<td width="1">&nbsp;</td>
		<td><small><font color="#A94442">Agregue 10 &oacute; m&aacute;s intereses o m&aacute;s en su perfil para poder encontrarle su pareja ideal</font></small></td>
		{if $notFromApp}
			<td width="1">&nbsp;</td>
			<td align="right" valign="middle">{button href="PERFIL EDITAR" size="small" caption="Agregar" body="Envie este email tal y como esta. Recibira como respuesta su perfil en modo de edicion."}</td>
		{/if}
	</tr></table>
	{space10}
{/if}

<h1>Diga S&iacute; o No</h1>

<p>Si usted dice "S&iacute;" a alguien, y esa persona dice "S&iacute;" a usted, podr&aacute;n chatear. Termine con esta lista y vea m&aacute;s, o valla a su lista de parejas en el bot&oacute;n al final.</p>

{space10}

{foreach name=people item=person from=$people}
	<table width="100%" cellspacing="0" cellspadding="0" border=0>
		<tr>
			<td width="100" valign="middle" align="center">
				{if $person->crown}
					<spam style="color:orange;"><big><big><b>&#9813;</b></big></big><spam><br/>
				{/if}

				{if empty($person->picture)}
					{noimage width="100" height="100" text="Tristemente<br/>aun sin foto<br/>:'-("}
				{else}
					<table cellpadding="3"><tr><td bgcolor="#202020">
					{img src="{$person->picture_internal}" alt="Picture" width="100"}
					</td></tr></table>
				{/if}

				<small style="color:#202020"><nobr>&nbsp;{$person->location}&nbsp;</nobr></small>
			</td>
			<td>&nbsp;&nbsp;</td>
			<td valign="top">
				{foreach item=tag from=$person->tags}
					<small style="background-color:#D9EDF7;"><font color="#757B8F"><nobr>&nbsp;{$tag}&nbsp;</nobr></font></small>
				{/foreach}

 	 			<p>{link href="PERFIL {$person->username}" caption="@{$person->username}"}: {$person->about_me}</p>

 	 			{button href="PIROPAZO SI @{$person->username}" caption="&hearts; S&iacute;" color="green" size="small" wait="false"}
				{button href="PIROPAZO NO @{$person->username}" caption="&#10008; No" color="red" size="small" wait="false"}
			</td>
		</tr>

		{if not $smarty.foreach.people.last}
			<tr><td colspan=3><hr/></td></tr>
		{/if}
	</table>
{/foreach}

{space30}

<center>
	<p><small>Si ya dijo S&iacute; o No a todos, &iquest;Que m&aacute;s quiere hacer?</small></p>
	{button href="PIROPAZO OTROS {foreach name=people item=person from=$people}@{$person->username} {/foreach}" caption="Ver m&aacute;s" color="green"}
	{button href="PIROPAZO PAREJAS" caption="Mis parejas" color="grey"}
</center>

{space30}

{if not $crowned}
	<h1>P&oacute;ngase una corona</h1>
	<p>Una corona se le mostrar&aacute; en su cabeza por los pr&oacute;ximos tres d&iacute;as, adem&aacute;s har&aacute; que su perfil se muestre a otros con mucha mayor frecuencia.</p>
	<center>
		{button href="PIROPAZO CORONA" caption="&#9813; Usar Corona"}
	</center>
	{space30}
{/if}

<small>
	1. Si estas personas no le agradan, complete {if $notFromApp}{link href="PERFIL EDITAR" caption="su perfil"}{else}su perfil{/if}<br/>
	2. Compre m&aacute;s Flores y Coronas en {link href="PIROPAZO TIENDA" caption="nuestra tienda"}<br/>
	3. Si ya encontr&oacute; a su media naranja, puede {link href="PIROPAZO SALIR" caption="salir de Piropazo"}<br/>
</small>
