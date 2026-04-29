#!/bin/sh

# ATENÇÃO: Este é um script de diagnóstico pesado que realiza inúmeras
# requisições de rede (ping, dig, curl). Sua execução pode levar um tempo
# considerável. Se chamado a partir de uma aplicação web (PHP, etc.),
# deve ser executado de forma assíncrona para não bloquear a interface do usuário.
#
#
# Script para teste de protocolos e servicos basicos para Internet
# Homologador de Internet
# Autor: Patrick Brandao <patrickbrandao@gmail.com>
#
# curl 'https://www.expressvpn.com/what-is-my-ip' 2>/dev/null | grep www.google.com/maps | sed 's#.*place.q=##g; s#&.*##g'
#

# NOTA DE SEGURANÇA: Este script realiza parsing de saídas de comandos e APIs externas
# usando ferramentas como sed, grep e cut. Esta é uma prática frágil e potencialmente
# insegura. Para parsing de JSON, o uso da ferramenta 'jq' é fortemente recomendado
# para maior robustez e segurança.


	useragent="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Safari/537.36"
	url4="http://ec2-reachability.amazonaws.com/"
	url6="http://ipv6.ec2-reachability.amazonaws.com/"

	_echo_green(){ /bin/echo -e "\033[0;32m$@\033[0m"; }
	_echo_green_n(){ /bin/echo -ne "\033[0;32m$@\033[0m"; }

	_echo_yellow(){ /bin/echo -e "\033[0;33m$@\033[0m"; }
	_echo_yellow_n(){ /bin/echo -ne "\033[0;33m$@\033[0m"; }

	_echo_cyan(){ /bin/echo -e "\033[0;36m$@\033[0m"; }
	_echo_lighcyan_n(){ /bin/echo -ne "\x1B[96m$@\033[0m"; }

	_echo_red(){ /bin/echo -e "\033[0;31m$@\033[0m"; }
	_echo_red_n(){ /bin/echo -ne "\033[0;31m$@\033[0m"; }

	_echo_blue(){ /bin/echo -e "\033[0;34m$@\033[0m"; }
	_echo_blue_n(){ /bin/echo -ne "\033[0;34m$@\033[0m"; }

	_echo_gray(){ /bin/echo -e "\033[0;90m$@\033[0m"; }
	_echo_gray_n(){ /bin/echo -ne "\033[0;90m$@\033[0m"; }

	_echo_lighpink(){ /bin/echo -e "\x1B[95m$@\033[0m"; }
	_echo_lighpink_n(){ /bin/echo -ne "\x1B[95m$@\033[0m"; }

	_echo_lighwhite(){ /bin/echo -e "\x1B[97m$@\033[0m"; }
	_echo_lighwhite_n(){ /bin/echo -ne "\x1B[97m$@\033[0m"; }

	_echo_success(){ /bin/echo -en "\\033[91G"; /bin/echo -en "\x1B[97m["; /bin/echo -en "\033[1;32m  OK  \033[0m"; /bin/echo -e "\x1B[97m]\033[0m"; }
	_echo_failure(){ /bin/echo -en "\\033[91G"; /bin/echo -en "\033[41m\e[5m"; /bin/echo -en "\033[1;38m\x1B  FAILED \033[0m"; /bin/echo -e "\x1B[97m\033[0m"; }
	_echo_fail(){ /bin/echo -en "\033[41m\e[5m "; /bin/echo -e "\033[1;38m\x1B $1 \033[0m"; }

	_abort(){ echo; _echo_red "ABORTED"; echo; _echo_yellow "$1"; echo; exit "$2"; }
	_http_get(){
		lurl="$1"
		lfile="$2"
		lproto="-${3}"
		[ "$lproto" = "-" -o  "$lproto" = "-x" ] && lproto=""
		curl \
			$lproto \
			--user-agent "$useragent" \
			--retry-delay 2 \
			--max-time 9 \
			--connect-timeout 5 \
			-o "$lfile" \
			$lurl  2>/dev/null 1>/dev/null || return "$?"
		return 0
	}
	_url_test(){
		lurl="$1"
		lproto="$2"
		lproto="-${3}"
		[ "$lproto" = "-" -o  "$lproto" = "-x" ] && lproto=""
		curl \
			$lproto \
			--user-agent "$useragent" \
			--retry-delay 2 \
			--max-time 9 \
			--connect-timeout 5 \
			-o "/dev/null" "$lurl" 2>/dev/null 1>/dev/null || return 1
		return 0
	}

	# Teste de ping IPv4
	_ping_ipv4_test(){
		pip="$1"
		xping=$(ping -c 2 -q "$pip" | grep 'min/avg/max' | cut -f2 -d '=' | cut -f1 -d'/')
		pret="$?"
		#echo "PRET: $pret / [$xping]"
		if [ "$pret" = "0" ]; then
			_echo_blue " OK - Latencia: $(echo "$xping" | xargs)"
			return 0
		else
			_echo_red " FALHOU, sem resposta."
			return 1
		fi
	}
	# Teste de ping IPv6
	_ping_ipv6_test(){
		pip="$1"
		pdev="$2"
		ptg="$pip"
		echo "$pip" | grep -E -q "fe80" && ptg="$pip%$pdev"
		xping=$(ping6 -c 2 -q "$ptg" | grep 'min/avg/max' | cut -f2 -d '=' | cut -f1 -d'/')
		pret="$?"
		#echo "PRET: $pret / [$xping]"
		if [ "$pret" = "0" ]; then
			_echo_blue " OK - Latencia: $xping"
			return 0
		else
			_echo_red " FALHOU, sem resposta."
			return 1
		fi
	}

    # Fping para teste de MTU
	_fping_mtu_test(){
		ldst="$1"
		lpkgsize="$2"
		lcount="$3"
		[ "x$lpkgsize" = "x" ] && lpkgsize=1500
		[ "x$lcount" = "x" ] && lcount=2
		# subtrair cabecalho icmp (8) e IP (20) = 28 bytes
		lsize=$(($lpkgsize-28))
		#echo "PING: $ldst  SIZE: $lpkgsize (icmp size=$lsize)"
		lret=$(fping -M -c $lcount -i 20 -t 500 -b $lsize "$ldst" 2>&1); lsn="$?"        

		# se constar 'is alive' na resposta, houve resposta, bug de -M
		echo "$lret" | grep -E -q 'is alive' && {
			# resposta ok
			return 0
		}
		#echo "LRET: $lret"
		if [ "$lsn" = "0" ]; then
			return 0
		else
			# deu merda
			return "$lsn"
		fi
	}
	# LRET: 200.160.2.3 : xmt/rcv/%loss = 2/0/100%
	# LRET: 200.160.2.3 : xmt/rcv/%loss = 2/2/0%, min/avg/max = 25.3/25.7/26.1	

	_resolv(){
		lfqdn="$1"
		lsrv="$2"
		ltype="$3"
		#dig -u -t a @8.8.8.8 registro.br 
		#rs=$(dig -u -t a +timeout=1 +tries=2 @8.8.8.8 registro.br | sed 's#^;;.*time:.#TIME=#; s#.usec##g' | egrep -v '^;' | egrep -v '^$')
		rs=$(dig -t "$ltype" +timeout=2 +tries=2 @"$lsrv" "$lfqdn" |grep -v 'IN.SOA' |grep -v 'IN.NS' |sed 's#^;;.*time:.#|#; s#.usec##g; s#.*IN.*A.##g; s#.msec##' | grep -E -v '^;' | grep -E -v '^$' | tail -2)
		rs=$(echo "$rs" | xargs)
		ret=$(echo "$rs" | cut -f1 -d'|' | xargs)
		sn=0
		[ "x$ret" = "x" ] && sn=1
		echo "$rs"
		return $sn
	}

	# Obter IP e localizacao do site: maxmind.com
	GEO_MAXMIND=""
	_geoip_maxmind(){
		# curl --referer 'https://www.maxmind.com/en/locate-my-ip-address' 'https://www.maxmind.com/geoip/v2.1/city/me?'
		lproto="$1"
		if [ "$lproto" = "6" -o "$lproto" = "v6" -o "$lproto" = "ipv6" ]; then
			lproto="--ipv6"
		else
			lproto="--ipv4"
		fi
		geo_url="https://www.maxmind.com/geoip/v2.1/city/me?"
		geo_ref="https://www.maxmind.com/en/locate-my-ip-address"

		ret=$(curl "$lproto" --user-agent "$useragent" --referer "$geo_ref" --retry-delay 1 --max-time 3 --connect-timeout 5 "$geo_url" 2>/dev/null)
		stdno="$?"
		if [ "$stdno" = "0" ]; then

			myip=$(echo "$ret" | jq -r '.traits.ip_address // empty')
			asnumber=$(echo "$ret" | jq -r '.traits.autonomous_system_number // empty')
			asname=$(echo "$ret" | jq -r '.traits.autonomous_system_organization // empty' | sed 's/ /_/g')
			network=$(echo "$ret" | jq -r '.traits.network // empty')
			organization=$(echo "$ret" | jq -r '.traits.organization // empty' | sed 's/ /_/g')
			lat=$(echo "$ret" | jq -r '.location.latitude // empty')
			lng=$(echo "$ret" | jq -r '.location.longitude // empty')
			
			xcityname=$(echo "$ret" | jq -r '.city.names["pt-BR"] // .city.names.en // empty' | sed 's/ /_/g')
			xcountryname=$(echo "$ret" | jq -r '.country.names["pt-BR"] // .country.names.en // empty' | sed 's/ /_/g')
			xcountrycode=$(echo "$ret" | jq -r '.country.iso_code // empty')
			
			statecode=$(echo "$ret" | jq -r '.subdivisions[0].iso_code // empty')
			statename=$(echo "$ret" | jq -r '.subdivisions[0].names["pt-BR"] // .subdivisions[0].names.en // empty' | sed 's/ /_/g')

			if [ "$DEBUG" = "yes" ]; then
				echo "[DEBUG-MAXMIND]"
				echo "    myip...........: $myip"
				echo "    asnumber.......: $asnumber"
				echo "    asname.........: $asname"
				echo "    network........: $network"
				echo "    organization...: $organization"
				echo "    lat............: $lat"
				echo "    lng............: $lng"
				echo "    xcityname......: $xcityname"
				echo "    xcountryname...: $xcountryname"
				echo "    xcountrycode...: $xcountrycode"
				echo "    statecode......: $statecode"
				echo "    statename......: $statename"
			fi

			reg="$myip;AS_${asnumber}_-_${asname}_-_$network|$organization|$lat,$lng|${xcityname}_-_${statename}_($statecode)_-_${xcountryname}_($xcountrycode)"
			GEO_MAXMIND="$reg"
			return 0
		else
			return 1
		fi

	}
	#_geoip_maxmind 6; echo "$GEO_MAXMIND"; echo; exit
	#_abort "Encerrado para teste do _geoip_maxmind" 2


	# Obter IP e localizacao do site: myip.com
	GEO_MYIP=""
	_geoip_myip(){
		lproto="$1"
		if [ "$lproto" = "6" -o "$lproto" = "v6" -o "$lproto" = "ipv6" ]; then
			lproto="--ipv6"
		else
			lproto="--ipv4"
		fi
		geo_url="https://api.myip.com"
		ret=$(curl "$lproto" --user-agent "$useragent" --retry-delay 1 --max-time 3 --connect-timeout 5 "$geo_url" 2>/dev/null)
		stdno="$?"
		if [ "$stdno" = "0" ]; then

			#echo; echo "RETORNO: $ret"; echo
			# {"ip":"45.197.24.93","country":"Brazil","cc":"BR"}
			# {"ip":"2804:3dce:3aba::93","country":"Brazil","cc":"BR"}

			myip=$(echo "$ret" | jq -r '.ip // empty')
			geo=$(echo "$ret" | jq -r '.country // empty')
			cc=$(echo "$ret" | jq -r '.cc // empty')

			GEO_MYIP="$myip|$geo|$cc"
			return 0
		else
			return 1
		fi
	}

	# Obter geolocalizacao do IP do site: api.toolbox.paessler.io
	GEO_PAESSLER=""
	_geoip_paessler(){
		lipaddr="$1"
		geo_url="https://api.toolbox.paessler.io/ip/location/$lipaddr"
		ret=$(curl --ipv4 --user-agent "$useragent" --retry-delay 1 --max-time 3 --connect-timeout 5 "$geo_url" 2>/dev/null)
		stdno="$?"
		if [ "$stdno" = "0" ]; then
			myip=$(echo "$ret" | jq -r '.ip // empty')
			xreverse=$(echo "$ret" | jq -r '.reverse_entry // empty')
			xcountry=$(echo "$ret" | jq -r '.country // empty')
			xcname=$(echo "$ret" | jq -r '.country_name // empty' | sed 's/ /_/g')
			xlatitude=$(echo "$ret" | jq -r '.latitude // empty')
			xlongitude=$(echo "$ret" | jq -r '.longitude // empty')
			xcity=$(echo "$ret" | jq -r '.city // empty' | sed 's/ /_/g')
			postal_code=$(echo "$ret" | jq -r '.postal_code // empty')

			gps=""
			[ "x$xlatitude" = "x" -o "x$xlongitude" = "x" ] || gps="$xlatitude,$xlongitude"
			GEO_PAESSLER="$myip;$xreverse|$xcountry|$xcname|$gps|$xcity|$postal_code"
			#echo "RETORNO: $GEO_PAESSLER"
			return 0
		else
			return 1
		fi
	}
	#_geoip_paessler "191.185.39.68"; echo "STDN=$? - $GEO_PAESSLER"; exit 0


	# Obter geolocalizacao do IP do site: api.toolbox.paessler.io
	GEO_TEOHIO=""
	_geoip_teohio(){
		lproto="$1"
		if [ "$lproto" = "6" -o "$lproto" = "v6" -o "$lproto" = "ipv6" ]; then
			lproto="--ipv6"
		else
			lproto="--ipv4"
		fi
		geo_url="https://ip.teoh.io/vpn-detection"
		TEOHIO_OUT=$(mktemp)
		ret=$(curl "$lproto" -o "$TEOHIO_OUT" --user-agent "$useragent" --retry-delay 1 --max-time 3 --connect-timeout 5 "$geo_url" 2>/dev/null 1>/dev/null)
		stdno="$?"

		# <h2>IP Address: 191.185.39.68</h2><b><h2>No VPN/Proxy Detected</h2></b><h3>ISP: CLARO S.A.</h3>		                    	<br>
		# cat /tmp/pt01 | egrep '.h2.IP.Address:'
		# <h2>IP Address: 191.185.39.68</h2><b><h2>No VPN/Proxy Detected</h2></b><h3>ISP: CLARO S.A.</h3>		                    	<br>
		if [ "$stdno" = "0" -a -f "$TEOHIO_OUT" ]; then
			html_content=$(cat "$TEOHIO_OUT")

			# Extrair IP
			myip=$(echo "$html_content" | grep -o 'IP Address: [^<]*' | cut -d' ' -f3)

			# Extrair status de VPN/Proxy
			infoline=$(echo "$html_content" | grep -o '<h2>[^<]*VPN[^<]*</h2>' | sed -e 's/<[^>]*>//g' -e 's/ /_/g')
			[ -z "$infoline" ] && infoline="Status_Desconhecido"

			# Extrair ISP
			ispname=$(echo "$html_content" | grep -o 'ISP: [^<]*' | cut -d' ' -f2- | sed 's/ /_/g')
			[ -z "$ispname" ] && ispname="ISP_Desconhecido"
			
			GEO_TEOHIO="$myip;$infoline|$ispname"
			rm -f "$TEOHIO_OUT"
			return 0
		else
			return 1
		fi
	}
	#_geoip_teohio; echo "STDN=$? - $GEO_TEOHIO"; exit 0



	# imprimir informacoes tabeuladas de string geoip dividida por pipe
	_gprint(){
		gi="$1"
		glist=$(echo "$gi" | sed 's#|# #g')
		for it in $glist; do
			x=$(echo $it | sed 's#_# #g')
			_echo_blue "    $x"
		done
	}
	_wprint(){
		gi="$1"
		glist=$(echo "$gi" | sed 's#|# #g')
		for it in $glist; do
			x=$(echo $it | sed 's#_# #g')
			_echo_lighwhite "    $x"
		done
	}

	# isolar servidores DNSs locais
	DNSLOCAL4=""
	DNSLOCAL6=""
	_get_local_dns(){
		ldnslocals=$(cat /etc/resolv.conf | grep -E -v '^#' | grep nameserver | awk '{print $2" "$3" "$4}' | sed 's#,# #g')
		ldns4=""
		ldns6=""
		for ldns in $ldnslocals; do
			echo "$ldns" | grep -E -q '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' && ldns4="$ldns4 $ldns"
			echo "$ldns" | grep -E -q '^[0-9]+:'                         && ldns6="$ldns6 $ldns"
		done
		# remover repetidos
		DNSLOCAL4=""
		for lx4 in $ldns4; do
			# ja registrado como publico
			echo "$DNS4" | grep -E -q "$lx4" && continue
			DNSLOCAL4="$DNSLOCAL4 $lx4"
		done
		DNSLOCAL6=""
		for lx6 in $ldns6; do
			# ja registrado como publico
			echo "$DNS6" | grep -E -q "$lx6" && continue
			DNSLOCAL6="$DNSLOCAL6 $lx6"
		done
	}

	_get_mtu_list(){
		lmtulist="$MAXMTU"
		imtu="$MAXMTU"
		[ "$imtu" -ge 9000 ] && lmtulist="$lmtulist 9000 8900 8800 8700 8600 8700 8600 8500 8400 8300 8200 8100"
		[ "$imtu" -gt 8000 ] && lmtulist="$lmtulist 8000 7000 6000 5000 4000 3000 2000"
		[ "$imtu" -gt 1600 ] && lmtulist="$lmtulist 1600 1580 1570 1560 1550 1540 1530 1520 1518 1516 1514 1512 1510 1508 1506 1504 1502"
		[ "$imtu" -ge "1500" ] && lmtulist="$lmtulist 150"
		[ "$imtu" -le "1492" ] && {
			lmtulist="1492 1490 1488 1486 1484 1482 1480 1470 1460 1450 1440 1430 1420 1410 1400 1300 1200 1100 1000"
			echo $lmtulist
			return 0
		}
		# completar tamanhos pequenos (ppp, pppoe, vpns)
		lmtulist="$lmtulist 1498 1496 1494 1492 1490 1488 1486 1484 1482 1480 1470 1460 1450 1440 1430 1420 1410 1400 1300 1200 1100 1000"
		# anti repetidor de numeros
		lmtulist=$(for n in $lmtulist; do echo $n; done | sort -unr)
		echo $lmtulist
	}

