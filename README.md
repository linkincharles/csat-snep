# Dashboard CSAT para SNEP 3

Dashboard em PHP sem framework, compatível com PHP 5.x e MySQL 5.5. Ela consulta a tabela `snep.csat_avaliacoes` e lê as credenciais já existentes em `/etc/odbc.ini`; não há senha no código. Não depende de `mysqlnd`, comum em instalações antigas de PHP.

## Arquivos

```text
csat/
├── index.php
├── config.php
└── assets/
    └── style-v2.css
```

## Instalação

### Instalação rápida em outro SNEP

Copie a pasta `csat` inteira para o servidor e rode, dentro dela:

```bash
sudo sh install.sh
```

O instalador pede o usuário de acesso, usa `admin` como padrão, solicita a senha de modo interativo e configura a proteção do Apache. Ele também cria a tabela `csat_avaliacoes` caso ela ainda não exista, usando as credenciais do `/etc/odbc.ini`. Se a tabela já existir, seus dados permanecem intactos. Para atualizar uma instalação existente, execute novamente o mesmo comando e confirme a atualização.

### Áudios do Asterisk

Antes de copiar a pasta para outro SNEP, inclua nela estes três arquivos:

```text
audio/avaliacao-atendimento.wav
audio/opcao-invalida.wav
audio/agradecimento-avaliacao.wav
```

O instalador os copia automaticamente para `/var/lib/asterisk/sounds/pt_BR/`. Se algum estiver ausente, a instalação avisa qual arquivo falta, mas não sobrescreve nem interrompe a instalação da dashboard.

### Instalação manual

1. Copie a pasta `csat` para o servidor SNEP:

   ```bash
   sudo cp -R csat /var/www/html/
   ```

2. Ajuste dono e permissões para que o Apache possa ler os arquivos:

   ```bash
   sudo chown -R root:www-data /var/www/html/csat
   sudo find /var/www/html/csat -type d -exec chmod 755 {} \;
   sudo find /var/www/html/csat -type f -exec chmod 644 {} \;
   ```

3. Confirme que o módulo `mysqli` está habilitado:

   ```bash
   php -m | grep mysqli
   ```

4. Acesse `http://IP-DO-SNEP/csat/`.

## Proteção por senha

O arquivo `.htaccess` pode ser ignorado por instalações do Apache configuradas com `AllowOverride None`. Por isso, use a configuração pronta `apache/csat-auth.conf`, que não depende de `.htaccess`. O acesso está limitado ao usuário `admin`.

No servidor SNEP, dentro da pasta copiada da dashboard, execute:

```bash
sudo cp apache/csat-auth.conf /etc/apache2/conf-available/csat-auth.conf
sudo htpasswd -c /etc/apache2/csat.htpasswd admin
sudo chown root:www-data /etc/apache2/csat.htpasswd
sudo chmod 640 /etc/apache2/csat.htpasswd
sudo a2enmod auth_basic authn_file
sudo a2enconf csat-auth
sudo service apache2 reload
```

O comando `htpasswd` solicita a senha de forma interativa, portanto ela não fica gravada no histórico do terminal. Depois de recarregar o Apache, abra `http://10.6.20.6/csat/` em uma janela anônima: o navegador deverá pedir usuário e senha antes de mostrar a dashboard.

Em servidores muito antigos onde `a2enconf` não existe, use este comando no lugar de `sudo a2enconf csat-auth`:

```bash
sudo cp apache/csat-auth.conf /etc/apache2/conf.d/csat-auth.conf
```

## Como a conexão funciona

`config.php` lê `/etc/odbc.ini`, procura a primeira seção com `Server` e `Database`, e usa `User`, `Password`, `Database` e `Port` dessa mesma seção. Normalmente a seção que o SNEP usa já contém algo parecido com:

```ini
[snep]
Server = localhost
Database = snep
User = usuario_do_banco
Password = senha_ja_existente
Port = 3306
```

Se existirem várias seções válidas no arquivo, deixe a seção do SNEP como a primeira delas ou ajuste apenas a seleção em `config.php`. A senha continua exclusivamente em `/etc/odbc.ini`.

## Estrutura esperada da tabela

```sql
DESCRIBE snep.csat_avaliacoes;
```

São esperadas as colunas: `telefone`, `ramal`, `atendente`, `fila`, `nota` e `data_avaliacao`. A listagem é paginada para não pesar o servidor; os indicadores usam todas as avaliações que atendem aos filtros.

O arquivo `setup-database.php` é chamado automaticamente pelo instalador. Ele pode ser executado manualmente no servidor com `php setup-database.php` para verificar ou criar a tabela, sem apagar avaliações existentes.

## Filtros e navegação

A tela tem atalhos para **Hoje**, **Esta semana** e **Este mês**, além dos campos manuais de data. A lista pode mostrar 50, 100 ou 200 linhas por página e preserva os filtros ao avançar ou voltar de página. Os indicadores sempre consideram todas as avaliações encontradas pelo filtro, e não somente a página visível.

## Aparência

O botão **Modo escuro** no cabeçalho alterna entre os temas claro e escuro. A escolha é salva somente no navegador do usuário e não altera dados do SNEP ou do banco.

## Segurança

Proteja o endereço com a autenticação já utilizada no Apache ou com uma regra de acesso à rede, pois a tela mostra dados de atendimento. Não exponha `/etc/odbc.ini` pela web: ele permanece fora de `/var/www/html`.
