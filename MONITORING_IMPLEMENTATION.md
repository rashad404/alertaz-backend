# Alert.az Monitoring System Implementation

## Overview
A comprehensive B2C monitoring and alerting system has been implemented for Alert.az, allowing users to create personal alerts for various services including cryptocurrency prices, weather conditions, website uptime, stock prices, and currency exchange rates.

## ✅ Completed Implementation

### 1. Monitoring Services
All monitoring services have been implemented with base class inheritance for code reuse:

#### **CryptoMonitor** (`app/Services/Monitoring/CryptoMonitor.php`)
- Monitors cryptocurrency prices via Binance and CoinGecko APIs
- Supports major cryptocurrencies (BTC, ETH, BNB, etc.)
- Includes 1-minute caching to avoid rate limits
- Provides mock data fallback for development

#### **WeatherMonitor** (`app/Services/Monitoring/WeatherMonitor.php`)
- Integrates with OpenWeatherMap API
- Monitors temperature, humidity, rain chance, wind speed
- 10-minute cache for API efficiency
- Calculates rain probability from cloud coverage

#### **WebsiteMonitor** (`app/Services/Monitoring/WebsiteMonitor.php`)
- Checks website uptime and response time
- Handles redirects and SSL certificates
- Tracks HTTP status codes
- Provides detailed error reporting

#### **StockMonitor** (`app/Services/Monitoring/StockMonitor.php`)
- Fetches stock prices from Alpha Vantage and Yahoo Finance
- Tracks price, volume, market cap, daily changes
- 5-minute caching strategy
- Mock data for common stocks (AAPL, GOOGL, MSFT, etc.)

#### **CurrencyMonitor** (`app/Services/Monitoring/CurrencyMonitor.php`)
- Integrates with Azerbaijan Central Bank (CBAR) API
- Falls back to ExchangeRate API for international currencies
- Calculates cross-rates between currencies
- 30-minute cache for stability

### 2. Notification System
Complete multi-channel notification dispatcher with individual channel implementations:

#### **NotificationDispatcher** (`app/Services/NotificationDispatcher.php`)
- Central hub for all notification channels
- Handles delivery status tracking
- Supports test notifications
- Channel availability checking

#### Implemented Channels:
- **EmailChannel**: HTML email with markdown conversion
- **SmsChannel**: Supports Twilio, Nexmo, Azercell
- **TelegramChannel**: Bot API integration with markdown support
- **WhatsAppChannel**: Twilio WhatsApp and Business API
- **SlackChannel**: Webhook integration with rich formatting
- **PushChannel**: Web Push with VAPID keys and service worker

### 3. Queue System
Laravel Queue infrastructure for background processing:

- **CheckAlerts Job** (`app/Jobs/CheckAlerts.php`): Batch processing all alerts
- **CheckUserAlert Job** (`app/Jobs/CheckUserAlert.php`): Individual alert processing
- **Console Command** (`app/Console/Commands/CheckAlertsCommand.php`): Manual alert checking
- Database queue driver configured with retry logic

### 4. Scheduler Configuration
Automated periodic checks configured in `routes/console.php`:

```php
Schedule::command('alerts:check')->everyMinute();
Schedule::command('alerts:check --type=crypto')->everyThirtySeconds()->between('09:00', '17:00');
Schedule::command('alerts:check --type=website')->everyTwoMinutes();
Schedule::command('alerts:check --type=weather')->everyTenMinutes();
Schedule::command('alerts:check --type=stock')->everyMinute()->between('09:30', '16:00');
Schedule::command('alerts:check --type=currency')->everyFiveMinutes();
```

### 5. Frontend Components
Complete user interface for alert management:

- **Alert Creation Wizard**: 5-step process with validation
- **Notification Settings Page**: Channel configuration interface
- **Service Worker**: Push notification handling
- **Real-time channel validation**: Shows green/red status

### 6. Database Schema
All necessary tables and relationships:

