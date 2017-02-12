{if $waitingCounter gt 0}
	<h1>Esperando por ti</h1>
	<p><small>Estas personas dijeron que les gustas y est&aacute;n esperando por tu respuesta.</small></p>
	{foreach item=person from=$people}
		{if $person->type neq "WAITING"}{continue}{/if}
		<table width="100%" cellspacing="0" cellspadding="0" border=0>
			<tr>
				<td width="50" valign="middle" align="center">
					{if empty($person->picture)}
						{noimage width="50" height="100" text="No Foto"}
					{else}
						{img src="{$person->pictureURL}" alt="Picture" width="50"}
					{/if}
				</td>
				<td>&nbsp;</td>
				<td valign="middle">
					{link href="PERFIL {$person->username}" caption="@{$person->username}"}
					{if $person->gender eq "M"}<font color="#4863A0">M</font>{/if}
					{if $person->gender eq "F"}<font color=#F778A1>F</font>{/if}
					<br/>
					{if $person->age}{$person->age} a&ntilde;os,{/if}
					{if $person->location}{$person->location}{/if}
					<br/>
					<font color="gray"><small>{$person->time_left} d&iacute;as para responder</small></font>
				</td>
				<td valign="middle" align="right">
					{button href="PIROPAZO SI @{$person->username}" caption="&hearts; S&iacute;" color="green" size="small"}
					{button href="PIROPAZO NO @{$person->username}" caption="&#10008; No" color="red" size="small"}
				</td>
			</tr>
			<tr><td colspan="4" heigth="50"><small><small>&nbsp;</small></small></td></tr>
		</table>
	{/foreach}
{/if}

{space15}

{if $matchCounter gt 0}
	<h1>De pareja contigo</h1>
	<p><small>Estas personas y usted se gustaron mutuamente y ahora pueden chatear.</small></p>
	{foreach item=person from=$people}
		{if $person->type neq "MATCH"}{continue}{/if}
		<table width="100%" cellspacing="0" cellspadding="0" border=0>
			<tr>
				<td width="50" valign="middle" align="center">
					{if empty($person->picture)}
						{noimage width="50" height="100" text="No Foto"}
					{else}
						{img src="{$person->pictureURL}" alt="Picture" width="50"}
					{/if}
				</td>
				<td>&nbsp;</td>
				<td valign="middle">
					{link href="PERFIL {$person->username}" caption="@{$person->username}"}
					{if $person->gender eq "M"}<font color="#4863A0">M</font>{/if}
					{if $person->gender eq "F"}<font color=#F778A1>F</font>{/if}
					<br/>
					{if $person->age}{$person->age} a&ntilde;os,{/if}
					{if $person->location}{$person->location}{/if}
					<br/>
					<font color="gray"><small>Se conocieron el {$person->matched_on|date_format:"%d/%m/%Y"}</small></font>
				</td>
				<td valign="middle" align="right">
					{button href="NOTA @{$person->username}" caption="Chat" color="green" size="small"}
					{button href="PIROPAZO NO @{$person->username}" caption="Borrar" color="red" size="small"}
				</td>
			</tr>
			<tr><td colspan="4" heigth="50"><small><small>&nbsp;</small></small></td></tr>
		</table>
	{/foreach}
{/if}

{space15}

{if $likeCounter gt 0}
	<h1>Esperando por ellos</h1>
	<p><small>Usted dijo "S&iacute;" a estas personas y ahora estamos esperando su respuesta. Si el tiempo de espera vence desapareceran de su lista. Mandeles flores para agregar una semana al tiempo de espera.</small></p>
	{foreach item=person from=$people}
		{if $person->type neq "LIKE"}{continue}{/if}
		<table width="100%" cellspacing="0" cellspadding="0" border=0>
			<tr>
				<td width="50" valign="middle" align="center">
					{if empty($person->picture)}
						{noimage width="50" height="100" text="No Foto"}
					{else}
						{img src="{$person->pictureURL}" alt="Picture" width="50"}
					{/if}
				</td>
				<td>&nbsp;</td>
				<td valign="middle">
					{link href="PERFIL {$person->username}" caption="@{$person->username}"}
					{if $person->gender eq "M"}<font color="#4863A0">M</font>{/if}
					{if $person->gender eq "F"}<font color=#F778A1>F</font>{/if}
					<br/>
					{if $person->age}{$person->age} a&ntilde;os,{/if}
					{if $person->location}{$person->location}{/if}
					<br/>
					<font color="gray"><small>{$person->time_left} d&iacute;as para responder</small></font>
				</td>
				<td valign="middle" align="right">
					{button href="PIROPAZO FLOR @{$person->username}" caption="&#9880; Flor" color="green" size="small"}
					{button href="PIROPAZO NO @{$person->username}" caption="Borrar" color="red" size="small"}
				</td>
			</tr>
			<tr><td colspan="4" heigth="50"><small><small>&nbsp;</small></small></td></tr>
		</table>
	{/foreach}
{/if}
