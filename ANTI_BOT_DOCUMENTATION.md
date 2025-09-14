# Anti-Bot Protection Documentation

## Overview

The FP Prenotazioni Ristorante includes comprehensive anti-bot protection to prevent automated spam submissions while maintaining a seamless experience for legitimate users. The system uses multiple detection methods and conditional challenges to ensure security without compromising usability.

## Protection Mechanisms

### 1. Honeypot Field

**Purpose**: Detect automated form submissions by bots that fill all visible fields.

**Implementation**:
- An invisible text field (`rbf_website`) is added to the form
- Positioned off-screen using `position: absolute; left: -9999px; visibility: hidden`
- Includes `tabindex="-1"` and `autocomplete="off"` attributes
- Legitimate users cannot see or interact with this field
- Bots typically fill all form fields, including hidden ones

**Detection Logic**:
- If the honeypot field contains any value → **Immediate bot detection (High severity)**
- Results in immediate submission blocking

### 2. Timestamp Validation

**Purpose**: Detect submissions that are too fast (automated) or suspiciously slow.

**Implementation**:
- Hidden timestamp field (`rbf_form_timestamp`) added when form loads
- Server compares submission time with form load time
- Validates human-like completion timing

**Detection Rules**:
- **Too Fast** (< 5 seconds): High suspicion score (+80 points)
- **Normal Range** (5 seconds - 30 minutes): No penalty
- **Too Slow** (> 30 minutes): Moderate suspicion (+30 points)
- **Missing Timestamp**: Moderate suspicion (+40 points)

### 3. User Agent Analysis

**Purpose**: Identify known bot patterns and suspicious browser signatures.

**Detection Patterns**:
- Empty or missing User-Agent headers
- Common bot keywords: `bot`, `crawler`, `spider`, `scraper`, `curl`, `wget`, `python`
- Very short User-Agent strings (< 20 characters)
- Automated tool signatures

**Scoring**: Adds 60 points to suspicion score when detected.

### 4. Field Pattern Analysis

**Purpose**: Detect fake, test, or obviously automated data entries.

**Analysis Methods**:
- **Test Data Detection**: Looks for common test patterns (`test`, `bot`, `fake`, `example`, etc.)
- **Identical Names**: Flags when first name equals last name
- **Temporary Email Services**: Detects disposable email domains
- **Generic Patterns**: Identifies keyboard mashing or sequential characters

**Scoring**: Variable points based on pattern severity (15-25 points each).

### 5. Rate Limiting

**Purpose**: Detect multiple rapid submissions from the same IP address.

**Implementation**:
- Tracks submission frequency per IP using WordPress transients
- Maintains rolling 1-hour window of submissions
- Automatically cleans old entries

**Thresholds**:
- **1-3 submissions**: Normal rate (0 points)
- **4-5 submissions**: Moderate rate (+10 points)
- **6-10 submissions**: High rate (+20 points)
- **10+ submissions**: Very high rate (+30 points)

### 6. Conditional reCAPTCHA v3

**Purpose**: Human verification for suspicious submissions without impacting legitimate users.

**Trigger Conditions**:
- Bot detection score ≥ 70 points AND severity = "high"
- Only activates if reCAPTCHA keys are configured
- Falls back to blocking if reCAPTCHA fails

**Implementation**:
- Loads Google reCAPTCHA v3 script conditionally
- Executes on form submission with action "booking_submit"
- Server-side verification with configurable threshold (default: 0.5)
- Graceful fallback on API errors to avoid blocking legitimate users

## Scoring System

The anti-bot system uses a cumulative scoring approach:

| Score Range | Severity | Action |
|-------------|----------|---------|
| 0-39 | Low | Allow submission, log if score > 0 |
| 40-69 | Medium | Allow submission, log for monitoring |
| 70+ | High | Trigger reCAPTCHA or block submission |

### Score Contributors:
- Honeypot filled: **Immediate block (severity: high)**
- Too fast submission (< 5s): **+80 points**
- Missing timestamp: **+40 points**
- Bot user agent: **+60 points**
- Very slow submission (> 30m): **+30 points**
- Rate limiting violations: **+10 to +30 points**
- Suspicious field patterns: **+15 to +25 points each**

## Configuration

### Admin Settings

Navigate to **Prenotazioni → Impostazioni → Protezione Anti-Bot**:

1. **reCAPTCHA v3 Site Key**: Public key from Google reCAPTCHA console
2. **reCAPTCHA v3 Secret Key**: Private key for server-side verification
3. **reCAPTCHA Threshold**: Minimum score required (0.0-1.0, default: 0.5)

### Setup Instructions