#----------------------------------------------------------------------------------- LISTs

	DNSNAMES="google.com facebook.com registro.br netflix.com youtube.com yahoo.com"
	WEBSITES="www.google.com.br www.facebook.com www.registro.br www.uol.com.br www.globo.com"

	PINGLIST4="
		200.160.2.3
		8.8.8.8
		9.9.9.9
	"
	PINGLIST6="
		2001:12ff:0:2::3
		2001:4860:4860::8888
		2620:fe::fe
	"

    DNS4="
		cisco_opendns/208.67.222.222,208.67.220.220
		cloud_flare/1.1.1.1,1.0.0.1
		google/8.8.8.8,8.8.4.4
		level3/209.244.0.3,209.244.0.4,4.2.2.1,4.2.2.2,4.2.2.3,4.2.2.4,4.2.2.5,4.2.2.6
		gigadns/189.38.95.95,189.38.95.96
		quad9/9.9.9.9,149.112.112.112
		hurricane/74.82.42.42
    "
	#	dyndns/216.146.35.35,216.146.36.36
	#	verisign/64.6.64.6,64.6.65.6
	#	baidu/180.76.76.76
	#	dnswatch/84.200.69.80,84.200.70.40
	#	dns_filter/103.247.36.36,103.247.37.37
	#	cleanbrowsing/185.228.168.9,185.228.168.10,185.228.168.168,185.228.169.168,185.228.169.11,185.228.169.9
	#	nextdns/45.90.28.233,45.90.30.233
	#	neustar/156.154.70.5,156.154.71.5
	#	comodo/8.26.56.26,8.20.247.20,8.26.56.10,8.20.247.10

    DNS6="
		cisco_opendns/2620:119:35::35,2620:119:53::53,2620:0:ccc::2,2620:0:ccd::2
		cloud_flare/2606:4700:4700::1111,2606:4700:4700::1001
		google/2001:4860:4860::8888,2001:4860:4860::8844
		gigadns/2804:10:10::10,2804:10:10::20
		quad9/2620:fe::fe,2620:fe::9
		hurricane/2001:470:20::2
    "
	#	verisign/2620:74:1b::1:1,2620:74:1c::2:2
	#	cleanbrowsing/2a0d:2a00:1::,2a0d:2a00:2::,2a0d:2a00:1::1,2a0d:2a00:2::1,2a0d:2a00:1::2,2a0d:2a00:2::2
	#	nextdns/2a07:a8c0::4c:2cbb,2a07:a8c1::4c:2cbb
	#	baidu/2400:da00::6666
	#	dnswhatch/2001:1608:10:25::1c04:b12f,2001:1608:10:25::9249:d69b

	MAXMTU=1500

