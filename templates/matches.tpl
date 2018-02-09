{include file="../includes/appmenu.tpl"}

<!--WAITING FOR YOU-->
{if $waitingCounter gt 0}
	<center>
		<h1>Esperando por ti</h1>
		<p><small>Estas personas dijeron Si, y est&aacute;n esperando tu respuesta</small></p>
	</center>
	{foreach item=person from=$people}
		{if $person->type neq "WAITING"}{continue}{/if}

		<!--COLOR BASED ON GENDER-->
		{assign var="color" value="gray"}
		{if $person->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
		{if $person->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

		<table width="100%" cellspacing="0" cellspadding="0">
			<tr>
				{if $APRETASTE_ENVIRONMENT eq "web"}
				<td width="50" valign="middle" align="center">
					{if empty($person->picture)}
						{noimage width="45" height="45" text="No Foto"}
					{else}
						{img src="{$person->picture_internal}" alt="Picture" width="45" height="45" style="border-radius:100px; border:2px solid {$color}"}
					{/if}
				</td>
				<td>&nbsp;</td>
				{/if}

				<td valign="middle">
					{link href="PIROPAZO PERFIL {$person->username}" caption="@{$person->username}" style="color:{$color};"}
					{if $person->online}&nbsp;<span class="online">ONLINE</span>{/if}
					<br/>
					{if $person->age OR $person->location}
					<small>
						{if $person->age}{$person->age} a&ntilde;os &nbsp;<b>&middot;</b>&nbsp;{/if}
						{if {$APRETASTE_ENVIRONMENT} eq "web"}<img class="flag" src="/images/flags/{$person->country|lower}.png" alt="{$person->country}"/>{/if}
						{if $person->location}{$person->location}{/if}
					</small>
					<br/>
					{/if}
					<font color="gray"><small>{$person->time_left} d&iacute;as para responder</small></font>
				</td>
				<td valign="middle" align="right">
					{button href="PIROPAZO SI @{$person->username}" caption="&hearts; S&iacute;" color="green" size="small" wait="false"}
					{button href="PIROPAZO NO @{$person->username}" caption="&#10008;" color="red" size="icon" wait="false"}
				</td>
			</tr>
			<tr><td colspan="4" heigth="50"><small><small>&nbsp;</small></small></td></tr>
		</table>
	{/foreach}
	{space15}
{/if}

<!--YOUR MATCHES-->
{if $matchCounter gt 0}
	<center>
		<h1>De pareja contigo</h1>
		<p><small>Ambos dijeron Si y ahora pueden chatear</small></p>
	</center>
	{foreach item=person from=$people}
		{if $person->type neq "MATCH"}{continue}{/if}

		<!--COLOR BASED ON GENDER-->
		{assign var="color" value="gray"}
		{if $person->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
		{if $person->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

		<table width="100%" cellspacing="0" cellspadding="0">
			<tr>
				{if $APRETASTE_ENVIRONMENT eq "web"}
				<td width="50" valign="middle" align="center">
					{if empty($person->picture)}
						{noimage width="45" height="45" text="No Foto"}
					{else}
						{img src="{$person->picture_internal}" alt="Picture" width="45" height="45" style="border-radius:100px; border:2px solid {$color}"}
					{/if}
				</td>
				<td>&nbsp;</td>
				{/if}

				<td valign="middle">
					{link href="PIROPAZO PERFIL {$person->username}" caption="@{$person->username}" style="color:{$color};"}
					{if $person->online}&nbsp;<span class="online">ONLINE</span>{/if}
					<br/>
					{if $person->age OR $person->location}
					<small>
						{if $person->age}{$person->age} a&ntilde;os &nbsp;<b>&middot;</b>&nbsp;{/if}
						{if {$APRETASTE_ENVIRONMENT} eq "web"}<img class="flag" src="/images/flags/{$person->country|lower}.png" alt="{$person->country}"/>{/if}
						{if $person->location}{$person->location}{/if}
					</small>
					<br/>
					{/if}
					<font color="gray"><small>Se conocieron el {$person->matched_on|date_format:"%d/%m/%Y"}</small></font>
				</td>
				<td valign="middle" align="right">
					{button href="PIROPAZO CHAT @{$person->username}" caption="Chat" color="green" size="small"}
					{button href="PIROPAZO NO @{$person->username}" caption="&#10008;" color="red" size="icon" wait="false"}
				</td>
			</tr>
			<tr><td colspan="4" heigth="50"><small><small>&nbsp;</small></small></td></tr>
		</table>
	{/foreach}
	{space15}
{/if}

<!--YOU ARE WAITING FOR THEM-->
{if $likeCounter gt 0}
	<center>
		<h1>Esperando por ellos</h1>
		<p><small>Mandando flores agregara una semana al tiempo de espera</small></p>
	</center>
	{foreach item=person from=$people}
		{if $person->type neq "LIKE"}{continue}{/if}

		<!--COLOR BASED ON GENDER-->
		{assign var="color" value="gray"}
		{if $person->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
		{if $person->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

		<table width="100%" cellspacing="0" cellspadding="0">
			<tr>
				{if $APRETASTE_ENVIRONMENT eq "web"}
				<td width="50" valign="middle" align="center">
					{if empty($person->picture)}
						{noimage width="45" height="45" text="No Foto"}
					{else}
						{img src="{$person->picture_internal}" alt="Picture" width="45" height="45" style="border-radius:100px; border:2px solid {$color}"}
					{/if}
				</td>
				<td>&nbsp;</td>
				{/if}

				<td valign="middle">
					{link href="PIROPAZO PERFIL {$person->username}" caption="@{$person->username}" style="color:{$color};"}
					{if $person->online}&nbsp;<span class="online">ONLINE</span>{/if}
					<br/>
					{if $person->age OR $person->location}
					<small>
						{if $person->age}{$person->age} a&ntilde;os &nbsp;<b>&middot;</b>&nbsp;{/if}
						{if {$APRETASTE_ENVIRONMENT} eq "web"}<img class="flag" src="/images/flags/{$person->country|lower}.png" alt="{$person->country}"/>{/if}
						{if $person->location}{$person->location}{/if}
					</small>
					<br/>
					{/if}
					<font color="gray"><small>{$person->time_left} d&iacute;as para responder</small></font>
				</td>
				<td valign="middle" align="right">
					{button href="PIROPAZO FLOR @{$person->username}" caption="&#x1F33C; Flor" popup="true" desc="a:Mande a decir algo con su flor*" color="green" size="small"}
					{button href="PIROPAZO NO @{$person->username}" caption="&#10008;" color="red" size="icon" wait="false"}
				</td>
			</tr>
			<tr><td colspan="4" heigth="50"><small><small>&nbsp;</small></small></td></tr>
		</table>
	{/foreach}
{/if}
