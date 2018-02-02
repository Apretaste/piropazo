{include file="../layouts/appmenu.tpl"}

{if $noProfilePic}
	<div class="notice">Agregue su foto de perfil para recibir 70% m&aacute;s atenci&oacute;n</div>
{/if}

{if $completion lt 85}
	<div class="notice">Complete 85% o mas de su perfil para encontrar su pareja ideal</div>
{/if}

{if $noProvince}
	<div class="notice">Agregue su pais o provincia para encontrar gente cercana</div>
{/if}

{if $fewInterests}
	<div class="notice">Agregue 10 &oacute; m&aacute;s intereses para encontrar su pareja ideal</div>
{/if}

<h1>Diga S&iacute; o No</h1>

{space5}

<center>
	<!--CROWN-->
	{if $people[0]->crown}
		<spam style="color:orange; font-size:30px;" class="emogi">&#x1F451;</spam><br/>
	{/if}

	<!--COLOR BASED ON GENDER-->
	{assign var="color" value="gray"}
	{if $people[0]->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
	{if $people[0]->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

	<!--PICTURE-->
	{if $people[0]->picture}
		{link href="PIROPAZO PERFIL @{$people[0]->username}" style="text-decoration:none;" caption="
			{img src="{$people[0]->picture_internal}" alt="Picture" width="200" height="200" style="border-radius:10px; border:3px solid {$color};"}
		"}
	{else}
		{noimage width="200" height="200" text="Tristemente ...<br/>Sin foto de perfil :'-("}
	{/if}<br/>

	<!--TAGS-->
	{foreach item=tag from=$people[0]->tags}
		<small style="background-color:#D9EDF7;"><font color="#757B8F"><nobr>&nbsp;{$tag}&nbsp;</nobr></font></small>
	{/foreach}

	<!--FLAG AND LOCATION-->
	<p style="font-size:small;">
		{link href="PIROPAZO PERFIL @{$people[0]->username}" caption="@{$people[0]->username}" style="color:{$color}"}
		&nbsp;<b>&middot;</b>&nbsp;
		{if $people[0]->age}
			{$people[0]->age} a&ntilde;os
			&nbsp;<b>&middot;</b>&nbsp;
		{/if}
		{if {$APRETASTE_ENVIRONMENT} eq "web"}
			<img class="flag" src="/images/flags/{$people[0]->country|lower}.png" alt="{$people[0]->country}"/>
		{/if}
		{$people[0]->location}
	</p>

	<!--BUTTONS-->
	{space5}
	{button href="PIROPAZO SINEXT @{$people[0]->username}" caption="&hearts; S&iacute;" color="green"}
	{button href="PIROPAZO NONEXT @{$people[0]->username}" caption="&#10008; No" color="red"}
</center>
