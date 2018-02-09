<style type="text/css">
	h1{
		color: #5DBB48;
		text-transform: uppercase;
		font-size: 22px;
		text-align: center;
		font-weight: normal;
	}
	.notice{
		background-color:#F2DEDE;
		color:#A94442;
		padding:5px;
		font-size:small;
		margin-bottom: 10px;
	}
	.online{
		background-color:#74C365;
		font-size:7px;
		padding:2px;
		border-radius:3px;
		color:white;
		font-weight:bold;
	}
	#menu td{
		background-color:#F2F2F2;
		border-radius:5px;
	}
</style>

{if $APRETASTE_ENVIRONMENT eq "app"}
	<table id="menu" width="100%" cellspacing="10">
		<tr align="center">
			<td>{link href="PIROPAZO" caption="💕" style="color:#5DBB48; text-decoration:none;"}</td>
			<td>{link href="PIROPAZO PAREJAS" caption="💘" style="color:#5DBB48; text-decoration:none;"}</td>
			<td>{link href="PIROPAZO PERFIL" caption="👤" style="color:#5DBB48; text-decoration:none;"}</td>
			<td>{link href="PIROPAZO TIENDA" caption="💰" style="color:#5DBB48; text-decoration:none;"}</td>
		</tr>
	</table>
	{space10}
{/if}
