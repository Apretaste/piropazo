<table width="100%" height="100%"><tr><td align="center" valign="middle">
	{include file="../includes/appmenu.tpl"}

	<h1>Diga S&iacute; o No</h1>

	<!--CROWN-->
	{if $person->crown}
		<spam style="color:orange; font-size:30px;" class="emoji">&#x1F451;</spam><br/>
	{/if}

	<!--COLOR BASED ON GENDER-->
	{assign var="color" value="gray"}
	{if $person->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
	{if $person->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

	<!--PICTURE-->
	{link href="PIROPAZO PERFIL @{$person->username}" style="text-decoration:none;" caption="
		{img src="{$person->picture_internal}" alt="Picture" width="200" height="200" style="border-radius:10px; border:3px solid {$color};"}
	"}

	<!--TAGS-->
	{if $person->tags}
		<br/>
		{foreach item=tag from=$person->tags}
			<small class="label"><nobr>&nbsp;{$tag}&nbsp;</nobr></small>
		{/foreach}
	{/if}

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
		{if $APRETASTE_ENVIRONMENT eq "web" or $APRETASTE_ENVIRONMENT eq "appnet"}
			{img src="{$person->country|lower}.png" alt="{$person->country}" class="flag"}
		{/if}
		{$person->location}
	</p>

	<!--BUTTONS-->
	{button href="PIROPAZO SINEXT @{$person->username}" class="btn btn-green" caption="&#10004;"}
	{button href="PIROPAZO NONEXT @{$person->username}" class="btn btn-red" caption="&#10007;"}
</td></tr></table>

<style type="text/css">
	.btn { font-size: 50px; margin: 20px 20px 0px 20px; }
	.btn-green { color: green; }
	.btn-red { color: red; }
	.label { background-color:#D9EDF7; color:#757B8F; }
</style>