#----------------------------------------------------------------------------------- Ajuda

	_help(){
		echo ""
		echo "$ME (argumentos)"
		echo ""
		echo "   -all                Ativar todos os testes"
		echo "   -all4               Ativar todos os testes em ipv4 apenas"
		echo "   -all6               Ativar todos os testes em ipv6 apenas"
		echo
		echo "   -aws                Ativar testes de IPs Amazon AWS"
		echo "   -aws4               Ativar testes de IPs Amazon AWS em IPv4 apenas"
		echo "   -aws6               Ativar testes de IPs Amazon AWS em IPv6 apenas"
		echo
		echo "   -ping               Ativar testes de ping ICMP"
		echo "   -ping4              Ativar testes de ping ICMP em IPv4 apenas"
		echo "   -ping6              Ativar testes de ping ICMP em IPv6 apenas"
		echo ""
		echo "   -dns                Ativar testes de DNS"
		echo "   -dns4               Ativar testes de DNS em IPv4 apenas"
		echo "   -dns6               Ativar testes de DNS em IPv6 apenas"
		echo ""
		echo "   -ntp                Ativar testes de NTP"
		echo "   -ntp4               Ativar testes de NTP em IPv4 apenas"
		echo "   -ntp6               Ativar testes de NTP em IPv6 apenas"
		echo ""
		echo "   -geo                Ativar testes de GeoIP"
		echo "   -geo4               Ativar testes de GeoIP em IPv4 apenas"
		echo "   -geo6               Ativar testes de GeoIP em IPv6 apenas"
		echo ""
		echo "   -rev                Ativar testes de Reverso"
		echo "   -rev4               Ativar testes de Reverso em IPv4 apenas"
		echo "   -rev6               Ativar testes de Reverso em IPv6 apenas"
		echo ""
		echo "   -mtu                Ativar testes de MTU usando ICMP"
		echo "   -mtu4               Ativar testes de MTU usando ICMP em IPv4 apenas"
		echo "   -mtu6               Ativar testes de MTU usando ICMP em IPv4 apenas"
		echo "   -pppoe              Ativar testes de MTU apartir 1492 bytes para baixo"
		echo "   -jumboframe         Ativar testes de MTU apartir 9000 bytes para baixo"
		echo ""
		echo "   src4:SOURCE_IPV4    Especificar IPv4 de origem para testes"
		echo "   src6:SOURCE_IPV6    Especificar IPv4 de origem para testes"
		echo ""
		exit 1
	}

	_debug(){
		[ "$DEBUG" = "yes" ] && {
			/bin/echo -ne "\033[0;90mDEBUG: \033[0m"; echo "$1"
		}
	}

	# Limpar arquivo de erros
	ERRFILE=/tmp/itest.err
	echo -n > $ERRFILE
	# Registrar erros
	_error(){
		lerr="$1"
		echo "$(date "+%Y-%m-%d %T") $lerr" >> $ERRFILE
	}

	# Relatorio de encerramento
	_final_report(){
		tc=$(cat $ERRFILE | wc -l)
		echo
		if [ "$tc" = "0" ]; then
			_echo_green "Concluido - Nenhum erro encontrado"
			errno=0
		else
			_echo_yellow "Concluido - ERROS ENCONTRADOS - Total de erros: $tc"
			cat $ERRFILE | while read error; do
				_echo_red "   $error"
			done
			errno=101
		fi
		echo
		exit $errno
	}

	_install(){
		apt-get -y update
		apt-get -y install rsync iproute2 tcpdump mtr wget whois curl fping bind9utils dnsutils psmisc jq
		exit 0
	}


