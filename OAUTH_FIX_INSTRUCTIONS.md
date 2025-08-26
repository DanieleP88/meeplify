# 🚨 RIPARAZIONE URGENTE OAUTH GOOGLE

## Problema
Durante la pulizia del progetto, il file `.env` è stato resettato con valori vuoti, causando l'errore 500 nel login Google.

## Soluzione Immediata

### Passo 1: Recupera le tue credenziali Google
1. Vai su **Google Cloud Console**: https://console.cloud.google.com/
2. Seleziona il tuo progetto Meeplify
3. Vai su **APIs & Services** > **Credentials**
4. Trova le tue credenziali OAuth 2.0 esistenti
5. Copia **Client ID** e **Client Secret**

### Passo 2: Aggiorna il file .env
Apri il file `/home/user/webapp/.env` e sostituisci:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

Con le tue credenziali reali:

```env
GOOGLE_CLIENT_ID=il_tuo_client_id_google.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=il_tuo_client_secret_google
```

### Passo 3: Verifica la configurazione
Il redirect URI deve essere esattamente:
```
https://www.meeplify.it/api/google/callback
```

### Passo 4: Testa il login
1. Ricarica la pagina Meeplify
2. Clicca "Accedi con Google"
3. Dovrebbe funzionare normalmente

## Se non hai più le credenziali
1. Vai su Google Cloud Console
2. Crea nuove credenziali OAuth 2.0
3. Imposta authorized redirect URI: `https://www.meeplify.it/api/google/callback`
4. Copia le nuove credenziali nel file .env

## Mi dispiace per l'inconveniente!
Durante la pulizia del progetto ho erroneamente resettato il tuo file .env. Questo è un errore da parte mia che ha causato la regressione nel sistema di login.