- Users table with notification channel fields
- Alert types (crypto, weather, website, stock, currency)
- Personal alerts with conditions and frequencies
- Alert history tracking
- OTP verifications for phone/SMS

## 🚀 How to Use

### Starting the System

1. **Configure Environment Variables**
```bash
# Copy the monitoring config
cat .env.monitoring >> .env

# Add your API keys
OPENWEATHER_API_KEY=your_key
TELEGRAM_BOT_TOKEN=your_bot_token
# etc...
```

2. **Generate VAPID Keys for Push Notifications**
```bash
php artisan push:generate-keys
```

3. **Run Migrations**
```bash
php artisan migrate
```

4. **Start Queue Worker**
```bash
php artisan queue:work
# Or for development with auto-restart:
php artisan queue:listen
```

5. **Start Scheduler**
```bash
# Development:
php artisan schedule:work

# Production (add to crontab):
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### Testing the System

1. **Run Test Script**
```bash
php test-monitoring.php
```

2. **Manual Alert Check**
```bash
# Check all alerts
php artisan alerts:check --sync

# Check specific type
php artisan alerts:check --type=crypto --sync

# Check for specific user
php artisan alerts:check --user=1 --sync
```

## 📊 System Architecture

```
User → Creates Alert → PersonalAlert Model
           ↓
    Scheduler/Manual Trigger
           ↓
    CheckAlerts Job (Queue)
           ↓
    Monitor Service (Crypto/Weather/etc.)
           ↓
    Fetch Current Data (API/Cache)
           ↓
    Check Conditions
           ↓
    If Triggered → NotificationDispatcher
                          ↓
                   Channel (Email/SMS/etc.)
                          ↓
                   User Receives Alert
```

## 🔧 Configuration

### API Services
- **OpenWeatherMap**: Weather data
- **Alpha Vantage**: Stock market data
- **Binance/CoinGecko**: Cryptocurrency prices
- **CBAR**: Azerbaijan currency rates

### Notification Providers
- **Email**: SMTP (Gmail, Mailgun, etc.)
- **SMS**: Twilio, Nexmo, Azercell
- **Telegram**: Bot API
- **WhatsApp**: Twilio, Business API
- **Slack**: Incoming Webhooks
- **Push**: Web Push with VAPID

## 📈 Performance Considerations

1. **Caching Strategy**
   - Crypto: 1 minute
   - Weather: 10 minutes
   - Website: No cache (real-time)
   - Stock: 5 minutes
   - Currency: 30 minutes

2. **Queue Optimization**
   - Separate queues for different priorities
   - Retry logic with exponential backoff
   - Failed job tracking and admin alerts

3. **Rate Limiting**
   - API call throttling
   - User alert limits (configurable)
   - Notification frequency controls

## 🔒 Security Features

- Encrypted notification credentials
- OAuth support for social login
- OTP verification for phone numbers
- CSRF protection on API endpoints
- Rate limiting on alert creation
- Sanitized user inputs

## 📝 Next Steps

1. **Admin Dashboard**: Monitor system health and user alerts
2. **Analytics**: Track alert triggers and notification success rates
3. **Mobile App**: Native iOS/Android apps with push support
4. **Webhooks**: Allow users to receive alerts via custom webhooks
5. **AI Predictions**: Machine learning for predictive alerts
6. **Group Alerts**: Shared alerts for teams/organizations

## 🎉 Summary

The Alert.az monitoring system is now fully functional with:
- ✅ 5 types of monitors (Crypto, Weather, Website, Stock, Currency)
- ✅ 6 notification channels (Email, SMS, Telegram, WhatsApp, Slack, Push)
- ✅ Automated scheduling and queue processing
- ✅ Complete frontend interface for alert management
- ✅ Robust error handling and retry logic
- ✅ Production-ready architecture

The system successfully retrieves live data from multiple APIs, processes user-defined conditions, and sends notifications through configured channels. The test script demonstrates all capabilities working together seamlessly.