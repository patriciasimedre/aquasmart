/* ============================================================
 *  AquaSmart — Firmware ESP32
 *  Citeste senzorii -> POST /api/v1/readings (backend-ul ruleaza
 *  fuzzy Mamdani si emite comenzi) -> ESP32 ia comenzile prin
 *  GET /api/v1/commands/next, porneste pompa, apoi /ack.
 *
 *  Biblioteci (Arduino IDE -> Library Manager):
 *    - ArduinoJson           (v6.x recomandat)
 *    - DHT sensor library    (Adafruit) + Adafruit Unified Sensor
 *    - OneWire
 *    - DallasTemperature
 *    - Adafruit SSD1306 + Adafruit GFX
 *  Placa: "ESP32 Dev Module".
 *
 *  >>> EDITEAZA sectiunea CONFIG de mai jos (WiFi + API_BASE). <<<
 * ============================================================ */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <DHT.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

// ---------------- CONFIG (de editat) ------------------------
#define WIFI_SSID   "SSID_TAU"
#define WIFI_PASS   "PAROLA_TA"

// Local: PC-ul ruleaza  php -S 0.0.0.0:8080 -t public public/index.php
//        si pui IP-ul LAN al PC-ului (ex: http://192.168.1.20:8080)
// Azure: https://numele-app.azurewebsites.net
#define API_BASE    "https://aquasmart-app-g3eubyfefscsemdq.germanywestcentral-01.azurewebsites.net"

// Gol = fara autentificare (dev). Cand setezi ESP32_API_KEY in
// .env / Azure, pune AICI exact aceeasi valoare.
#define API_TOKEN   ""

// Releul:
//   ACTIVE-LOW  (RELAY_ACTIVE_LOW=1) — IN=LOW -> pompa PORNITA (multe module SongLe/Songhe).
//   ACTIVE-HIGH (RELAY_ACTIVE_LOW=0) — IN=HIGH -> pompa PORNITA (module 5V Songle 2-canal).
// Cum verifici: dupa boot, pompa NU trebuie sa porneasca singura. Daca incepe sa
// pompeze chiar daca nimeni n-a cerut udare, polaritatea e inversa -> flip-ul valorii.
#define RELAY_ACTIVE_LOW 0

// --- Calibrare TS-300B (turbiditate) ---
// Citeste raw-ul de pe Serial cand senzorul e in apa CLARA -> pune valoarea aici.
// La apa f. tulbure (lapte/cafea), raw scade — citeste si pune in TURB_RAW_TURBID.
// Mai sus = apa clara (Vout mare). Mai jos = tulbure.
#define TURB_RAW_CLEAR   3100    // raw cand apa e CLARA -> 0 NTU
#define TURB_RAW_TURBID  1500    // raw cand apa e FOARTE tulbure -> 3000 NTU
#define TURB_RAW_MIN_VALID 800   // sub asta, senzorul e probabil neconectat sau in aer

// --- Protectie pompa ---
// Distanta in cm peste care consideram rezervorul GOL.
// Pompa NU porneste daca nivelCm depaseste valoarea asta (protectie functionare in gol).
#define NIVEL_GOL_CM   20.0f
// ------------------------------------------------------------

// ---------------- PINOUT (conform README1.md) ---------------
#define PIN_DHT        32      // DHT22 data
#define PIN_DS18B20     4      // DS18B20 data (pull-up 4.7k)
#define PIN_TDS        25      // TDS analog — GPIO39 / VN (ADC1, MERGE cu WiFi). Era 25 (ADC2 → mereu 0).
#define PIN_TURB       34      // TS-300B turbiditate analog (ADC1, merge cu WiFi).
                               // Divizor de tensiune 10k+27k pe Vout (TS-300B da 0-4.5V, ADC ESP32 0-3.3V).
#define PIN_RAIN_DO    14      // senzor ploaie, iesire digitala
#define PIN_SOL        36      // senzor capacitiv umiditate sol v2.0.0 (ADC1, merge cu WiFi)
#define PIN_RELAY       5      // releu pompa (IN)
#define PIN_TRIG       13      // HC-SR04 ultrasonic — TRIG
#define PIN_ECHO       12      // HC-SR04 ultrasonic — ECHO
#define PIN_OLED_SDA   21
#define PIN_OLED_SCL   22
// NOTA: GPIO25 e pe ADC2, care NU functioneaza cu analogRead cat
// timp WiFi e pornit. Daca TDS iese 0/aiurea, muta firul pe un pin
// ADC1 (ex. GPIO34) si schimba PIN_TDS in 34. De evitat GPIO33/35.

