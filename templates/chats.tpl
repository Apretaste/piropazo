{include file="../includes/appmenu.tpl"}

<h1>Chats abiertos</h1>

{foreach from=$chats item=chat name=loop} 
	{assign var="color" value="gray"} 
	{if $chat->profile->gender eq "M"}{assign var="color" value="#4863A0"}{/if} 
	{if $chat->profile->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

	<table width="100%" cellpadding="3">
		<tr>
			<!--PICTURE-->
			{if $APRETASTE_ENVIRONMENT eq "web" OR $APP_METHOD eq "http"}
				<td width="32px" rowspan="2">
					{img src="{$chat->profile->picture_internal}" title="@{$chat->profile->username}" alt="@{$chat->profile->username}" style="border:2px solid {$color}; width: 28px; height: 28px; border-radius: 100px;"}
				</td>
			{/if}		

			<td>
				<!--USERNAME, COUNTER, ONLINE-->
				{if $chat->last_note_user neq $myUserId}<span class="unread">&bull;</span>{/if}
				{link href="PIROPAZO PERFIL @{$chat->profile->username}" caption="@{$chat->profile->username}" style="color:{$color};"}
				{if $chat->profile->online}&nbsp;<span class="online">ONLINE</span>{/if}
				<br/>

				<!--LAST NOTE SEND-->
				<span class="date">{$chat->last_sent}</span>
			</td>
			<td align="right">
				{button href="PIROPAZO CHAT @{$chat->profile->username}" caption="Ver" size="small" color="grey"}
			</td>
		</tr>
	</table>

	{if not $smarty.foreach.loop.last}<hr/>{/if}
{/foreach}

<style type="text/css">
	hr{
		border: 0;
		height: 0;
		border-top: 1px solid rgba(0, 0, 0, 0.1);
		border-bottom: 1px solid rgba(255, 255, 255, 0.3);
	}
	.online{
		background-color:#74C365;
		font-size:7px;
		padding:2px;
		border-radius:3px;
		color:white;
		font-weight:bold;
	}
	.date{
		font-size: small;
		color: grey;
	}
	.unread{
		color: red;
		margin: 0px;
	}
</style>