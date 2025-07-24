# HomeBannerAPI

**HomeBannerAPI** è un modulo PrestaShop che espone i banner della homepage tramite un endpoint API JSON, pensato per essere utilizzato da app mobili, frontend headless o altri sistemi esterni.

## 🔧 Funzionalità

- Espone un endpoint API REST sicuro via token
- Supporta banner provenienti da:
  - Modulo `ps_banner`
  - Modulo `ps_imageslider`
  - Modulo Creative Elements (Elementor)
  - Configurazione statica
- Caching automatico delle risposte JSON
- Pannello di configurazione nel back office
- Compatibile con PrestaShop 1.7 e 8.x

## 📡 Endpoint API

```
GET /module/homebannerapi/bannerapi?token=TUO_TOKEN
```

**Risposta JSON esempio:**

```json
[
  {
    "desktop": "https://tuosito.com/img/slide1-desktop.jpg",
    "mobile": "https://tuosito.com/img/slide1-mobile.jpg"
  }
]
```

## ⚙️ Configurazione

Dalla pagina Moduli nel back office:

- Attiva/disattiva l'API
- Imposta il token di sicurezza
- Seleziona la fonte del banner

## 👨‍💻 Autore

**i-creativi**

## 📜 Licenza

Distribuito liberamente per uso personale o commerciale. Può essere adattato o esteso per le proprie esigenze.
