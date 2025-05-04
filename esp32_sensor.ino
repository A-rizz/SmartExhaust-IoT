#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <DHT.h>

// WiFi credentials
const char* ssid = "LetMeCook";
const char* password = "ubosnadatako";

// Server endpoints - using your computer's IP address on the local network
const char* getSettingsURL = "http://192.168.187.118/exhaust/api/control_relay.php";
const char* sendDataURL    = "http://192.168.187.118/exhaust/api/esp32_data.php";

// Simple API key for local testing
const char* apiKey = "123456789";

// Pin Configuration
#define MQ2_ANALOG 34     // MQ-2 Analog Output (A0)
#define MQ2_DIGITAL 14    // MQ-2 Digital Output (D0)
#define DHTPIN 18         // DHT11 Data Pin
#define DHTTYPE DHT11
#define FAN_RELAY_PIN 5   // Relay control pin
#define RPM_PIN 35        // RPM signal pin

// Constants
#define MEASUREMENT_INTERVAL 5000 // Time between measurements in ms
#define RPM_CALC_INTERVAL 1000   // Time between RPM calculations (ms)
#define MAX_RPM 3000            // Maximum realistic RPM for error checking
#define DEBOUNCE_TIME 1         // Minimum time between pulses (milliseconds)

// Global variables
DHT dht(DHTPIN, DHTTYPE);
volatile unsigned long rpmCount = 0;
volatile unsigned long lastPulseTime = 0;  // For debouncing
unsigned long lastRpmTime = 0;
unsigned long lastMeasurementTime = 0;
bool isWifiConnected = false;

// Debug variables for RPM
unsigned long lastDebugTime = 0;
#define DEBUG_INTERVAL 1000  // Print debug every second

// Control settings
struct ControlSettings {
    int gasThreshold = 400;
    float tempThreshold = 30.0;
    float humidityThreshold = 80.0;
    bool currentState = false;
    bool autoMode = true;
    unsigned long lastUpdate = 0;
} settings;

// Sensor readings
struct SensorData {
    float temperature = 0;
    float humidity = 0;
    int gasLevel = 0;
    bool gasDetected = false;
    int rpm = 0;
    bool fanStatus = false;
} sensorData;

#define RELAY_ON LOW    // LOW turns relay ON
#define RELAY_OFF HIGH   // HIGH turns relay OFF

void IRAM_ATTR rpmInterrupt() {
    // Debounce
    unsigned long currentTime = millis();
    if (currentTime - lastPulseTime > DEBOUNCE_TIME) {
        rpmCount++;
        lastPulseTime = currentTime;
    }
}

bool connectWiFi() {
    if (WiFi.status() == WL_CONNECTED) {
        return true;
    }

    Serial.print("Connecting to WiFi");
    WiFi.begin(ssid, password);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        Serial.print(".");
        attempts++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nConnected to WiFi");
        Serial.println("IP: " + WiFi.localIP().toString());
        isWifiConnected = true;
        return true;
    }

    Serial.println("\nWiFi connection failed");
    return false;
}

void controlFan(bool shouldBeOn) {
    Serial.println("\n=== Fan Control ===");
    Serial.print("Request: Turn fan "); 
    Serial.println(shouldBeOn ? "ON" : "OFF");

    // Convert the desired state to relay pin level
    int relayLevel = shouldBeOn ? RELAY_ON : RELAY_OFF;
    
    // Get current relay state
    int currentLevel = digitalRead(FAN_RELAY_PIN);
    bool isCurrentlyOn = (currentLevel == RELAY_ON);
    
    Serial.print("Current relay state: ");
    Serial.print(isCurrentlyOn ? "ON" : "OFF");
    Serial.print(" (Pin level: ");
    Serial.print(currentLevel);
    Serial.println(")");

    // Only change state if needed
    if (currentLevel != relayLevel) {
        Serial.println("Changing relay state...");
        digitalWrite(FAN_RELAY_PIN, relayLevel);
        
        // Verify the change
        delay(50); // Small delay to let the pin settle
        int newLevel = digitalRead(FAN_RELAY_PIN);
        bool isNowOn = (newLevel == RELAY_ON);
        
        Serial.print("New relay state: ");
        Serial.print(isNowOn ? "ON" : "OFF");
        Serial.print(" (Pin level: ");
        Serial.print(newLevel);
        Serial.println(")");
        
        if (newLevel != relayLevel) {
            Serial.println("WARNING: Relay did not change to requested state!");
        }
    } else {
        Serial.println("No change needed - relay already in requested state");
    }
    
    // Update status for reporting
    sensorData.fanStatus = shouldBeOn;
}

