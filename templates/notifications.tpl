{if $notificactions}
	<h1>Ultimas notificaciones</h1>

	<table width="100%" cellpadding="5" cellspacing="0">
		{foreach from=$notificactions item=notif}
		<tr  {if $notif@iteration is even}bgcolor="#F2F2F2"{/if}>
			<td style="{if $notif->viewed}border-left:5px solid grey;color:gray;{else}border-left:5px solid green;{/if}">
				<small>{$notif->inserted_date|date_format:"%B %e, %Y %l:%M %p"}</small><br/>
				{$notif->text}
			</td>
			<td width="30">
				{if $notif->link !== ''}
				{button caption="Ver" color="grey" size="small" href="{$notif->link}"}
				{/if}
			</td>
		</tr>
		{/foreach}
	</table>
{else}
	<center>
		<p style="font-size:100px; margin:0px;">&#128277;</p>
		<p>no tiene notificaciones por leer</p>
	</center>
{/if}