#----------------------------------------------------------------------------------- ARGs

	# testes
	testgeo4=no
	testgeo6=no
	testdns4=no
	testdns6=no
	testping4=no
	testping6=no
	testmtu4=no
	testmtu6=no
	testaws4=no
	testaws6=no
	args="$@"
	ME="$0"
	DEBUG=no

	_options_enable_all(){
		testaws4=yes; testaws6=yes; testping4=yes; testping6=yes; testgeo4=yes testgeo6=yes; testdns4=yes; testdns6=yes; testmtu4=yes; testmtu6=yes;
	}

	# Padrao: ativar tudo
	[ "x$args" = "x" ] && args="all"
	_debug "ME=$ME"
	_debug "args=$args"
	havearg=0
	for arg in $args; do
		_debug "CHECK ARG: $arg"
		[ "$arg" = "help" -o "$arg" = "-help" -o "$arg" = "-h" -o "$arg" = "h" -o "$arg" = "?" ] && _help

		[ "$arg" = "install" -o "$arg" = "-install" -o "$arg" = "-i" ] && _install

		[ "$arg" = "-all" -o  "$arg" = "all" ] && { _options_enable_all; havearg=1; }
		[ "$arg" = "-all4" -o  "$arg" = "all4" ] && { testaws4=yes; testaws6=no; testping4=yes; testping6=no; testgeo4=yes; testdns4=yes; testdns6=no; testdns4=yes; testdns6=no; havearg=1; }
		[ "$arg" = "-all6" -o  "$arg" = "all6" ] && { testaws4=no; testaws6=yes; testping4=no; testping6=yes; testgeo4=no; testdns4=no; testdns6=yes; testdns4=no; testdns6=yes; havearg=1; }

		[ "$arg" = "ping" -o "$arg" = "-ping" -o "$arg" = "icmp" -o "$arg" = "-icmp" ] && { testping4=yes; testping6=yes; havearg=1; }
		[ "$arg" = "ping4" -o  "$arg" = "-ping4" -o "$arg" = "icmp4" -o "$arg" = "-icmp4" ] && { testping4=yes; havearg=1; }
		[ "$arg" = "ping6" -o  "$arg" = "-ping6" -o "$arg" = "icmp6" -o "$arg" = "-icmp6" ] && { testping6=yes; havearg=1; }

		[ "$arg" = "dns" -o "$arg" = "-dns" ] && { testdns4=yes; testdns6=yes; havearg=1; }
		[ "$arg" = "dns4" -o  "$arg" = "-dns4" ] && { testdns4=yes; havearg=1; }
		[ "$arg" = "dns6" -o  "$arg" = "-dns6" ] && { testdns6=yes; havearg=1; }

		[ "$arg" = "geo" -o "$arg" = "-geo" ] && { testgeo4=yes; testgeo6=yes; havearg=1; }
		[ "$arg" = "geo4" -o  "$arg" = "-geo4" ] && { testgeo4=yes; havearg=1; }
		[ "$arg" = "geo6" -o  "$arg" = "-geo6" ] && { testgeo6=yes; havearg=1; }

		[ "$arg" = "aws" -o "$arg" = "-aws" ] && { testaws4=yes; testaws6=yes; havearg=1; }
		[ "$arg" = "aws4" -o "$arg" = "-aws4" ] && { testaws4=yes; testaws6=no; havearg=1; }
		[ "$arg" = "aws6" -o "$arg" = "-aws6" ] && { testaws4=no; testaws6=yes; havearg=1; }

		[ "$arg" = "mtu" -o "$arg" = "-mtu" ] && { testmtu4=yes; testmtu6=yes; havearg=1; }
		[ "$arg" = "mtu4" -o "$arg" = "-mtu4" ] && { testmtu4=yes; testmtu6=no; havearg=1; }
		[ "$arg" = "mtu6" -o "$arg" = "-mtu6" ] && { testmtu4=no; testmtu6=yes; havearg=1; }

		[ "$arg" = "jumboframe" -o "$arg" = "-jumboframe" -o "$arg" = "-jumbo" -o "$arg" = "jumbo" ] && { MAXMTU=9000; }
		[ "$arg" = "ppp" -o "$arg" = "-ppp" -o "$arg" = "-pppoe" -o "$arg" = "pppoe" ] && { MAXMTU=1492; testmtu4=yes; testmtu6=yes; }

		[ "$arg" = "-d" -o  "$arg" = "debug" ] && { DEBUG=yes; }
	done

	# na ausencia de argumentos de comando, ativar todos
	[ "$havearg" = "0" ] && _options_enable_all


	_debug "SETs:"
	_debug "  testgeo4=$testgeo4"
	_debug "  testgeo6=$testgeo6"
	_debug "  testdns4=$testdns4"
	_debug "  testdns6=$testdns6"
	_debug "  testping4=$testping4"
	_debug "  testping6=$testping6"
	_debug "  testaws4=$testaws4"
	_debug "  testaws6=$testaws6"
	_debug "  testmtu4=$testmtu4"
	_debug "  testmtu6=$testmtu6"
	_debug "  args=$args"

	# v6 internet required?
	usev4=no; [ "$testgeo4" = "yes" -o "$testdns4" = "yes" -o "$testping4" = "yes" -o "$testaws4" = "yes" -o "$testmtu4" = "yes" ] && usev4=yes
	usev6=no; [ "$testgeo6" = "yes" -o "$testdns6" = "yes" -o "$testping6" = "yes" -o "$testaws6" = "yes" -o "$testmtu6" = "yes" ] && usev6=yes

	# nenhum teste para fazer
	[ "$usev4" = "no" -a "$usev6" = "no" ] && _abort "Nenhum teste ativo, use: $ME help" 2

	# se ipv6 estiver ausente e houver opcoes de ipv6, abortar teste
	LOCALIPV4=$(ip -o -4 ro get 99.88.77.66  | sed 's#.*src.##g' | awk '{print $1}' | xargs)
	LOCALIPV6=$(ip -o -6 ro get 2001:ffff::1  | sed 's#.*src.##g' | awk '{print $1}' | grep -E -i -v 'fe80' | xargs)

	_echo_green_n "Local IPv4....................: "; _echo_yellow "$LOCALIPV4"
	_echo_green_n "Local IPv6....................: "; _echo_yellow "$LOCALIPV6"

