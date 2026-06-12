# Firmware ESP32 — AquaSmart

`aquasmart_esp32.ino` — citește senzorii, trimite în tabele prin API, execută comenzile de udare decise de fuzzy-ul din backend.

## 1. Biblioteci (Arduino IDE → Tools → Manage Libraries)

- **ArduinoJson** (v6.x recomandat)
- **DHT sensor library** (Adafruit) + **Adafruit Unified Sensor**
- **OneWire**
- **DallasTemperature**
- **Adafruit SSD1306** + **Adafruit GFX**

Board: **ESP32 Dev Module** (Tools → Board → esp32). Adaugă URL-ul plăcilor ESP32 în Preferences dacă lipsește:
`https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json`

## 2. De editat în `.ino` (secțiunea CONFIG)

| Define | Pune |
|---|---|
| `WIFI_SSID` / `WIFI_PASS` | rețeaua ta WiFi (ESP32 = doar 2.4 GHz) |
| `API_BASE` | unde rulează backend-ul (vezi mai jos) |
| `API_TOKEN` | gol acum; egal cu `ESP32_API_KEY` din `.env` când îl setezi |
| `RELAY_ACTIVE_LOW` | `1` (implicit) sau `0` dacă pompa pornește invers |

## 3. API_BASE — important

**Local (test pe rețeaua ta):** pornește serverul să asculte pe toate interfețele, nu doar localhost:
```bash
php -S 0.0.0.0:8080 -t public public/index.php
```
Află IP-ul LAN al PC-ului (`ipconfig getifaddr en0` pe macOS) și pune:
`#define API_BASE "http://192.168.x.y:8080"`. PC-ul și ESP32 pe aceeași rețea.

**Azure (producție):** `#define API_BASE "https://numele-app.azurewebsites.net"` — codul comută automat pe HTTPS.

## 4. Note hardware

- **GPIO25 / TDS:** e pe ADC2, care **nu merge cu `analogRead` cât timp WiFi e pornit**. Dacă TDS iese 0 sau aiurea, mută firul analogic pe **GPIO34** și schimbă `PIN_TDS` în `34`. (GPIO33/35 — de evitat, nefiabile pe placă.)
- **Releu:** majoritatea modulelor cu optocuplor sunt active-LOW (lăsat `RELAY_ACTIVE_LOW 1`).
- **Float switch:** `INPUT_PULLUP`, contact la masă când activat. `sus=1`→plin, `jos=1`→are apă. Dacă nivelul apare invers în dashboard, inversează logica în `readSensors()`.
- **Senzor ploaie:** DO = LOW la detecție (depinde de modul; inversează dacă trebuie).
- DS18B20: rezistență pull-up 4.7 kΩ între data (GPIO4) și 3V3.

## 5. Verifică că ajung date în tabele

După upload, Serial Monitor la **115200 baud** arată `[readings] HTTP 201 ...`. Apoi:

```bash
php scripts/inspect_db.php          # sensor_readings trebuie să crească
```
sau deschide dashboard-ul → cardurile live se actualizează la 7s, iar pagina **Control → Decizie fuzzy** arată ce decide backend-ul. Când fuzzy decide udare, comanda apare la `GET /commands/next`, ESP32 pornește pompa pe durata primită și confirmă cu `/ack` (se loghează automat în `irrigation_events`).

## Flux

```
ESP32 --POST /api/v1/readings--> backend (rulează fuzzy Mamdani)
ESP32 --GET  /api/v1/commands/next--> {tip:udare,durata} -> pompă ON (non-blocant)
ESP32 --POST /api/v1/commands/{id}/ack--> backend loghează irrigation_event
ESP32 --POST /api/v1/heartbeat--> la 5 min
```
