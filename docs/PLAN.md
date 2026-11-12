# Piano: "PriceWatch" — monitoraggio e previsione prezzi in Laravel

**Stato:** approvato · settimana 1 completata (2026-11-12)
**Scopo:** progetto portfolio per candidatura in Pricefy
**Data:** 2026-11-12

## 1. Requisiti (dall'intervista)

| Decisione | Scelta |
|---|---|
| Fonte dati | Pagine prodotto e-commerce (scraping reale) + seeder con storici realistici |
| Previsione | Statistica in PHP puro, testata, senza servizi esterni |
| Frontend | Livewire + Blade + Tailwind + Alpine (TALL), grafici ApexCharts |
| Tempo / consegna | 3-4 settimane, demo online con login demo, repo pubblico |

**Pitch in una frase:** "Inserisci l'URL di un prodotto tuo e dei concorrenti: PriceWatch ne storicizza il prezzo, ti avvisa quando cambia e stima dove andrà nei prossimi 14 giorni, con un intervallo di confidenza."

Perché parla a Pricefy: replica il loro dominio (competitor price monitoring, repricing insight) mostrando le competenze che un team Laravel cerca: code, scheduler, scraping robusto, dominio modellato bene, test.

## 2. Funzionalità MVP

1. **Auth** (Laravel Breeze, variante Livewire) + utente demo read-only con dati pre-caricati.
2. **Prodotti monitorati**: un "Product" (es. "iPhone 16 128GB") raggruppa più **Listing** (URL su negozi diversi). Uno è marcato "mio prezzo".
3. **Scraping a scheduler**: job in coda ogni N ore per listing; estrazione a cascata:
   1. JSON-LD `schema.org/Product` → `offers.price` (copre la maggior parte degli shop)
   2. meta tag OpenGraph `product:price:amount`
   3. selettore CSS configurato per negozio (fallback)
4. **Demo Shop interno**: rotte `/demo-shop/{slug}` che servono pagine prodotto finte con JSON-LD e prezzi che variano nel tempo (simulatore deterministico con seed). Rende lo scraping dimostrabile, legale e stabile durante il colloquio.
5. **Storico prezzi** + grafico (prezzo nel tempo per ogni negozio, banda di previsione tratteggiata).
6. **Previsione 14 giorni** con intervallo di confidenza + segnali: "trend al ribasso", "probabile promo nel weekend", "sei il più caro del 12%".
7. **Alert**: regole per utente (prezzo sotto soglia, concorrente sotto il mio prezzo, variazione > X%) → notifica email (Mailpit in locale) + database notification in-app.
8. **API REST** read-only (Sanctum): `GET /api/products`, `/api/products/{id}/history`, `/api/products/{id}/forecast`.
9. **Dashboard**: KPI (prodotti monitorati, variazioni ultime 24h, alert attivi), tabella "posizionamento prezzo" vs concorrenti.

**Fuori scope (esplicito):** repricing automatico, multi-tenant/team, pagamenti, rendering JS headless dei siti (si documenta come estensione via Browsershot/Panther).

## 3. Architettura

```
app/
  Models/            Product, Listing, Store, PricePoint, AlertRule, Forecast
  Scraping/
    Contracts/PriceExtractor.php
    Extractors/JsonLdExtractor.php, OpenGraphExtractor.php, CssSelectorExtractor.php
    PriceScraper.php          (orchestra la cascata, normalizza "1.299,00 €" → 129900 cent)
    Money/PriceParser.php
  Forecasting/
    Contracts/ForecastModel.php
    Models/LinearTrend.php, MovingAverage.php, HoltWinters.php (seasonalità settimanale)
    ModelSelector.php         (sceglie il modello col MAE minore su backtest)
    Backtester.php
    Signals/PromoPatternDetector.php, PricePositionAnalyzer.php
  Jobs/ScrapeListing.php, RecomputeForecast.php
  Events/PriceChanged.php → Listeners/EvaluateAlertRules.php
  Notifications/PriceAlert.php
  Livewire/Dashboard, ProductShow, ProductForm, AlertRules
  Http/Controllers/Api/...  + DemoShopController
database/
  migrations, factories, seeders/DemoDataSeeder.php (180 giorni di storico simulato)
```

