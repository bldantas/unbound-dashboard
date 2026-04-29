#!/bin/bash
# Script para baixar root.hints (Servidores Raíz) para o Unbound

DEST_FILE="/etc/unbound/root.hints"
URL="https://www.internic.net/domain/named.cache"

# Tenta baixar o arquivo
wget -qO "$DEST_FILE.tmp" "$URL"

if [ $? -eq 0 ]; then
    # Sucesso no download, move para o destino final
    mv "$DEST_FILE.tmp" "$DEST_FILE"
    chmod 644 "$DEST_FILE"
    echo "Root hints (named.cache) atualizado com sucesso em $DEST_FILE."
    
    # Recarrega o Unbound se ele estiver rodando (utilizando o unbound-control local ou via systemctl)
    systemctl is-active --quiet unbound && unbound-control reload >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "Serviço Unbound recarregou o cache dos ROOT Servers com sucesso!"
    else
        echo "Root hints baixado, mas não foi possível aplicar (unbound offline ou controle indisponível)."
    fi
    exit 0
else
    # Falha
    rm -f "$DEST_FILE.tmp"
    echo "Falha ao baixar os root servers da Internic."
    exit 1
fi
