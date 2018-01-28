<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<style type="text/css">
			@font-face {
				font-family: "emoji";
				src: url('/fonts/seguiemj.ttf') format("truetype");
			}
			@media only screen and (max-width: 600px) {
				#container {
					width: 100%;
				}
			}
			@media only screen and (max-width: 480px) {
				.button {
					display: block !important;
				}
				.button a {
					display: block !important;
					font-size: 18px !important; width: 100% !important;
					max-width: 600px !important;
				}
				.section {
					width: 100%;
					margin: 2px 0px;
					display: block;
				}
				.phone-block {
					display: block;
				}
			}
			body{
				font-family: Arial;
			}
			h1{
				color: #5DBB48;
				text-transform: uppercase;
				font-size: 22px;
				text-align: center;
				font-weight: normal;
			}
			h2{
				color: #5DBB48;
				text-transform: uppercase;
				font-size: 16px;
				margin-top: 30px;
				font-weight: normal;
			}
			hr {
				border: 0;
				height: 0;
				border-top: 1px solid rgba(0, 0, 0, 0.1);
				border-bottom: 1px solid rgba(255, 255, 255, 0.3);
			}
			.rounded{
				border-radius: 10px;
				background: white;
				padding: 10px;
			}
			.emoji{
				font-family: emoji;
			}
			.flag{
				vertical-align:middle;
				width:20px;
				margin-right:3px;
			}
			.notice{
				background-color:#F2DEDE;
				color:#A94442;
				padding:5px;
				font-size:small;
				margin-bottom: 10px;
			}
		</style>
	</head>
	<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="font-family:Arial;">
		<center>
			<table id="container" bgcolor="#F2F2F2" border="0" cellpadding="0" cellspacing="0" valign="top" align="center" width="600">
				<tr>
					<!--logo-->
					<td valign="middle" style="padding-left:25px;">
						{link href="PIROPAZO" caption="&hearts;" style="color:#5DBB48; font-size:40px; text-decoration: none;"}
					</td>

					<!--notifications & profile-->
					<td align="right" class="emoji" valign="middle" style="padding:10px 25px 0px 0px;">
						{link href="PIROPAZO" caption="&#x1F495;" title="Personas" style="color:#5DBB48; text-decoration: none;"}&nbsp;&nbsp;&nbsp;
						{link href="PIROPAZO PAREJAS" caption="&#x1F498;" title="Parejas" style="color:#5DBB48; text-decoration:none; font-size:18px;"}&nbsp;&nbsp;
						{link href="PERFIL EDITAR" caption="&#128100;" title="Perfil" style="color:#5DBB48; text-decoration: none;"}&nbsp;&nbsp;&nbsp;
						{if $num_notifications}{assign var="bell" value="🔔"}{assign var="color" value="#5DBB48"}{else}{assign var="bell" value="🔕"}{assign var="color" value="grey"}{/if}
						{link href="NOTIFICACIONES piropazo chat" caption="{$bell}" title="Notificacioens" style="color:{$color}; text-decoration: none;"}
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