**Scelte chiave**
- Prezzi in **centesimi interi** (`unsignedInteger`) + `currency` char(3): niente float sul denaro.
- `price_points` registra solo **le variazioni** più un "heartbeat" giornaliero → tabella piccola, grafici a gradini corretti.
- Scraping: `Http::` di Laravel con timeout, retry esponenziale, User-Agent identificabile, rispetto di `robots.txt`, rate limit per dominio (`RateLimiter` + middleware `WithoutOverlapping` sul job).
- Code: database driver in locale, Redis in produzione; Horizon opzionale.
- DB: PostgreSQL (o SQLite in test).
- Strategy pattern su estrattori e modelli di previsione → facile da spiegare e da testare in isolamento.

## 4. Previsione (dettaglio)

1. **Ricampionamento** dello storico a serie giornaliera (forward-fill del prezzo).
2. **Modelli candidati**: media mobile, regressione lineare (minimi quadrati), Holt-Winters additivo con stagionalità 7 giorni.
3. **Backtest walk-forward** sugli ultimi 28 giorni → MAE e MAPE per modello; vince il MAE minore. MAPE mostrato in UI ("errore medio storico: 3,1%") — onestà sulla precisione.
4. **Intervallo di confidenza** dai residui del backtest (±1,96·σ).
5. **Segnali** a regole: promo ricorrenti (cali > 10% che rientrano entro 7 giorni, con giorno della settimana ricorrente), posizionamento vs concorrenti.
6. Minimo **14 punti** giornalieri, altrimenti la UI mostra "dati insufficienti" invece di inventare.

## 5. Piano di lavoro (4 settimane)

**Settimana 1 — fondamenta e dominio** ✅
- [x] Scaffold Laravel 13 + starter kit Livewire 4 (Flux, Fortify) + Pest 5 + Larastan **livello 7** + Pint.
- [x] Migrazioni e modelli, factory, `PriceParser` (TDD: formati IT/EN, separatori, valute).
- [x] `Listing::recordPrice()`: salva solo variazioni + heartbeat 24h, resetta i fallimenti.
- [x] Demo Shop (`/demo-shop`) con simulatore prezzi deterministico: promo weekend, trend a dente di sega su 90 giorni, volatilità ±5%.
- [x] CI GitHub Actions: `composer audit`, Pint, Larastan, Pest su PHP 8.5.
- [ ] Note Laravel/Livewire/Pest nel vault Dev Knowledge (rimandato).

> **Deviazioni rispetto al piano iniziale**
> - Niente Sail: sulla macchina di sviluppo non ci sono Docker né un WSL funzionante. In locale si usa PHP 8.5 di php.new + SQLite; Postgres/Redis restano per la produzione.
> - Breeze è stato sostituito dallo starter kit ufficiale Livewire (Laravel 13).
> - Larastan a livello 7 invece di 6 (default dello starter kit).
> - Il trend del negozio "ElettroCasa" è ciclico (90 giorni) invece che lineare: uno lineare toccava il minimo a maggio e restava piatto.

**Settimana 2 — scraping e storico**
- [ ] Estrattori JSON-LD / OG / CSS (TDD con fixture HTML in `tests/Fixtures/html/`).
- [ ] `ScrapeListing` job, scheduler, rate limit, gestione errori (listing marcato "in errore" dopo 3 fallimenti).
- [ ] Evento `PriceChanged`, `DemoDataSeeder` con 180 giorni di storico.
- [ ] CRUD prodotti/listing in Livewire.

**Settimana 3 — previsione e alert**
- [ ] Modelli di previsione + backtester + selector (TDD con serie sintetiche a risposta nota).
- [ ] Pagina prodotto con grafico storico + banda previsione.
- [ ] Regole alert, notifiche email + in-app.
- [ ] API Sanctum + test feature.

