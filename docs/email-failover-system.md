# Email Failover System Documentation

## Overview

The Email Failover System provides robust email notification reliability for the FP Prenotazioni Ristorante by implementing a two-tier email delivery architecture with automatic failover capabilities.

## Architecture

### Primary Provider: Brevo
- **Purpose**: Customer automation emails and admin notifications
- **Features**: 
  - Multi-language support (IT/EN lists)
  - Marketing automation workflows
  - Event tracking and analytics
  - Transactional email delivery

### Fallback Provider: wp_mail
- **Purpose**: Admin notifications only (when Brevo fails)
- **Features**: 
  - WordPress native email function
  - SMTP configuration support
  - Reliable backup delivery
  - No automation capabilities

## Failover Logic

### Admin Notifications
1. **Primary Attempt**: Send via Brevo transactional email
2. **Fallback Trigger**: API errors, timeouts, or service unavailability
3. **Fallback Action**: Send via wp_mail to configured admin emails
4. **Logging**: All attempts logged with provider, status, and timestamps

### Customer Notifications  
1. **Primary Attempt**: Send via Brevo automation (contact lists + events)
2. **No Fallback**: Customer automations are Brevo-specific
3. **Logging**: Failures logged for monitoring and manual follow-up

## Configuration

### Required Settings
```php
// Brevo Configuration
'brevo_api' => 'your-brevo-api-key',
'brevo_list_it' => 123, // Italian contact list ID
'brevo_list_en' => 456, // English contact list ID

// Admin Email Configuration  
'notification_email' => 'restaurant@example.com',
'webmaster_email' => 'admin@example.com'
```

### Database Tables
The system uses the `rbf_email_notifications` table for comprehensive logging:

```sql
CREATE TABLE rbf_email_notifications (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    booking_id int(11),
    notification_type varchar(50),
    recipient_email varchar(255),
    subject varchar(255),
    status enum('pending','success','failed','fallback_success'),
    provider_used varchar(50),
    attempt_number int(11) DEFAULT 1,
    error_message text,
    metadata longtext,
    attempted_at datetime,
    completed_at datetime,
    INDEX idx_booking_id (booking_id),
    INDEX idx_status (status),
    INDEX idx_attempted_at (attempted_at)
);
```

## Usage Examples

### Integration in Booking Process
```php
// Replace direct Brevo calls with failover service
$service = rbf_get_email_failover_service();

// Admin notification with failover
$admin_result = $service->send_admin_notification(
    $booking_id,
    $customer_data,
    $booking_details
);

// Customer notification (Brevo only, with logging)
$customer_result = $service->send_customer_notification(
    $booking_id,
    $customer_data,
    $booking_details
);
```

### Manual Retry from Admin
```php
// Retry failed notification
$log_id = 123;
$service = rbf_get_email_failover_service();
$result = $service->retry_notification($log_id);
```

## Monitoring & Analytics

### Admin Dashboard
Access via WordPress Admin → Prenotazioni → Notifiche Email

**Key Metrics:**
- Total notifications sent
- Success rate percentage  
- Fallback usage percentage
- Failed notifications requiring attention

**Provider Usage Charts:**
- Brevo (Primary) usage statistics
- wp_mail (Fallback) usage statistics
- System health indicators

**Detailed Log Views:**
- Filterable by status, type, and date range
- Individual notification details
- Error messages and retry capabilities
- Booking ID cross-references

### Performance Monitoring
```php
// Get notification statistics
$service = rbf_get_email_failover_service();
$stats = $service->get_notification_stats(7); // Last 7 days

// Check system health
$health = $service->get_system_health();
echo "Success Rate: " . $health['success_rate'] . "%";
echo "Fallback Rate: " . $health['fallback_rate'] . "%";
```

## Error Handling

### Common Scenarios

1. **Brevo API Key Invalid**
   - Status: `failed`
   - Fallback: wp_mail for admin notifications
   - Action: Verify API key configuration

2. **Brevo Service Timeout**
   - Status: `fallback_success` (if admin) or `failed` (if customer)
   - Fallback: wp_mail for admin notifications
   - Action: Monitor Brevo service status

3. **Network Connectivity Issues**
   - Status: `failed`
   - Fallback: wp_mail for admin notifications
   - Action: Check server network configuration

4. **wp_mail SMTP Issues**
   - Status: `failed` (both primary and fallback failed)
   - Action: Configure WordPress SMTP settings

### Debugging Tips

1. **Check Notification Logs**
   ```php
   $service = rbf_get_email_failover_service();
   $logs = $service->get_notification_logs($booking_id);
   ```

2. **Test Brevo Connectivity**
   ```php
   $service = rbf_get_email_failover_service();
   $test = $service->test_brevo_connection();
   ```

3. **Verify wp_mail Configuration**
   ```php
   $test_email = wp_mail('test@example.com', 'Test', 'Test message');
   if (!$test_email) {
       error_log('wp_mail failed: ' . print_r(error_get_last(), true));
   }
   ```

## Best Practices

### Configuration
- Always configure both primary and fallback email addresses
- Use different email addresses for restaurant and webmaster notifications
- Test email delivery after configuration changes
- Monitor notification logs regularly

### Maintenance  
- Review failed notifications weekly
- Clean up old log entries (>90 days) for performance
- Update Brevo API keys before expiration
- Verify SMTP settings after server changes

### Troubleshooting
- Use the admin dashboard for first-level diagnostics
- Check both Brevo and WordPress logs for errors
- Test with small booking volumes before peak periods
- Set up external monitoring for critical failure rates

## API Reference

### Core Functions

#### `rbf_get_email_failover_service()`
Returns the singleton email failover service instance.

#### `rbf_send_admin_notification_with_failover($data)`
Sends admin notification with automatic failover to wp_mail.

#### `rbf_send_customer_notification_with_failover($data)` 
Sends customer automation notification via Brevo (logs failures).

### Service Methods

#### `send_notification($notification_data)`
Core method for sending notifications with failover logic.

#### `get_notification_logs($booking_id = null, $limit = 50)`
Retrieves notification logs with optional filtering.

#### `get_notification_stats($days = 7)`
Returns notification statistics for specified time period.

#### `retry_notification($log_id)`
Retries a failed notification by log ID.

## Security Considerations

- API keys stored as WordPress options (use constants for production)
- Email addresses validated before sending
- Log data sanitized to prevent XSS
- Admin access required for notification management
- Rate limiting on retry attempts to prevent abuse

## Performance Impact

- Minimal overhead during normal operation
- Database logging adds ~1ms per notification
- Fallback attempts may add 5-10 seconds on failures
- Log table should be maintained (automated cleanup recommended)
- Async processing recommended for high-volume installations