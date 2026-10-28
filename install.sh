#!/bin/sh
# Instalador da dashboard CSAT para SNEP / Apache em Debian.
# Uso: sudo sh install.sh

set -e

TARGET_DIR="/var/www/html/csat"
APACHE_CONF_DIR="/etc/apache2/conf-available"
APACHE_CONF_FILE="$APACHE_CONF_DIR/csat-auth.conf"
PASSWORD_FILE="/etc/apache2/csat.htpasswd"
ASTERISK_SOUND_DIR="/var/lib/asterisk/sounds/pt_BR"
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

if [ "$(id -u)" -ne 0 ]; then
    echo "Execute como root: sudo sh install.sh"
    exit 1
fi

if [ ! -f "$SCRIPT_DIR/index.php" ] || [ ! -f "$SCRIPT_DIR/apache/csat-auth.conf" ]; then
    echo "Execute este script a partir da pasta csat original."
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "O comando php não foi encontrado. Instale o PHP com mysqli e execute novamente."
    exit 1
fi

if ! command -v htpasswd >/dev/null 2>&1; then
    echo "O comando htpasswd não foi encontrado. Instale o pacote apache2-utils e execute novamente."
    exit 1
fi

printf "Usuário de acesso [admin]: "
read CSAT_USER
CSAT_USER=${CSAT_USER:-admin}

case "$CSAT_USER" in
    *[!A-Za-z0-9._-]*|'')
        echo "Use apenas letras, números, ponto, hífen ou sublinhado no usuário."
        exit 1
        ;;
esac

if [ -d "$TARGET_DIR" ]; then
    printf "A pasta %s já existe. Atualizar os arquivos? [s/N]: " "$TARGET_DIR"
    read UPDATE
    case "$UPDATE" in
        s|S|sim|SIM) ;;
        *) echo "Instalação cancelada."; exit 0 ;;
    esac
fi

mkdir -p "$TARGET_DIR"
if [ "$SCRIPT_DIR" != "$TARGET_DIR" ]; then
    cp -R "$SCRIPT_DIR"/. "$TARGET_DIR"/
fi

echo "Verificando a tabela csat_avaliacoes no banco..."
php "$TARGET_DIR/setup-database.php"

echo "Verificando os áudios da avaliação..."
mkdir -p "$ASTERISK_SOUND_DIR"
MISSING_AUDIO=0
for AUDIO_FILE in avaliacao-atendimento.wav opcao-invalida.wav agradecimento-avaliacao.wav; do
    if [ -f "$SCRIPT_DIR/audio/$AUDIO_FILE" ]; then
        cp "$SCRIPT_DIR/audio/$AUDIO_FILE" "$ASTERISK_SOUND_DIR/$AUDIO_FILE"
        chmod 644 "$ASTERISK_SOUND_DIR/$AUDIO_FILE"
        if id asterisk >/dev/null 2>&1; then
            chown asterisk:asterisk "$ASTERISK_SOUND_DIR/$AUDIO_FILE"
        fi
    else
        echo "Áudio ausente no pacote: audio/$AUDIO_FILE"
        MISSING_AUDIO=1
    fi
done

if [ "$MISSING_AUDIO" -eq 0 ]; then
    echo "Áudios instalados em $ASTERISK_SOUND_DIR."
else
    echo "A dashboard será instalada, mas copie os áudios ausentes antes de ativar a pesquisa no dialplan."
fi

chown -R root:www-data "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 755 {} \;
find "$TARGET_DIR" -type f -exec chmod 644 {} \;

mkdir -p "$APACHE_CONF_DIR"
sed "s/Require user admin/Require user $CSAT_USER/" "$SCRIPT_DIR/apache/csat-auth.conf" > "$APACHE_CONF_FILE"
chown root:root "$APACHE_CONF_FILE"
chmod 644 "$APACHE_CONF_FILE"

echo
echo "Defina agora a senha do usuário $CSAT_USER."
htpasswd -c "$PASSWORD_FILE" "$CSAT_USER"
chown root:www-data "$PASSWORD_FILE"
chmod 640 "$PASSWORD_FILE"

if command -v a2enmod >/dev/null 2>&1; then
    a2enmod auth_basic authn_file >/dev/null
fi

if command -v a2enconf >/dev/null 2>&1; then
    a2enconf csat-auth >/dev/null
else
    mkdir -p /etc/apache2/conf.d
    cp "$APACHE_CONF_FILE" /etc/apache2/conf.d/csat-auth.conf
fi

if command -v systemctl >/dev/null 2>&1 && systemctl is-active apache2 >/dev/null 2>&1; then
    systemctl reload apache2
else
    service apache2 reload
fi

echo
echo "Dashboard instalada com sucesso."
echo "Acesse: http://IP-DO-SNEP/csat/"
echo "Usuário: $CSAT_USER"
echo "O navegador solicitará a senha definida agora."
