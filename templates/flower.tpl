{space10}
<center>
	<!--COLOR BASED ON GENDER-->
	{assign var="color" value="gray"}
	{if $person->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
	{if $person->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

	<!--PICTURE-->
	<span style="color:green; font-size:80px; vertical-align:top;" class="emoji">
		&#x1F339;
		{if $person->picture}
			{img src="{$person->picture_internal}" alt="Picture" width="80" height="80" style="border-radius:100px; border:3px solid {$color};"}
		{else}
			{noimage width="200" height="200" text="Tristemente ...<br/>Sin foto de perfil :'-("}
		{/if}
	</span>

	<p>@{$person->username} le ha mandado una flor porque ha visto algo especial en t&iacute; y quisiera conocerte mejor.</p>

	<!--MESSAGE-->
	{if $message}
		<p><b>{$message}</b></p>
	{/if}

	<p><small>&iquest;Te gustar&iacute;a chatear con @{$person->username}?</small></p>
	{button href="PIROPAZO SIMATCHES @{$person->username}" caption="Decir Si" color="green" size="small"}
	{button href="PIROPAZO NOMATCHES @{$person->username}" caption="Decir No" color="red" size="small"}
	{button href="PIROPAZO PERFIL @{$person->username}" caption="Ver perfil" size="small" color="grey"}
</center>
