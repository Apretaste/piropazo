{include file="../layouts/appmenu.tpl"}

<!--COLOR BASED ON GENDER-->
{assign var="color" value="gray"}
{if $profile->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
{if $profile->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

<!--PROFILE PICTURE-->
<center>
	<!--CROWN OR PERCENTAGE-->
	{if $crowned}
		<span style="color:orange; font-size:50px;" class="emogi">&#x1F451;</span><br/>
	{/if}

	{if $profile->picture}
		{img src="{$profile->picture_internal}" alt="Picture" width="200" style="border-radius:100px; border:3px solid {$color};"}
	{else}
		{noimage width="200" height="200" text="Tristemente ...<br/>Sin foto de perfil :'-("}
	{/if}

	{space10}

	<div style="font-size:30px;">
		{if $isMyOwnProfile}
			<span style="color:green;" class="emogi">&#x1F339; {$flowers}</span>
			&nbsp;&nbsp;
			{link href="PIROPAZO CORONA" caption="<span style='color:orange;' class='emogi'>&#x1F451; {$crowns}</span>" style="text-decoration:underline solid {$color};"}
		{else}
			<span style="color:{$color};">{$percentageMatch}% IGUALES<span>
		{/if}
	</div>

	{space5}

	<!--FLAG AND LOCATION-->
	<p style="font-size:small;">
		<span style="color:{$color}">@{$profile->username}</span>
		&nbsp;<b>&middot;</b>&nbsp;
		{if $profile->age}
			{$profile->age} a&ntilde;os
			&nbsp;<b>&middot;</b>&nbsp;
		{/if}
		{if {$APRETASTE_ENVIRONMENT} eq "web"}
			<img class="flag" src="/images/flags/{$profile->country|lower}.png" alt="{$profile->country}"/>
		{/if}
		{$profile->location}
	</p>

	<!--ABOUT ME-->
	<div>{$profile->about_me}</div>

	{space5}

	<!--MY INTERESTS-->
	{if $profile->interests|@count gt 0}
	<div style="background-color:#F2F2F2; padding:10px;">
		<span>MIS INTERESES</span>
		{space5}
		{foreach from=$profile->interests item=interest}
			{tag caption="{$interest}"}
		{/foreach}
	</div>
	{/if}

	{space10}

	<!--BUTTONS-->
	{if $isMyOwnProfile}
		{if {$APRETASTE_ENVIRONMENT} eq "web"}
			{button href="PERFIL EDITAR" caption="Editar perfil"}
		{/if}
	{elseif $status == "no_relationship" OR $status == "they_like_you"}
		{button href="PIROPAZO SI @{$profile->username}" caption="Decir Si" color="green" wait="false"}
		{button href="PIROPAZO NOMATCHES @{$profile->username}" caption="Decir No" color="red"}
	{elseif $status == "you_like_them"}
		{button href="PIROPAZO FLOR @{$profile->username}" caption="&#x1F33C; Flor" popup="true" desc="a:Mande a decir algo con su flor*" color="green"}
		{button href="PIROPAZO NOMATCHES @{$profile->username}" caption="&#10008; Bloquear" color="red"}
	{elseif $status == "match"}
		{button href="CHAT @{$profile->username}" caption="Chatear" color="grey"}
		{button href="PIROPAZO NOMATCHES @{$profile->username}" caption="&#10008; Bloquear" color="red"}
	{/if}

	<!--DENOUNCE BUTTON-->
	{if not $isMyOwnProfile}
		{space5}
		<p style="font-size:small; color:grey;">Si ve algun problema {link href="PIROPAZO REPORTAR @{$profile->username}" caption="denuncie a este usuario" desc="m:Por que desea denunciar a este usuario? [La foto o el texto es ofensivo,El perfil tiene informacion falsa,La persona no luce como el perfil,Esta impersonando a alguien,El perfil viola los derechos de autor]*" popup="true" wait="false" style="color:grey;"}</p>
	{/if}
</center>