void readSensors() {
    Serial.println("\n=== Sensor Readings ===");
    
    // Read DHT sensor
    float newTemp = dht.readTemperature();
    float newHumidity = dht.readHumidity();
    
    if (!isnan(newTemp) && !isnan(newHumidity)) {
        sensorData.temperature = newTemp;
        sensorData.humidity = newHumidity;
        Serial.print("Temperature: "); Serial.print(newTemp); Serial.println("°C");
        Serial.print("Humidity: "); Serial.print(newHumidity); Serial.println("%");
    } else {
        Serial.println("Failed to read from DHT sensor!");
    }

    // Read gas sensor
    sensorData.gasLevel = analogRead(MQ2_ANALOG);
    sensorData.gasDetected = !digitalRead(MQ2_DIGITAL);
    Serial.print("Gas Level: "); Serial.println(sensorData.gasLevel);
    Serial.print("Gas Detected: "); Serial.println(sensorData.gasDetected ? "YES!" : "No");

    // Calculate and debug RPM
    unsigned long currentTime = millis();
    
    // Debug RPM counts more frequently
    if (currentTime - lastDebugTime >= DEBUG_INTERVAL) {
        Serial.println("\n=== RPM Debug ===");
        Serial.print("Raw Count: "); Serial.println(rpmCount);
        Serial.print("Time since last debug: "); Serial.print(currentTime - lastDebugTime); Serial.println("ms");
        lastDebugTime = currentTime;
    }

    // Calculate RPM every RPM_CALC_INTERVAL
    if (currentTime - lastRpmTime >= RPM_CALC_INTERVAL) {
        // Calculate RPM: (counts) * (60 seconds / 1 minute) * (1000 ms / 1 second) / (interval in ms)
        // Divide by 2 because we get 2 pulses per revolution
        unsigned long elapsedTime = currentTime - lastRpmTime;
        float rpm = ((float)rpmCount * 30000.0) / (float)elapsedTime;  // Using float for more accurate calculation
        
        Serial.println("\n=== RPM Calculation ===");
        Serial.print("Pulses counted: "); Serial.println(rpmCount);
        Serial.print("Time elapsed: "); Serial.print(elapsedTime); Serial.println("ms");
        Serial.print("Calculated RPM: "); Serial.println(rpm);
        
        // Error check the RPM value
        if (rpm <= MAX_RPM && rpm >= 0) {
            sensorData.rpm = (int)rpm;
            Serial.print("Final RPM value: "); Serial.println(sensorData.rpm);
        } else {
            Serial.println("Warning: Invalid RPM reading detected");
            sensorData.rpm = 0;
        }
        
        rpmCount = 0;
        lastRpmTime = currentTime;
    }
}

bool fetchSettings() {
    if (!isWifiConnected) {
        Serial.println("Cannot fetch settings - WiFi not connected");
        return false;
    }

    Serial.println("Fetching settings from server...");
    HTTPClient http;
    http.begin(getSettingsURL);
    
    // Add API key header
    http.addHeader("X-API-Key", apiKey);
    
    int httpCode = http.GET();
    if (httpCode == 200) {
        String payload = http.getString();
        StaticJsonDocument<512> doc;
        DeserializationError error = deserializeJson(doc, payload);
        
        if (!error) {
            // Match exact field names from server response
            settings.currentState = doc["current_state"].as<int>() == 1;
            settings.autoMode = doc["auto_mode"].as<int>() == 1;
            settings.gasThreshold = doc["gas_threshold"].as<int>();
            settings.tempThreshold = doc["temperature_threshold"].as<float>();
            settings.humidityThreshold = doc["humidity_threshold"].as<float>();
            settings.lastUpdate = millis();
            
            Serial.println("\n=== Current Settings ===");
            Serial.print("Mode: "); Serial.println(settings.autoMode ? "AUTO" : "MANUAL");
            Serial.print("Gas Threshold: "); Serial.println(settings.gasThreshold);
            Serial.print("Temperature Threshold: "); Serial.print(settings.tempThreshold); Serial.println("°C");
            Serial.print("Humidity Threshold: "); Serial.print(settings.humidityThreshold); Serial.println("%");
            Serial.print("Current State: "); Serial.println(settings.currentState ? "ON" : "OFF");
            Serial.print("Raw response: "); Serial.println(payload); // Debug line
            
            return true;
        } else {
            Serial.print("JSON parse error: ");
            Serial.println(error.c_str());
            Serial.print("Raw response: ");
            Serial.println(payload);
        }
    } else if (httpCode == 401) {
        Serial.println("Authentication failed - check your API key");
    } else {
        Serial.print("Failed to fetch settings. HTTP code: ");
        Serial.println(httpCode);
    }
    
    http.end();
    return false;
}

