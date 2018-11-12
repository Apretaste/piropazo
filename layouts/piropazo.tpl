<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<style type="text/css">
			{include file="../includes/styles.css"}
		</style>
	</head>
	<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="font-family:Arial;">
		<center>
			<table id="container" bgcolor="#F2F2F2" border="0" cellpadding="0" cellspacing="0" valign="top" align="center" width="600">
				<tr>
					<!--logo-->
					<td valign="middle" align="left">
						<img src="/images/icon.png" style="width:20px; margin-left:20px;" caption="Piropazo Logo"/>
					</td>

					<!--notifications & profile-->
					<td align="right" class="emoji" valign="middle" style="padding:10px 25px 0px 0px;">
						{if $num_notifications}{assign var="bell" value="🔔"}{assign var="color" value="#5DBB48"}{else}{assign var="bell" value="🔕"}{assign var="color" value="grey"}{/if}

						{link href="PIROPAZO" caption="&#x1F495;" title="Citas" style="color:#5DBB48; text-decoration: none;"}&nbsp;&nbsp;&nbsp;
						{link href="PIROPAZO PAREJAS" caption="&#x1F498;" title="Parejas" style="color:#5DBB48; text-decoration:none; font-size:18px;"}&nbsp;&nbsp;
						{link href="PIROPAZO PERFIL" caption="&#128100;" title="Perfil" style="color:#5DBB48; text-decoration: none;"}&nbsp;&nbsp;&nbsp;
						{link href="PIROPAZO CHAT" caption="&#128220;" title="Perfil" style="color:#5DBB48; text-decoration: none;"}&nbsp;&nbsp;&nbsp;
						{link href="PIROPAZO TIENDA" caption="💰" title="Tienda" style="color:#5DBB48; text-decoration: none;"}&nbsp;&nbsp;&nbsp;
						{link href="PIROPAZO NOTIFICACIONES" caption="{$bell}" title="Alertas" style="color:{$color}; text-decoration: none;"}
					</td>
				</tr>

				<!--main section-->
				<tr>
					<td style="padding: 5px 10px 0px 10px;" colspan="3">
						<div class="rounded">
							{include file="$APRETASTE_USER_TEMPLATE"}
						</div>
					</td>
				</tr>

				<!--footer-->
				<tr>
					<td align="center" colspan="3" bgcolor="#F2F2F2" style="padding: 20px 0px;">
						<small>Piropazo &copy; {$smarty.now|date_format:"%Y"}. All rights reserved.</small><br/>
						<small>Descarga nuestra app en <a target="_blank" href="http://piropazo.com">Piropazo.com</a></small>
					</td>
				</tr>
			</table>
		</center>
	</body>
</html>
