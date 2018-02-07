<style type="text/css">
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
	.notice{
		background-color:#F2DEDE;
		color:#A94442;
		padding:5px;
		font-size:small;
		margin-bottom: 10px;
	}
	hr{
		border: 0;
		height: 0;
		border-top: 1px solid rgba(0, 0, 0, 0.1);
		border-bottom: 1px solid rgba(255, 255, 255, 0.3);
	}
</style>

{if {$APRETASTE_ENVIRONMENT} eq "app"}
	<table width="100%" cellspacing="10">
		<tr align="center" style="background-color:#F2F2F2;">
			<td>{link href="PIROPAZO" caption="💕" style="color:#5DBB48; text-decoration:none;"}</td>
			<td>{link href="PIROPAZO PAREJAS" caption="💘" style="color:#5DBB48; text-decoration:none;"}</td>
			<td>{link href="PIROPAZO PERFIL" caption="👤" style="color:#5DBB48; text-decoration:none;"}</td>
			<td>{link href="PIROPAZO TIENDA" caption="💰" style="color:#5DBB48; text-decoration:none;"}</td>
		</tr>
	</table>
	{space10}
{/if}
