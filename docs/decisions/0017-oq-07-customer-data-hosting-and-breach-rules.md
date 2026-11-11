# 0017: OQ-07 Customer Data Hosting Location and Breach Notification Rules

## Context
BRD Open Question OQ-07 addresses legal and regulatory requirements for hosting customer personal and sensitive health data (allergies, chronic conditions) within Ethiopia, alongside breach notification protocols and data retention limits.

## Decision
1. **Hosting Location & Cloud Policy**:
   - The PMS deployment environment will adhere to national data sovereignty and EFDA directives as they are formalized.
   - Database servers storing patient records, encrypted health fields, and access logs must reside on in-country infrastructure or compliant sovereign clouds.
2. **Breach Notification Protocol**:
   - In the event of confirmed unauthorized access or exposure of patient health data, an incident must be logged immediately.
   - Notification must be escalated to the Pharmacy Manager and Owner within 24 hours, followed by regulatory notification to the relevant Ethiopian healthcare authority in accordance with statutory guidelines.
3. **Audit and Masking Safeguards**:
   - System Administrators (SA) never receive permission to view customer health fields or access logs containing customer identity.
   - Sensitive health fields (`allergies`, `chronic_conditions`) are encrypted at rest using AES-256 (`'encrypted'` cast).
   - Every read of sensitive health fields is immutably logged to `customer_health_views` and the central audit trail.
   - Health data cannot be stored without explicit informed consent (`consent_given` = true).

