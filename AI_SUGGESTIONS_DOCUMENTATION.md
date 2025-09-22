# AI Alternative Suggestions - Technical Documentation

## Overview
The AI Alternative Suggestions system provides intelligent booking recommendations when requested time slots are full. This feature enhances user experience by automatically suggesting available alternatives instead of simply showing "no availability."

## Architecture

### Core Components

1. **Backend Algorithm** (`includes/ai-suggestions.php`)
   - Rule-based suggestion engine (MVP implementation)
   - Alternative time slot discovery
   - Capacity checking and validation

2. **AJAX Integration** (Modified `includes/booking-handler.php`)
   - Enhanced availability endpoint
   - Automatic suggestion inclusion when slots are full

3. **Frontend UI** (Modified `assets/js/frontend.js` and `assets/css/frontend.css`)
   - Suggestion display components
   - Interactive selection interface
   - Form auto-population

4. **Testing Suite** (`tests/ai-suggestions-tests.php`)
   - Automated functionality tests
   - Admin test interface

## Algorithm Logic

### Suggestion Strategy Priorities

The system employs a multi-strategy approach to generate suggestions:

1. **Same Day, Different Service** (Priority: High - Score 90+)
   - Suggests alternative meal services on the same requested date
   - Example: If lunch is full, suggest dinner or aperitivo

2. **Same Service, Nearby Dates** (Priority: Medium - Score 70-80)
   - Suggests same meal type on adjacent dates (±1-3 days)
   - Prioritizes future dates over past dates

3. **Same Weekday, Future Weeks** (Priority: Low - Score 50-60)
   - Suggests same day of the week in following weeks (1-2 weeks ahead)
   - Maintains day-of-week consistency for user convenience

### Filtering Logic

```php
function rbf_get_alternative_suggestions($date, $meal, $people, $requested_time = '')
```

#### Input Validation
- Date format validation (Y-m-d)
- Meal type validation against active meals
- Party size constraints based on the configured maximum capacity

#### Availability Checks
- Restaurant opening hours
- Meal service availability by day of week
- Capacity constraints with party size consideration
- Closed dates and holiday exceptions

#### Suggestion Ranking
```php
$suggestion['preference_score'] = base_score - (distance_penalty * multiplier);
```

- **Same Day**: 90 base score
- **Nearby Dates**: 80 base score - (5 * days_distance)
- **Same Weekday**: 60 base score - (10 * weeks_distance)

## API Reference

### Core Functions

#### `rbf_get_alternative_suggestions($date, $meal, $people, $requested_time = '')`
**Purpose**: Generate alternative booking suggestions
**Parameters**:
- `$date` (string): Original requested date (Y-m-d)
- `$meal` (string): Meal type identifier
- `$people` (int): Number of people (must respect the configured limit)
- `$requested_time` (string): Original time for context (optional)

**Returns**: Array of suggestion objects
```php
[
    'date' => '2024-02-15',
    'date_display' => 'Thursday, February 15',
    'meal' => 'pranzo',
    'meal_name' => 'Lunch',
    'time' => '13:00',
    'time_display' => '13:00',
    'reason' => 'Same day, different service',
    'preference_score' => 90,
    'remaining_spots' => 12
]
```

#### `rbf_suggest_same_day_alternatives($date, $original_meal, $people, $meals)`
**Purpose**: Find alternative services on the same day
**Returns**: Array of same-day suggestions

#### `rbf_suggest_nearby_dates($original_date, $meal, $people, $days_range = 3)`
**Purpose**: Find same service on nearby dates
**Returns**: Array of nearby date suggestions

#### `rbf_suggest_same_weekday($original_date, $meal, $people, $weeks_ahead = 2)`
**Purpose**: Find same weekday in future weeks
**Returns**: Array of same-weekday suggestions

### AJAX Endpoints

#### `rbf_get_suggestions`
**Action**: `wp_ajax_rbf_get_suggestions` / `wp_ajax_nopriv_rbf_get_suggestions`
**Method**: POST
**Parameters**:
```javascript
{
    nonce: 'rbf_ajax_nonce',
    date: '2024-02-15',
    meal: 'pranzo', 
    people: 2,
    time: '13:00' // optional
}
```

**Response**:
```javascript
{
    success: true,
    data: {
        suggestions: [...], // Array of suggestion objects
        count: 2,
        message: "We found some alternatives for you!"
    }
}
```

#### Enhanced `rbf_get_availability`
**Modified Response**: Now includes suggestions when no times available
```javascript
{
    success: true,
    data: {
        available_times: [], // Empty when full
        suggestions: [...], // Alternative suggestions
        message: "This time is full, but we found alternatives!"
    }
}
```