#define DHTTYPE DHT22
DHT dht(PIN_DHT, DHTTYPE);
OneWire oneWire(PIN_DS18B20);
DallasTemperature ds18b20(&oneWire);
Adafruit_SSD1306 oled(128, 64, &Wire, -1);

// ---------------- Intervale (ms) ----------------------------
const unsigned long T_READINGS  = 30000;   // POST /readings la 30s
const unsigned long T_COMMANDS  = 5000;    // GET  /commands/next la 5s
const unsigned long T_HEARTBEAT = 300000;  // POST /heartbeat la 5min
const unsigned long T_DISPLAY   = 1000;    // citire senzori la 1s

unsigned long lastReadings = 0, lastCommands = 0, lastHeartbeat = 0, lastDisplay = 0;

// ---------------- Stare senzori -----------------------------
float tempAer = NAN, umidAer = NAN, tempApa = NAN;
int   tds = 0, ploaie = 0;
int   umiditSol = 0;     // % umiditate sol (senzor capacitiv v2.0.0)
int   turbiditate = 0;   // NTU, claritate apa (TS-300B): 0=clara, >50=tulbure, >200=toxic
float nivelCm   = 0.0f;  // distanta HC-SR04 (cm) la suprafata apei

// ---------------- Stare pompa (non-blocant) -----------------
long  activeCmdId   = -1;       // comanda in executie (pt /ack)
unsigned long pumpOffAt = 0;    // millis() cand se opreste pompa
bool  pumpRunning   = false;

// ============================================================
void pumpSet(bool on) {
  pumpRunning = on;
#if RELAY_ACTIVE_LOW
  digitalWrite(PIN_RELAY, on ? LOW : HIGH);
#else
  digitalWrite(PIN_RELAY, on ? HIGH : LOW);
#endif
}

void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  unsigned long t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < 15000) {
    delay(300);
  }
}

// HTTPClient peste WiFiClient (http) sau WiFiClientSecure (https).
// Returneaza codul HTTP; raspunsul ajunge in `out`.
int httpSend(const char* method, const String& path, const String& body, String& out) {
  if (WiFi.status() != WL_CONNECTED) { connectWiFi(); }
  if (WiFi.status() != WL_CONNECTED) return -1;

  String url = String(API_BASE) + path;
  HTTPClient http;
  bool https = url.startsWith("https");

  // IMPORTANT: clientii trebuie sa traiasca pentru toata durata cererii.
  // Daca ii declari in if/else block-uri locale, ies din scope inainte ca
  // http.GET()/POST() sa-i foloseasca -> use-after-free -> timeout HTTP -11.
  WiFiClientSecure secureClient;
  WiFiClient plainClient;

  bool ok;
  if (https) {
    secureClient.setInsecure();    // licenta: fara validare certificat
    ok = http.begin(secureClient, url);
  } else {
    ok = http.begin(plainClient, url);
  }
  if (!ok) return -2;

  http.addHeader("Content-Type", "application/json");
  if (strlen(API_TOKEN) > 0) {
    http.addHeader("Authorization", String("Bearer ") + API_TOKEN);
  }
  http.setTimeout(30000);          // 30s — Azure F1 Free poate avea cold-start
  http.setConnectTimeout(15000);
  http.setReuse(false);

  int code;
  if (strcmp(method, "GET") == 0) code = http.GET();
  else                            code = http.POST((uint8_t*)body.c_str(), body.length());

  out = (code > 0) ? http.getString() : "";
  http.end();
  return code;
}

