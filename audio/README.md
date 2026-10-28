# Áudios da pesquisa CSAT

Coloque nesta pasta os três arquivos WAV abaixo antes de executar `install.sh` em outro SNEP:

```text
audio/avaliacao-atendimento.wav
audio/opcao-invalida.wav
audio/agradecimento-avaliacao.wav
```

O instalador os copia para:

```text
/var/lib/asterisk/sounds/pt_BR/
```

Eles serão usados no dialplan sem a extensão `.wav`:

```text
pt_BR/avaliacao-atendimento
pt_BR/opcao-invalida
pt_BR/agradecimento-avaliacao
```
