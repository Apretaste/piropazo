{include file="../includes/appmenu.tpl"}

<h1>Tienda de Piropazo</h1>
<p>En nuestra tienda encontrar&aacute; flores y coronas que le har&aacute;n m&aacute;s sencillo el arduo proceso de encontrar a su media naranja.</p>

<table width="100%" cellspacing="0" cellpadding="3">
	<tr>
		<td align="left" bgcolor="#FCF8E3"><small>Usted tiene <b>&sect;{$credit}</b> de cr&eacute;dito.</small></td>
		<td align="right" bgcolor="#FCF8E3" width="10">
			<a href="https://apretaste.com/topup?email={$email}" target="_blank" class="btn-ap">Recargar</a>
		</td>
	</tr>
</table>

{space10}

<table width="100%" style="font-size:small;">
	<tr>
		<td align="center"><span style="color:green; font-size:30px;" class="emoji">&#x1F339;</span></td>
		<td>Regale una flor para atraer su atenci&oacute;n y agregar una semana al tiempo de espera</td>
		<td>{button href="CREDITO COMPRAR FLOWER" caption="Pagar &sect;0.5" size="small"}</td>
	</tr>
	<tr><td colspan="3"><hr/></td></tr>
	<tr>
		<td align="center"><span style="color:orange; font-size:30px;" class="emoji">&#x1F451;</span></td>
		<td>Una corona aparecer&aacute; en su cabeza por tres d&iacute;as, haciendo que su perfil se muestre con mucha mayor frecuencia</td>
		<td>{button href="CREDITO COMPRAR CROWN" caption="Pagar &sect;2" size="small"}</td>
	</tr>
	<tr><td colspan="3"><hr/></td></tr>
	<tr>
		<td align="center">
			<span style="color:green; font-size:15px;" class="emoji">&#x1F339;</span>
			<span style="color:orange; font-size:15px;" class="emoji">&#x1F451;</span>
		</td>
		<td>7 flores y 2 coronas, la oferta perfecta para que empieces a enlazar corazones como todo un profesional</td>
		<td>{button href="CREDITO COMPRAR PACK_ONE" caption="Pagar &sect;5" size="small"}</td>
	</tr>
	<tr><td colspan="3"><hr/></td></tr>
	<tr>
		<td align="center">
			<span style="color:green; font-size:15px;" class="emoji">&#x1F339;</span>
			<span style="color:orange; font-size:15px;" class="emoji">&#x1F451;</span>
		</td>
		<td>15 flores y 4 coronas. Saca el mayor beneficio posible de Piropazo y conviertete en todo un cyber-Romeo.</td>
		<td>{button href="CREDITO COMPRAR PACK_TWO" caption="Pagar &sect;10" size="small"}</td>
	</tr>
</table>

<style type="text/css">
	hr{
		border: 0;
		height: 0;
		border-top: 1px solid rgba(0, 0, 0, 0.1);
		border-bottom: 1px solid rgba(255, 255, 255, 0.3);
	}

	.btn-ap {
		color:#000000;
		background-color:#E6E6E6;
		border:1px solid #CCCCCC; 
		border-radius:3px;
		display:inline-block; 
		font-family:sans-serif;
		font-size:12px; 
		line-height:20px;
		text-align:center;
		text-decoration:none; 
		width:80px;
	}
</style>