#----------------------------------------------------------------------------------- Softwares dependencies

	# DNS - sem dig nao da pra fazer
	if [ "$testdns4" = "yes" -o  "$testdns6" = "yes" ]; then
		digpath=$(which dig 2>/dev/null)
		[ "x$digpath" = "x" ] && {
			# nao tem fping, abortar
			_abort "Incapaz de realizar teste de DNS, software ausente: dig" 101
		}
	fi

	# ICMP - sem ping e ping6 nao da pra fazer
	if [ "$testping4" = "yes" -o  "$testping6" = "yes" ]; then
		pingpath=$(which ping 2>/dev/null)
		[ "x$pingpath" = "x" ] && {
			# nao tem fping, abortar
			_abort "Incapaz de realizar teste de IMCP IPv4, software ausente: ping" 102
		}
	fi

	# HTTP - sem curl nao da pra fazer
	if [ "$testaws4" = "yes" -o  "$testaws6" = "yes" -o "$testgeo4" = "yes" -o  "$testgeo6" = "yes" ]; then
		curlpath=$(which curl 2>/dev/null)
		[ "x$curlpath" = "x" ] && {
			# nao tem fping, abortar
			_abort "Incapaz de realizar teste de HTTP, software ausente: curl" 103
		}
	fi


	# MTU - sem fping nao da pra fazer
	if [ "$testmtu4" = "yes" -o  "$testmtu6" = "yes" ]; then
		fpingpath=$(which fping 2>/dev/null)
		[ "x$fpingpath" = "x" ] && {
			# nao tem fping, abortar
			_abort "Incapaz de realizar teste de MTU, software ausente: fping" 104
		}
	fi

	# JSON - sem jq nao da pra fazer
	if [ "$testgeo4" = "yes" -o  "$testgeo6" = "yes" ]; then
		jqpath=$(which jq 2>/dev/null)
		[ "x$jqpath" = "x" ] && {
			_abort "Incapaz de realizar parsing de JSON, software ausente: jq" 105
		}
	fi