// ============================================================
void readSensors() {
  // DHT22 da uneori valori aberante (3300% etc.) cand pull-up-ul de 4.7k pe data
  // lipseste sau cablul e prea lung -> validam strict intervalul.
  float h = dht.readHumidity();
  float t = dht.readTemperature();
  if (!isnan(h) && h >= 0.0f && h <= 100.0f) umidAer = h;
  else Serial.printf("[dht22] umiditate INVALIDA: %.1f (verifica pull-up 4.7k pe DATA)\n", h);
  if (!isnan(t) && t >= -40.0f && t <= 80.0f) tempAer = t;
  else Serial.printf("[dht22] temperatura INVALIDA: %.1f\n", t);

  ds18b20.requestTemperatures();
  float tw = ds18b20.getTempCByIndex(0);
  if (tw > -100 && tw < 125) tempApa = tw;

  // TDS cu compensare de temperatura (formula uzuala; calibreaza la nevoie)
  int raw = analogRead(PIN_TDS);
  float volt = raw * 3.3f / 4095.0f;
  float comp = 1.0f + 0.02f * ((isnan(tempApa) ? 25.0f : tempApa) - 25.0f);
  float cv = volt / comp;
  float ppm = (133.42f * cv * cv * cv - 255.86f * cv * cv + 857.39f * cv) * 0.5f;
  tds = (ppm < 0) ? 0 : (int)ppm;
  // DEBUG: trimite raw + voltaj pe Serial ca sa vedem ce primeste pinul
  Serial.printf("[tds] pin=%d  raw=%d  volt=%.3fV  ppm=%d\n", PIN_TDS, raw, volt, tds);

  // Senzor capacitiv umiditate sol (calibrare: ~3200 in aer uscat, ~1500 in apa)
  int rawSol = analogRead(PIN_SOL);
  umiditSol  = map(rawSol, 3200, 1500, 0, 100);
  umiditSol  = constrain(umiditSol, 0, 100);

  // TS-300B (turbiditate) — Vout DROP la cresterea turbiditatii:
  //   apa clara  => Vout mare (~4.1V) => raw mare (~3200 dupa divizor)
  //   apa f.tulb => Vout mic  (~2.5V) => raw mic  (~1500 dupa divizor)
  //
  // Daca raw < TURB_RAW_MIN_VALID, senzorul nu e in apa (sau e neconectat) —
  // raportam 0 in loc de o valoare faramoasa de mare.
  int rawTurb = analogRead(PIN_TURB);
  if (rawTurb < TURB_RAW_MIN_VALID) {
    turbiditate = 0;   // senzor neimersat / firul desfacut — nu speriem dashboard-ul
  } else {
    turbiditate = map(rawTurb, TURB_RAW_TURBID, TURB_RAW_CLEAR, 3000, 0);
    turbiditate = constrain(turbiditate, 0, 3000);
  }
  Serial.printf("[turb] pin=%d  raw=%d  ntu=%d  (calibrare: clear=%d turbid=%d)\n",
                PIN_TURB, rawTurb, turbiditate, TURB_RAW_CLEAR, TURB_RAW_TURBID);

  // Senzor ploaie: DO = LOW cand detecteaza apa (depinde de modul; inverseaza daca e cazul)
  ploaie = (digitalRead(PIN_RAIN_DO) == LOW) ? 1 : 0;

  // HC-SR04: puls 10us pe TRIG, masuram durata pulsului ECHO (us).
  // distanta_cm = (durata / 2) * 0.0343 (viteza sunet in aer ~343 m/s).
  digitalWrite(PIN_TRIG, LOW);  delayMicroseconds(2);
  digitalWrite(PIN_TRIG, HIGH); delayMicroseconds(20);
  digitalWrite(PIN_TRIG, LOW);
  long dur = pulseIn(PIN_ECHO, HIGH, 30000);
  nivelCm = dur > 0 ? (dur / 2.0f) * 0.0343f : 0.0f;

  // ===== LOG complet pe Serial: toate valorile la fiecare citire =====
  Serial.println("---------------------------------------------------------------");
  Serial.printf("[senzori] Aer    : %.1f °C   |  Umiditate aer : %.1f %%\n",
                tempAer, umidAer);
  Serial.printf("          Apa    : %.1f °C   |  TDS apa       : %d ppm\n",
                tempApa, tds);
  Serial.printf("          Sol    : %d %%    |  Nivel rezervor: %.1f cm\n",
                umiditSol, nivelCm);
  Serial.printf("          Turb   : %d NTU   |  Ploaie        : %s\n",
                turbiditate, ploaie ? "DA" : "NU");
  Serial.printf("          Pompa  : %s\n",
                pumpRunning ? "PORNITA" : "oprita");
  Serial.println("---------------------------------------------------------------");
}