**Settimana 4 — rifinitura e consegna**
- [ ] Dashboard KPI, empty state, stati di errore, responsive.
- [ ] Deploy (es. Fly.io o VPS con Forge/Ploi): worker coda + scheduler attivi, utente demo, reset notturno dei dati demo.
- [ ] README: screenshot/GIF, diagramma architettura, "come funziona la previsione", limiti noti, scelte tecniche.
- [ ] Buffer per bug e un'ora di prova del pitch.

## 6. Criteri di accettazione (verificabili)

1. `sail artisan migrate:fresh --seed` produce ≥ 10 prodotti, ≥ 30 listing, ≥ 180 giorni di storico ciascuno.
2. Dato un fixture HTML con JSON-LD `"price": "1299.00"`, lo scraper restituisce `129900` cent e valuta `EUR`; con `"1.299,00 €"` in solo CSS fallback, idem.
3. Su una serie sintetica lineare (y = 100 − 0,5·t, senza rumore), `LinearTrend` prevede il giorno +7 con errore < 0,01.
4. Su serie con stagionalità settimanale nota, `ModelSelector` sceglie Holt-Winters.
5. Con < 14 punti, l'endpoint forecast risponde `422` con messaggio "dati insufficienti" e la UI lo mostra.
6. Una variazione di prezzo sotto la soglia di una regola invia esattamente 1 notifica (no duplicati entro 24h).
7. Il job su un dominio con 3 errori consecutivi marca il listing `failing` e non riprova prima di 6h.
8. CI verde: Pint, Larastan livello 6, Pest con copertura ≥ 80% su `app/Scraping` e `app/Forecasting`.
9. Demo online raggiungibile, login demo funzionante, scheduler che aggiunge almeno 1 punto prezzo al giorno sui listing del Demo Shop.
10. Pagina prodotto carica in < 500 ms con 180 giorni × 5 listing (forecast precalcolato e salvato in `forecasts`, non a ogni request).

## 7. Rischi e mitigazioni

| Rischio | Mitigazione |
|---|---|
| Siti reali bloccano lo scraping o cambiano markup | Demo Shop interno come fonte primaria in demo; estrattori a cascata; listing reali solo come "bonus" |
| Questioni legali/ToS sullo scraping | robots.txt rispettato, UA identificabile, rate limit, nessun sito reale nei dati seed; sezione nel README |
| Previsione poco accurata percepita come "finta" | Mostrare MAPE da backtest e intervallo; chiamarla "stima", non "previsione certa" |
| Scope creep in 4 settimane | Fuori scope esplicito (§2); settimana 4 con buffer; ogni settimana termina con qualcosa di dimostrabile |
| Deploy con code/scheduler complicato | Scegliere piattaforma entro settimana 1; provare un deploy "hello world" con worker subito |
| Laravel non è ancora nel vault Dev Knowledge | Aggiungere note Laravel/Livewire/Pest al vault durante la settimana 1 |

## 8. Verifica finale prima della candidatura

- [ ] Clone pulito del repo → README seguito alla lettera → app funzionante in < 10 minuti.
- [ ] CI verde sull'ultimo commit.
- [ ] Percorso demo di 5 minuti provato: dashboard → prodotto → grafico con previsione → crea alert → forza scrape → notifica arrivata.
- [ ] Revisione del codice (sicurezza: SSRF sugli URL inseriti dall'utente — bloccare IP privati/localhost tranne il Demo Shop; validazione input; policy di autorizzazione per prodotti di altri utenti).

## 9. Punti di discussione per il colloquio

- Perché centesimi interi e solo-variazioni in `price_points`.
- Perché statistica semplice + backtest invece di ML: spiegabilità, misurabilità, zero infrastruttura.
- Come scalerebbe: sharding delle code per dominio, proxy rotation, headless rendering, TimescaleDB per lo storico.