#----------------------------------------------------------------------------------- Internet Test

	fail4=no
	GATEWAYIPV4=""
	GATEWAYDEV4=""

	fail6=no
	GATEWAYIPV6=""
	GATEWAYDEV6=""

	# ************************************************** Testar Gateway IPv4
	if [ "$usev4" = "yes" ]; then
		_debug "Teste de internet IPv4"

		# verificar presenca de gateway padrao
		# - INTERFACE
		GATEWAYDEV4=$(ip -4 -o ro get 124.108.115.87 2>/dev/null | grep -E 'dev'  | sed 's#.*dev#dev#' | awk '{print $2}')
		_debug "Interface Gateway IPv4.: $GATEWAYDEV4"
		_echo_green "Interface Gateway IPv4........: $GATEWAYDEV4"
		# - IP VIA
		GATEWAYIPV4=$(ip -4 -o ro get 124.108.115.87 2>/dev/null | grep -E 'via'  | sed 's#.*via#via#' | awk '{print $2}')
		_debug "Gateway IPv4 via.......: $GATEWAYIPV4"

		# caso nao tenha gateway 'via', pode haver 'dev' nos casos com PPP
		[ "x$GATEWAYIPV4" = "x" ] && {
			GATEWAYIPV4="$GATEWAYDEV4"
		}

		# Sem gateway IPv4, falha fatal
		if [ "x$GATEWAYIPV4" = "x" ]; then
			_debug "Falhou a deteccao de gateway IPv4"
			_error "Falhou a deteccao de gateway IPv4"
			fail4=yes
		else
			_echo_green "Endereco do Gateway IPv4......: $GATEWAYIPV4"
		fi

	else
		_debug "Teste de internet IPv4 DESATIVADO, nenhum teste de IPv4 a ser realizado"
	fi

	# ************************************************** Testar Gateway IPv6
	if [ "$usev6" = "yes" ]; then
		_debug "Teste de internet IPv6"

		# verificar presenca de gateway padrao
		# - INTERFACE
		GATEWAYDEV6=$(ip -6 -o ro get 2001:4998:1a0::1 2>/dev/null | grep -E 'dev'  | sed 's#.*dev#dev#' | awk '{print $2}')
		_debug "Interface Gateway IPv6.: $GATEWAYDEV6"
		_echo_green "Interface Gateway IPv6........: $GATEWAYDEV6"
		# - IP VIA
		GATEWAYIPV6=$(ip -6 -o ro get 2001:4998:1a0::1 2>/dev/null | grep -E 'via'  | sed 's#.*via#via#' | awk '{print $2}')
		_debug "Gateway IPv6 via.......: $GATEWAYIPV6"

		# caso nao tenha gateway 'via', pode haver 'dev' nos casos com PPP
		[ "x$GATEWAYIPV6" = "x" ] && {
			GATEWAYIPV6="$GATEWAYDEV6"
		}

		# Sem gateway IPv6, falha fatal
		if [ "x$GATEWAYIPV6" = "x" ]; then
			_debug "Falhou a deteccao de gateway IPv6"
			_error "Falhou a deteccao de gateway IPv6"
			fail6=yes
		else
			_echo_green "Endereco do Gateway IPv6......: $GATEWAYIPV6"
		fi

	else
		_debug "Teste de internet IPv6 DESATIVADO, nenhum teste de IPv6 a ser realizado"
	fi

	# Se houve falha em ambos, parar script
	if [ "$fail4" = "yes" -a "$fail6" = "yes" ]; then
		_abort "Falha ao obter gateway padrao IPv4 e IPv6 - FALHA DUPLA" 30
	fi

	# Se os testes de IPv4 estiverem ativos e falhou o IPv4, abortar
	if [ "$fail4" = "yes" ]; then
		if [ "$testgeo4" = "yes" -o "$testdns4" = "yes" -o "$testping4" = "yes" -o "$testaws4" = "yes" ]; then
			_abort "Falha ao obter gateway padrao IPv4 - Testes de IPv4 impossiveis de realizar" 31
		fi
	fi
	# Se os testes de IPv6 estiverem ativos e falhou o IPv6, abortar
	if [ "$fail6" = "yes" ]; then
		if [ "$testgeo6" = "yes" -o "$testdns6" = "yes" -o "$testping6" = "yes" -o "$testaws6" = "yes" ]; then
			_abort "Falha ao obter gateway padrao IPv6 - Testes de IPv6 impossiveis de realizar" 32
		fi
	fi


#----------------------------------------------------------------------------------- Ping para o Gateway IPv4

	# * GATEWAY IPv4
	if [ "$testping4" = "yes" ]; then
	 	echo "$GATEWAYIPV4" | grep -E -q "[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+" && {
			_echo_green_n "Ping ICMP para Gateway IPv4...: $GATEWAYIPV4 "
			_ping_ipv4_test "$GATEWAYIPV4"
			[ "$?" = "0" ] || _abort "FALHA em ping ICMP para $GATEWAYIPV4" 33
		}
	fi

#----------------------------------------------------------------------------------- Ping para o Gateway IPv6

	# * GATEWAY IPv6
	if [ "$testping6" = "yes" ]; then
		echo "$GATEWAYIPV6" | grep -E -q ":" && {
			_echo_green_n "Ping ICMP para Gateway IPv6...: $GATEWAYIPV6 "
			_ping_ipv6_test "$GATEWAYIPV6" "$GATEWAYDEV6"
			[ "$?" = "0" ] || _abort "FALHA em ping ICMP para $GATEWAYIPV6" 34
		}
	fi

	#_abort "finalizado para teste" 1

#----------------------------------------------------------------------------------- Ping para o hosts IPv4

	if [ "$testping4" = "yes" ]; then
		for ipv4 in $PINGLIST4; do
			_echo_green_n "Ping ICMP para IPv4...........: $ipv4 "
			_ping_ipv4_test "$ipv4"
			[ "$?" = "0" ] || _error "FALHA em ping ICMP para $ipv4"
		done
	fi

#----------------------------------------------------------------------------------- Ping para o hosts IPv6

	if [ "$testping6" = "yes" ]; then
		for ipv6 in $PINGLIST6; do
			_echo_green_n "Ping ICMP para IPv6...........: $ipv6 "
			_ping_ipv6_test "$ipv6"
			[ "$?" = "0" ] || _error "FALHA em ping ICMP para $ipv6"
		done
	fi

