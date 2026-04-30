# 📖 Manual de Instalação e Migração — Unbound Dashboard

Este manual descreve o procedimento para exportar o sistema de um servidor para outro e realizar uma instalação limpa e profissional usando as ferramentas de automação integradas.

> [!IMPORTANT]
> Este manual é da linha **v1 (PHP + MariaDB)** em `/var/www/html/unbound-dashboard`.
> A linha **v2 (Python/FastAPI + DuckDB)** está em `/opt/unbound-dashboard` e possui scripts próprios.
> Para build da v2 a partir deste host, use `tools/build-package-v2.sh`.

---

## 📋 Requisitos do Sistema

Para garantir o funcionamento correto, o servidor de destino deve atender aos seguintes requisitos:

*   **Sistema Operacional**: Debian 12 (Bookworm), Debian 13 (Trixie) ou Ubuntu 22.04 LTS ou superior.
*   **Permissões**: Acesso de superusuário (**root**) via `sudo`.
*   **Internet**: Conexão ativa para download automático de dependências (Apache, PHP, MariaDB, Unbound).
*   **Arquitetura**: x86_64 ou ARM64.

---

## 🏗️ Passo 1: Gerando o Pacote (Servidor de Origem)

No servidor onde o dashboard já está rodando ou onde você preparou os arquivos:

1.  Acesse o diretório das ferramentas:
    ```bash
    cd /var/www/html/unbound-dashboard/tools
    ```
2.  Execute o script de build:
    ```bash
    sudo bash build-package.sh
    ```
3.  O script gerará um arquivo compactado (ex: `unbound-dashboard-v1.0.0.tar.gz`) dentro da pasta `tools/`.

### Build da linha v2 no mesmo servidor

Se o objetivo for empacotar a versão nova (v2), execute:

```bash
cd /var/www/html/unbound-dashboard/tools
sudo bash build-package-v2.sh --skip-frontend
```

O artefato da v2 é gerado em `/opt/unbound-dashboard/dist/`.

---

## 🚚 Passo 2: Transferência

Transfira o arquivo `.tar.gz` gerado para o novo servidor usando `scp`, `rsync` ou qualquer outro método de sua preferência:

```bash
scp unbound-dashboard-v1.0.0.tar.gz usuario@novo-servidor:/tmp/
```

---

## ⚙️ Passo 3: Implantação Base (Servidor de Destino)

No novo servidor, siga os comandos abaixo para preparar a infraestrutura:

1.  Extraia o pacote:
    ```bash
    cd /tmp
    tar xzf unbound-dashboard-v1.0.0.tar.gz
    cd unbound-dashboard-v1.0.0
    ```
2.  Inicie o instalador automatizado:
    ```bash
    sudo bash install.sh
    ```

> [!NOTE]
> Este script instalará o servidor web, o banco de dados e o DNS, além de configurar todas as permissões de pastas e regras de `sudo` necessárias.

---

## 🌐 Passo 4: Wizard de Configuração (Navegador)

Após o término do script CLI, o sistema estará pronto para a configuração final via interface gráfica.

1.  Acesse o endereço exibido no terminal (ex: `http://IP-DO-SERVIDOR/unbound-dashboard/setup.php`).
2.  O **Wizard Multi-Step** irá guiá-lo em 5 etapas:
    *   **Ambiente**: Validação automática de extensões e serviços.
    *   **Banco de Dados**: Configuração das senhas do MariaDB e criação do schema.
    *   **DNS**: Diagnóstico informativo do status do Unbound.
    *   **Administrador**: Criação da conta de acesso mestre.
    *   **Finalização**: Verificação final e bloqueio de segurança do setup.

---

## 🚀 Passo 5: Pós-Instalação

Uma vez concluído o wizard no browser, retorne ao terminal do servidor e inicie o serviço de captura de logs:

```bash
sudo systemctl start unbound-log-ingester
```

> [!TIP]
> O serviço de log ingester é responsável por alimentar os gráficos em tempo real do seu dashboard. Certifique-se de que ele esteja sempre rodando: `systemctl status unbound-log-ingester`.

---

## 🛠️ Troubleshooting (Resolução de Problemas)

> [!WARNING]
> **O Wizard não carrega?**
> Verifique se o Apache está rodando: `systemctl status apache2`.
> Certifique-se de que o firewall permite tráfego na porta 80.

> [!IMPORTANT]
> **Erro de Permissão no Database.php?**
> O instalador tenta ajustar as permissões automaticamente, mas se o wizard falhar ao salvar as configurações do banco, execute:
> `sudo chown www-data:www-data /var/www/html/unbound-dashboard/src/Database.php`
> `sudo chmod 660 /var/www/html/unbound-dashboard/src/Database.php`

---

## 🏁 Conclusão

Seu Unbound Dashboard agora está instalado e pronto para monitorar sua rede com segurança e performance máxima!