## Frontend Integration

### JavaScript Implementation

#### Suggestion Display
```javascript
function displayAlternativeSuggestions(suggestions, message)
```
- Creates responsive suggestion cards
- Implements click/keyboard navigation
- Handles accessibility (ARIA, screen readers)

#### Suggestion Application
```javascript
function applySuggestion(date, meal, time)
```
- Auto-populates form with selected suggestion
- Triggers appropriate form validation
- Provides smooth user experience transitions

### CSS Classes

#### Container Classes
- `.rbf-suggestions-container`: Main container
- `.rbf-suggestions-header`: Title and description
- `.rbf-suggestions-list`: Grid layout for suggestions

#### Item Classes
- `.rbf-suggestion-item`: Individual suggestion card
- `.rbf-suggestion-primary`: Date and time display
- `.rbf-suggestion-secondary`: Meal and reason
- `.rbf-suggestion-capacity`: Remaining spots indicator

#### Interactive States
- `:hover` and `:focus` styling
- `.rbf-suggestion-item[aria-selected="true"]` for selection
- Animation classes for smooth transitions

## Configuration

### Requirements
- WordPress 5.0+
- PHP 7.4+
- Existing meal configurations in restaurant settings
- Active booking system with capacity management

### Settings Integration
The system automatically inherits from existing plugin settings:
- Restaurant opening hours
- Meal service configurations
- Capacity limits and overbooking rules
- Closed dates and exceptions

### Performance Considerations
- Transient caching for availability checks (1 hour TTL)
- Limited suggestion count (2 maximum) to prevent UI overload
- Efficient database queries with proper indexing
- Conditional loading based on suggestion availability

## Testing

### Test Coverage
1. **Basic Functionality**: Suggestion generation with valid inputs
2. **Full Capacity Scenarios**: Behavior when original slot is unavailable
3. **Closed Restaurant**: Handling when restaurant is closed
4. **AJAX Endpoints**: API response validation

### Running Tests
```php
// Admin interface: Prenotazioni > AI Tests
// Manual execution:
$results = rbf_test_ai_suggestions();
```

### Test Categories
- **Unit Tests**: Individual function validation
- **Integration Tests**: AJAX endpoint testing
- **UI Tests**: Frontend suggestion display (manual)

## Security

### Input Validation
- CSRF protection via WordPress nonces
- SQL injection prevention with prepared statements
- XSS protection with proper data escaping
- Input sanitization using `rbf_sanitize_input_fields()`

### Data Privacy
- No personal data stored in suggestions
- Temporary data only (availability checks)
- GDPR-compliant data handling

## Troubleshooting

### Common Issues

#### No Suggestions Generated
- Check restaurant opening hours configuration
- Verify meal availability settings
- Ensure capacity limits are properly configured
- Review closed dates configuration

#### Suggestions Not Displaying
- Verify JavaScript console for errors
- Check AJAX endpoint responses
- Confirm CSS is properly loaded
- Test with network debugging tools

#### Performance Issues
- Monitor database query performance
- Check transient cache effectiveness
- Consider reducing suggestion count limit
- Optimize meal configuration complexity

### Debug Mode
Enable debug logging:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check logs for RBF-specific messages
tail -f /path/to/wp-content/debug.log | grep "RBF"
```

## Future Enhancements

### Planned Features
1. **Machine Learning Integration**: Replace rule-based logic with ML algorithms
2. **User Preference Learning**: Track and adapt to user selection patterns
3. **Dynamic Pricing**: Integrate with pricing algorithms for suggestions
4. **Multi-location Support**: Cross-restaurant suggestions for chains

### Extension Points
- Custom suggestion strategies via hooks
- Third-party algorithm integration
- Advanced analytics and tracking
- A/B testing framework for suggestion effectiveness

## Changelog

### Version 1.5 (Initial Implementation)
- ✅ Rule-based suggestion algorithm
- ✅ Same-day alternative suggestions
- ✅ Nearby date suggestions  
- ✅ Same weekday suggestions
- ✅ Frontend UI with suggestion cards
- ✅ AJAX integration
- ✅ Comprehensive test suite
- ✅ Technical documentation
- ✅ Accessibility compliance
- ✅ Responsive design
- ✅ Multilingual support (IT/EN)

## Support

For technical support or custom development:
- Review plugin logs for detailed error information
- Check WordPress admin test interface
- Consult this documentation for API reference
- Contact plugin developer for advanced customization needs