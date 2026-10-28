#!/bin/sh
# Instalador remoto verificado para SNEP Debian.
# Uso: sudo sh install-from-github.sh

set -e

# Esta versão é imutável. Não usa uma branch pública mutável como fonte da instalação.
REPOSITORY_COMMIT="772eeb7"
ARCHIVE_URL="https://github.com/linkincharles/csat-snep/archive/${REPOSITORY_COMMIT}.zip"
ARCHIVE_SHA256="PENDENTE"
TEMP_DIR=$(mktemp -d)

cleanup() {
    rm -rf "$TEMP_DIR"
}
trap cleanup EXIT INT TERM

if [ "$(id -u)" -ne 0 ]; then
    echo "Execute como root: sudo sh install-from-github.sh"
    exit 1
fi

if [ "$ARCHIVE_SHA256" = "PENDENTE" ]; then
    echo "O instalador ainda não foi publicado com uma assinatura de integridade."
    exit 1
fi

if ! command -v curl >/dev/null 2>&1 && ! command -v wget >/dev/null 2>&1; then
    echo "Instale curl ou wget antes de executar este instalador."
    exit 1
fi

if ! command -v unzip >/dev/null 2>&1 || ! command -v sha256sum >/dev/null 2>&1; then
    echo "Instale unzip e sha256sum antes de executar este instalador."
    exit 1
fi

echo "Baixando pacote CSAT verificado..."
if command -v curl >/dev/null 2>&1; then
    curl -fL --retry 3 "$ARCHIVE_URL" -o "$TEMP_DIR/csat-snep.zip"
else
    wget -O "$TEMP_DIR/csat-snep.zip" "$ARCHIVE_URL"
fi

ACTUAL_SHA256=$(sha256sum "$TEMP_DIR/csat-snep.zip" | awk '{print $1}')
if [ "$ACTUAL_SHA256" != "$ARCHIVE_SHA256" ]; then
    echo "Falha na verificação de integridade. A instalação foi cancelada."
    exit 1
fi

unzip -q "$TEMP_DIR/csat-snep.zip" -d "$TEMP_DIR"
SOURCE_DIR="$TEMP_DIR/csat-snep-${REPOSITORY_COMMIT}"
if [ ! -f "$SOURCE_DIR/install.sh" ]; then
    echo "O pacote verificado não contém install.sh."
    exit 1
fi

cd "$SOURCE_DIR"
exec sh ./install.sh
