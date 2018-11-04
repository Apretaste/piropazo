{include file="../includes/appmenu.tpl"}

<!--COLOR BASED ON GENDER-->
{assign var="color" value="gray"}
{if $profile->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
{if $profile->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

<!--PROFILE PICTURE-->
<center>
	<!--CROWN OR PERCENTAGE-->
	{if $crowned}
		<span style="color:orange; font-size:50px;" class="emoji">&#x1F451;</span><br/>
	{/if}

	{if $profile->picture}
		{img src="{$profile->picture_internal}" alt="Picture" width="200" height="200" style="border-radius:100px; border:3px solid {$color};"}
	{else}
		{noimage width="200" height="200" text="Tristemente ...<br/>Sin foto de perfil :'-("}
	{/if}

	{space10}

	<div style="font-size:30px;">
		{if $isMyOwnProfile}
			<span style="color:green;" class="emoji">&#x1F339; {$flowers}</span>
			&nbsp;&nbsp;
			{link href="PIROPAZO CORONA" caption="<span style='color:orange;' class='emoji'>&#x1F451; {$crowns}</span>" style="text-decoration:underline solid {$color};"}
		{else}
			<span style="color:{$color};">{$percentageMatch}% IGUALES<span>
		{/if}
	</div>

	{space5}

	<!--FLAG AND LOCATION-->
	<p style="font-size:small;">
		<span style="color:{$color}">@{$profile->username}</span>
		{if $profile->age}
			&nbsp;<b>&middot;</b>&nbsp;
			{$profile->age} a&ntilde;os
		{/if}
		{if ($environment eq "web" or $environment eq "appnet") and $profile->country}
			&nbsp;<b>&middot;</b>&nbsp;
			{img src="{$profile->country|lower}.png" alt="{$profile->country}" class="flag"}
		{/if}
		{$profile->location}
	</p>

	<!--ABOUT ME-->
	<div>{$profile->about_me}</div>

	{space15}

	<!--MY INTERESTS-->
	{if $profile->interests|@count gt 0}
		<span>MIS INTERESES</span>
		{space5}
		{foreach from=$profile->interests item=interest}
			{tag caption="{$interest}"}
		{/foreach}

		{space15}
	{/if}

	<!--BUTTONS-->
	{if $isMyOwnProfile}
		{button href="PIROPAZO EDITAR" caption="Editar Perfil"}
	{else}
		{button href="PIROPAZO {$returnTo}" caption="Atr&aacute;s" color="grey"}
	{/if}

	<!--DENOUNCE BUTTON-->
	{if not $isMyOwnProfile}
		{space5}
		<p id="value_report" style="font-size:small; color:grey;">{button class="empty" href="PIROPAZO REPORTAR @{$profile->username}|" caption="Denuncie a este usuario" desc="m:Por que desea denunciar a este usuario? [La foto o el texto es ofensivo,El perfil tiene informacion falsa,La persona no luce como el perfil,Esta impersonando a alguien,El perfil viola los derechos de autor]*" popup="true" wait="false" style="color:grey; text-decoration:underline;" callback="reloadReport"}</p>
	{/if}
</center>

<script>
	function reloadReport(values) { 
		document.getElementById('value_report').innerHTML = "Este usuario ha sido reportado";
		document.getElementById('value_report').style.color = "red";
	}
</script>