1. **Create reCAPTCHA v3 Keys**:
   - Visit [Google reCAPTCHA Console](https://www.google.com/recaptcha/admin)
   - Create new site with reCAPTCHA v3
   - Add your domain(s)
   - Copy Site Key and Secret Key

2. **Configure Plugin**:
   - Enter keys in admin settings
   - Set appropriate threshold (0.5 recommended)
   - Save settings

3. **Test Configuration**:
   - Use bot simulation tests (see Testing section)
   - Monitor error logs for detection events
   - Verify legitimate users can submit successfully

## Logging and Monitoring

### Log Entries

All bot detection events are logged with:
- Detection reason
- Suspicion score
- IP address
- Timestamp

### Log Examples:
```
RBF Bot Detection: Honeypot field filled - IP: 192.168.1.100
RBF Bot Detection: Too fast submission: 2s, Bot user agent detected - IP: 10.0.0.50
RBF reCAPTCHA Failed: Score 0.3 below threshold 0.5 - IP: 203.0.113.10
RBF Bot detected but reCAPTCHA passed - allowing submission
```

### Monitoring Recommendations:
- Review logs regularly for patterns
- Adjust reCAPTCHA threshold based on false positive rates
- Monitor submission success rates
- Track bot detection effectiveness

## Testing

### Automated Tests

Run the comprehensive test suite:
```bash
php tests/anti-bot-tests.php
```

### Manual Testing

#### Bot Simulation:
1. **Honeypot Test**: Manually fill the hidden website field
2. **Speed Test**: Submit form immediately after loading
3. **User Agent Test**: Use curl or bot-like tools
4. **Pattern Test**: Use fake names like "Test Bot"

#### Legitimate User Testing:
1. **Normal Flow**: Complete form naturally (30+ seconds)
2. **Real Data**: Use authentic names and emails
3. **Human Browser**: Use standard browser with normal User-Agent
4. **Reasonable Timing**: Fill form at human pace

## Troubleshooting

### Common Issues

#### False Positives
- **Symptoms**: Legitimate users blocked
- **Solutions**: 
  - Lower reCAPTCHA threshold
  - Review field pattern rules
  - Check timestamp validation logic

#### False Negatives
- **Symptoms**: Bots getting through
- **Solutions**:
  - Increase reCAPTCHA threshold
  - Add more field patterns
  - Review user agent detection

#### reCAPTCHA Issues
- **API Errors**: Check keys and domain configuration
- **Network Issues**: Verify server can reach Google APIs
- **Threshold Problems**: Adjust based on score distribution

### Debug Mode

Enable WordPress debug logging to see detailed bot detection analysis:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Best Practices

### Configuration
- Start with default threshold (0.5) and adjust based on results
- Monitor logs for first few weeks after implementation
- Test with various browsers and user scenarios
- Keep reCAPTCHA keys secure and rotate periodically

### Maintenance
- Review detection rules quarterly
- Update user agent patterns as needed
- Monitor false positive/negative rates
- Adjust thresholds based on traffic patterns

### User Experience
- Never block without clear indication of bot detection
- Provide helpful error messages
- Ensure graceful fallbacks for API failures
- Test accessibility of form protection

## Security Considerations

### Data Protection
- IP addresses are only used for rate limiting (not stored permanently)
- Submission patterns logged but not personal data
- reCAPTCHA tokens processed server-side only

### Privacy Compliance
- Bot detection happens transparently
- No additional personal data collection
- Compatible with GDPR/privacy regulations
- Optional reCAPTCHA integration

### Performance Impact
- Minimal overhead for detection logic
- Conditional script loading for reCAPTCHA
- Efficient transient-based rate limiting
- No database queries for basic detection

## API Reference

### Functions

#### `rbf_detect_bot_submission($form_data)`
Main detection function that analyzes submission for bot patterns.

**Parameters:**
- `$form_data` (array): POST data from form submission

**Returns:**
- `array`: Detection result with `is_bot`, `severity`, `reason`, and `score`

#### `rbf_verify_recaptcha($token, $action)`
Verifies reCAPTCHA v3 token with Google API.

**Parameters:**
- `$token` (string): reCAPTCHA response token
- `$action` (string): Expected action name (default: 'booking_submit')

**Returns:**
- `array`: Verification result with `success`, `score`, and `reason`

### Hooks

#### Actions
- `rbf_bot_detected`: Fired when bot is detected
- `rbf_recaptcha_failed`: Fired when reCAPTCHA verification fails

#### Filters
- `rbf_bot_detection_rules`: Modify detection rules
- `rbf_recaptcha_threshold`: Override threshold per submission