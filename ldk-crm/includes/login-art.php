<?php
/**
 * Arte do login: redes sociais com a identidade da agência (azul-marinho + ciano). SVG puro, sem imagem externa.
 * As cores vêm do tema (identity.php): --accent (ciano) e --ink (azul-marinho).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_login_art_svg() {
	ob_start();
	?>
<svg class="la" viewBox="0 0 560 600" role="img" aria-label="Ilustração de redes sociais: post, métricas e calendário de conteúdo" preserveAspectRatio="xMidYMid meet">
	<defs>
		<radialGradient id="la-glow" cx="50%" cy="45%" r="55%"><stop offset="0" stop-color="#14E9EC" stop-opacity=".35"/><stop offset="1" stop-color="#14E9EC" stop-opacity="0"/></radialGradient>
		<linearGradient id="la-phone" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0c1f33"/><stop offset="1" stop-color="#050b14"/></linearGradient>
		<linearGradient id="la-art" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#14E9EC"/><stop offset=".55" stop-color="#2f7bff"/><stop offset="1" stop-color="#7c3aed"/></linearGradient>
		<linearGradient id="la-spark" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#14E9EC" stop-opacity=".4"/><stop offset="1" stop-color="#14E9EC"/></linearGradient>
		<filter id="la-sh" x="-30%" y="-30%" width="160%" height="170%"><feDropShadow dx="0" dy="14" stdDeviation="16" flood-color="#000" flood-opacity=".45"/></filter>
		<pattern id="la-dots" width="22" height="22" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.4" fill="#fff" fill-opacity=".10"/></pattern>
	</defs>
	<rect width="560" height="600" fill="url(#la-dots)"/>
	<circle cx="300" cy="290" r="250" fill="url(#la-glow)"/>
	<circle cx="300" cy="290" r="205" fill="none" stroke="#14E9EC" stroke-opacity=".18"/>
	<circle cx="300" cy="290" r="255" fill="none" stroke="#fff" stroke-opacity=".07" stroke-dasharray="4 10"/>

	<!-- celular com um post -->
	<g transform="translate(300 300) rotate(-5)" filter="url(#la-sh)">
		<rect x="-118" y="-232" width="236" height="464" rx="38" fill="url(#la-phone)" stroke="#14E9EC" stroke-opacity=".35" stroke-width="2"/>
		<rect x="-34" y="-222" width="68" height="16" rx="8" fill="#02060c"/>
		<circle cx="-92" cy="-176" r="15" fill="#14E9EC"/><text x="-92" y="-171" text-anchor="middle" class="la-t" font-size="13" font-weight="800" fill="#05080B">LDK</text>
		<rect x="-68" y="-186" width="92" height="9" rx="4.5" fill="#fff" fill-opacity=".85"/><rect x="-68" y="-170" width="58" height="7" rx="3.5" fill="#fff" fill-opacity=".35"/>
		<circle cx="74" cy="-176" r="2.3" fill="#fff" fill-opacity=".7"/><circle cx="84" cy="-176" r="2.3" fill="#fff" fill-opacity=".7"/><circle cx="94" cy="-176" r="2.3" fill="#fff" fill-opacity=".7"/>
		<rect x="-106" y="-148" width="212" height="212" rx="14" fill="url(#la-art)"/>
		<circle cx="42" cy="-88" r="34" fill="#fff" fill-opacity=".22"/><circle cx="42" cy="-88" r="20" fill="#fff" fill-opacity=".35"/>
		<path d="M-106 64 L-40 -20 L6 34 L48 -8 L106 64 Z" fill="#05080B" fill-opacity=".35"/>
		<text x="-84" y="-6" class="la-t" font-size="26" font-weight="800" fill="#fff">Conteúdo</text>
		<text x="-84" y="22" class="la-t" font-size="26" font-weight="800" fill="#05080B">que vende.</text>
		<g fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" transform="translate(-100 84)">
			<path d="M10 23s-9-6-9-12a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 6-9 12-9 12z" fill="#14E9EC" stroke="#14E9EC"/>
			<path d="M44 21a10 10 0 1 0-4 3l-5 3z" transform="translate(0 -7)"/>
			<path d="M80 12 L66 17 L72 20 M80 12 L72 28 L72 20" transform="translate(-4 -4)"/>
			<path d="M196 -4h12a2 2 0 0 1 2 2v26l-8-6-8 6V-2a2 2 0 0 1 2-2z" transform="translate(-6 4)"/>
		</g>
		<rect x="-100" y="124" width="120" height="8" rx="4" fill="#fff" fill-opacity=".85"/><rect x="-100" y="142" width="176" height="7" rx="3.5" fill="#fff" fill-opacity=".35"/><rect x="-100" y="157" width="140" height="7" rx="3.5" fill="#fff" fill-opacity=".35"/>
		<rect x="-100" y="178" width="46" height="16" rx="8" fill="#14E9EC" fill-opacity=".18"/><text x="-77" y="190" text-anchor="middle" class="la-t" font-size="9" font-weight="700" fill="#14E9EC">#marketing</text>
		<rect x="-48" y="178" width="38" height="16" rx="8" fill="#14E9EC" fill-opacity=".18"/><text x="-29" y="190" text-anchor="middle" class="la-t" font-size="9" font-weight="700" fill="#14E9EC">#social</text>
	</g>

	<!-- cartão: alcance -->
	<g transform="translate(40 96) rotate(-4)" filter="url(#la-sh)">
		<rect width="196" height="112" rx="18" fill="#0b1b2d" stroke="#14E9EC" stroke-opacity=".3"/>
		<text x="18" y="30" class="la-t" font-size="11" font-weight="600" fill="#9db4c8">Alcance do mês</text>
		<text x="18" y="62" class="la-t" font-size="28" font-weight="800" fill="#fff">+38%</text>
		<rect x="132" y="16" width="48" height="20" rx="10" fill="#14E9EC" fill-opacity=".18"/><text x="156" y="30" text-anchor="middle" class="la-t" font-size="10" font-weight="700" fill="#14E9EC">↑ 12,4k</text>
		<polyline points="18,98 48,88 76,92 108,72 138,78 168,52 182,44" fill="none" stroke="url(#la-spark)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
		<circle cx="182" cy="44" r="5" fill="#14E9EC"/>
	</g>

	<!-- cartão: curtidas -->
	<g transform="translate(352 392) rotate(3)" filter="url(#la-sh)">
		<rect width="190" height="84" rx="18" fill="#fff"/>
		<circle cx="38" cy="42" r="22" fill="#14E9EC"/><path d="M38 54s-13-8-13-17a7 7 0 0 1 13-4 7 7 0 0 1 13 4c0 9-13 17-13 17z" fill="#05080B"/>
		<text x="72" y="38" class="la-t" font-size="19" font-weight="800" fill="#05080B">2,8 mil</text>
		<text x="72" y="58" class="la-t" font-size="11" font-weight="600" fill="#5a6b7b">curtidas e comentários</text>
	</g>

	<!-- cartão: calendário -->
	<g transform="translate(24 392) rotate(-3)" filter="url(#la-sh)">
		<rect width="176" height="148" rx="18" fill="#0b1b2d" stroke="#fff" stroke-opacity=".12"/>
		<text x="16" y="28" class="la-t" font-size="12" font-weight="700" fill="#fff">Calendário</text>
		<circle cx="150" cy="24" r="5" fill="#ff4d5e"/>
		<g class="la-t" font-size="8" fill="#9db4c8" text-anchor="middle"><text x="25" y="50">D</text><text x="49" y="50">S</text><text x="73" y="50">T</text><text x="97" y="50">Q</text><text x="121" y="50">Q</text><text x="145" y="50">S</text><text x="169" y="50" opacity="0">S</text></g>
		<g fill="#fff" fill-opacity=".16">
			<rect x="14" y="58" width="20" height="20" rx="6"/><rect x="38" y="58" width="20" height="20" rx="6" fill="#14E9EC" fill-opacity="1"/><rect x="62" y="58" width="20" height="20" rx="6"/><rect x="86" y="58" width="20" height="20" rx="6" fill="#14E9EC" fill-opacity="1"/><rect x="110" y="58" width="20" height="20" rx="6"/><rect x="134" y="58" width="20" height="20" rx="6" fill="#ff4d5e" fill-opacity=".85"/>
			<rect x="14" y="84" width="20" height="20" rx="6"/><rect x="38" y="84" width="20" height="20" rx="6"/><rect x="62" y="84" width="20" height="20" rx="6" fill="#14E9EC" fill-opacity="1"/><rect x="86" y="84" width="20" height="20" rx="6"/><rect x="110" y="84" width="20" height="20" rx="6" fill="#14E9EC" fill-opacity="1"/><rect x="134" y="84" width="20" height="20" rx="6"/>
			<rect x="14" y="110" width="20" height="20" rx="6"/><rect x="38" y="110" width="20" height="20" rx="6" fill="#14E9EC" fill-opacity="1"/><rect x="62" y="110" width="20" height="20" rx="6"/><rect x="86" y="110" width="20" height="20" rx="6"/><rect x="110" y="110" width="20" height="20" rx="6"/><rect x="134" y="110" width="20" height="20" rx="6"/>
		</g>
	</g>

	<!-- notificação -->
	<g transform="translate(396 70) rotate(4)" filter="url(#la-sh)">
		<rect width="150" height="54" rx="27" fill="#14E9EC"/>
		<circle cx="27" cy="27" r="15" fill="#05080B"/><path d="M27 35s-8-5-8-11a4.5 4.5 0 0 1 8-2.600 4.500 4.500 0 0 1 8 2.600c0 6-8 11-8 11z" fill="#14E9EC"/>
		<text x="52" y="24" class="la-t" font-size="11" font-weight="800" fill="#05080B">Novo seguidor</text>
		<text x="52" y="40" class="la-t" font-size="10" font-weight="600" fill="#05080B" fill-opacity=".7">@sua.marca</text>
	</g>
</svg>
	<?php
	return ob_get_clean();
}