void showOLED(const char* line) {
  oled.clearDisplay();
  oled.setTextSize(1);
  oled.setTextColor(SSD1306_WHITE);
  oled.setCursor(0, 0);
  oled.println("AquaSmart");
  oled.printf("Aer %.1fC  Um %.0f%%\n", tempAer, umidAer);
  oled.printf("Sol:%d%%  Niv:%.1fcm\n", umiditSol, nivelCm);
  oled.printf("Apa %.1fC TDS:%d\n", tempApa, tds);
  oled.printf("Turb:%d NTU\n", turbiditate);
  oled.printf("Ploaie:%s Pompa:%s\n", ploaie ? "DA" : "NU", pumpRunning ? "ON" : "off");
  oled.printf("%s\n", line);
  oled.print(WiFi.status() == WL_CONNECTED ? WiFi.localIP().toString() : "WiFi...");
  oled.display();
}

void postReadings() {
  String b = "{";
  b += "\"temp_apa\":"      + (isnan(tempApa) ? String("null") : String(tempApa, 2)) + ",";
  b += "\"temp_aer\":"      + (isnan(tempAer) ? String("null") : String(tempAer, 2)) + ",";
  b += "\"umiditate_aer\":" + (isnan(umidAer) ? String("null") : String(umidAer, 2)) + ",";
  b += "\"umiditate_sol\":" + String(umiditSol) + ",";
  b += "\"tds\":"           + String(tds) + ",";
  b += "\"turbiditate\":"   + String(turbiditate) + ",";
  b += "\"ploaie\":"        + String(ploaie) + ",";
  b += "\"nivel_cm\":"      + String(nivelCm, 1);
  b += "}";

  String resp;
  int code = httpSend("POST", "/api/v1/readings", b, resp);
  Serial.printf("[readings] HTTP %d %s\n", code, resp.c_str());
}

void pollCommands() {
  String resp;
  int code = httpSend("GET", "/api/v1/commands/next", "", resp);
  if (code != 200) return;

  DynamicJsonDocument doc(512);   // compatibil ArduinoJson v6 si v7
  if (deserializeJson(doc, resp)) return;
  if (!doc["success"].as<bool>() || doc["data"].isNull()) return;

  long id      = doc["data"]["id"]     | -1;
  const char* tip = doc["data"]["tip"] | "";
  int durata   = doc["data"]["durata"] | 0;
  if (id < 0) return;

  Serial.printf("[command] #%ld %s %ds  (nivelCm=%.1f)\n", id, tip, durata, nivelCm);

  if (strcmp(tip, "udare") == 0 && durata > 0) {
    // ---- PROTECTIE LOCALA POMPA ----
    // Indiferent ce zice backend-ul (chiar si comanda manuala), firmware-ul
    // refuza udarea daca rezervorul e gol — protejeaza pompa de functionare in gol
    // (pompele DC mici se ard rapid daca pompeaza aer in loc de apa).
    if (nivelCm > NIVEL_GOL_CM || nivelCm <= 0.0f) {
      Serial.printf("[command] #%ld REFUZATA — rezervor gol/invalid (nivel=%.1f cm > %.1f)\n",
                    id, nivelCm, NIVEL_GOL_CM);
      // ACK direct cu motiv ca sa nu ramana stuck in 'executing' in DB
      String r;
      httpSend("POST", "/api/v1/commands/" + String(id) + "/ack",
               "{\"refused\":\"rezervor_gol\"}", r);
      return;
    }
    // OK — pornim pompa
    pumpSet(true);
    activeCmdId = id;
    pumpOffAt   = millis() + (unsigned long)durata * 1000UL;
  } else { // "oprire" sau durata 0
    pumpSet(false);
    pumpOffAt   = 0;
    activeCmdId = -1;
    String r;
    httpSend("POST", "/api/v1/commands/" + String(id) + "/ack", "{}", r);
  }
}

void postHeartbeat() {
  String r;
  int code = httpSend("POST", "/api/v1/heartbeat", "{}", r);
  Serial.printf("[heartbeat] HTTP %d\n", code);
}

