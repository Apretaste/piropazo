{include file="../includes/appmenu.tpl"}

<h1>Charla con @{$username}</h1>

{if not $chats}
	<p>Usted no ha chateado con @{$username} anteriormente. Presione el bot&oacute;n a continuaci&oacute;n para enviarle una primera nota.</p>
{else}
	<table width="100%" cellspacing="0" cellpadding="5" border=0>
	{foreach item=item from=$chats}
		<tr {if $username == $item->username}bgcolor="#F2F2F2"{/if}>
			{assign var="color" value="black"}
			{if $item->profile->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
			{if $item->profile->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

			<td width="1" valign="top">
				{if {$APRETASTE_ENVIRONMENT} eq "web"}
					<img class="profile-small" src="{$item->picture}" title="@{$item->profile->username}" alt="@{$item->profile->username}"/>
				{/if}
			</td>
			<td>
				<span style="color:grey; font-size:10px;">
					{link href="PIZARRA PERFIL @{$item->profile->username}" caption="@{$item->profile->username}" style="color:{$color};"}
					{$item->sent|date_format:"%e/%m/%Y %I:%M %p"}
				</span><br/>
				<span style="color:{if $username == $item->username}#000000{else}#000066{/if};">{$item->text}</span>
			</td>
		</tr>
	{/foreach}
	</table>
{/if}

{space15}

<center>
	{button href="CHAT @{$username}" caption="Escribir" size="medium" desc="a:Escriba el texto a enviar*" popup="true" wait="false"}
	{button href="PIROPAZO PAREJAS" caption="Parejas" size="medium" color="grey"}
</center>
