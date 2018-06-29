<?php

// Text
$_['text_title'] = 'Vindi Payment Gateway Services';
$_['text_callback'] = 'Vindi Payment Gateway Services: %s %s in Transaction #%s for this order.';
$_['text_new_card'] = '-- New Card --';
$_['text_existing_card'] = 'Existing Card';
$_['text_select_card'] = 'For a faster checkout experience, you have the option to select one of the cards you used in previous purchases:';
$_['text_loading'] = 'Loading... Please wait...';
$_['text_items'] = 'Item(s)';
$_['text_log_api_intro'] = '[Vindi Payment Gateway Services - REST API %s]: ';

// Error
$_['error_session'] = 'Error: Could not establish payment session. Vindi Payment Gateway Services cannot proceed.';
$_['error_invalid_request'] = 'Error: Invalid request.';
$_['error_api'] = 'An API error occurred: %s';
$_['error_unknown'] = 'An unexpected error has occurred. Please report this to the store owners.';
$_['error_event_missing'] = 'Configuration error: Vindi Payment Gateway Services was not initialized properly. Make sure the event catalog/controller/checkout/checkout/before > extension/payment/vindi/init is enabled!';
$_['error_validate_protocol'] = 'Error: Protocol invalid.';
$_['error_secret_mismatch'] = 'Error: Notification secret mismatch.';
$_['error_notification_parse'] = 'Error: Notification was not parsed.';
$_['error_transaction_logged'] = 'Error: Transaction is already logged.';

// Warning
$_['warning_test_mode'] = 'Warning: Vindi Payment Gateway Services is running in test mode. Note: Test orders above &#36;100 may not get processed.';

$_['text_title'] = 'Credit or Debit Card (Processed securely by Stripe)';
$_['text_wait'] = 'Validating your credit card...';
$_['text_credit_card'] = 'Credit Card Details';

// Entry
$_['entry_cc_type'] = 'Card Type';
$_['entry_cc_number'] = 'Card Number';
$_['entry_cc_expire_date'] = 'Card Expiry Date';
$_['entry_cc_cvv2'] = 'CV Code';
$_['entry_cc_issue'] = 'Card Issue Number';

// Help
$_['help_start_date'] = '(if available)';
$_['help_issue'] = '(for Maestro and Solo cards only)';
