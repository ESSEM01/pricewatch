# PriceWatch API

A small read-only JSON API over your own products. Every endpoint needs a
personal token, is limited to **60 requests per minute per user**, and only
returns data you own (other users' products answer `403`).

## Authentication

Create a token in **Settings → API tokens**. It is shown once; store it like
a password. Tokens are read-only (`read` ability), expire after **90 days**
(`SANCTUM_EXPIRATION`, in minutes) and can be revoked from the same page.

```bash
export PW_TOKEN="1|…"
curl -s http://localhost:8000/api/products \
  -H "Authorization: Bearer $PW_TOKEN" \
  -H "Accept: application/json"
```

## Money

Prices are **integer minor units plus an ISO currency**, never floats:
`{"amount": 49990, "currency": "EUR"}` means 499,90 €.

## Endpoints

### `GET /api/products`

Your products, 50 per page, with your own price and the cheapest competitor.

```json
{
  "data": [
    {
      "id": 4,
      "name": "Sony PlayStation 5 Slim",
      "sku": "PS5-SLIM",
      "own_price": { "amount": 54990, "currency": "EUR" },
      "cheapest_competitor": { "store": "ElettroCasa", "price": { "amount": 51990, "currency": "EUR" } },
      "listings_count": 3
    }
  ],
  "links": { "…": "…" },
  "meta": { "…": "…" }
}
```

### `GET /api/products/{id}`

The same, plus every store link (`listings`): URL, price, `status`
(`active`, `failing`, `paused`), `last_error`, `last_checked_at` (ISO 8601).

### `GET /api/products/{id}/history?days=90`

Recorded price points per store, oldest first. Only changes and a daily
heartbeat are stored, so draw them as a step line. `days` is 1-365
(default 90); anything else answers `422`.

```json
{
  "data": [
    {
      "listing_id": 31,
      "store": "TecnoShop",
      "currency": "EUR",
      "points": [{ "recorded_at": "2026-11-07T09:00:00+00:00", "price": 54990 }]
    }
  ]
}
```

### `GET /api/products/{id}/forecast`

The 14-day forecast per store, with the model chosen by the backtest and its
mean absolute percentage error over the last 4 weeks. `lower`/`upper` bound
a 95% band. A store with less than 14 days of history has `"forecast": null`;
when no store can be forecast yet the endpoint answers `422`.

```json
{
  "data": [
    {
      "listing_id": 31,
      "store": "TecnoShop",
      "currency": "EUR",
      "forecast": {
        "model": "holt_winters",
        "backtest_mape": 3.4,
        "generated_at": "2026-11-12T03:00:00+00:00",
        "points": [{ "date": "2026-11-13", "price": 54990, "lower": 52310, "upper": 57670 }]
      }
    }
  ]
}
```

## Errors

| Status | When |
|---|---|
| 401 | Missing or revoked token |
| 403 | The product belongs to someone else |
| 404 | No such product |
| 422 | Invalid `days`, or nothing to forecast yet |
| 429 | Over 60 requests per minute; retry after `Retry-After` seconds |