// ============================================================
void setup() {
  Serial.begin(115200);

  // IMPORTANT: scriem mai intai starea OFF pe pin, apoi pinMode(OUTPUT).
  // Daca facem invers, in fereastra dintre pinMode si pumpSet(false) pin-ul e
  // in starea default (LOW pentru ESP32) — iar pe relay active-HIGH asta e OK,
  // dar pe active-LOW ar porni pompa pentru cateva ms la fiecare boot.
  // Scriem starea sigura inainte de a configura pin-ul ca output.
#if RELAY_ACTIVE_LOW
  digitalWrite(PIN_RELAY, HIGH);   // active-LOW -> HIGH = OFF
#else
  digitalWrite(PIN_RELAY, LOW);    // active-HIGH -> LOW = OFF
#endif
  pinMode(PIN_RELAY, OUTPUT);
  pumpSet(false);                  // dubla siguranta dupa pinMode
  pinMode(PIN_RAIN_DO, INPUT);
  pinMode(PIN_TRIG, OUTPUT);
  pinMode(PIN_ECHO, INPUT);
  analogReadResolution(12);

  Wire.begin(PIN_OLED_SDA, PIN_OLED_SCL);
  oled.begin(SSD1306_SWITCHCAPVCC, 0x3C);
  oled.clearDisplay();
  oled.setTextColor(SSD1306_WHITE);
  oled.setCursor(0, 0);
  oled.println("AquaSmart\nPornire...");
  oled.display();

  dht.begin();
  ds18b20.begin();

  connectWiFi();
  readSensors();
  showOLED("Gata");
}

void loop() {
  unsigned long now = millis();

  // ======= WATCHDOG SIGURANTA POMPA =======
  // Indiferent de starea logica (pumpRunning), daca rezervorul e gol fortam pin-ul
  // releului in starea OFF la fiecare iteratie a buclei. Protejeaza pompa de
  // functionare in gol chiar daca:
  //   - apare un glitch electric pe pin
  //   - polaritatea relay-ului e gresita
  //   - backend-ul emite gresit o comanda de udare cand n-ar trebui
  if (nivelCm > NIVEL_GOL_CM || nivelCm <= 0.0f) {
    if (pumpRunning) {
      Serial.printf("[watchdog] REZERVOR GOL (nivel=%.1f cm) -> opresc pompa fortat\n", nivelCm);
      pumpSet(false);
      pumpOffAt = 0;
      // activeCmdId ramane > 0 daca era ceva in executie -> retry-ACK il va inchide
    }
#if RELAY_ACTIVE_LOW
    digitalWrite(PIN_RELAY, HIGH);
#else
    digitalWrite(PIN_RELAY, LOW);
#endif
  }

  // Oprire pompa cand expira durata; ACK-ul se face mai jos cu retry pana primim 200
  // (daca primul ACK pica pe network error, comanda ramane stuck 'executing' in DB).
  if (pumpRunning && pumpOffAt > 0 && now >= pumpOffAt) {
    pumpSet(false);
    pumpOffAt = 0;
    Serial.printf("[pump] durata expirata, pompa oprita; ACK pentru #%ld pending\n", activeCmdId);
  }

  // Retry-ACK: cat timp avem un activeCmdId si pompa nu mai ruleaza, batem
  // /ack la fiecare 5s pana primim 200 OK. Fara asta, daca network-ul pica
  // exact in momentul ACK-ului, dashboard-ul ramane „Udare in curs" la infinit.
  static unsigned long lastAckTry = 0;
  if (!pumpRunning && activeCmdId >= 0 && (now - lastAckTry >= 5000UL)) {
    lastAckTry = now;
    String r;
    int ackCode = httpSend("POST", "/api/v1/commands/" + String(activeCmdId) + "/ack", "{}", r);
    Serial.printf("[ack] comanda #%ld -> HTTP %d\n", activeCmdId, ackCode);
    if (ackCode >= 200 && ackCode < 300) {
      activeCmdId = -1;   // confirmat — dashboard-ul va vedea pompa „oprita"
    }
  }

  if (now - lastDisplay >= T_DISPLAY) {
    lastDisplay = now;
    readSensors();
    showOLED(pumpRunning ? "Udare in curs" : "OK");
  }
  if (now - lastCommands >= T_COMMANDS) {
    lastCommands = now;
    pollCommands();
  }
  if (now - lastReadings >= T_READINGS) {
    lastReadings = now;
    postReadings();
  }
  if (now - lastHeartbeat >= T_HEARTBEAT) {
    lastHeartbeat = now;
    postHeartbeat();
  }
}