/*
  MediCare HMS — Arduino RFID Reader (MFRC522)
  Hardware: Arduino UNO + MFRC522 RFID module + ESP8266/Ethernet OR PC via Serial
  Wiring (UNO):
    SDA  -> D10
    SCK  -> D13
    MOSI -> D11
    MISO -> D12
    IRQ  -> not used
    GND  -> GND
    RST  -> D9
    3.3V -> 3.3V

  Two modes:
   A) Serial mode  : prints UID on Serial; a tiny PHP/Python bridge on the PC POSTs it.
   B) Wi-Fi mode   : if using NodeMCU/ESP32 — uncomment WiFi block to POST directly.
*/

#include <SPI.h>
#include <MFRC522.h>

#if defined(ESP8266)
  #include <ESP8266WiFi.h>
  #include <ESP8266HTTPClient.h>
#elif defined(ESP32)
  #include <WiFi.h>
  #include <HTTPClient.h>
#endif

// Uncomment the next line for ESP8266/ESP32 Wi-Fi mode.
// #define WIFI_MODE

#define SS_PIN 10
#define RST_PIN 9
MFRC522 rfid(SS_PIN, RST_PIN);

#ifdef WIFI_MODE
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
const char* serverUrl = "http://YOUR_HMS_HOST/hms/api/rfid_ingest.php";
const char* healthUrl = "http://YOUR_HMS_HOST/hms/api/rfid_health.php";
#endif

bool checkHealth() {
  #if defined(ESP8266) || defined(ESP32)
  HTTPClient http;
  WiFiClient client;
  http.begin(client, healthUrl);
  int httpCode = http.GET();
  bool ok = (httpCode == 200);
  Serial.printf("Health check %s (%d)\n", ok ? "ok" : "failed", httpCode);
  http.end();
  return ok;
  #else
  return true;
  #endif
}

void connectWiFi() {
  #if defined(ESP8266) || defined(ESP32)
  Serial.printf("Connecting to Wi-Fi %s...\n", ssid);
  WiFi.begin(ssid, password);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print('.');
    if (millis() - start > 20000) {
      Serial.println("\nWi-Fi connect failed, rebooting...");
      ESP.restart();
    }
  }
  Serial.println();
  Serial.print("Wi-Fi connected, IP: ");
  Serial.println(WiFi.localIP());

  int retries = 0;
  while (!checkHealth()) {
    retries++;
    if (retries >= 5) {
      Serial.println("Health check failed repeatedly, rebooting...");
      ESP.restart();
    }
    Serial.println("Retrying health check...");
    delay(2000);
  }
  #endif
}

String uidToHex() {
  String s = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) s += "0";
    s += String(rfid.uid.uidByte[i], HEX);
  }
  s.toUpperCase();
  return s;
}

void setup() {
  Serial.begin(9600);
  SPI.begin();
  rfid.PCD_Init();
  Serial.println("MediCare RFID reader ready.");
  #ifdef WIFI_MODE
  connectWiFi();
  #endif
}

void postUid(const String& uid) {
  #if defined(ESP8266) || defined(ESP32)
  HTTPClient http;
  WiFiClient client;
  String payload = "uid=" + uid + "&station=reception";
  http.begin(client, serverUrl);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  int httpCode = http.POST(payload);
  Serial.printf("POST %s -> %d\n", uid.c_str(), httpCode);
  http.end();
  #else
  Serial.print("UID:");
  Serial.println(uid);
  #endif
}

void loop() {
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) return;
  String uid = uidToHex();
  #ifdef WIFI_MODE
  postUid(uid);
  #else
  Serial.print("UID:");
  Serial.println(uid);
  #endif
  delay(1500);
  rfid.PICC_HaltA();
}
