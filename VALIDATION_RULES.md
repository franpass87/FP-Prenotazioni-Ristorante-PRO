# Validation Rules Documentation

## Overview

The Restaurant Booking Form implements comprehensive inline validation to provide immediate feedback to users without requiring page reloads. This document outlines all validation rules, indicators, and behaviors.

## Required Field Indicators

All required fields are marked with a red asterisk (`*`) next to the field label. The following fields are required:

- **Pasto/Meal** - User must select a meal type
- **Data/Date** - User must select a booking date
- **Orario/Time** - User must select a time slot
- **Persone/People** - Number of people (minimum 1, maximum 20)
- **Nome/Name** - First name (minimum 2 characters)
- **Cognome/Surname** - Last name (minimum 2 characters)
- **Email** - Valid email address
- **Telefono/Phone** - Valid phone number (8-15 digits)
- **Privacy Policy** - Must be accepted to proceed

## Validation Types

### Synchronous Validation

Immediate validation that occurs on field blur or input:

#### 1. Meal Selection
- **Rule**: Must select one meal option
- **Trigger**: On radio button change
- **Error**: "Seleziona un pasto per continuare."

#### 2. Date Selection
- **Rule**: Must select a date that is not in the past
- **Trigger**: On date picker change
- **Errors**: 
  - "Seleziona una data per continuare."
  - "La data selezionata non può essere nel passato."

#### 3. Time Selection
- **Rule**: Must select an available time slot
- **Trigger**: On dropdown change
- **Error**: "Seleziona un orario per continuare."

#### 4. Number of People
- **Rule**: Between 1 and 20 people
- **Trigger**: On input change
- **Errors**:
  - "Il numero di persone deve essere almeno 1."
  - "Il numero di persone non può superare 20."

#### 5. Name Fields (Nome/Cognome)
- **Rules**: 
  - Minimum 2 characters
  - Only letters, spaces, apostrophes, and hyphens allowed
- **Trigger**: On field blur
- **Errors**:
  - "Il nome deve contenere almeno 2 caratteri."
  - "Il nome può contenere solo lettere, spazi, apostrofi e trattini."

#### 6. Email Address
- **Rules**:
  - Must be a valid email format
  - Cannot be test/example addresses
- **Trigger**: On field blur, debounced on input (1 second)
- **Errors**:
  - "L'indirizzo email è obbligatorio."
  - "Inserisci un indirizzo email valido."

#### 7. Phone Number
- **Rules**:
  - Minimum 8 digits
  - Maximum 15 digits
  - Only numbers (formatting characters ignored)
- **Trigger**: On field blur, debounced on input (1 second)
- **Errors**:
  - "Il numero di telefono è obbligatorio."
  - "Il numero di telefono deve contenere almeno 8 cifre."
  - "Il numero di telefono non può superare 15 cifre."

#### 8. Privacy Policy
- **Rule**: Must be checked
- **Trigger**: On checkbox change
- **Error**: "Devi accettare la Privacy Policy per procedere."

### Asynchronous Validation

Enhanced validation that may involve server communication:

#### Email Validation
- **Purpose**: Check for test email addresses
- **Behavior**: Shows loading state, then validates
- **Error**: "Utilizza un indirizzo email reale per la prenotazione."

## Visual States

### Field States

1. **Default State**: Normal field appearance
2. **Valid State**: Green border and checkmark
3. **Invalid State**: Red border and error message
4. **Validating State**: Yellow border with spinning loader

### Error Messages

- **Appearance**: Red text with warning icon (⚠️)
- **Animation**: Fade in from top with smooth transition
- **Position**: Below the corresponding field
- **Timing**: Immediate for synchronous, delayed for asynchronous

### Success Indicators

- **Appearance**: Green text with checkmark icon (✅)
- **Animation**: Fade in smoothly
- **Trigger**: After successful validation

## User Experience

### Validation Timing

- **On Focus**: Clears previous validation state
- **On Blur**: Validates required fields if they have content
- **On Input**: Debounced validation for email and phone (1 second delay)
- **On Change**: Immediate validation for select fields and checkboxes

### Error Recovery

- Users can immediately see what needs to be fixed
- Validation state clears when field receives focus
- Real-time feedback prevents form submission errors

### Accessibility

- All error messages are announced to screen readers
- Field states are communicated via ARIA attributes
- Keyboard navigation is fully supported
- Color is not the only indicator (icons and text are used)

## Technical Implementation

### CSS Classes

- `.rbf-required-indicator` - Red asterisk for required fields
- `.rbf-field-wrapper` - Container for field and validation messages
- `.rbf-field-error` - Error message styling
- `.rbf-field-success` - Success message styling
- `.rbf-field-invalid` - Invalid field state
- `.rbf-field-valid` - Valid field state
- `.rbf-field-validating` - Loading/validating state

### JavaScript Events

- Form fields use event delegation for validation
- Debounced input validation prevents excessive validation calls
- Promise-based asynchronous validation for better performance

### Validation Functions

Each field has a corresponding validation rule in the `ValidationManager.rules` object with:
- `required`: Boolean indicating if field is required
- `validate`: Synchronous validation function
- `asyncValidate`: Optional asynchronous validation function

## Error Prevention

The inline validation system prevents common booking errors:

1. **Date Issues**: Prevents past date selection
2. **Contact Information**: Ensures reachable contact details
3. **Group Size**: Validates party size within restaurant capacity
4. **Required Fields**: Clear indication of what must be completed

## Maintenance

### Adding New Validation Rules

1. Add the field rule to `ValidationManager.rules`
2. Add corresponding error messages to the labels array
3. Add error container to the form structure
4. Update this documentation

### Customizing Messages

All validation messages are translatable and can be customized in the `includes/frontend.php` file in the `labels` array.

## Testing

Run the validation tests using:
```php
php tests/inline-validation-tests.php
```

The test suite validates:
- CSS styling presence
- Form structure integrity
- JavaScript functionality
- Message localization
- Accessibility compliance
- UX requirements
- Existing functionality preservation