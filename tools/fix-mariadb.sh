#!/bin/bash

# Configurações - Altere se desejar
DB_USER="unbounddb"
DB_PASS="unbounddash"

echo "--- Iniciando correção do MariaDB para o Unbound Dash ---"

# Executa os comandos SQL via root do sistema (sudo)
sudo mysql -u root <<EOF
-- 1. Remove usuários antigos para evitar conflito 1396
DROP USER IF EXISTS '$DB_USER'@'localhost';
DROP USER IF EXISTS '$DB_USER'@'127.0.0.1';

-- 2. Cria os usuários com o plugin de senha nativo (evita erro 1698)
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';

-- 3. Concede privilégios totais com GRANT OPTION (evita erro 1044 e 1064 no instalador)
GRANT ALL PRIVILEGES ON *.* TO '$DB_USER'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO '$DB_USER'@'127.0.0.1' WITH GRANT OPTION;

-- 4. Aplica as mudanças
FLUSH PRIVILEGES;
EOF

if [ $? -eq 0 ]; then
    echo "--- Sucesso! Usuário '$DB_USER' configurado com a senha '$DB_PASS' ---"
    echo "Agora use esses dados no formulário do navegador."
else
    echo "--- Ocorreu um erro ao executar o script. ---"
fi