void sendData() {
    if (!isWifiConnected) {
        Serial.println("Cannot send data - WiFi not connected");
        return;
    }

    Serial.println("Sending data to server...");
    HTTPClient http;
    http.begin(sendDataURL);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("X-API-Key", apiKey);

    String data = "temperature=" + String(sensorData.temperature, 2) +
                 "&humidity=" + String(sensorData.humidity, 2) +
                 "&gas_level=" + String(sensorData.gasLevel) +
                 "&fan_status=" + String(int(sensorData.fanStatus)) +
                 "&rpm=" + String(sensorData.rpm);

    int httpResponseCode = http.POST(data);
    if (httpResponseCode == 200) {
        Serial.println("Data sent successfully");
    } else if (httpResponseCode == 401) {
        Serial.println("Authentication failed - check your API key");
    } else {
        Serial.print("Error sending data. Code: ");
        Serial.println(httpResponseCode);
    }
    http.end();
}

void setup() {
    Serial.begin(115200);
    
    // Initialize pins with proper modes
    pinMode(MQ2_DIGITAL, INPUT);
    pinMode(RPM_PIN, INPUT_PULLUP);
    pinMode(FAN_RELAY_PIN, OUTPUT);
    pinMode(MQ2_ANALOG, INPUT);
    
    // Ensure fan is initially OFF
    digitalWrite(FAN_RELAY_PIN, RELAY_OFF);
    sensorData.fanStatus = false;

    // Initialize sensors
    dht.begin();
    
    Serial.println("\n=== RPM Setup ===");
    Serial.print("RPM Pin (GPIO35) Mode: INPUT_PULLUP");
    Serial.print("\nAttaching interrupt to pin "); Serial.println(RPM_PIN);
    
    // Setup RPM interrupt with RISING edge and enable internal pullup
    pinMode(RPM_PIN, INPUT_PULLUP);
    attachInterrupt(digitalPinToInterrupt(RPM_PIN), rpmInterrupt, RISING);
    
    // Initial WiFi connection
    if (connectWiFi()) {
        // Fetch initial settings
        if (fetchSettings()) {
            // Apply initial fan state
            controlFan(settings.currentState);
        }
    }
    
    Serial.println("\n=== Setup Complete ===");
    Serial.print("Fan relay pin: "); Serial.println(FAN_RELAY_PIN);
    Serial.print("Initial relay state: "); Serial.println(digitalRead(FAN_RELAY_PIN) == RELAY_ON ? "ON" : "OFF");
    Serial.print("RPM monitoring pin: "); Serial.println(RPM_PIN);
}

void loop() {
    // Ensure WiFi connection
    if (!isWifiConnected) {
        connectWiFi();
    }

    unsigned long currentTime = millis();

    // Read sensors and control fan at regular intervals
    if (currentTime - lastMeasurementTime >= MEASUREMENT_INTERVAL) {
        Serial.println("\n=== Begin Measurement Cycle ===");
        
        // Read sensors
        readSensors();

        // Fetch latest settings
        bool settingsUpdated = fetchSettings();
        
        // Control logic with debug output
        bool shouldTurnOn = false;
        Serial.println("\n=== Control Logic ===");
        if (settings.autoMode) {
            Serial.println("Mode: AUTO");
            // Check each condition
            bool gasTriggered = (sensorData.gasLevel > settings.gasThreshold);
            bool tempTriggered = (sensorData.temperature > settings.tempThreshold);
            bool humidityTriggered = (sensorData.humidity > settings.humidityThreshold);
            
            Serial.print("Gas Level: "); Serial.print(sensorData.gasLevel);
            Serial.print(" > "); Serial.print(settings.gasThreshold);
            Serial.println(gasTriggered ? " (TRIGGERED)" : " (Normal)");
            
            Serial.print("Temperature: "); Serial.print(sensorData.temperature);
            Serial.print("°C > "); Serial.print(settings.tempThreshold);
            Serial.println(tempTriggered ? " (TRIGGERED)" : " (Normal)");
            
            Serial.print("Humidity: "); Serial.print(sensorData.humidity);
            Serial.print("% > "); Serial.print(settings.humidityThreshold);
            Serial.println(humidityTriggered ? " (TRIGGERED)" : " (Normal)");
            
            shouldTurnOn = gasTriggered || tempTriggered || humidityTriggered;
        } else {
            Serial.println("Mode: MANUAL");
            shouldTurnOn = settings.currentState;
        }

        // Control fan
        controlFan(shouldTurnOn);

        // Send data to server
        sendData();

        // Update measurement time
        lastMeasurementTime = currentTime;
        Serial.println("=== End Measurement Cycle ===\n");
    }

    // Small delay to prevent tight looping
    delay(100);
} 