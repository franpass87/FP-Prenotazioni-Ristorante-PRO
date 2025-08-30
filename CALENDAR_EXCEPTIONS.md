# Calendar Exceptions - Feature Documentation

## Overview
The Calendar Exceptions system allows restaurant administrators to manage special dates that require different handling than normal operating days. This includes closures, holidays, special events, and extended hours.

## Exception Types

### 1. **Closure** (`closure`)
- Restaurant is completely closed
- No bookings accepted
- Examples: Maintenance, vacation, unexpected closure

### 2. **Holiday** (`holiday`)  
- Special holiday closure
- No bookings accepted
- Examples: Christmas, New Year, national holidays

### 3. **Special Event** (`special`)
- Special themed event with custom hours
- Custom time slots available
- Examples: Valentine's Day dinner, wine tasting events

### 4. **Extended Hours** (`extended`)
- Restaurant open beyond normal hours
- Custom time slots available
- Examples: New Year's Eve, special celebrations

## Data Format

### New Enhanced Format
```
YYYY-MM-DD|type|hours|description
```

Examples:
- `2024-12-25|closure||Christmas Day`
- `2024-12-31|extended|19:00-02:00|New Year's Eve`
- `2024-02-14|special|18:00-23:00|Valentine's Dinner`
- `2024-08-15|holiday||Ferragosto`

### Legacy Format (Still Supported)
- Single dates: `2024-12-25`
- Date ranges: `2024-12-24 - 2024-12-26`

## Special Hours Format

### Time Ranges
- **Same day**: `18:00-23:00` (6 PM to 11 PM)
- **Overnight**: `19:00-02:00` (7 PM to 2 AM next day)

### Comma Separated
- **Specific times**: `12:00,13:00,14:00,19:00,20:00`

### Single Time
- **One slot**: `20:00`

## Admin Interface Features

### Visual Exception Manager
- **Add Form**: Easy-to-use form for adding new exceptions
- **Exception List**: Visual display of all active exceptions with color coding
- **Validation**: Real-time validation of dates and hours format
- **Color Coding**: 
  - 🔴 Red: Closures
  - 🟠 Orange: Holidays  
  - 🟢 Green: Special Events
  - 🔵 Blue: Extended Hours

### Manual Override
Administrators can still manually edit the raw exception data in the textarea for advanced configurations.

## Frontend Features

### Calendar Visualization
- **Exception Indicators**: Small colored dots on calendar dates
- **Legend**: Shows what each color represents
- **Tooltip**: Hover over dates to see exception details

### Smart Availability
- **Closure Dates**: Completely disabled for booking
- **Special/Extended Dates**: Show custom time slots instead of regular hours
- **Holiday Dates**: Disabled like closures but with different visual indication

## Technical Implementation

### Backend Functions
- `rbf_get_closed_specific()`: Parse and return all exception data
- `rbf_get_date_exceptions()`: Get exceptions for a specific date
- `rbf_get_special_hours_for_date()`: Get custom hours for special dates

### Frontend Integration
- **JavaScript Data**: Exception data passed to frontend via `rbfData.exceptions`
- **Calendar Integration**: Flatpickr calendar enhanced with exception handling
- **Visual Indicators**: CSS-based colored dots and styling

### Backward Compatibility
- Existing closed dates continue to work without modification
- Legacy date ranges remain functional
- New features don't break existing installations

## Usage Examples

### Restaurant Scenarios

1. **Christmas Closure**
   ```
   2024-12-25|closure||Christmas Day
   ```

2. **New Year's Eve Party**
   ```
   2024-12-31|extended|19:00-02:00|NYE Celebration
   ```

3. **Valentine's Special Menu**
   ```
   2024-02-14|special|18:00,19:00,20:00,21:00|Valentine's Dinner
   ```

4. **Summer Holiday**
   ```
   2024-08-15|holiday||Ferragosto
   ```

## CSS Classes for Customization

### Exception Indicators
- `.rbf-exception-closure`: Red closure indicator
- `.rbf-exception-holiday`: Orange holiday indicator  
- `.rbf-exception-special`: Green special event indicator
- `.rbf-exception-extended`: Blue extended hours indicator

### Calendar Styling
- `.rbf-exception-legend`: Exception legend container
- `.rbf-exception-legend-item`: Individual legend items
- `.rbf-exception-legend-dot`: Color dots in legend

## Future Enhancements

Potential future improvements could include:
- Recurring exceptions (e.g., "every Sunday in December")
- Integration with external calendar systems
- Capacity overrides for special events
- Email notifications for upcoming exceptions
- Advanced reporting on exception usage

## Migration Notes

When upgrading from the basic closed dates system:
1. Existing closed dates are automatically preserved
2. New exception types can be added gradually
3. No data loss occurs during the upgrade
4. Admin interface provides tools for easy migration to new format