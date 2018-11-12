{include file="../includes/appmenu.tpl"}

<table id="newChatOk" class="hidden" width="100%" cellspacing="0" cellpadding="3">
	<tr>
		<td align="left" bgcolor="#DFF0D8"><small>Le hemos enviado su mensaje a @{$username}</small></td>
		<td align="right" bgcolor="#DFF0D8" width="10">
			{button href="PIROPAZO CHAT @{$username}" caption="Recargar" color="grey" size="small"}
		</td>
	</tr>
</table>

<h1>Charla con @{$username}</h1>

{if not $chats}
	<p>Usted no ha chateado con @{$username} anteriormente. Presione el bot&oacute;n a continuaci&oacute;n para enviarle una primera nota.</p>
{else}
	<table width="100%" cellspacing="0" cellpadding="5" border=0>
	{foreach item=item from=$chats}
		<tr {if $username == $item->username}bgcolor="#F2F2F2"{/if}>
			{assign var="color" value="black"}
			{if $item->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
			{if $item->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

			<td width="1" valign="top">
				{if $APRETASTE_ENVIRONMENT eq "web" OR $APRETASTE_ENVIRONMENT eq "appnet"}
					{img src="{$item->picture_internal}" title="@{$item->username}" alt="@{$item->username}" class="profile-small"}
				{/if}
			</td>
			<td>
				<span style="font-size:10px;">
					{link href="PIZARRA PERFIL @{$item->username}" caption="@{$item->username}" style="color:{$color};"}
					<b>&middot;</b>
					<span style="color:grey;">{$item->sent|date_format:"%e/%m/%Y %I:%M %p"}</span>
				</span><br/>
				<span style="color:{if $username == $item->username}#000000{else}#000066{/if};">{$item->text}</span>
			</td>
		</tr>
	{/foreach}
	</table>
{/if}

{space15}

<center>
	{button href="CHAT @{$username}" caption="Escribir" size="medium" desc="a:Escriba el texto a enviar*" popup="true" wait="false" callback="addChat"}
	{button href="PIROPAZO CHAT" caption="Chats" size="medium" color="grey"}
</center>

<script>
	function addChat(values) { document.getElementById('newChatOk').style.display = "table"; }
</script>