#----------------------------------------------------------------------------------- Teste de MTU IPv4

	if [ "$testmtu4" = "yes" ]; then
		MTULIST4="$PINGLIST4"
		# incluir o gateway apenas se for um ipv4 (no ppp e' ppp0)
		echo "$GATEWAYIPV4" | grep -E -q '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' && MTULIST4="$MTULIST4 $GATEWAYIPV4"
		mlist=$(_get_mtu_list)
		_debug "MTU: lista de teste de enderecos: $MTULIST4"
		_debug "MTU: lista de tamanho de pacotes: $mlist"
		for ipv4 in $MTULIST4; do
			for mtu in $mlist; do
				_echo_green_n "Teste MTU ICMP para IPv4...: $ipv4 - $mtu bytes"
				fmtr=$(_fping_mtu_test "$ipv4" "$mtu"); sn="$?"
				[ "$sn" = "0" ] && {
					_echo_success
					break
				}
				# falhou com mtu
				_echo_yellow_n " falhou com $mtu bytes"
				_echo_failure
				_error "Falha de MTU-ICMP $mtu bytes para $ipv4"
			done
		done
	fi


#----------------------------------------------------------------------------------- Teste de DNS IPv4

	# agregar dns local a lista de testes
	if [ "$testdns4" = "yes" -o "$testdns6" = "yes" ]; then
		_get_local_dns
		[ "$testdns4" = "yes" ] && DNS4="$DNS4 local4/$(echo "$DNSLOCAL4" | sed 's#\ #,#g')"
		[ "$testdns6" = "yes" ] && DNS6="$DNS6 local6/$(echo "$DNSLOCAL6" | sed 's#\ #,#g')"
	fi

	if [ "$testdns4" = "yes" ]; then
		dnserrcount4=0
		for qtype in A AAAA; do
			for dnssrv in $DNS4; do
				dname=$(echo $dnssrv | cut -f1 -d'/')
				dlist=$(echo $dnssrv | cut -f2 -d'/' | sed 's#,# #g')
				for dnsaddr in $dlist; do
					for fqdn in $DNSNAMES; do
						_echo_green_n "[DNS-IPv4] $dname: $dnsaddr ($qtype) > $fqdn - "
						dnsr=$(_resolv "$fqdn" "$dnsaddr" "$qtype"); dnss="$?"
						if [ "$dnss" = "0" ]; then
							# ok
							rp=$(echo "$dnsr" | cut -f1 -d'|')
							lt=$(echo "$dnsr" | cut -f2 -d'|')
							_echo_lighwhite_n "$rp"
							_echo_gray_n " latency "
							_echo_cyan "$lt ms"
						else
							# nao resolveu
							dnserrcount4=$(($dnserrcount4+1))
							_error "FALHOU: [DNS-IPv4] $dname - $dnsaddr - QUERY $qtype - FQDN $fqdn"
							_echo_fail "FALHOU"
						fi
					done
				done
			done
		done
		[ "$dnserrcount4" = "0" ] || {
			_echo_yellow "DNS-IPv4: falhas encontradas, erros: $dnserrcount4"
			_error "ERROS DE DNS-IPv4: $dnserrcount4"
		}
	fi


#----------------------------------------------------------------------------------- Teste de DNS IPv6

	if [ "$testdns6" = "yes" ]; then
		dnserrcount6=0
		for qtype in A AAAA; do
			for dnssrv in $DNS6; do
				dname=$(echo $dnssrv | cut -f1 -d'/')
				dlist=$(echo $dnssrv | cut -f2 -d'/' | sed 's#,# #g')
				for dnsaddr in $dlist; do
					for fqdn in $DNSNAMES; do
						_echo_green_n "[DNS-IPv6] $dname: $dnsaddr ($qtype) > $fqdn - "
						dnsr=$(_resolv "$fqdn" "$dnsaddr" "$qtype"); dnss="$?"
						if [ "$dnss" = "0" ]; then
							# ok
							rp=$(echo "$dnsr" | cut -f1 -d'|')
							lt=$(echo "$dnsr" | cut -f2 -d'|')
							_echo_lighwhite_n "$rp"
							_echo_gray " latency $lt us"
						else
							# nao resolveu
							dnserrcount6=$(($dnserrcount6+1))
							_error "FALHOU: [DNS-IPv6] $dname - $dnsaddr - QUERY $qtype - FQDN $fqdn"
							_echo_fail "FALHOU"
						fi
					done
				done
			done
		done
		[ "$dnserrcount6" = "0" ] || {
			_echo_yellow "DNS-IPv6: falhas encontradas, erros: $dnserrcount6"
			_error "ERROS DE DNS-IPv6: $dnserrcount6"
		}
	fi



