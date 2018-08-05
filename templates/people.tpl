{include file="../includes/appmenu.tpl"}

{if $noProfilePic}
	<div class="notice">Agregue su foto de perfil para recibir m&aacute;s atenci&oacute;n</div>
{/if}

{if $completion lt 65}
	<div class="notice">Complete al menos el 65% del perfil para encontrar su pareja ideal</div>
{/if}

{if $noProvince}
	<div class="notice">Agregue su pais o provincia para encontrar gente cercana</div>
{/if}

{if $fewInterests}
	<div class="notice">Agregue 3 &oacute; m&aacute;s intereses para encontrar su pareja ideal</div>
{/if}

<center>
	<!--CROWN-->
	{if $person->crown}
		<spam style="color:orange; font-size:30px;" class="emogi">&#x1F451;</spam><br/>
	{/if}

	<!--COLOR BASED ON GENDER-->
	{assign var="color" value="gray"}
	{if $person->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
	{if $person->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

	<!--PICTURE-->
	{if $person->picture}
		{link href="PIROPAZO PERFIL @{$person->username}" style="text-decoration:none;" caption="
			{img src="{$person->picture_internal}" alt="Picture" width="200" height="200" style="border-radius:10px; border:3px solid {$color};"}
		"}
	{else}
		{noimage width="200" height="200" text="Tristemente ...<br/>Sin foto de perfil :'-("}
	{/if}<br/>

	<!--TAGS-->
	{foreach item=tag from=$person->tags}
		<small style="background-color:#D9EDF7;"><font color="#757B8F"><nobr>&nbsp;{$tag}&nbsp;</nobr></font></small>
	{/foreach}

	<p style="font-size:small;">
		<!--USERNAME-->
		{link href="PIROPAZO PERFIL @{$person->username}" caption="@{$person->username}" style="color:{$color}"}
		{if $person->online}&nbsp;<span class="online">ONLINE</span>{/if}

		<!--AGE-->
		{if $person->age}
			&nbsp;<b>&middot;</b>&nbsp;
			{$person->age} a&ntilde;os
		{/if}

		<!--FLAG AND LOCATION-->
		&nbsp;<b>&middot;</b>&nbsp;
		{if $environment eq "web" or $environment eq "appnet"}
			{img src="{$person->country|lower}.png" alt="{$person->country}" class="flag"}
		{/if}
		{$person->location}
	</p>

	<!--BUTTONS-->
	{space5}
	{button href="PIROPAZO SINEXT @{$person->username}" caption="&hearts; S&iacute;" color="green"}
	{button href="PIROPAZO NONEXT @{$person->username}" caption="&#10008; No" color="red"}
</center>