#----------------------------------------------------------------------------------- GEOIP

	if [ "$testgeo4" = "yes" -o "$testgeo6" = "yes" ]; then

		# Testar IPv4 e IPv6
		for gtest in ipv4 ipv6; do
			# Se a versao do IP estiver desativada, ignorar
			[ "$gtest" = "ipv4" -a "$testgeo4" = "no" ] && continue
			[ "$gtest" = "ipv6" -a "$testgeo6" = "no" ] && continue
			ipv=IPv4
			[ "$gtest"  = "ipv6" ] && ipv="IPv6"

			tc=0

			# ****** MYIP
			myipa=""
			_debug "Teste de GeoIP para $ipv via MYIP"
			_echo_green_n "Analisando GeoIP para $ipv via MYIP - "

			_geoip_myip "$gtest"; sg="$?"
			if [ "$sg" = "0" ]; then
				# ok
				myipa=$(echo "$GEO_MYIP" | cut -f1 -d'|')
				geodetail=$(echo "$GEO_MYIP" | cut -f2,3 -d'|')
				_echo_lighpink_n "$myipa"
				_echo_success
				_wprint "$geodetail"
				tc=$(($tc+1))
			else
				# falhou
				_echo_failure
				_debug "Falhou a deteccao de GeoIP $ipv via MYIP"
				_error "Falhou a deteccao de GeoIP $ipv via MYIP"
			fi

			# ****** MAXMIND
			myipb=""

			_debug "Teste de GeoIP para $ipv via MaxMind"
			_echo_green_n "Analisando GeoIP para $ipv via MaxMind - "
			_geoip_maxmind "$gtest"; sg="$?"
			if [ "$sg" = "0" ]; then
				# ok
				myipb=$(echo "$GEO_MAXMIND" | cut -f1 -d';')
				geodetail=$(echo "$GEO_MAXMIND" | cut -f2 -d';')
				_echo_lighpink_n "$myipb"
				_echo_success
				_wprint "$geodetail"
				tc=$(($tc+1))
			else
				# falhou
				_echo_failure
				_debug "Falhou a deteccao de GeoIP $ipv via MaxMind"
				_error "Falhou a deteccao de GeoIP $ipv via MaxMind"
			fi


			# Disparidade entre IPs
			ipok=0
			if [ "$tc" = "2" ]; then
				# ambos os testes obteram IP, verificar paridade
				if [ "$myipa" = "$myipb" ]; then
					#_echo_lighcyan_n "GEOIP: IP de origem - "
					#_echo_lighwhite_n "$myipa"
					#_echo_success
					ipok=1
				else
					_error "ERRO de GEOIP - Disparidade entre IPs de origem - $myipa ~ $myipb"
					_echo_yellow_n "GEOIP - Disparidade entre IPs de origem - $myipa ~ $myipb"
					_echo_failure
				fi
			fi

			# TEOH.io
			#- myipc=""
			#- _debug "Teste de GeoIP para $ipv via TEOH.io"
			#- _echo_green_n "Analisando GeoIP para $ipv via TEOH.io - "
			#- _geoip_teohio $gtest; sg="$?"
			#- if [ "$sg" = "0" ]; then
			#- 	# ok
			#- 	myipc=$(echo "$GEO_TEOHIO" | cut -f1 -d';')
			#- 	geodetail=$(echo "$GEO_TEOHIO" | cut -f2 -d';')
			#- 	_echo_lighpink_n "$myipc"
			#- 	_echo_success
			#- 	_wprint "$geodetail"
			#- 	tc=$(($tc+1))
			#- else
			#- 	# falhou
			#- 	_echo_failure
			#- 	_debug "Falhou a deteccao de GeoIP $ipv via TEOH.io"
			#- 	_error "Falhou a deteccao de GeoIP $ipv via TEOH.io"
			#- fi


			# Obter geolocalizacao da API da Pasler (PRTG)
			if [ "$ipok" = "1" ]; then
				_debug "Teste de GeoIP para $ipv via Paessler"
				_echo_green_n "Analisando GeoIP para $ipv via Paessler - "

				_geoip_paessler "$myipa"; sg="$?"
				if [ "$sg" = "0" ]; then
					# ok
					myipb=$(echo "$GEO_PAESSLER" | cut -f1 -d';')
					geodetail=$(echo "$GEO_PAESSLER" | cut -f2 -d';')
					_echo_lighpink_n "$myipb"
					_echo_success
					_wprint "$geodetail"
				else
					# falhou
					_echo_failure
					_debug "Falhou a deteccao de GeoIP $ipv via Paessler"
					_error "Falhou a deteccao de GeoIP $ipv via Paessler"
				fi
			fi

		done

	fi


#----------------------------------------------------------------------------------- IPv4 AWS

	if [ "$testaws4" = "yes" ]; then

		AWS4_OUT=$(mktemp)
		AWS4_URLS=$(mktemp)
		_echo_lighcyan_n "#> Testando IPv4 - $url4 "
		_http_get "$url4" "$AWS4_OUT" "4" || _abort "AWS-IPv4: erro $? ao requisitar url $url4" 14
		_echo_success

		# Isolar URLs
		grep -E 'test.*http' "$AWS4_OUT" | sed 's#.*http://#http://#g' | cut -f1 -d'"' > "$AWS4_URLS"
		aws4total=$(cat "$AWS4_URLS" | wc -l)

		# Testando URLs
		aws4c=0
		cat "$AWS4_URLS" | while read -r url; do
			aws4c=$(($aws4c+1))
			_echo_lighcyan_n "$aws4c/$aws4total $url "
			_url_test "$url" && { _echo_success; continue; }
			_error "FALHOU: HTTP-AWS-IPv4 - $url"
			_echo_failure
		done
		rm -f "$AWS4_OUT" "$AWS4_URLS"
		echo
	fi

#----------------------------------------------------------------------------------- IPv6 AWS

	if [ "$testaws6" = "yes" ]; then

		AWS6_OUT=$(mktemp)
		AWS6_URLS=$(mktemp)
		_echo_lighcyan_n "#> Testando IPv6 - $url6 "
		_http_get "$url6" "$AWS6_OUT" "x" || _abort "AWS-IPv6: erro $? ao requisitar url $url6" 16
		_echo_success

		# Isolar URLs
		grep -E 'test.*http' "$AWS6_OUT" | sed 's#.*http://#http://#g' | cut -f1 -d'"' > "$AWS6_URLS"
		aws6total=$(cat "$AWS6_URLS" | wc -l)

		# Testando URLs
		aws6c=0
		cat "$AWS6_URLS" | while read -r url; do
			aws6c=$(($aws6c+1))
			_echo_lighcyan_n "$aws6c/$aws6total $url "
			_url_test "$url" && { _echo_success; continue; }
			_error "FALHOU: HTTP-AWS-IPv6 - $url"
			_echo_failure
		done
		rm -f "$AWS6_OUT" "$AWS6_URLS"
		echo
	fi

#-----------------------------------------------------------------------------------
#-----------------------------------------------------------------------------------


	_final_report


#-----------------------------------------------------------------------------------
#-----------------------------------------------------------------------------------
#-----------------------------------------------------------------------------------
#-----------------------------------------------------------------------------------
#-----------------------------------------------------------------------------------

exit


# Teste MSS com PPPoE Client:

  150  wget -O /dev/null 'http://srv01.udns.com.br/64k.zip'
  151  ip ro del default;  ip route add default dev ppp0 advmss 1440
  152  wget -O /dev/null 'http://srv01.udns.com.br/64k.zip'
  153  ip ro del default;  ip route add default dev ppp0 advmss 1418
  154  wget -O /dev/null 'http://srv01.udns.com.br/64k.zip'
  155  ip ro del default;  ip route add default dev ppp0 advmss 1421
  156  wget -O /dev/null 'http://srv01.udns.com.br/64k.zip'
  157  ip ro del default;  ip route add default dev ppp0 advmss 1416
  158  wget -O /dev/null 'http://srv01.udns.com.br/64k.zip'
  159  ip ro del default;  ip route add default dev ppp0 advmss 1416
  160  ip ro del default;  ip route add default dev ppp0 advmss 1416
  161  wget -O /dev/null 'http://srv01.udns.com.br/64k.zip'
  162  ip ro del default;  ip route add default dev ppp0 advmss 1421
  163  wget -O /dev/null 'http://srv01.udns.com.br/64k.zip'
  164  wget -O /dev/null http://168.197.223.251
  165  ip a
  166  ip ro del default;  ip route add default dev ppp0 advmss 1460
  167  wget -O /dev/null http://168.197.223.251
  168  ip a
  169  history 
